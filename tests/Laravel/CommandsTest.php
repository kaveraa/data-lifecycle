<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Member;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\User;
use Symfony\Component\Console\Command\Command as Console;

/**
 * Les trois commandes artisan.
 */
final class CommandsTest extends TestCase
{
    public function test_run_applies_the_policies(): void
    {
        $user = $this->sleepingUser();

        $this->moveTo('2026-12-05 09:00:00');

        $output = $this->fire('lifecycle:run');

        self::assertStringContainsString('Entité', $output);
        self::assertStringContainsString('User', $output);
        self::assertStringContainsString('rappel', $output);
        self::assertStringContainsString('Total : 1 ligne(s).', $output);
        self::assertStringNotContainsString('Essai à blanc', $output);

        self::assertSame(1, (int) DB::table('users')->find($user->id)->lifecycle_warn_stage);
    }

    public function test_run_says_nothing_to_do_when_everything_is_fresh(): void
    {
        User::query()->create(['last_active_at' => '2024-01-01 00:00:00']);

        $this->moveTo('2024-02-01 09:00:00');

        self::assertStringContainsString('Rien à faire', $this->fire('lifecycle:run'));
    }

    public function test_run_with_dry_run_writes_nothing(): void
    {
        $user = $this->sleepingUser();

        $this->moveTo('2026-12-05 09:00:00');

        $output = $this->fire('lifecycle:run', ['--dry-run' => true]);

        self::assertStringContainsString('Essai à blanc', $output);
        self::assertStringNotContainsString('imposé par la configuration', $output);
        self::assertSame(0, (int) DB::table('users')->find($user->id)->lifecycle_warn_stage);
    }

    public function test_the_configuration_can_force_the_observe_mode(): void
    {
        $this->app->make('config')->set('data-lifecycle.dry_run', true);

        $user = $this->sleepingUser();

        $this->moveTo('2026-12-05 09:00:00');

        $output = $this->fire('lifecycle:run');

        self::assertStringContainsString('Essai à blanc', $output);
        self::assertStringContainsString('imposé par la configuration', $output);
        self::assertSame(0, (int) DB::table('users')->find($user->id)->lifecycle_warn_stage);
    }

    public function test_run_can_be_narrowed_to_one_subject_and_one_step(): void
    {
        $user = $this->sleepingUser();

        Member::query()->create([
            'email' => 'oubli@example.test',
            'last_active_at' => '2020-01-01 00:00:00',
        ]);

        $this->moveTo('2026-12-05 09:00:00');

        $this->fire('lifecycle:run', [
            '--subject' => [User::class],
            '--step' => ['warn'],
            '--limit' => 10,
        ]);

        self::assertSame(1, (int) DB::table('users')->find($user->id)->lifecycle_warn_stage);
        self::assertNull(DB::table('members')->first()->anonymised_at);
    }

    public function test_run_refuses_an_unknown_step(): void
    {
        $this->withoutMockingConsoleOutput();

        $code = $this->artisan('lifecycle:run', ['--step' => ['sieste']]);

        self::assertSame(Console::FAILURE, $code);
        self::assertStringContainsString('Étape inconnue', Artisan::output());
    }

    public function test_report_describes_the_policies_and_writes_nothing(): void
    {
        $user = $this->sleepingUser();

        $this->moveTo('2026-12-05 09:00:00');

        $before = DB::table('users')->find($user->id);

        $output = $this->fire('lifecycle:report');

        self::assertStringContainsString('Règles de conservation', $output);
        self::assertStringContainsString(
            'User : conservation 3 years, rappels 30 days puis 7 days, grâce 30 days, fin : anonymisation de email, name',
            $output,
        );
        self::assertStringContainsString(
            'Ticket : conservation 1 year, aucun rappel, pas de désactivation, fin : suppression définitive',
            $output,
        );
        self::assertStringContainsString('Ping : conservation 1 year, aucun rappel, pas de désactivation, fin : suppression', $output);
        self::assertStringContainsString('Essai à blanc', $output);

        self::assertEquals($before, DB::table('users')->find($user->id));
    }

    public function test_install_publishes_the_config_and_the_migration(): void
    {
        $output = $this->fire('lifecycle:install');

        self::assertStringContainsString('Étapes suivantes', $output);
        self::assertFileExists(config_path('data-lifecycle.php'));
        self::assertNotSame([], $this->publishedMigrations());
    }

    protected function tearDown(): void
    {
        $config = config_path('data-lifecycle.php');

        if (file_exists($config)) {
            unlink($config);
        }

        foreach ($this->publishedMigrations() as $migration) {
            unlink($migration);
        }

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function test_a_limit_that_makes_no_sense_stops_the_command(): void
    {
        $this->withoutMockingConsoleOutput();

        self::assertSame(Console::FAILURE, $this->artisan('lifecycle:run', ['--limit' => '0']));
        self::assertStringContainsString('Limite invalide', Artisan::output());

        self::assertSame(Console::FAILURE, $this->artisan('lifecycle:report', ['--limit' => 'beaucoup']));
        self::assertStringContainsString('Limite invalide', Artisan::output());
    }

    private function fire(string $command, array $options = []): string
    {
        $this->withoutMockingConsoleOutput();

        self::assertSame(Console::SUCCESS, $this->artisan($command, $options));

        return Artisan::output();
    }

    /**
     * @return list<string>
     */
    private function publishedMigrations(): array
    {
        return array_values((array) glob(database_path('migrations/*_add_lifecycle_columns_to_users_table.php')));
    }

    private function sleepingUser(): User
    {
        return User::query()->create([
            'name' => 'Ilan',
            'email' => 'ilan@example.test',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);
    }
}
