<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Doctrine;

use InvalidArgumentException;
use Kaveraa\DataLifecycle\Exception\DataLifecycleException;

/**
 * Le nom d'un champ de la règle ne correspond à aucune propriété de l'entité.
 *
 * A field name of the policy matches no property of the entity.
 */
final class UnknownProperty extends InvalidArgumentException implements DataLifecycleException
{
    /**
     * @param list<string> $tried noms de propriétés essayés / property names tried
     */
    public static function on(string $entity, string $role, string $field, array $tried): self
    {
        return new self(sprintf(
            'The lifecycle field "%s" of "%s" is named "%s", but the class has no such property (tried %s). '
            . 'Add the property to the entity, or point that field to an existing one.',
            $role,
            $entity,
            $field,
            '"' . implode('", "', $tried) . '"',
        ));
    }
}
