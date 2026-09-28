<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine;

use Kaveraa\DataLifecycle\Stage;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Member;

/**
 * Le parcours d'une personne inactive, mois après mois.
 */
final class LifecycleJourneyTest extends DoctrineTestCase
{
    public function test_the_whole_journey_from_active_to_anonymised(): void
    {
        $member = new Member('alice@example.test', 'Alice', self::at('2020-01-01 09:00:00'));
        $this->save($member);

        $id = (int) $member->id;
        $lifecycle = $this->lifecycleOf(Member::class);

        // Trois ans de conservation : à six mois de l'échéance, rien ne bouge.
        $this->clock->moveTo('2022-06-01 12:00:00');

        self::assertTrue($lifecycle->run()->isEmpty());
        self::assertSame(Stage::Active, $lifecycle->stageOf($this->reload(Member::class, $id)));
        self::assertSame([], $this->events->names());

        // Premier rappel : trente jours avant l'échéance du 1er janvier 2023.
        $this->clock->moveTo('2022-12-05 12:00:00');

        self::assertSame(1, $lifecycle->run()->countFor(Member::class, Step::Warn));

        $fresh = $this->reload(Member::class, $id);

        self::assertSame(1, $fresh->lifecycleWarnStage);
        self::assertSame('2022-12-05 12:00', $fresh->lifecycleWarnedAt?->format('Y-m-d H:i'));
        self::assertNull($fresh->disabledAt);
        self::assertSame('alice@example.test', $fresh->email);
        self::assertSame(Stage::Warned, $lifecycle->stageOf($fresh));
        self::assertSame(['SubjectWarned'], $this->events->names());

        // Le lendemain, le premier rappel ne repart pas.
        $this->clock->moveTo('2022-12-06 12:00:00');

        self::assertTrue($lifecycle->run()->isEmpty());

        // Deuxième rappel : sept jours avant l'échéance.
        $this->clock->moveTo('2022-12-28 12:00:00');

        self::assertSame(1, $lifecycle->run()->countFor(Member::class, Step::Warn));
        self::assertSame(2, $this->reload(Member::class, $id)->lifecycleWarnStage);

        // Échéance passée : désactivation, et rien d'autre le même jour.
        $this->clock->moveTo('2023-01-02 12:00:00');

        $report = $lifecycle->run();

        self::assertSame(1, $report->countFor(Member::class, Step::Disable));
        self::assertSame(0, $report->countFor(Member::class, Step::Erase));

        $fresh = $this->reload(Member::class, $id);

        self::assertSame('2023-01-02 12:00', $fresh->disabledAt?->format('Y-m-d H:i'));
        self::assertNull($fresh->anonymisedAt);
        self::assertSame('alice@example.test', $fresh->email);
        self::assertSame(Stage::Disabled, $lifecycle->stageOf($fresh));

        // Pendant la grâce de trente jours, la ligne reste intacte.
        $this->clock->moveTo('2023-01-20 12:00:00');

        self::assertTrue($lifecycle->run()->isEmpty());
        self::assertNull($this->reload(Member::class, $id)->anonymisedAt);

        // Grâce écoulée : anonymisation.
        $this->clock->moveTo('2023-02-05 12:00:00');

        self::assertSame(1, $lifecycle->run()->countFor(Member::class, Step::Erase));

        $fresh = $this->reload(Member::class, $id);

        self::assertSame(sprintf('anonymous-%d@anonymous.invalid', $id), $fresh->email);
        self::assertSame('Anonymous', $fresh->name);
        self::assertSame('2023-02-05 12:00', $fresh->anonymisedAt?->format('Y-m-d H:i'));
        self::assertSame(Stage::Erased, $lifecycle->stageOf($fresh));

        self::assertSame(
            ['SubjectWarned', 'SubjectWarned', 'SubjectDisabled', 'SubjectAnonymised'],
            $this->events->names(),
        );

        // Une ligne anonymisée ne repasse jamais dans le cycle.
        $this->clock->moveTo('2024-01-01 12:00:00');

        self::assertTrue($lifecycle->run()->isEmpty());
    }

    public function test_a_row_that_is_still_active_is_never_touched(): void
    {
        $member = new Member('bob@example.test', 'Bob', self::at('2022-11-01 09:00:00'));
        $this->save($member);

        $this->clock->moveTo('2023-01-01 12:00:00');

        self::assertTrue($this->lifecycleOf(Member::class)->run()->isEmpty());

        $fresh = $this->reload(Member::class, (int) $member->id);

        self::assertNull($fresh->lifecycleWarnStage);
        self::assertNull($fresh->lifecycleWarnedAt);
        self::assertNull($fresh->disabledAt);
        self::assertNull($fresh->anonymisedAt);
    }
}
