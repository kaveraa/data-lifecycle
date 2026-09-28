<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Support\Facades\DB;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ping;

/**
 * Une règle sans rappel ni désactivation fonctionne sur une table qui n'a que
 * la colonne du dernier signe de vie. Le paquet n'impose aucun schéma.
 */
final class MinimalSchemaTest extends TestCase
{
    public function test_a_table_with_only_the_since_column_is_enough(): void
    {
        $old = Ping::query()->create(['last_active_at' => '2024-01-01 00:00:00']);
        $fresh = Ping::query()->create(['last_active_at' => '2025-06-01 00:00:00']);

        // La table n'a ni compteur de rappels, ni date de désactivation, ni
        // date d'anonymisation : la moindre colonne en trop ferait échouer SQLite.
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
