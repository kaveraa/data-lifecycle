<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Event;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Subject;

/**
 * La ligne reste, mais les données personnelles sont parties.
 *
 * The row stays, but the personal data is gone.
 */
final class SubjectAnonymised
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        public readonly Policy $policy,
        public readonly Subject $subject,
        public readonly array $values,
        public readonly DateTimeImmutable $at,
    ) {
    }

    public function entity(): object
    {
        return $this->subject->entity;
    }
}
