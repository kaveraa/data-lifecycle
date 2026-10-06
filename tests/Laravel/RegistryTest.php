<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Member;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ping;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ticket;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\User;

/**
 * The registry is filled with the attributes, then with the configuration.
 */
final class RegistryTest extends TestCase
{
    public function test_it_reads_attributes_and_configuration(): void
    {
        $registry = $this->app->make(PolicyRegistry::class);

        self::assertTrue($registry->has(User::class));
        self::assertTrue($registry->has(Member::class));
        self::assertTrue($registry->has(Ping::class));
        self::assertTrue($registry->has(Ticket::class));

        // Ticket comes from its PHP attributes.
        $ticket = $registry->get(Ticket::class);
        self::assertSame('1 year', (string) $ticket->keepFor);
        self::assertSame(Ending::Delete, $ticket->ending);
        self::assertTrue($ticket->forceDelete);
        self::assertFalse($ticket->hasDisableStep());

        // Member is in "discover" without #[KeepFor]: skipped silently,
        // then declared by the configuration.
        $member = $registry->get(Member::class);
        self::assertSame('2 years', (string) $member->keepFor);
        self::assertSame(Ending::Anonymise, $member->ending);

        // The reminders are sorted from the farthest to the closest.
        $user = $registry->get(User::class);
        self::assertSame(['30 days', '7 days'], array_map(strval(...), $user->warnBefore));
        self::assertSame('last_active_at', $user->fields->since);
    }

    public function test_the_column_names_come_from_the_configuration(): void
    {
        $this->app->make('config')->set('data-lifecycle.fields.since', 'seen_at');
        $this->app->forgetInstance(PolicyRegistry::class);
        $this->app->forgetInstance(\Kaveraa\DataLifecycle\Fields::class);

        self::assertSame('seen_at', $this->app->make(PolicyRegistry::class)->get(User::class)->fields->since);
    }
}
