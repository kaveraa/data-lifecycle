<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * Où en est une ligne dans son cycle de vie.
 *
 * Where a record stands in its lifecycle.
 */
enum Stage: string
{
    case Active = 'active';
    case Warned = 'warned';
    case Disabled = 'disabled';
    case Erased = 'erased';
}
