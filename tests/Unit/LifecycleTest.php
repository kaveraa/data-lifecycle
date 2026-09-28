<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Unit;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Anonymiser;
use Kaveraa\DataLifecycle\Duration;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Event\SubjectReactivated;
use Kaveraa\DataLifecycle\Exception\InvalidPolicy;
use Kaveraa\DataLifecycle\FrozenClock;
use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Runner;
use Kaveraa\DataLifecycle\Stage;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Strategy;
use Kaveraa\DataLifecycle\Tests\Support\ArrayDriver;
use Kaveraa\DataLifecycle\Tests\Support\Recorder;
use Kaveraa\DataLifecycle\Tests\Support\Row;
use PHPUnit\Framework\TestCase;

final class LifecycleTest extends TestCase
{
    private ArrayDriver $driver;
    private FrozenClock $clock;
    private Recorder $events;

    protected function setUp(): void
    {
        $this->driver = new ArrayDriver();
        $this->clock = FrozenClock::at('2026-01-02');
        $this->events = new Recorder();
    }

    public function test_it_says_when_a_row_will_be_disabled(): void
    {
        $row = new Row(1, ['last_active_at' => new DateTimeImmutable('2023-01-01')]);
        $this->driver->seed(Row::class, $row);

        self::assertSame('2026-01-01', $this->lifecycle()->dueAt($row)?->format('Y-m-d'));
    }

    public function test_a_row_without_a_date_has_no_deadline(): void
    {
        self::assertNull($this->lifecycle()->dueAt(new Row(1, ['last_active_at' => null])));
    }

    public function test_it_says_where_a_row_stands(): void
    {
        $lifecycle = $this->lifecycle();

        self::assertSame(Stage::Active, $lifecycle->stageOf(new Row(1, ['last_active_at' => new DateTimeImmutable('2025-12-01')])));
        self::assertSame(Stage::Warned, $lifecycle->stageOf(new Row(2, ['lifecycle_warn_stage' => 1])));
        self::assertSame(Stage::Disabled, $lifecycle->stageOf(new Row(3, ['lifecycle_warn_stage' => 2, 'disabled_at' => new DateTimeImmutable('2026-01-01')])));
        self::assertSame(Stage::Erased, $lifecycle->stageOf(new Row(4, ['anonymised_at' => new DateTimeImmutable('2026-01-01')])));
    }

    public function test_coming_back_clears_the_reminders_and_the_disabling(): void
    {
        $row = new Row(1, [
            'last_active_at' => new DateTimeImmutable('2023-01-01'),
            'lifecycle_warn_stage' => 2,
            'disabled_at' => new DateTimeImmutable('2026-01-02'),
        ]);
        $this->driver->seed(Row::class, $row);

        self::assertTrue($this->lifecycle()->reactivate($row));

        self::assertSame(0, $row->get('lifecycle_warn_stage'));
        self::assertNull($row->get('disabled_at'));
        self::assertNull($row->get('lifecycle_warned_at'));
        self::assertSame('2026-01-02', $row->get('last_active_at')?->format('Y-m-d'));
        self::assertCount(1, $this->events->of(SubjectReactivated::class));
    }

    public function test_a_row_that_came_back_is_no_longer_a_candidate(): void
    {
        $row = new Row(1, ['last_active_at' => new DateTimeImmutable('2023-01-01'), 'disabled_at' => new DateTimeImmutable('2026-01-02')]);
        $this->driver->seed(Row::class, $row);

        $lifecycle = $this->lifecycle();
        $lifecycle->reactivate($row);

        $this->clock->moveTo('2026-03-01');

        self::assertTrue($lifecycle->run()->isEmpty());
    }

    public function test_an_anonymised_row_never_comes_back(): void
    {
        $row = new Row(1, ['anonymised_at' => new DateTimeImmutable('2026-01-01')]);
        $this->driver->seed(Row::class, $row);

        self::assertFalse($this->lifecycle()->reactivate($row));
        self::assertSame([], $this->driver->writes);
        self::assertSame([], $this->events->events);
    }

    public function test_observe_runs_every_policy_without_writing(): void
    {
        $this->driver->seed(Row::class, new Row(1, ['last_active_at' => new DateTimeImmutable('2020-01-01'), 'email' => 'lea@example.org']));

        $report = $this->lifecycle()->observe();

        self::assertTrue($report->dryRun);
        self::assertSame(1, $report->countFor(Row::class, Step::Disable));
        self::assertSame([], $this->driver->writes);
    }

    public function test_it_can_run_one_policy_only(): void
    {
        $this->driver->seed(Row::class, new Row(1, ['last_active_at' => new DateTimeImmutable('2020-01-01'), 'email' => 'lea@example.org']));

        self::assertSame(1, $this->lifecycle()->runFor(Row::class)->countFor(Row::class, Step::Disable));
    }

    public function test_an_unknown_class_says_so_clearly(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessage('No lifecycle policy is registered');

        $this->lifecycle()->stageOf(new \stdClass());
    }

    private function lifecycle(): Lifecycle
    {
        $policy = new Policy(
            subject: Row::class,
            keepFor: Duration::parse('3 years'),
            ending: Ending::Anonymise,
            warnBefore: ['30 days'],
            grace: Duration::parse('30 days'),
            anonymise: ['email' => Strategy::Email],
        );

        $policies = new PolicyRegistry([$policy]);

        return new Lifecycle(
            $policies,
            new Runner($this->driver, $this->clock, new Anonymiser(), $this->events),
            $this->driver,
            $this->clock,
            $this->events,
        );
    }
}
