<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kaveraa\DataLifecycle\FrozenClock;
use Kaveraa\DataLifecycle\Laravel\DataLifecycleServiceProvider;
use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Member;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ping;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ticket;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\User;
use Orchestra\Testbench\TestCase as Testbench;
use Psr\Clock\ClockInterface;

/**
 * Base of the Laravel tests: the service provider, four demo tables
 * and a stopped clock that each test moves forward.
 */
abstract class TestCase extends Testbench
{
    protected FrozenClock $clock;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [DataLifecycleServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->clock = FrozenClock::at($this->startsAt());
        $app->instance(ClockInterface::class, $this->clock);

        $config = $app->make('config');

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $config->set('data-lifecycle.subjects', $this->subjects());
        $config->set('data-lifecycle.discover', $this->discover());
    }

    protected function defineDatabaseMigrations(): void
    {
        // The full journey: all the columns.
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedTinyInteger('lifecycle_warn_stage')->default(0);
            $table->timestamp('lifecycle_warned_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();
        });

        // Direct anonymisation: no reminder counter, no disable date.
        Schema::create('members', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('email')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();
        });

        // Deletion: no anonymisation date.
        Schema::create('tickets', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('label')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // The absolute minimum: a single column besides the key.
        Schema::create('pings', function (Blueprint $table): void {
            $table->increments('id');
            $table->timestamp('last_active_at')->nullable();
        });
    }

    protected function startsAt(): string
    {
        return '2024-01-01 00:00:00';
    }

    /**
     * @return array<class-string, array<string, mixed>>
     */
    protected function subjects(): array
    {
        return [
            User::class => [
                'keep_for' => '3 years',
                'warn_before' => ['30 days', '7 days'],
                'grace' => '30 days',
                'anonymise' => ['email' => 'email', 'name' => 'text'],
            ],
            Member::class => [
                'keep_for' => '2 years',
                'anonymise' => ['email' => 'email'],
            ],
            Ping::class => [
                'keep_for' => '1 year',
                'delete' => true,
            ],
        ];
    }

    /**
     * Ticket has its attributes, Member has none: it must be skipped.
     *
     * @return list<class-string>
     */
    protected function discover(): array
    {
        return [Ticket::class, Member::class];
    }

    protected function moveTo(string $moment): void
    {
        $this->clock->moveTo($moment);
    }

    protected function lifecycle(): Lifecycle
    {
        return $this->app->make(Lifecycle::class);
    }
}
