<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * Les trois actions du cycle de vie, dans l'ordre.
 *
 * The three actions of the lifecycle, in order.
 */
enum Step: string
{
    case Warn = 'warn';
    case Disable = 'disable';
    case Erase = 'erase';
}
