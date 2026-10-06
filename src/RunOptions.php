<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use Kaveraa\DataLifecycle\Exception\InvalidOption;

/**
 * How to launch a run.
 */
final class RunOptions
{
    /** @var list<Step> */
    public readonly array $steps;

    /**
     * @param list<Step> $steps steps to play
     */
    public function __construct(
        /** Write nothing: only say what would happen. */
        public readonly bool $dryRun = false,
        /** Maximum number of rows per step and per policy. */
        public readonly int $limit = 1000,
        array $steps = [],
        /** Keep the id of the first rows in the report. */
        public readonly int $samples = 5,
    ) {
        if ($limit < 1) {
            throw InvalidOption::limit($limit);
        }

        if ($samples < 0) {
            throw InvalidOption::samples($samples);
        }

        $this->steps = $steps === [] ? [Step::Warn, Step::Disable, Step::Erase] : array_values($steps);
    }

    public static function observe(int $limit = 1000): self
    {
        return new self(dryRun: true, limit: $limit);
    }

    public function plays(Step $step): bool
    {
        return in_array($step, $this->steps, true);
    }
}
