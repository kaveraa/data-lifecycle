<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Support\Facades\DB;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Member;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ticket;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\User;

/**
 * Observe mode: the report gives the right numbers and the database does not change.
 */
final class ObserveModeTest extends TestCase
{
    public function test_observing_counts_everything_and_writes_nothing(): void
    {
        $toWarn = User::query()->create([
            'name' => 'Alix',
            'email' => 'alix@example.test',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);

        $toDisable = User::query()->create([
            'name' => 'Noa',
            'email' => 'noa@example.test',
            'last_active_at' => '2023-01-01 00:00:00',
            'lifecycle_warn_stage' => 2,
        ]);

        $toErase = User::query()->create([
            'name' => 'Remi',
            'email' => 'remi@example.test',
            'last_active_at' => '2022-01-01 00:00:00',
            'lifecycle_warn_stage' => 2,
            'disabled_at' => '2026-01-01 00:00:00',
        ]);

        $member = Member::query()->create([
            'email' => 'zoe@example.test',
            'last_active_at' => '2020-01-01 00:00:00',
        ]);

        $ticket = Ticket::query()->create([
            'label' => 'Vieux billet',
            'last_active_at' => '2020-01-01 00:00:00',
        ]);

        $this->moveTo('2026-12-05 09:00:00');

        $before = $this->snapshot();

        $report = $this->lifecycle()->observe();

        self::assertTrue($report->dryRun);
        self::assertSame(1, $report->countFor(User::class, Step::Warn));
        self::assertSame(1, $report->countFor(User::class, Step::Disable));
        self::assertSame(1, $report->countFor(User::class, Step::Erase));
        self::assertSame(1, $report->countFor(Member::class, Step::Erase));
        self::assertSame(1, $report->countFor(Ticket::class, Step::Erase));
        self::assertSame(5, $report->total());

        self::assertSame([$toWarn->id], $report->samplesFor(User::class, Step::Warn));
        self::assertSame([$toDisable->id], $report->samplesFor(User::class, Step::Disable));
        self::assertSame([$toErase->id], $report->samplesFor(User::class, Step::Erase));
        self::assertSame([$member->id], $report->samplesFor(Member::class, Step::Erase));
        self::assertSame([$ticket->id], $report->samplesFor(Ticket::class, Step::Erase));

        // Column by column: nothing changed.
        self::assertEquals($before, $this->snapshot());

        // And we can do it again as many times as we want.
        self::assertSame(5, $this->lifecycle()->observe()->total());
        self::assertEquals($before, $this->snapshot());
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $tables = [];

        foreach (['users', 'members', 'tickets', 'pings'] as $table) {
            $tables[$table] = array_map(
                static fn (object $row): array => (array) $row,
                DB::table($table)->orderBy('id')->get()->all(),
            );
        }

        return $tables;
    }
}
