<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony;

use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Wires the two PSR interfaces the package needs, without ever overwriting what
 * the application already has: the Symfony clock and its event dispatcher
 * implement them, we do not replace them.
 */
final class OptionalAliasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ClockInterface::class) && $container->has('data_lifecycle.clock')) {
            $container->setAlias(ClockInterface::class, 'data_lifecycle.clock');
        }

        // The Symfony dispatcher already implements the PSR interface: the
        // package events can be listened to like any other.
        if (!$container->has(EventDispatcherInterface::class) && $container->has('event_dispatcher')) {
            $container->setAlias(EventDispatcherInterface::class, 'event_dispatcher');
        }
    }
}
