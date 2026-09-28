<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Attribute;

use Attribute;

/**
 * Prévenir la personne avant l'échéance. À répéter pour plusieurs rappels.
 *
 * Warn the person before the deadline. Repeat it for several reminders.
 *
 *     #[WarnBefore('30 days')]
 *     #[WarnBefore('7 days')]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class WarnBefore
{
    public function __construct(public readonly string $before)
    {
    }
}
