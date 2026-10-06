<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
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
        // Resolved on each dispatch: Event::fake() swaps the dispatcher along the way.
        $this->container->make(Dispatcher::class)->dispatch($event);

        return $event;
    }
}
