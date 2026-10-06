<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony;

use Doctrine\ORM\EntityManagerInterface;
use Kaveraa\DataLifecycle\Doctrine\PropertyNames;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The activity signal: records that the connected person came by, at most once
 * every N minutes.
 *
 * One write per request would be unacceptable in production: the date of the
 * last sign of life is read back from the entity, and is written again only if
 * it is older than N minutes. Nothing is written without a logged in person,
 * without a policy for its class, or on sub-requests.
 *
 * To turn it off completely: data_lifecycle.activity.throttle = 0, the
 * service is then not registered.
 */
final class ActivityListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entities,
        private readonly PolicyRegistry $policies,
        private readonly ClockInterface $clock,
        private readonly CurrentUser $user,
        /** Minutes between two writes. 0: write nothing at all. */
        private readonly int $throttleMinutes = 15,
        private readonly PropertyNames $names = new PropertyNames(),
    ) {
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 8]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($this->throttleMinutes <= 0 || !$event->isMainRequest()) {
            return;
        }

        $user = ($this->user)();

        if ($user === null) {
            return;
        }

        $policy = $this->policies->for($user);

        if ($policy === null || $this->entities->getMetadataFactory()->isTransient($user::class)) {
            return;
        }

        $meta = $this->entities->getClassMetadata($user::class);
        $property = $this->names->find($meta, $policy->fields->since);

        if ($property === null) {
            return;
        }

        $now = $this->clock->now();
        $last = $meta->getFieldValue($user, $property);

        if ($last instanceof \DateTimeInterface && $last > $now->modify('-' . $this->throttleMinutes . ' minutes')) {
            return;
        }

        // Write through the metadata: no entity is required to have a setter.
        $meta->setFieldValue($user, $property, $now);

        $this->entities->flush();
    }
}
