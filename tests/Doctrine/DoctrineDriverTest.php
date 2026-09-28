<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Doctrine\UnknownProperty;
use Kaveraa\DataLifecycle\Fields;
use Kaveraa\DataLifecycle\Selection;
use Kaveraa\DataLifecycle\Stage;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Contact;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Member;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Session;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Subscriber;

final class DoctrineDriverTest extends DoctrineTestCase
{
    public function test_coming_back_during_the_grace_period_resets_everything(): void
    {
        $member = new Member('carla@example.test', 'Carla', self::at('2019-01-01 09:00:00'));
        $this->save($member);

        $id = (int) $member->id;
        $lifecycle = $this->lifecycleOf(Member::class);

        $this->clock->moveTo('2023-01-02 12:00:00');
        $lifecycle->run();

        self::assertNotNull($this->reload(Member::class, $id)->disabledAt);

        $this->events->forget();

        $came = $this->reload(Member::class, $id);

        self::assertTrue($lifecycle->reactivate($came));

        $fresh = $this->reload(Member::class, $id);

        self::assertSame(0, $fresh->lifecycleWarnStage);
        self::assertNull($fresh->lifecycleWarnedAt);
        self::assertNull($fresh->disabledAt);
        self::assertNull($fresh->anonymisedAt);
        self::assertSame('carla@example.test', $fresh->email);
        self::assertSame(Stage::Active, $lifecycle->stageOf($fresh));
        self::assertSame(['SubjectReactivated'], $this->events->names());

        // Le retour est un signe de vie : le compteur repart de la date du retour.
        self::assertSame('2023-01-02 12:00', $fresh->lastActiveAt?->format('Y-m-d H:i'));
        self::assertSame('2026-01-02 12:00', $lifecycle->dueAt($fresh)?->format('Y-m-d H:i'));

        // Sans quoi la ligne serait redésactivée dès l'exécution suivante.
        $this->clock->moveTo('2023-02-05 12:00:00');

        self::assertTrue($lifecycle->run()->isEmpty());

        $fresh = $this->reload(Member::class, $id);

        self::assertNull($fresh->disabledAt);
        self::assertNull($fresh->anonymisedAt);
        self::assertSame(Stage::Active, $lifecycle->stageOf($fresh));
    }

    public function test_an_anonymised_row_never_comes_back(): void
    {
        $member = new Member('erik@example.test', 'Erik', self::at('2015-01-01 09:00:00'));
        $member->anonymisedAt = self::at('2022-01-01 09:00:00');
        $this->save($member);

        self::assertFalse($this->lifecycleOf(Member::class)->reactivate($member));
    }

    public function test_observe_mode_changes_nothing_in_the_database(): void
    {
        $member = new Member('dora@example.test', 'Dora', self::at('2019-01-01 09:00:00'));
        $this->save($member);

        $id = (int) $member->id;

        $this->clock->moveTo('2023-06-01 12:00:00');

        $report = $this->lifecycleOf(Member::class)->observe();

        self::assertTrue($report->dryRun);
        self::assertSame(1, $report->countFor(Member::class, Step::Warn));
        self::assertSame(1, $report->countFor(Member::class, Step::Disable));

        $fresh = $this->reload(Member::class, $id);

        self::assertSame('dora@example.test', $fresh->email);
        self::assertSame('Dora', $fresh->name);
        self::assertSame('2019-01-01 09:00', $fresh->lastActiveAt?->format('Y-m-d H:i'));
        self::assertNull($fresh->lifecycleWarnStage);
        self::assertNull($fresh->lifecycleWarnedAt);
        self::assertNull($fresh->disabledAt);
        self::assertNull($fresh->anonymisedAt);
        self::assertSame([], $this->events->names());
    }

    public function test_without_a_disable_step_the_row_is_anonymised_straight_away(): void
    {
        $old = new Subscriber('old@example.test', self::at('2021-06-01 09:00:00'));
        $recent = new Subscriber('recent@example.test', self::at('2022-09-01 09:00:00'));

        $this->save($old, $recent);

        $oldId = (int) $old->id;
        $recentId = (int) $recent->id;

        $this->clock->moveTo('2023-01-01 12:00:00');

        $report = $this->lifecycleOf(Subscriber::class)->run();

        self::assertSame(0, $report->countFor(Subscriber::class, Step::Disable));
        self::assertSame(1, $report->countFor(Subscriber::class, Step::Erase));

        $erased = $this->reload(Subscriber::class, $oldId);

        self::assertSame(sprintf('anonymous-%d@anonymous.invalid', $oldId), $erased->email);
        self::assertSame('2023-01-01 12:00', $erased->anonymisedAt?->format('Y-m-d H:i'));

        $kept = $this->reload(Subscriber::class, $recentId);

        self::assertSame('recent@example.test', $kept->email);
        self::assertNull($kept->anonymisedAt);
    }

    public function test_an_entity_that_only_has_its_last_sign_of_life(): void
    {
        $old = new Session(self::at('2022-11-01 09:00:00'));
        $recent = new Session(self::at('2022-12-28 09:00:00'));

        $this->save($old, $recent);

        $oldId = (int) $old->id;
        $recentId = (int) $recent->id;

        $this->clock->moveTo('2023-01-01 12:00:00');

        $lifecycle = $this->lifecycleOf(Session::class);
        $report = $lifecycle->run();

        self::assertSame(1, $report->countFor(Session::class, Step::Erase));
        self::assertSame(['SubjectDeleted'], $this->events->names());

        self::assertNull($this->reload(Session::class, $oldId));

        $kept = $this->reload(Session::class, $recentId);

        self::assertNotNull($kept);
        // Aucune propriété de suivi sur cette entité : le paquet n'impose rien.
        self::assertSame(Stage::Active, $lifecycle->stageOf($kept));
        self::assertSame('2023-01-27 09:00', $lifecycle->dueAt($kept)?->format('Y-m-d H:i'));
    }

    public function test_custom_field_names_and_writing_through_the_setter(): void
    {
        $contact = new Contact(self::at('2020-01-01 09:00:00'));
        $this->save($contact);

        $id = (int) $contact->id;
        $lifecycle = $this->lifecycleOf(Contact::class);

        $this->clock->moveTo('2022-01-05 12:00:00');

        self::assertSame(1, $lifecycle->run()->countFor(Contact::class, Step::Disable));
        self::assertSame(1, $contact->setterCalls);
        self::assertSame('2022-01-05 12:00', $contact->blockedAt()?->format('Y-m-d H:i'));

        // Grâce de dix jours, puis suppression.
        $this->clock->moveTo('2022-01-10 12:00:00');

        self::assertTrue($lifecycle->run()->isEmpty());

        $this->clock->moveTo('2022-01-20 12:00:00');

        self::assertSame(1, $lifecycle->run()->countFor(Contact::class, Step::Erase));
        self::assertNull($this->reload(Contact::class, $id));
    }

    public function test_a_clear_message_when_the_property_is_missing(): void
    {
        $policy = $this->policyOf(Session::class, new Fields(since: 'nope_at'));

        try {
            $this->driver->candidates($policy, new Selection(Step::Erase, new DateTimeImmutable()));
            self::fail('A missing property should be reported.');
        } catch (UnknownProperty $failure) {
            self::assertStringContainsString(Session::class, $failure->getMessage());
            self::assertStringContainsString('"since"', $failure->getMessage());
            self::assertStringContainsString('nope_at', $failure->getMessage());
            self::assertStringContainsString('nopeAt', $failure->getMessage());
        }
    }
}
