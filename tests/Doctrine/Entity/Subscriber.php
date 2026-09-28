<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenAnonymise;

/**
 * Sans étape de désactivation : on anonymise dès la fin de la conservation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'subscribers')]
#[KeepFor('1 year')]
#[ThenAnonymise('email')]
class Subscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $anonymisedAt = null;

    public function __construct(
        #[ORM\Column]
        public string $email = 'someone@example.test',
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        public ?DateTimeImmutable $lastActiveAt = null,
    ) {
    }
}
