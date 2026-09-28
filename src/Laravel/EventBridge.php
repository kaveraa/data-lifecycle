<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Fait passer les événements du paquet par le répartiteur de Laravel, pour que
 * Event::listen(SubjectWarned::class, ...) fonctionne normalement.
 *
 * Sends the package events through the Laravel dispatcher, so that
 * Event::listen(SubjectWarned::class, ...) works as usual.
 */
final class EventBridge implements EventDispatcherInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function dispatch(object $event): object
    {
        // Résolu à chaque envoi : Event::fake() remplace le répartiteur en cours de route.
        $this->container->make(Dispatcher::class)->dispatch($event);

        return $event;
    }
}
