<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * Where a record stands in its lifecycle.
 */
enum Stage: string
{
    case Active = 'active';
    case Warned = 'warned';
    case Disabled = 'disabled';
    case Erased = 'erased';
}
