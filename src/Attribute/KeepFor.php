<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Attribute;

use Attribute;

/**
 * Combien de temps la ligne est gardée après son dernier signe de vie.
 *
 * How long the row is kept after its last sign of life.
 *
 *     #[KeepFor('3 years')]
 *     #[KeepFor('18 months', since: 'last_order_at')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class KeepFor
{
    public function __construct(
        public readonly string $duration,
        public readonly ?string $since = null,
    ) {
    }
}
