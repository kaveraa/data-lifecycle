<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * The three actions of the lifecycle, in order.
 */
enum Step: string
{
    case Warn = 'warn';
    case Disable = 'disable';
    case Erase = 'erase';
}
