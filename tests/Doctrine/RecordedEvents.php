<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Un répartiteur qui garde tout, pour vérifier ce que le paquet annonce.
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
