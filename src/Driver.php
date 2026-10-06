<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateTimeImmutable;

/**
 * The bridge between the package and your database: one implementation per ORM.
 */
interface Driver
{
    /**
     * The rows to handle for this step.
     *
     * Warn    : last sign of life <= cutoff, and reminder number $warnIndex not sent yet.
     * Disable : last sign of life <= cutoff, not disabled nor erased yet.
     * Erase   : disable date <= cutoff (or last sign of life if there is no
     *           disable step), not erased yet.
     *
     * @return iterable<Subject>
     */
    public function candidates(Policy $policy, Selection $selection): iterable;

    public function markWarned(Policy $policy, Subject $subject, int $warnIndex, DateTimeImmutable $at): void;

    public function disable(Policy $policy, Subject $subject, DateTimeImmutable $at): void;

    /**
     * @param array<string, mixed> $values
     */
    public function anonymise(Policy $policy, Subject $subject, array $values, DateTimeImmutable $at): void;

    public function delete(Policy $policy, Subject $subject): void;

    /**
     * Resets the row: no reminder sent, no disable date.
     */
    public function reactivate(Policy $policy, Subject $subject, DateTimeImmutable $at): void;

    /**
     * Finds one row by its id, for single-row actions.
     */
    public function find(Policy $policy, int|string $id): ?Subject;

    /**
     * Wraps an object already loaded by the application.
     */
    public function subject(Policy $policy, object $entity): Subject;
}
