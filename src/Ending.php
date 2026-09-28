<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * Ce qui arrive à la fin : anonymiser (la ligne reste, sans donnée personnelle)
 * ou supprimer (la ligne disparaît).
 *
 * What happens at the end: anonymise (the row stays, without personal data)
 * or delete (the row is removed).
 */
enum Ending: string
{
    case Anonymise = 'anonymise';
    case Delete = 'delete';
}
