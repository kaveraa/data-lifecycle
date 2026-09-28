<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Illuminate\Support\Facades\DB;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Member;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ticket;

/**
 * Deux règles sans étape de désactivation : anonymisation directe d'un côté,
 * suppression définitive de l'autre.
 */
final class DirectErasureTest extends TestCase
{
    public function test_a_policy_without_a_disable_step_anonymises_straight_away(): void
    {
        $member = Member::query()->create([
            'email' => 'dominique@example.test',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);

        // Un an avant l'échéance : rien.
        $this->moveTo('2025-02-01 09:00:00');
        self::assertSame(0, $this->lifecycle()->runFor(Member::class)->total());

        // Deux ans passés : anonymisation, sans passer par la désactivation.
        $this->moveTo('2026-02-01 09:00:00');
        $report = $this->lifecycle()->runFor(Member::class);

        self::assertSame(0, $report->countFor(Member::class, Step::Disable));
        self::assertSame(1, $report->countFor(Member::class, Step::Erase));

        $row = DB::table('members')->find($member->id);
        self::assertSame('anonymous-' . $member->id . '@anonymous.invalid', $row->email);
        self::assertNotNull($row->anonymised_at);

        // Une seconde fois : la ligne n'est plus candidate.
        self::assertSame(0, $this->lifecycle()->runFor(Member::class)->total());
    }

    public function test_a_then_delete_policy_read_from_attributes_removes_the_row(): void
    {
        $ticket = Ticket::query()->create([
            'label' => 'Ancien billet',
            'last_active_at' => '2024-01-01 00:00:00',
        ]);

        $this->moveTo('2024-06-01 09:00:00');
        self::assertSame(0, $this->lifecycle()->runFor(Ticket::class)->total());

        $this->moveTo('2025-06-01 09:00:00');
        $report = $this->lifecycle()->runFor(Ticket::class);

        self::assertSame(1, $report->countFor(Ticket::class, Step::Erase));

        // force: true, donc la ligne part vraiment, corbeille comprise.
        self::assertSame(0, Ticket::query()->withTrashed()->count());
        self::assertNull(DB::table('tickets')->find($ticket->id));
    }
}
