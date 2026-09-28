<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Support;

/**
 * Une ligne en mémoire, pour tester le cœur sans base de données.
 */
final class Row
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        public readonly int|string $id,
        public array $values = [],
    ) {
    }

    public function get(string $field): mixed
    {
        return $this->values[$field] ?? null;
    }

    public function set(string $field, mixed $value): void
    {
        $this->values[$field] = $value;
    }
}
