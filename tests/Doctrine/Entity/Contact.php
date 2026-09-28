<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Kaveraa\DataLifecycle\Attribute\DisableFirst;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;

/**
 * Noms de champs sur mesure : une propriété déjà en snake_case, et une
 * propriété privée qui ne s'écrit que par son setter.
 */
#[ORM\Entity]
#[ORM\Table(name: 'contacts')]
#[KeepFor('2 years', since: 'last_seen_at')]
#[DisableFirst('10 days', field: 'blocked_at')]
#[ThenDelete]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    /** Propriété écrite telle quelle, sans passer par camelCase. */
    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $last_seen_at = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $blockedAt = null;

    /** Prouve que le setter a bien été préféré à l'écriture par métadonnées. */
    public int $setterCalls = 0;

    public function __construct(?DateTimeImmutable $lastSeenAt = null)
    {
        $this->last_seen_at = $lastSeenAt;
    }

    public function setBlockedAt(?DateTimeImmutable $at): void
    {
        ++$this->setterCalls;
        $this->blockedAt = $at;
    }

    public function blockedAt(): ?DateTimeImmutable
    {
        return $this->blockedAt;
    }
}
