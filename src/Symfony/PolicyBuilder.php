<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony;

use InvalidArgumentException;
use Kaveraa\DataLifecycle\Fields;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\PolicyFactory;
use Kaveraa\DataLifecycle\PolicyRegistry;

/**
 * Gathers the policies: first the ones read from the attributes of the classes
 * listed under "discover", then the ones written under "subjects", which win.
 */
final class PolicyBuilder
{
    /**
     * @param list<string>                $discover
     * @param array<string, mixed>        $subjects
     */
    public static function build(Fields $defaults, array $discover, array $subjects): PolicyRegistry
    {
        $registry = new PolicyRegistry();
        $factory = new PolicyFactory($defaults);

        foreach ($discover as $class) {
            self::addFromAttributes($registry, $factory, (string) $class);
        }

        foreach ($subjects as $class => $options) {
            $class = (string) $class;
            $options = is_array($options) ? $options : [];

            // Empty entry: we keep the policy from the attributes, if the class has one.
            if ($options === []) {
                if (!$registry->has($class)) {
                    self::addFromAttributes($registry, $factory, $class);
                }

                continue;
            }

            $registry->add(Policy::fromArray($class, $options, $defaults));
        }

        return $registry;
    }

    private static function addFromAttributes(PolicyRegistry $registry, PolicyFactory $factory, string $class): void
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf(
                'The data_lifecycle configuration mentions "%s", but no such class can be loaded.',
                $class,
            ));
        }

        $policy = $factory->fromAttributes($class);

        if ($policy !== null) {
            $registry->add($policy);
        }
    }
}
