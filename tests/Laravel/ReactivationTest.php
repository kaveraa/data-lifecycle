<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Kaveraa\DataLifecycle\Event\SubjectReactivated;
use Kaveraa\DataLifecycle\Stage;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\User;

/**
 * La personne revient pendant la période de grâce : tout repart à zéro.
 */
final class ReactivationTest extends TestCase
{
    public function test_coming_back_during_the_grace_period_resets_everything(): void
    {
        $user = User::query()->create([
            'name' => 'Sacha',
            'email' => 'sacha@example.test',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);

        // Rappels puis désactivation.
        $this->moveTo('2026-12-05 09:00:00');
        $this->lifecycle()->run();
        $this->moveTo('2026-12-28 09:00:00');
        $this->lifecycle()->run();
        $this->moveTo('2027-01-02 09:00:00');
        $this->lifecycle()->run();

        self::assertSame(Stage::Disabled, $user->fresh()->lifecycleStage());

        $seen = [];
        Event::listen(SubjectReactivated::class, static function (SubjectReactivated $event) use (&$seen): void {
            $seen[] = $event->subject->id;
        });

        // Elle revient avant la fin de la grâce.
        $this->moveTo('2027-01-10 09:00:00');

        self::assertTrue($user->fresh()->reactivate());
        self::assertSame([$user->id], $seen);

        $row = DB::table('users')->find($user->id);
        self::assertNull($row->disabled_at);
        self::assertNull($row->lifecycle_warned_at);
        self::assertSame(0, (int) $row->lifecycle_warn_stage);
        self::assertSame('2027-01-10 09:00:00', $row->last_active_at);
        self::assertSame('sacha@example.test', $row->email);
        self::assertSame(Stage::Active, $user->fresh()->lifecycleStage());

        // Et la ligne n'est plus candidate à rien, même après la fin de la grâce.
        $this->moveTo('2027-02-20 09:00:00');
        self::assertTrue($this->lifecycle()->run()->isEmpty());

        self::assertSame(1, User::query()->count());
        self::assertSame('sacha@example.test', $user->fresh()->email);
    }
}
