<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Support\Facades\Event;
use Kaveraa\DataLifecycle\Event\SubjectAnonymised;
use Kaveraa\DataLifecycle\Event\SubjectDeleted;
use Kaveraa\DataLifecycle\Event\SubjectDisabled;
use Kaveraa\DataLifecycle\Event\SubjectWarned;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ticket;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\User;

/**
 * Les événements du paquet passent par le répartiteur de Laravel, et se taisent
 * en mode observation.
 */
final class EventsTest extends TestCase
{
    /** @var list<string> */
    private array $seen = [];

    public function test_every_step_dispatches_its_event(): void
    {
        $this->record();

        $user = User::query()->create([
            'name' => 'Ines',
            'email' => 'ines@example.test',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);

        $ticket = Ticket::query()->create(['last_active_at' => '2024-01-01 00:00:00']);

        // La forme sans nom d'événement, comme dans le README.
        $warned = null;
        Event::listen(static function (SubjectWarned $event) use (&$warned): void {
            $warned = $event;
        });

        $this->moveTo('2026-12-05 09:00:00');
        $this->lifecycle()->runFor(User::class);

        self::assertSame(['warned:' . $user->id], $this->seen);
        self::assertSame(0, $warned?->warnIndex);
        self::assertSame('2027-01-01', $warned?->dueAt->format('Y-m-d'));
        self::assertSame($user->id, $warned?->entity()->getKey());

        $this->seen = [];
        $this->moveTo('2026-12-28 09:00:00');
        $this->lifecycle()->runFor(User::class);
        self::assertSame(['warned:' . $user->id], $this->seen);
        self::assertSame(1, $warned?->warnIndex);

        $this->seen = [];
        $this->moveTo('2027-01-02 09:00:00');
        $this->lifecycle()->runFor(User::class);
        self::assertSame(['disabled:' . $user->id], $this->seen);

        $this->seen = [];
        $this->moveTo('2027-02-05 09:00:00');
        $this->lifecycle()->run();

        self::assertSame(['deleted:' . $ticket->id, 'anonymised:' . $user->id], $this->seen);
    }

    public function test_observe_mode_stays_silent(): void
    {
        $this->record();

        User::query()->create([
            'name' => 'Ines',
            'email' => 'ines@example.test',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);

        Ticket::query()->create(['last_active_at' => '2024-01-01 00:00:00']);

        $this->moveTo('2027-02-05 09:00:00');

        $report = $this->lifecycle()->observe();

        self::assertGreaterThan(0, $report->total());
        self::assertSame([], $this->seen);
    }

    private function record(): void
    {
        Event::listen(SubjectWarned::class, function (SubjectWarned $event): void {
            $this->seen[] = 'warned:' . $event->subject->id;
        });

        Event::listen(SubjectDisabled::class, function (SubjectDisabled $event): void {
            $this->seen[] = 'disabled:' . $event->subject->id;
        });

        Event::listen(SubjectAnonymised::class, function (SubjectAnonymised $event): void {
            $this->seen[] = 'anonymised:' . $event->subject->id;
        });

        Event::listen(SubjectDeleted::class, function (SubjectDeleted $event): void {
            $this->seen[] = 'deleted:' . $event->subject->id;
        });
    }
}
