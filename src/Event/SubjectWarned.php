<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Event;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Subject;

/**
 * Un rappel doit partir. C'est ici que vous envoyez l'e-mail.
 *
 * A reminder has to go out. This is where you send the email.
 */
final class SubjectWarned
{
    public function __construct(
        public readonly Policy $policy,
        public readonly Subject $subject,
        /** Numéro du rappel : 0 pour le premier #[WarnBefore], 1 pour le suivant. */
        public readonly int $warnIndex,
        /** Date à laquelle la ligne sera désactivée ou effacée. */
        public readonly DateTimeImmutable $dueAt,
    ) {
    }

    public function entity(): object
    {
        return $this->subject->entity;
    }
}
