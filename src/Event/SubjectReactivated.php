<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Event;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Subject;

/**
 * The person came back: the account is live again, and the clock restarts.
 */
final class SubjectReactivated
{
    public function __construct(
        public readonly Policy $policy,
        public readonly Subject $subject,
        public readonly DateTimeImmutable $at,
    ) {
    }

    public function entity(): object
    {
        return $this->subject->entity;
    }
}
