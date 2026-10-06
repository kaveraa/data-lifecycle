<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Kaveraa\DataLifecycle\Laravel\Middleware\TrackActivity;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * The activity signal: a write from time to time, not one per request.
 */
final class TrackActivityTest extends TestCase
{
    public function test_it_writes_once_then_holds_back_inside_the_window(): void
    {
        $user = User::query()->create([
            'email' => 'lou@example.test',
            'last_active_at' => null,
        ]);

        DB::table('users')->where('id', $user->id)->update(['updated_at' => '2024-01-01 00:00:00']);

        // First request: the date is unknown, we write.
        $this->moveTo('2024-03-01 10:00:00');
        $this->pass($user->fresh());

        self::assertSame('2024-03-01 10:00:00', DB::table('users')->find($user->id)->last_active_at);

        // Five minutes later: inside the window, we touch nothing.
        $this->moveTo('2024-03-01 10:05:00');
        $queries = $this->passAndCountQueries($user->fresh());

        self::assertSame(0, $queries);
        self::assertSame('2024-03-01 10:00:00', DB::table('users')->find($user->id)->last_active_at);

        // Half an hour later: the window has passed, we write again.
        $this->moveTo('2024-03-01 10:31:00');
        $this->pass($user->fresh());

        self::assertSame('2024-03-01 10:31:00', DB::table('users')->find($user->id)->last_active_at);

        // And updated_at never changed.
        self::assertSame('2024-01-01 00:00:00', DB::table('users')->find($user->id)->updated_at);
    }

    public function test_it_does_nothing_when_nobody_is_signed_in(): void
    {
        $user = User::query()->create([
            'email' => 'lou@example.test',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);

        $this->moveTo('2024-03-01 10:00:00');

        $queries = $this->passAndCountQueries(null);

        self::assertSame(0, $queries);
        self::assertSame('2024-01-01 00:00:00', DB::table('users')->find($user->id)->last_active_at);
    }

    public function test_the_window_comes_from_the_configuration(): void
    {
        $this->app->make('config')->set('data-lifecycle.activity.throttle', 1);
        $this->app->forgetInstance(TrackActivity::class);

        $user = User::query()->create(['last_active_at' => '2024-03-01 10:00:00']);

        // Thirty seconds: still inside the one minute window.
        $this->moveTo('2024-03-01 10:00:30');
        $this->pass($user->fresh());

        self::assertSame('2024-03-01 10:00:00', DB::table('users')->find($user->id)->last_active_at);

        // Two minutes: the window has passed.
        $this->moveTo('2024-03-01 10:02:00');
        $this->pass($user->fresh());

        self::assertSame('2024-03-01 10:02:00', DB::table('users')->find($user->id)->last_active_at);
    }

    public function test_a_window_of_zero_turns_the_signal_off(): void
    {
        $this->app->make('config')->set('data-lifecycle.activity.throttle', 0);
        $this->app->forgetInstance(TrackActivity::class);

        $user = User::query()->create(['last_active_at' => '2024-03-01 10:00:00']);

        $this->moveTo('2024-06-01 10:00:00');
        $queries = $this->passAndCountQueries($user->fresh());

        self::assertSame(0, $queries);
        self::assertSame('2024-03-01 10:00:00', DB::table('users')->find($user->id)->last_active_at);
    }

    private function pass(?User $user): void
    {
        $request = Request::create('/');
        $request->setUserResolver(static fn (): ?User => $user);

        $response = $this->app->make(TrackActivity::class)
            ->handle($request, static fn (Request $passed): Response => new Response('ok'));

        self::assertSame('ok', $response->getContent());
    }

    private function passAndCountQueries(?User $user): int
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        $this->pass($user);

        $count = count(DB::connection()->getQueryLog());

        DB::connection()->disableQueryLog();

        return $count;
    }
}
