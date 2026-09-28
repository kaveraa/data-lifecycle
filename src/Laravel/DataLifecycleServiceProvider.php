<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kaveraa\DataLifecycle\Anonymiser;
use Kaveraa\DataLifecycle\Driver;
use Kaveraa\DataLifecycle\Fields;
use Kaveraa\DataLifecycle\Laravel\Console\InstallCommand;
use Kaveraa\DataLifecycle\Laravel\Console\ReportCommand;
use Kaveraa\DataLifecycle\Laravel\Console\RunCommand;
use Kaveraa\DataLifecycle\Laravel\Middleware\TrackActivity;
use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\PolicyFactory;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Runner;
use Kaveraa\DataLifecycle\SystemClock;
use Psr\Clock\ClockInterface;

/**
 * Branche le paquet sur Laravel : configuration, services, commandes.
 *
 * Wires the package into Laravel: configuration, services, commands.
 */
final class DataLifecycleServiceProvider extends ServiceProvider
{
    private const CONFIG = __DIR__ . '/../../config/data-lifecycle.php';

    private const MIGRATION = __DIR__ . '/../../database/migrations/add_lifecycle_columns_to_users_table.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG, 'data-lifecycle');

        $this->app->singleton(Fields::class, static fn (Application $app): Fields => Fields::fromArray(
            (array) $app->make('config')->get('data-lifecycle.fields', []),
        ));

        $this->app->singleton(Anonymiser::class, static function (Application $app): Anonymiser {
            $options = (array) $app->make('config')->get('data-lifecycle.anonymiser', []);

            return new Anonymiser(
                emailDomain: (string) ($options['email_domain'] ?? 'anonymous.invalid'),
                redactedText: (string) ($options['redacted_text'] ?? '[removed]'),
                anonymousName: (string) ($options['anonymous_name'] ?? 'Anonymous'),
                pepper: (string) ($options['pepper'] ?? ''),
            );
        });

        // Une horloge déjà déclarée par l'application garde la main.
        $this->app->singletonIf(ClockInterface::class, SystemClock::class);

        $this->app->singleton(EventBridge::class, static fn (Application $app): EventBridge => new EventBridge($app));

        $this->app->singleton(Driver::class, static fn (): Driver => new EloquentDriver());
        $this->app->singleton(EloquentDriver::class, static fn (Application $app): Driver => $app->make(Driver::class));

        $this->app->singleton(PolicyRegistry::class, fn (Application $app): PolicyRegistry => $this->registry($app));

        $this->app->singleton(Runner::class, static fn (Application $app): Runner => new Runner(
            $app->make(Driver::class),
            $app->make(ClockInterface::class),
            $app->make(Anonymiser::class),
            $app->make(EventBridge::class),
        ));

        $this->app->singleton(TrackActivity::class, static fn (Application $app): TrackActivity => new TrackActivity(
            $app->make(PolicyRegistry::class),
            $app->make(Fields::class),
            $app->make(ClockInterface::class),
            (int) $app->make('config')->get('data-lifecycle.activity.throttle', 15),
        ));

        $this->app->singleton(Lifecycle::class, static fn (Application $app): Lifecycle => new Lifecycle(
            $app->make(PolicyRegistry::class),
            $app->make(Runner::class),
            $app->make(Driver::class),
            $app->make(ClockInterface::class),
            $app->make(EventBridge::class),
        ));
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([self::CONFIG => config_path('data-lifecycle.php')], 'data-lifecycle-config');

        $this->publishes([
            self::MIGRATION => database_path('migrations/' . date('Y_m_d_His') . '_add_lifecycle_columns_to_users_table.php'),
        ], 'data-lifecycle-migrations');

        $this->commands([RunCommand::class, ReportCommand::class, InstallCommand::class]);
    }

    /**
     * D'abord les attributs PHP, ensuite la configuration : ce qui est écrit en
     * clair dans config/data-lifecycle.php l'emporte.
     */
    private function registry(Application $app): PolicyRegistry
    {
        $config = (array) $app->make('config')->get('data-lifecycle', []);
        $defaults = $app->make(Fields::class);

        $factory = new PolicyFactory($defaults);
        $registry = new PolicyRegistry();

        foreach ((array) ($config['discover'] ?? []) as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }

            $policy = $factory->fromAttributes($class);

            // Une classe sans #[KeepFor] n'est pas une erreur : on passe.
            if ($policy !== null) {
                $registry->add($policy);
            }
        }

        foreach ((array) ($config['subjects'] ?? []) as $class => $options) {
            $registry->add(Policy::fromArray((string) $class, (array) $options, $defaults));
        }

        return $registry;
    }
}
