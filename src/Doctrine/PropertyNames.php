<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Traduit les noms de champs de la règle (pensés pour des colonnes SQL, donc en
 * snake_case) en noms de propriétés PHP, seuls compris par le DQL.
 *
 * Translates the field names of a policy (meant for SQL columns, so written in
 * snake_case) into PHP property names, the only ones DQL understands.
 */
final class PropertyNames
{
    /** @var array<string, string|null> classe::champ -> propriété, pour ne chercher qu'une fois */
    private array $known = [];

    /**
     * Le nom de la propriété, ou une exception qui dit ce qui manque et où.
     *
     * @param ClassMetadata<object> $meta
     */
    public function of(ClassMetadata $meta, string $field, string $role): string
    {
        return $this->find($meta, $field)
            ?? throw UnknownProperty::on($meta->getName(), $role, $field, $this->tried($field));
    }

    /**
     * Le nom de la propriété, ou null si l'entité n'en a pas. Sert aux champs
     * facultatifs : on n'impose pas un schéma, chacun n'ajoute que ce dont il a besoin.
     *
     * @param ClassMetadata<object> $meta
     */
    public function find(ClassMetadata $meta, string $field): ?string
    {
        $key = $meta->getName() . '::' . $field;

        if (array_key_exists($key, $this->known)) {
            return $this->known[$key];
        }

        foreach ($this->tried($field) as $candidate) {
            if ($meta->hasField($candidate) || $meta->hasAssociation($candidate)) {
                return $this->known[$key] = $candidate;
            }
        }

        $this->known[$key] = null;

        return null;
    }

    /**
     * Le nom tel quel, puis sa version camelCase : "last_active_at" -> "lastActiveAt".
     *
     * @return list<string>
     */
    public function tried(string $field): array
    {
        $camel = self::camel($field);

        return $camel === $field ? [$field] : [$field, $camel];
    }

    public static function camel(string $field): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $field))));
    }
}
