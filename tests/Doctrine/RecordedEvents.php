<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A dispatcher that keeps everything, to check what the package announces.
 */
final class RecordedEvents implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $all = [];

    public function dispatch(object $event): object
    {
        $this->all[] = $event;

        return $event;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static function (object $event): string {
            $parts = explode('\\', $event::class);

            return end($parts);
        }, $this->all);
    }

    public function forget(): void
    {
        $this->all = [];
    }
}
