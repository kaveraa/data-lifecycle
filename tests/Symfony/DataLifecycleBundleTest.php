<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Symfony;

use Kaveraa\DataLifecycle\Anonymiser;
use Kaveraa\DataLifecycle\Doctrine\DoctrineDriver;
use Kaveraa\DataLifecycle\Driver;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Fields;
use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Runner;
use Kaveraa\DataLifecycle\Strategy;
use Kaveraa\DataLifecycle\Symfony\ActivityListener;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Member;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Session;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Subscriber;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;

final class DataLifecycleBundleTest extends BundleTestCase
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'limit' => 50,
            'subjects' => [
                // The configuration wins over the class attributes (3 years -> 2 years).
                Member::class => [
                    'keep_for' => '2 years',
                    'warn_before' => ['30 days'],
                    'grace' => '15 days',
                    'anonymise' => ['email' => 'email', 'name' => 'text'],
                ],
            ],
            'discover' => [Member::class, Session::class, Subscriber::class],
        ];
    }

    public function test_policies_come_from_the_attributes_and_the_configuration(): void
    {
        $registry = $this->boot(self::config())->get(PolicyRegistry::class);

        self::assertInstanceOf(PolicyRegistry::class, $registry);
        self::assertCount(3, $registry->all());

        $member = $registry->get(Member::class);

        self::assertSame('2 years', (string) $member->keepFor);
        self::assertSame(['30 days'], array_map(static fn (object $one): string => (string) $one, $member->warnBefore));
        self::assertSame('15 days', (string) $member->grace);
        self::assertSame(Ending::Anonymise, $member->ending);
        self::assertSame(['email' => Strategy::Email, 'name' => Strategy::Text], $member->anonymise);
        self::assertSame('last_active_at', $member->fields->since);

        // Read from the attributes, without a single line of configuration.
        $session = $registry->get(Session::class);

        self::assertSame('30 days', (string) $session->keepFor);
        self::assertSame(Ending::Delete, $session->ending);
        self::assertFalse($session->hasDisableStep());

        self::assertSame(Ending::Anonymise, $registry->get(Subscriber::class)->ending);
    }

    public function test_the_services_are_wired(): void
    {
        $container = $this->boot(self::config() + [
            'fields' => ['since' => 'last_active_at', 'warned_at' => 'lifecycle_warned_at'],
            'anonymiser' => ['email_domain' => 'gone.invalid'],
        ]);

        self::assertInstanceOf(DoctrineDriver::class, $container->get(Driver::class));
        self::assertInstanceOf(Runner::class, $container->get(Runner::class));
        self::assertInstanceOf(Lifecycle::class, $container->get(Lifecycle::class));
        self::assertInstanceOf(Anonymiser::class, $container->get(Anonymiser::class));
        self::assertInstanceOf(ClockInterface::class, $container->get(ClockInterface::class));
        self::assertInstanceOf(ActivityListener::class, $container->get(ActivityListener::class));

        $fields = $container->get(Fields::class);

        self::assertInstanceOf(Fields::class, $fields);
        self::assertSame('last_active_at', $fields->since);
        self::assertSame('lifecycle_warned_at', $fields->warnedAt);
        self::assertSame('anonymised_at', $fields->anonymisedAt);

        // The package events can be listened to with the Symfony dispatcher.
        $events = $container->get(EventDispatcherInterface::class);

        self::assertInstanceOf(SymfonyEventDispatcherInterface::class, $events);
    }

    public function test_the_activity_signal_can_be_turned_off(): void
    {
        $container = $this->boot(self::config() + ['activity' => ['throttle' => 0]]);

        self::assertFalse($container->has(ActivityListener::class));
    }
}
