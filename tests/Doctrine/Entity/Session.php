<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;

/**
 * The minimum: a single package property, the last sign of life.
 * No reminder counter, no disable date, no anonymisation date.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
#[KeepFor('30 days')]
#[ThenDelete]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        public ?DateTimeImmutable $lastActiveAt = null,
    ) {
    }
}
