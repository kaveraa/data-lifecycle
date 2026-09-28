<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Attribute;

use Attribute;

/**
 * À la fin, la ligne est supprimée.
 *
 * At the end, the row is deleted.
 *
 *     #[ThenDelete]
 *     #[ThenDelete(force: true)] // ignore le soft delete
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ThenDelete
{
    public function __construct(public readonly bool $force = false)
    {
    }
}
