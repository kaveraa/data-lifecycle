<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Attribute;

use Attribute;

/**
 * Désactiver d'abord, effacer seulement après ce délai de grâce.
 * Pendant la grâce, la personne peut revenir et tout repart à zéro.
 *
 * Disable first, erase only after this grace period.
 * During the grace period the person can come back and everything is reset.
 *
 *     #[DisableFirst('30 days')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class DisableFirst
{
    public function __construct(
        public readonly string $grace,
        public readonly ?string $field = null,
    ) {
    }
}
