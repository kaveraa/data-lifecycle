<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Support;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps all the emitted events, to check them in the tests.
 */
final class Recorder implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @param class-string $class
     *
     * @return list<object>
     */
    public function of(string $class): array
    {
        return array_values(array_filter($this->events, static fn (object $event): bool => $event instanceof $class));
    }
}
