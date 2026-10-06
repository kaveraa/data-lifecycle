<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Support\Facades\DB;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ping;

/**
 * A policy without reminder or disable step works on a table that only has
 * the column of the last sign of life. The package forces no schema.
 */
final class MinimalSchemaTest extends TestCase
{
    public function test_a_table_with_only_the_since_column_is_enough(): void
    {
        $old = Ping::query()->create(['last_active_at' => '2024-01-01 00:00:00']);
        $fresh = Ping::query()->create(['last_active_at' => '2025-06-01 00:00:00']);

        // The table has no reminder counter, no disable date and no
        // anonymisation date: any extra column would make SQLite fail.
        $this->moveTo('2025-06-15 09:00:00');

        $report = $this->lifecycle()->runFor(Ping::class);

        self::assertSame(1, $report->countFor(Ping::class, Step::Erase));
        self::assertSame([$old->id], $report->samplesFor(Ping::class, Step::Erase));

        self::assertNull(DB::table('pings')->find($old->id));
        self::assertNotNull(DB::table('pings')->find($fresh->id));
    }

    public function test_the_queries_never_mention_the_columns_the_policy_does_not_need(): void
    {
        Ping::query()->create(['last_active_at' => '2024-01-01 00:00:00']);

        $this->moveTo('2025-06-15 09:00:00');

        DB::connection()->enableQueryLog();
        $this->lifecycle()->runFor(Ping::class);
        $queries = DB::connection()->getQueryLog();
        DB::connection()->disableQueryLog();

        $sql = implode(' ', array_column($queries, 'query'));

        self::assertStringNotContainsString('lifecycle_warn_stage', $sql);
        self::assertStringNotContainsString('disabled_at', $sql);
        self::assertStringNotContainsString('anonymised_at', $sql);
        self::assertStringContainsString('last_active_at', $sql);
    }
}
