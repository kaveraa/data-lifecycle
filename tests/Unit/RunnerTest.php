<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Unit;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Anonymiser;
use Kaveraa\DataLifecycle\Duration;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Event\SubjectAnonymised;
use Kaveraa\DataLifecycle\Event\SubjectDeleted;
use Kaveraa\DataLifecycle\Event\SubjectDisabled;
use Kaveraa\DataLifecycle\Event\SubjectWarned;
use Kaveraa\DataLifecycle\Exception\InvalidOption;
use Kaveraa\DataLifecycle\FrozenClock;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Report;
use Kaveraa\DataLifecycle\RunOptions;
use Kaveraa\DataLifecycle\Runner;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Strategy;
use Kaveraa\DataLifecycle\Tests\Support\ArrayDriver;
use Kaveraa\DataLifecycle\Tests\Support\Recorder;
use Kaveraa\DataLifecycle\Tests\Support\Row;
use PHPUnit\Framework\TestCase;

final class RunnerTest extends TestCase
{
    private const SUBJECT = 'App\Entity\User';

    private ArrayDriver $driver;
    private FrozenClock $clock;
    private Recorder $events;

    protected function setUp(): void
    {
        $this->driver = new ArrayDriver();
        $this->clock = FrozenClock::at('2025-01-01');
        $this->events = new Recorder();
    }

    public function test_the_whole_journey_from_active_to_anonymised(): void
    {
        $policy = $this->policy();
        $this->driver->seed(self::SUBJECT, new Row(1, ['last_active_at' => new DateTimeImmutable('2023-01-01'), 'email' => 'lea@example.org', 'name' => 'Lea']));

        // Too early: nothing moves.
        $this->clock->moveTo('2025-06-01');
        self::assertTrue($this->play($policy)->isEmpty());

        // 27 days before the deadline: first reminder.
        $this->clock->moveTo('2025-12-05');
        self::assertSame(1, $this->play($policy)->countFor(self::SUBJECT, Step::Warn));
        self::assertSame(1, $this->row()->get('lifecycle_warn_stage'));

        // The same day, we do not warn twice.
        self::assertTrue($this->play($policy)->isEmpty());

        // 4 days before the deadline: second reminder.
        $this->clock->moveTo('2025-12-28');
        self::assertSame(1, $this->play($policy)->countFor(self::SUBJECT, Step::Warn));
        self::assertSame(2, $this->row()->get('lifecycle_warn_stage'));

        // Deadline passed: disable, nothing is erased yet.
        $this->clock->moveTo('2026-01-02');
        self::assertSame(1, $this->play($policy)->countFor(self::SUBJECT, Step::Disable));
        self::assertNotNull($this->row()->get('disabled_at'));
        self::assertNull($this->row()->get('anonymised_at'));
        self::assertSame('lea@example.org', $this->row()->get('email'));

        // During the grace period: still nothing.
        $this->clock->moveTo('2026-01-20');
        self::assertTrue($this->play($policy)->isEmpty());

        // Grace over: anonymisation.
        $this->clock->moveTo('2026-02-05');
        self::assertSame(1, $this->play($policy)->countFor(self::SUBJECT, Step::Erase));
        self::assertSame('anonymous-1@anonymous.invalid', $this->row()->get('email'));
        self::assertSame('[removed]', $this->row()->get('name'));
        self::assertNotNull($this->row()->get('anonymised_at'));

        // An anonymised row leaves the cycle for good.
        $this->clock->moveTo('2027-01-01');
        self::assertTrue($this->play($policy)->isEmpty());

        self::assertCount(2, $this->events->of(SubjectWarned::class));
        self::assertCount(1, $this->events->of(SubjectDisabled::class));
        self::assertCount(1, $this->events->of(SubjectAnonymised::class));
    }

    public function test_the_reminder_says_when_the_deadline_is(): void
    {
        $policy = $this->policy();
        $this->driver->seed(self::SUBJECT, new Row(1, ['last_active_at' => new DateTimeImmutable('2023-01-01')]));

        $this->clock->moveTo('2025-12-05');
        $this->play($policy);

        $warned = $this->events->of(SubjectWarned::class)[0];
        self::assertInstanceOf(SubjectWarned::class, $warned);
        self::assertSame(0, $warned->warnIndex);
        self::assertSame('2026-01-01', $warned->dueAt->format('Y-m-d'));
    }

    public function test_observe_mode_writes_nothing(): void
    {
        $policy = $this->policy();
        $this->driver->seed(
            self::SUBJECT,
            new Row(1, ['last_active_at' => new DateTimeImmutable('2020-01-01'), 'email' => 'lea@example.org', 'name' => 'Lea']),
            new Row(2, ['last_active_at' => new DateTimeImmutable('2024-12-20'), 'email' => 'sam@example.org', 'name' => 'Sam']),
        );

        $this->clock->moveTo('2026-02-05');
        $report = $this->play($policy, RunOptions::observe());

        self::assertTrue($report->dryRun);
        self::assertSame(1, $report->countFor(self::SUBJECT, Step::Disable));
        self::assertSame([1], $report->samplesFor(self::SUBJECT, Step::Disable));

        self::assertSame([], $this->driver->writes);
        self::assertSame('lea@example.org', $this->row()->get('email'));
        self::assertNull($this->row()->get('disabled_at'));
        self::assertNull($this->row()->get('anonymised_at'));
        self::assertNull($this->row()->get('lifecycle_warn_stage'));
        self::assertSame([], $this->events->events);
    }

