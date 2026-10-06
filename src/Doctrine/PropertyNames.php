<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Translates the field names of a policy (meant for SQL columns, so written in
 * snake_case) into PHP property names, the only ones DQL understands.
 */
final class PropertyNames
{
    /** @var array<string, string|null> class::field -> property, so we search only once */
    private array $known = [];

    /**
     * The property name, or an exception that says what is missing and where.
     *
     * @param ClassMetadata<object> $meta
     */
    public function of(ClassMetadata $meta, string $field, string $role): string
    {
        return $this->find($meta, $field)
            ?? throw UnknownProperty::on($meta->getName(), $role, $field, $this->tried($field));
    }

    /**
     * The property name, or null if the entity does not have it. Used for optional
     * fields: we do not force a schema, everyone adds only what they need.
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
     * The name as is, then its camelCase form: "last_active_at" -> "lastActiveAt".
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
