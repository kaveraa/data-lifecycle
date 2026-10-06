<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Kaveraa\DataLifecycle\Attribute\DisableFirst;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;

/**
 * Custom field names: a property already in snake_case, and a private
 * property that can only be written through its setter.
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

    /** Property written as is, without going through camelCase. */
    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $last_seen_at = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $blockedAt = null;

    /** Proves that the setter was preferred over the write through metadata. */
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
