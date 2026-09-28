<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use Kaveraa\DataLifecycle\Exception\InvalidOption;

/**
 * Comment lancer une exécution.
 *
 * How to launch a run.
 */
final class RunOptions
{
    /** @var list<Step> */
    public readonly array $steps;

    /**
     * @param list<Step> $steps étapes à jouer / steps to play
     */
    public function __construct(
        /** Ne rien écrire : seulement dire ce qui se passerait. */
        public readonly bool $dryRun = false,
        /** Nombre de lignes maximum par étape et par règle. */
        public readonly int $limit = 1000,
        array $steps = [],
        /** Garder l'identifiant des premières lignes dans le rapport. */
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
