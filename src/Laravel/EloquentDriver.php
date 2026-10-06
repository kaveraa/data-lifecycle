<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Laravel;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use Kaveraa\DataLifecycle\Driver;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Selection;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Subject;

/**
 * The Eloquent driver: it turns retention policies into queries.
 *
 * A policy reads only the columns it needs. Without reminders, the counter
 * column never appears in the query; without anonymisation, the anonymisation
 * date does not either. So we do not force any schema.
 */
final class EloquentDriver implements Driver
{
    /**
     * @return iterable<Subject>
     */
    public function candidates(Policy $policy, Selection $selection): iterable
    {
        $model = $this->model($policy);
        $query = $model->newQuery();
        $fields = $policy->fields;

        if ($selection->step === Step::Warn) {
            $query->where($fields->since, '<=', $selection->cutoff);
            $this->whereWarnStage($query, $fields->warnStage, $selection->warnIndex);

            if ($policy->hasDisableStep()) {
                $query->whereNull($fields->disabledAt);
            }
        } elseif ($selection->step === Step::Disable) {
            $query->where($fields->since, '<=', $selection->cutoff);
            $query->whereNull($fields->disabledAt);
        } elseif ($policy->hasDisableStep()) {
            // Erase after disable: we count from the disable date.
            $query->whereNotNull($fields->disabledAt);
            $query->where($fields->disabledAt, '<=', $selection->cutoff);
        } else {
            $query->where($fields->since, '<=', $selection->cutoff);
        }

        if ($policy->ending === Ending::Anonymise) {
            $query->whereNull($fields->anonymisedAt);
        }

        $query->orderBy($model->getKeyName())->limit($selection->limit);

        $subjects = [];

        foreach ($query->get() as $entity) {
            $subjects[] = $this->subject($policy, $entity);
        }

        return $subjects;
    }

    public function markWarned(Policy $policy, Subject $subject, int $warnIndex, DateTimeImmutable $at): void
    {
        $this->write($policy, $subject, [
            $policy->fields->warnStage => $warnIndex + 1,
            $policy->fields->warnedAt => $at,
        ]);
    }

    public function disable(Policy $policy, Subject $subject, DateTimeImmutable $at): void
    {
        $this->write($policy, $subject, [$policy->fields->disabledAt => $at]);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function anonymise(Policy $policy, Subject $subject, array $values, DateTimeImmutable $at): void
    {
        $this->write($policy, $subject, array_merge($values, [$policy->fields->anonymisedAt => $at]));
    }

    public function delete(Policy $policy, Subject $subject): void
    {
        $entity = $this->entity($subject);

        if ($policy->forceDelete && $this->usesSoftDeletes($entity)) {
            $entity->forceDelete();

            return;
        }

        $entity->delete();
    }

    /**
     * The person came back: we reset the counter, the disable date and the
     * last sign of life. Without this last point the row would go straight
     * back to the disable step.
     */
    public function reactivate(Policy $policy, Subject $subject, DateTimeImmutable $at): void
    {
        $fields = $policy->fields;
        $values = [$fields->since => $at];

        if ($policy->warnBefore !== []) {
            $values[$fields->warnStage] = 0;
            $values[$fields->warnedAt] = null;
        }

        if ($policy->hasDisableStep()) {
            $values[$fields->disabledAt] = null;
        }

        $this->write($policy, $subject, $values);
    }

    public function find(Policy $policy, int|string $id): ?Subject
    {
        $entity = $this->model($policy)->newQuery()->find($id);

        return $entity instanceof Model ? $this->subject($policy, $entity) : null;
    }

    public function subject(Policy $policy, object $entity): Subject
    {
        $key = $entity instanceof Model ? $entity->getKey() : null;

        return new Subject(
            $policy->subject,
            is_int($key) || is_string($key) ? $key : (string) $key,
            $entity,
            static fn (object $model, string $field): mixed => $model->getAttribute($field),
        );
    }

    /**
     * The reminder counter can be NULL in the database: we read it as zero.
     */
    private function whereWarnStage(Builder $query, string $column, int $index): void
    {
        $query->where(static function (Builder $inner) use ($column, $index): void {
            $inner->where($column, '=', $index);

            if ($index === 0) {
                $inner->orWhereNull($column);
            }
        });
    }

    /**
     * Direct write: no Eloquent event, updated_at is not touched.
     *
     * @param array<string, mixed> $values
     */
    private function write(Policy $policy, Subject $subject, array $values): void
    {
        $model = $this->model($policy);

        $model->newModelQuery()->toBase()
            ->where($model->getKeyName(), '=', $subject->id)
            ->update($values);

        $entity = $subject->entity;

        if ($entity instanceof Model) {
            $entity->forceFill($values);
            $entity->syncOriginal();
        }
    }

    private function model(Policy $policy): Model
    {
        $class = $policy->subject;

        if (!is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException(sprintf(
                'The lifecycle subject "%s" is not an Eloquent model.',
                $class,
            ));
        }

        return new $class();
    }

    private function entity(Subject $subject): Model
    {
        $entity = $subject->entity;

        if (!$entity instanceof Model) {
            throw new InvalidArgumentException(sprintf(
                'The lifecycle subject "%s" is not an Eloquent model.',
                $subject->type,
            ));
        }

        return $entity;
    }

    private function usesSoftDeletes(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model::class), true);
    }
}
