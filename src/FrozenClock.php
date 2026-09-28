<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Une horloge arrêtée, pour les tests et pour rejouer une exécution à une date donnée.
 *
 * A stopped clock, for tests and to replay a run at a given date.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $moment)
    {
    }

    public static function at(string $moment): self
    {
        return new self(new DateTimeImmutable($moment));
    }

    public function now(): DateTimeImmutable
    {
        return $this->moment;
    }

    public function moveTo(string $moment): void
    {
        $this->moment = new DateTimeImmutable($moment);
    }
}
