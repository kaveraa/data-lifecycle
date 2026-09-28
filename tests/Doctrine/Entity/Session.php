<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;

/**
 * Le minimum : une seule propriété du paquet, le dernier signe de vie.
 * Aucun compteur de rappel, aucune date de désactivation ni d'anonymisation.
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
