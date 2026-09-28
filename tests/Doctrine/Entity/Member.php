<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Kaveraa\DataLifecycle\Attribute\DisableFirst;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenAnonymise;
use Kaveraa\DataLifecycle\Attribute\WarnBefore;
use Kaveraa\DataLifecycle\Strategy;

/**
 * Le parcours complet : deux rappels, une désactivation, puis une anonymisation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'members')]
#[KeepFor('3 years')]
#[WarnBefore('30 days')]
#[WarnBefore('7 days')]
#[DisableFirst('30 days')]
#[ThenAnonymise(['email' => Strategy::Email, 'name' => Strategy::Text])]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    /** Volontairement nullable : la requête doit passer par COALESCE. */
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $lifecycleWarnStage = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $lifecycleWarnedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $disabledAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $anonymisedAt = null;

    public function __construct(
        #[ORM\Column]
        public string $email = 'someone@example.test',
        #[ORM\Column]
        public string $name = 'Someone',
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        public ?DateTimeImmutable $lastActiveAt = null,
    ) {
    }
}
