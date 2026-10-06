<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Support\Facades\DB;
use Kaveraa\DataLifecycle\Stage;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\User;

/**
 * The full journey, month after month: active, reminder 1, reminder 2,
 * disable, anonymisation.
 */
final class LifecycleJourneyTest extends TestCase
{
    public function test_a_user_goes_all_the_way_from_active_to_anonymised(): void
    {
        $user = User::query()->create([
            'name' => 'Camille',
            'email' => 'camille@example.test',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);

        // Three years to go: nothing moves.
        $this->moveTo('2026-06-01 09:00:00');
        $report = $this->lifecycle()->run();

        self::assertTrue($report->isEmpty());
        self::assertSame(Stage::Active, $user->fresh()->lifecycleStage());
        self::assertSame('2027-01-01', $user->fresh()->lifecycleDueAt()?->format('Y-m-d'));

        // Thirty days before the deadline: first reminder.
        $this->moveTo('2026-12-05 09:00:00');
        $report = $this->lifecycle()->run();

        self::assertSame(1, $report->countFor(User::class, Step::Warn));
        self::assertSame([$user->id], $report->samplesFor(User::class, Step::Warn));

        $row = DB::table('users')->find($user->id);
        self::assertSame(1, (int) $row->lifecycle_warn_stage);
        self::assertNotNull($row->lifecycle_warned_at);
        self::assertNull($row->disabled_at);
        self::assertSame(Stage::Warned, $user->fresh()->lifecycleStage());

        // The same day, a second time: the reminder is not sent again.
        $report = $this->lifecycle()->run();
        self::assertSame(0, $report->countFor(User::class, Step::Warn));

        // Seven days before the deadline: second reminder.
        $this->moveTo('2026-12-28 09:00:00');
        $report = $this->lifecycle()->run();

        self::assertSame(1, $report->countFor(User::class, Step::Warn));
        self::assertSame(2, (int) DB::table('users')->find($user->id)->lifecycle_warn_stage);

        // Deadline reached: disable, and above all no erasure.
        $this->moveTo('2027-01-02 09:00:00');
        $report = $this->lifecycle()->run();

        self::assertSame(1, $report->countFor(User::class, Step::Disable));
        self::assertSame(0, $report->countFor(User::class, Step::Erase));

        $row = DB::table('users')->find($user->id);
        self::assertNotNull($row->disabled_at);
        self::assertNull($row->anonymised_at);
        self::assertSame('camille@example.test', $row->email);
        self::assertSame(Stage::Disabled, $user->fresh()->lifecycleStage());

        // Thirty days of grace passed: anonymisation.
        $this->moveTo('2027-02-05 09:00:00');
        $report = $this->lifecycle()->run();

        self::assertSame(1, $report->countFor(User::class, Step::Erase));

        $row = DB::table('users')->find($user->id);
        self::assertSame('anonymous-' . $user->id . '@anonymous.invalid', $row->email);
        self::assertSame('Anonymous', $row->name);
        self::assertNotNull($row->anonymised_at);
        self::assertSame(Stage::Erased, $user->fresh()->lifecycleStage());

        // The row is still there, but it never comes back.
        self::assertSame(1, User::query()->count());
        self::assertTrue($this->lifecycle()->run()->isEmpty());
        self::assertFalse($this->lifecycle()->reactivate($user->fresh()));
    }

    public function test_the_disable_step_does_not_touch_updated_at(): void
    {
        $user = User::query()->create([
            'email' => 'sans-bruit@example.test',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);

        DB::table('users')->where('id', $user->id)->update(['updated_at' => '2024-01-01 00:00:00']);

        $this->moveTo('2027-01-02 09:00:00');
        $this->lifecycle()->run();

        self::assertSame('2024-01-01 00:00:00', DB::table('users')->find($user->id)->updated_at);
    }

    public function test_the_scopes_of_the_trait_follow_the_policy_columns(): void
    {
        $active = User::query()->create(['last_active_at' => '2026-01-01 00:00:00']);
        $gone = User::query()->create([
            'last_active_at' => '2024-01-01 00:00:00',
            'disabled_at' => '2027-01-02 00:00:00',
        ]);

        self::assertSame([$active->id], User::query()->active()->pluck('id')->all());
        self::assertSame(1, User::active()->count());
        self::assertSame([$gone->id], User::query()->disabled()->pluck('id')->all());
        self::assertSame([], User::query()->anonymised()->pluck('id')->all());
    }
}
