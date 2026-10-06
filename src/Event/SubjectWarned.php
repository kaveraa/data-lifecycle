<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Event;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Subject;

/**
 * A reminder has to go out. This is where you send the email.
 */
final class SubjectWarned
{
    public function __construct(
        public readonly Policy $policy,
        public readonly Subject $subject,
        /** Reminder number: 0 for the first #[WarnBefore], 1 for the next one. */
        public readonly int $warnIndex,
        /** Date when the row will be disabled or erased. */
        public readonly DateTimeImmutable $dueAt,
    ) {
    }

    public function entity(): object
    {
        return $this->subject->entity;
    }
}
