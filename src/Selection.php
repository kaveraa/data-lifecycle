<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateTimeImmutable;

/**
 * Ce que le pilote doit aller chercher : quelle étape, jusqu'à quelle date,
 * et combien de lignes au maximum.
 *
 * What the driver has to look for: which step, up to which date, and how many
 * rows at most.
 */
final class Selection
{
    public function __construct(
        public readonly Step $step,
        public readonly DateTimeImmutable $cutoff,
        public readonly int $warnIndex = 0,
        public readonly int $limit = 1000,
    ) {
    }
}
