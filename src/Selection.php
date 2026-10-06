<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateTimeImmutable;

/**
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
