<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony;

use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Branche les deux interfaces PSR dont le paquet a besoin, sans jamais écraser
 * ce que l'application a déjà : l'horloge de Symfony et son répartiteur
 * d'événements les implémentent, on ne les remplace pas.
 *
 * Wires the two PSR interfaces the package needs, without ever overwriting what
 * the application already has.
 */
final class OptionalAliasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ClockInterface::class) && $container->has('data_lifecycle.clock')) {
            $container->setAlias(ClockInterface::class, 'data_lifecycle.clock');
        }

        // Le répartiteur de Symfony implémente déjà l'interface PSR : les
        // événements du paquet s'écoutent comme n'importe quel autre.
        if (!$container->has(EventDispatcherInterface::class) && $container->has('event_dispatcher')) {
            $container->setAlias(EventDispatcherInterface::class, 'event_dispatcher');
        }
    }
}
