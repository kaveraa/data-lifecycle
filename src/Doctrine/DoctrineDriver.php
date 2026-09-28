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
 * Le pilote Doctrine ORM : construit les requêtes DQL de chaque étape et écrit
 * les dates du cycle de vie sur les entités.
 *
 * The Doctrine ORM driver: builds the DQL query of every step and writes the
 * lifecycle dates on the entities.
 *
 * Seules les propriétés dont la règle a besoin sont utilisées : une entité qui
 * n'a que son dernier signe de vie fonctionne avec une règle simple.
 *
 * L'écriture est suivie d'un flush par action (une action = une ligne, parfois
 * plusieurs colonnes). C'est un UPDATE par ligne traitée : sûr et lisible, sans
 * dépendre de la façon dont l'appelant parcourt les candidats.
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

        // Date du dernier rappel : informative, écrite seulement si la propriété existe.
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
        // Doctrine ne connaît pas le soft delete : forceDelete n'a rien à ignorer ici.
        $this->entities->remove($subject->entity);
        $this->entities->flush();
    }

    public function reactivate(Policy $policy, Subject $subject, DateTimeImmutable $at): void
    {
        $meta = $this->meta($policy);

        // Le retour est un signe de vie : sans cette date, la ligne serait
        // redésactivée dès l'exécution suivante.
        $this->write($meta, $subject->entity, $this->of($meta, $policy->fields->since, 'since'), $at);

        // Tolérant : une entité sans rappels ni désactivation n'a pas ces propriétés.
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
     * Rappel numéro $warnIndex pas encore envoyé, et ligne ni désactivée ni effacée.
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
     * Avec étape de désactivation on compte depuis la désactivation, sinon
     * depuis le dernier signe de vie.
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
     * Écrit seulement si l'entité a la propriété.
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
     * Le setter s'il existe et accepte la valeur, sinon les métadonnées Doctrine.
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

        // Un setter non nullable ne peut pas recevoir la remise à zéro d'une date.
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
