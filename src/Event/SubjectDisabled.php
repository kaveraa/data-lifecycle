<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Event;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Subject;

/**
 * La ligne vient d'être désactivée. Rien n'est perdu : la personne peut revenir
 * avant la fin du délai de grâce.
 *
 * The row has just been disabled. Nothing is lost: the person can come back
 * before the end of the grace period.
 */
final class SubjectDisabled
{
    public function __construct(
        public readonly Policy $policy,
        public readonly Subject $subject,
        public readonly DateTimeImmutable $at,
        /** Date à laquelle la ligne sera effacée si personne ne revient. */
        public readonly ?DateTimeImmutable $eraseAt,
    ) {
    }

    public function entity(): object
    {
        return $this->subject->entity;
    }
}
