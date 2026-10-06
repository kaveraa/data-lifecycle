<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * What happens at the end: anonymise (the row stays, without personal data)
 * or delete (the row is removed).
 */
enum Ending: string
{
    case Anonymise = 'anonymise';
    case Delete = 'delete';
}
