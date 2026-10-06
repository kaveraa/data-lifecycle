<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Kaveraa\DataLifecycle\Driver;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Selection;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Subject;
use ReflectionMethod;

/**
 * The Doctrine ORM driver: builds the DQL query of every step and writes the
 * lifecycle dates on the entities.
 *
 * Only the properties the policy needs are used: an entity that only has its
 * last sign of life works with a simple policy.
 *
 * Each write is followed by a flush per action (one action = one row, sometimes
 * several columns). That is one UPDATE per handled row: safe and readable, and it
 * does not depend on how the caller goes through the candidates.
 */
final class DoctrineDriver implements Driver
{
    public function __construct(
        private readonly EntityManagerInterface $entities,
        private readonly PropertyNames $names = new PropertyNames(),
    ) {
    }

    /**
     * @return iterable<Subject>
     */
    public function candidates(Policy $policy, Selection $selection): iterable
    {
        $meta = $this->meta($policy);

        $query = $this->entities->createQueryBuilder()
            ->select('e')
            ->from($policy->subject, 'e')
            ->orderBy('e.' . $meta->getSingleIdentifierFieldName(), 'ASC')
            ->setMaxResults($selection->limit);

        match ($selection->step) {
            Step::Warn => $this->forWarn($query, $policy, $meta, $selection),
            Step::Disable => $this->forDisable($query, $policy, $meta, $selection),
            Step::Erase => $this->forErase($query, $policy, $meta, $selection),
        };

        return array_map(
            fn (object $entity): Subject => $this->subject($policy, $entity),
            $query->getQuery()->getResult(),
        );
    }

    public function markWarned(Policy $policy, Subject $subject, int $warnIndex, DateTimeImmutable $at): void
    {
        $meta = $this->meta($policy);

        $this->write($meta, $subject->entity, $this->of($meta, $policy->fields->warnStage, 'warn_stage'), $warnIndex + 1);

        // Date of the last reminder: for information only, written only if the property exists.
        $this->writeIfAny($meta, $subject->entity, $policy->fields->warnedAt, $at);

        $this->entities->flush();
    }

    public function disable(Policy $policy, Subject $subject, DateTimeImmutable $at): void
    {
        $meta = $this->meta($policy);

        $this->write($meta, $subject->entity, $this->of($meta, $policy->fields->disabledAt, 'disabled_at'), $at);

        $this->entities->flush();
    }

    /**
     * @param array<string, mixed> $values
     */
    public function anonymise(Policy $policy, Subject $subject, array $values, DateTimeImmutable $at): void
    {
        $meta = $this->meta($policy);

        foreach ($values as $field => $value) {
            $this->write($meta, $subject->entity, $this->of($meta, $field, 'anonymise'), $value);
        }

        $this->write($meta, $subject->entity, $this->of($meta, $policy->fields->anonymisedAt, 'anonymised_at'), $at);

        $this->entities->flush();
    }

    public function delete(Policy $policy, Subject $subject): void
    {
        // Doctrine has no soft delete: forceDelete has nothing to skip here.
        $this->entities->remove($subject->entity);
        $this->entities->flush();
    }

    public function reactivate(Policy $policy, Subject $subject, DateTimeImmutable $at): void
    {
        $meta = $this->meta($policy);

        // Coming back is a sign of life: without this date, the row would be
        // disabled again on the next run.
        $this->write($meta, $subject->entity, $this->of($meta, $policy->fields->since, 'since'), $at);

        // Tolerant: an entity without reminders or disable step does not have these properties.
        $this->writeIfAny($meta, $subject->entity, $policy->fields->warnStage, 0);
        $this->writeIfAny($meta, $subject->entity, $policy->fields->warnedAt, null);
        $this->writeIfAny($meta, $subject->entity, $policy->fields->disabledAt, null);

        $this->entities->flush();
    }

    public function find(Policy $policy, int|string $id): ?Subject
    {
        $entity = $this->entities->find($policy->subject, $id);

        return $entity === null ? null : $this->subject($policy, $entity);
    }

    public function subject(Policy $policy, object $entity): Subject
    {
        $meta = $this->meta($policy);

        $reader = function (object $row, string $field) use ($meta): mixed {
            $property = $this->names->find($meta, $field);

            return $property === null ? null : $meta->getFieldValue($row, $property);
        };

        return new Subject($policy->subject, $this->idOf($meta, $entity), $entity, $reader);
    }

