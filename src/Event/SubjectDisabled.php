<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Event;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Subject;

/**
 * The row has just been disabled. Nothing is lost: the person can come back
 * before the end of the grace period.
 */
final class SubjectDisabled
{
    public function __construct(
        public readonly Policy $policy,
        public readonly Subject $subject,
        public readonly DateTimeImmutable $at,
        /** Date when the row will be erased if nobody comes back. */
        public readonly ?DateTimeImmutable $eraseAt,
    ) {
    }

    public function entity(): object
    {
        return $this->subject->entity;
    }
}