    public function test_a_policy_without_a_disable_step_erases_straight_away(): void
    {
        $policy = new Policy(
            subject: self::SUBJECT,
            keepFor: Duration::parse('90 days'),
            ending: Ending::Anonymise,
            anonymise: ['email' => Strategy::Email],
        );

        $this->driver->seed(self::SUBJECT, new Row(1, ['last_active_at' => new DateTimeImmutable('2025-01-01'), 'email' => 'lea@example.org']));

        $this->clock->moveTo('2025-03-01');
        self::assertTrue($this->play($policy)->isEmpty());

        $this->clock->moveTo('2025-05-01');
        self::assertSame(1, $this->play($policy)->countFor(self::SUBJECT, Step::Erase));
        self::assertSame('anonymous-1@anonymous.invalid', $this->row()->get('email'));
    }

    public function test_a_policy_can_delete_the_row(): void
    {
        $policy = new Policy(
            subject: self::SUBJECT,
            keepFor: Duration::parse('90 days'),
            ending: Ending::Delete,
        );

        $this->driver->seed(self::SUBJECT, new Row(1, ['last_active_at' => new DateTimeImmutable('2025-01-01')]));

        $this->clock->moveTo('2025-05-01');

        self::assertSame(1, $this->play($policy)->countFor(self::SUBJECT, Step::Erase));
        self::assertSame([], $this->driver->rows(self::SUBJECT));
        self::assertCount(1, $this->events->of(SubjectDeleted::class));
    }

    public function test_a_row_without_a_date_is_left_alone(): void
    {
        $policy = $this->policy();
        $this->driver->seed(self::SUBJECT, new Row(1, ['last_active_at' => null, 'email' => 'lea@example.org']));

        $this->clock->moveTo('2030-01-01');

        self::assertTrue($this->play($policy)->isEmpty());
    }

    public function test_it_never_takes_more_rows_than_asked(): void
    {
        $policy = $this->policy();

        foreach (range(1, 10) as $id) {
            $this->driver->seed(self::SUBJECT, new Row($id, ['last_active_at' => new DateTimeImmutable('2020-01-01'), 'email' => 'a@example.org']));
        }

        $this->clock->moveTo('2026-01-02');

        self::assertSame(3, $this->play($policy, new RunOptions(limit: 3))->countFor(self::SUBJECT, Step::Disable));
    }

    public function test_a_limit_below_one_is_refused(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessage('The limit must be at least 1, 0 given.');

        new RunOptions(limit: 0);
    }

    public function test_a_negative_number_of_samples_is_refused(): void
    {
        $this->expectException(InvalidOption::class);

        new RunOptions(samples: -1);
    }

    public function test_it_can_play_one_step_only(): void
    {
        $policy = $this->policy();
        $this->driver->seed(self::SUBJECT, new Row(1, ['last_active_at' => new DateTimeImmutable('2020-01-01'), 'email' => 'lea@example.org']));

        $this->clock->moveTo('2026-01-02');
        $report = $this->play($policy, new RunOptions(steps: [Step::Warn]));

        self::assertSame(0, $report->countOf(Step::Disable));
        self::assertNull($this->row()->get('disabled_at'));
    }

    public function test_it_runs_every_policy_at_once(): void
    {
        $users = $this->policy();
        $invitations = new Policy(subject: 'App\Entity\Invitation', keepFor: Duration::parse('90 days'), ending: Ending::Delete);

        $this->driver->seed(self::SUBJECT, new Row(1, ['last_active_at' => new DateTimeImmutable('2020-01-01'), 'email' => 'lea@example.org']));
        $this->driver->seed('App\Entity\Invitation', new Row(9, ['last_active_at' => new DateTimeImmutable('2020-01-01')]));

        $this->clock->moveTo('2026-01-02');
        $report = $this->runner()->runAll([$users, $invitations], RunOptions::observe());

        self::assertSame(1, $report->countFor(self::SUBJECT, Step::Warn));
        self::assertSame(1, $report->countFor(self::SUBJECT, Step::Disable));
        self::assertSame(1, $report->countFor('App\Entity\Invitation', Step::Erase));

        // In observe mode, nothing is written: the same row therefore appears
        // in the reminder and in the disable step, as in a real run.
        self::assertSame(3, $report->total());
    }

    private function policy(): Policy
    {
        return new Policy(
            subject: self::SUBJECT,
            keepFor: Duration::parse('3 years'),
            ending: Ending::Anonymise,
            warnBefore: ['30 days', '7 days'],
            grace: Duration::parse('30 days'),
            anonymise: ['email' => Strategy::Email, 'name' => Strategy::Redact],
        );
    }

    private function play(Policy $policy, RunOptions $options = new RunOptions()): Report
    {
        return $this->runner()->run($policy, $options);
    }

    private function runner(): Runner
    {
        return new Runner($this->driver, $this->clock, new Anonymiser(), $this->events);
    }

    private function row(): Row
    {
        $row = $this->driver->row(self::SUBJECT, 1);

        self::assertNotNull($row);

        return $row;
    }
}
