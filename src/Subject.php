<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * One row covered by a policy, as seen by the package: its id, the original
 * object, and a way to read a field whatever the ORM is.
 */
final class Subject
{
    /**
     * @param Closure(object, string): mixed $reader
     */
    public function __construct(
        public readonly string $type,
        public readonly int|string $id,
        public readonly object $entity,
        private readonly Closure $reader,
    ) {
    }

    public function get(string $field): mixed
    {
        return ($this->reader)($this->entity, $field);
    }

    public function date(string $field): ?DateTimeImmutable
    {
        $value = $this->get($field);

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value);
        }

        if (is_int($value)) {
            return (new DateTimeImmutable())->setTimestamp($value);
        }

        return null;
    }
}
