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
 * Le signal d'activité : note le passage de la personne connectée, au plus une
 * fois toutes les N minutes.
 *
 * The activity signal: records that the connected person came by, at most once
 * every N minutes.
 *
 * Une écriture à chaque requête serait inacceptable en production : la date du
 * dernier signe de vie est donc relue sur l'entité, et n'est réécrite que si
 * elle a plus de N minutes. Rien n'est écrit sans personne connectée, sans
 * règle pour sa classe, ni sur les sous-requêtes.
 *
 * Pour le désactiver complètement : data_lifecycle.activity.throttle = 0, le
 * service n'est alors pas enregistré.
 */
final class ActivityListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entities,
        private readonly PolicyRegistry $policies,
        private readonly ClockInterface $clock,
        private readonly CurrentUser $user,
        /** Minutes entre deux écritures. 0 : ne rien écrire du tout. */
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

        // Écriture par les métadonnées : aucune entité n'est obligée d'avoir un setter.
        $meta->setFieldValue($user, $property, $now);

        $this->entities->flush();
    }
}
