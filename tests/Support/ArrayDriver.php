<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Kaveraa\DataLifecycle\Driver;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Selection;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Subject;

/**
 * Pilote en mémoire : il applique exactement les mêmes règles de sélection que
 * les pilotes Eloquent et Doctrine, mais sur des tableaux.
 */
final class ArrayDriver implements Driver
{
    /** @var array<string, list<Row>> */
    private array $rows = [];

    /** @var list<array{op: string, subject: string, id: int|string}> */
    public array $writes = [];

    public function seed(string $subject, Row ...$rows): void
    {
        $this->rows[$subject] = array_merge($this->rows[$subject] ?? [], array_values($rows));
    }

    /**
     * @return list<Row>
     */
    public function rows(string $subject): array
    {
        return array_values($this->rows[$subject] ?? []);
    }

    public function row(string $subject, int|string $id): ?Row
    {
        foreach ($this->rows[$subject] ?? [] as $row) {
            if ($row->id === $id) {
                return $row;
            }
        }

        return null;
    }

    public function candidates(Policy $policy, Selection $selection): iterable
    {
        $fields = $policy->fields;
        $found = 0;

        foreach ($this->rows[$policy->subject] ?? [] as $row) {
            if ($found >= $selection->limit) {
                return;
            }

            $since = $this->date($row->get($fields->since));
            $disabled = $this->date($row->get($fields->disabledAt));
            $erased = $policy->ending === Ending::Anonymise ? $this->date($row->get($fields->anonymisedAt)) : null;

            if ($erased !== null) {
                continue;
            }

            $keep = match ($selection->step) {
                Step::Warn => $since !== null
                    && $since <= $selection->cutoff
                    && (int) ($row->get($fields->warnStage) ?? 0) === $selection->warnIndex
                    && (!$policy->hasDisableStep() || $disabled === null),
                Step::Disable => $since !== null && $since <= $selection->cutoff && $disabled === null,
                Step::Erase => $policy->hasDisableStep()
                    ? ($disabled !== null && $disabled <= $selection->cutoff)
                    : ($since !== null && $since <= $selection->cutoff),
            };

            if (!$keep) {
                continue;
            }

            ++$found;

            yield $this->subject($policy, $row);
        }
    }

    public function markWarned(Policy $policy, Subject $subject, int $warnIndex, DateTimeImmutable $at): void
    {
        $row = $this->rowOf($subject);
        $row->set($policy->fields->warnStage, $warnIndex + 1);
        $row->set($policy->fields->warnedAt, $at);

        $this->writes[] = ['op' => 'warn', 'subject' => $policy->subject, 'id' => $subject->id];
    }

    public function disable(Policy $policy, Subject $subject, DateTimeImmutable $at): void
    {
        $this->rowOf($subject)->set($policy->fields->disabledAt, $at);

        $this->writes[] = ['op' => 'disable', 'subject' => $policy->subject, 'id' => $subject->id];
    }

    public function anonymise(Policy $policy, Subject $subject, array $values, DateTimeImmutable $at): void
    {
        $row = $this->rowOf($subject);

        foreach ($values as $field => $value) {
            $row->set($field, $value);
        }

        $row->set($policy->fields->anonymisedAt, $at);

        $this->writes[] = ['op' => 'anonymise', 'subject' => $policy->subject, 'id' => $subject->id];
    }

    public function delete(Policy $policy, Subject $subject): void
    {
        $this->rows[$policy->subject] = array_values(array_filter(
            $this->rows[$policy->subject] ?? [],
            static fn (Row $row): bool => $row->id !== $subject->id,
        ));

        $this->writes[] = ['op' => 'delete', 'subject' => $policy->subject, 'id' => $subject->id];
    }

    public function reactivate(Policy $policy, Subject $subject, DateTimeImmutable $at): void
    {
        $row = $this->rowOf($subject);
        $row->set($policy->fields->warnStage, 0);
        $row->set($policy->fields->warnedAt, null);
        $row->set($policy->fields->disabledAt, null);
        $row->set($policy->fields->since, $at);

        $this->writes[] = ['op' => 'reactivate', 'subject' => $policy->subject, 'id' => $subject->id];
    }

    public function find(Policy $policy, int|string $id): ?Subject
    {
        $row = $this->row($policy->subject, $id);

        return $row === null ? null : $this->subject($policy, $row);
    }

    public function subject(Policy $policy, object $entity): Subject
    {
        assert($entity instanceof Row);

        return new Subject(
            $policy->subject,
            $entity->id,
            $entity,
            static fn (object $row, string $field): mixed => $row->get($field),
        );
    }

    private function rowOf(Subject $subject): Row
    {
        assert($subject->entity instanceof Row);

        return $subject->entity;
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }
}