    /**
     * Reminder number $warnIndex not sent yet, and row neither disabled nor erased.
     *
     * @param ClassMetadata<object> $meta
     */
    private function forWarn(QueryBuilder $query, Policy $policy, ClassMetadata $meta, Selection $selection): void
    {
        $query
            ->andWhere('e.' . $this->of($meta, $policy->fields->since, 'since') . ' <= :cutoff')
            ->andWhere('COALESCE(e.' . $this->of($meta, $policy->fields->warnStage, 'warn_stage') . ', 0) = :warn_index')
            ->setParameter('cutoff', $selection->cutoff)
            ->setParameter('warn_index', $selection->warnIndex);

        if ($policy->hasDisableStep()) {
            $query->andWhere('e.' . $this->of($meta, $policy->fields->disabledAt, 'disabled_at') . ' IS NULL');
        }

        $this->andNotAnonymised($query, $policy, $meta);
    }

    /**
     * @param ClassMetadata<object> $meta
     */
    private function forDisable(QueryBuilder $query, Policy $policy, ClassMetadata $meta, Selection $selection): void
    {
        $query
            ->andWhere('e.' . $this->of($meta, $policy->fields->since, 'since') . ' <= :cutoff')
            ->andWhere('e.' . $this->of($meta, $policy->fields->disabledAt, 'disabled_at') . ' IS NULL')
            ->setParameter('cutoff', $selection->cutoff);

        $this->andNotAnonymised($query, $policy, $meta);
    }

    /**
     * With a disable step we count from the disable date, otherwise
     * from the last sign of life.
     *
     * @param ClassMetadata<object> $meta
     */
    private function forErase(QueryBuilder $query, Policy $policy, ClassMetadata $meta, Selection $selection): void
    {
        if ($policy->hasDisableStep()) {
            $disabledAt = 'e.' . $this->of($meta, $policy->fields->disabledAt, 'disabled_at');

            $query->andWhere($disabledAt . ' IS NOT NULL')->andWhere($disabledAt . ' <= :cutoff');
        } else {
            $query->andWhere('e.' . $this->of($meta, $policy->fields->since, 'since') . ' <= :cutoff');
        }

        $query->setParameter('cutoff', $selection->cutoff);

        $this->andNotAnonymised($query, $policy, $meta);
    }

    /**
     * @param ClassMetadata<object> $meta
     */
    private function andNotAnonymised(QueryBuilder $query, Policy $policy, ClassMetadata $meta): void
    {
        if ($policy->ending !== Ending::Anonymise) {
            return;
        }

        $query->andWhere('e.' . $this->of($meta, $policy->fields->anonymisedAt, 'anonymised_at') . ' IS NULL');
    }

    /**
     * @param ClassMetadata<object> $meta
     */
    private function of(ClassMetadata $meta, string $field, string $role): string
    {
        return $this->names->of($meta, $field, $role);
    }

    /**
     * Writes only if the entity has the property.
     *
     * @param ClassMetadata<object> $meta
     */
    private function writeIfAny(ClassMetadata $meta, object $entity, string $field, mixed $value): void
    {
        $property = $this->names->find($meta, $field);

        if ($property !== null) {
            $this->write($meta, $entity, $property, $value);
        }
    }

    /**
     * The setter if it exists and accepts the value, otherwise the Doctrine metadata.
     *
     * @param ClassMetadata<object> $meta
     */
    private function write(ClassMetadata $meta, object $entity, string $property, mixed $value): void
    {
        $setter = 'set' . ucfirst($property);

        if (method_exists($entity, $setter) && self::accepts($entity, $setter, $value)) {
            $entity->{$setter}($value);

            return;
        }

        $meta->setFieldValue($entity, $property, $value);
    }

    private static function accepts(object $entity, string $setter, mixed $value): bool
    {
        $method = new ReflectionMethod($entity, $setter);
        $first = $method->getParameters()[0] ?? null;

        if (!$method->isPublic() || $first === null) {
            return false;
        }

        // A non-nullable setter cannot receive the reset of a date to null.
        return $value !== null || $first->getType() === null || $first->allowsNull();
    }

    /**
     * @param ClassMetadata<object> $meta
     */
    private function idOf(ClassMetadata $meta, object $entity): int|string
    {
        $values = $meta->getIdentifierValues($entity);
        $id = reset($values);

        if (is_int($id) || is_string($id)) {
            return $id;
        }

        return $id instanceof \Stringable ? (string) $id : spl_object_id($entity);
    }

    /**
     * @return ClassMetadata<object>
     */
    private function meta(Policy $policy): ClassMetadata
    {
        /** @var ClassMetadata<object> $meta */
        $meta = $this->entities->getClassMetadata($policy->subject);

        return $meta;
    }
}
