<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use Kaveraa\DataLifecycle\Exception\InvalidPolicy;

/**
 * Toutes les règles de l'application, par classe.
 *
 * All the policies of the application, by class.
 */
final class PolicyRegistry
{
    /** @var array<string, Policy> */
    private array $policies = [];

    /**
     * @param list<Policy> $policies
     */
    public function __construct(array $policies = [])
    {
        foreach ($policies as $policy) {
            $this->add($policy);
        }
    }

    public function add(Policy $policy): void
    {
        $this->policies[$policy->subject] = $policy;
    }

    public function has(string $subject): bool
    {
        return isset($this->policies[$subject]);
    }

    public function get(string $subject): Policy
    {
        return $this->policies[$subject] ?? throw InvalidPolicy::unknownSubject($subject);
    }

    /**
     * @return list<Policy>
     */
    public function all(): array
    {
        return array_values($this->policies);
    }

    /**
     * Cherche la règle d'un objet, en remontant les classes parentes.
     */
    public function for(object $entity): ?Policy
    {
        $class = $entity::class;

        while ($class !== false) {
            if (isset($this->policies[$class])) {
                return $this->policies[$class];
            }

            $class = get_parent_class($class);
        }

        return null;
    }
}
