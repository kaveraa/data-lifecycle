<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use Kaveraa\DataLifecycle\Event\SubjectAnonymised;
use Kaveraa\DataLifecycle\Event\SubjectDeleted;
use Kaveraa\DataLifecycle\Event\SubjectDisabled;
use Kaveraa\DataLifecycle\Event\SubjectWarned;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Plays the three steps of a policy: warn, disable, erase.
 */
final class Runner
{
    public function __construct(
        private readonly Driver $driver,
        private readonly ClockInterface $clock,
        private readonly Anonymiser $anonymiser = new Anonymiser(),
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @param iterable<Policy> $policies
     */
    public function runAll(iterable $policies, RunOptions $options = new RunOptions()): Report
    {
        $report = new Report($options->dryRun, $options->samples);

        foreach ($policies as $policy) {
            $report->merge($this->run($policy, $options));
        }

        return $report;
    }

    public function run(Policy $policy, RunOptions $options = new RunOptions()): Report
    {
        $now = $this->clock->now();
        $report = new Report($options->dryRun, $options->samples);

        if ($options->plays(Step::Warn)) {
            $this->warn($policy, $options, $report, $now);
        }

        if ($options->plays(Step::Disable) && $policy->hasDisableStep()) {
            $this->disable($policy, $options, $report, $now);
        }

        if ($options->plays(Step::Erase)) {
            $this->erase($policy, $options, $report, $now);
        }

        return $report;
    }

    private function warn(Policy $policy, RunOptions $options, Report $report, \DateTimeImmutable $now): void
    {
        foreach ($policy->warnBefore as $index => $ignored) {
            $cutoff = $policy->warnCutoff($index, $now);

            if ($cutoff === null) {
                continue;
            }

            foreach ($this->driver->candidates($policy, new Selection(Step::Warn, $cutoff, $index, $options->limit)) as $subject) {
                $report->add($policy, Step::Warn, $subject);

                if ($options->dryRun) {
                    continue;
                }

                $this->driver->markWarned($policy, $subject, $index, $now);

                $since = $subject->date($policy->fields->since);

                $this->dispatch(new SubjectWarned($policy, $subject, $index, $policy->dueAt($since ?? $now)));
            }
        }
    }

    private function disable(Policy $policy, RunOptions $options, Report $report, \DateTimeImmutable $now): void
    {
        $selection = new Selection(Step::Disable, $policy->disableCutoff($now), limit: $options->limit);

        foreach ($this->driver->candidates($policy, $selection) as $subject) {
            $report->add($policy, Step::Disable, $subject);

            if ($options->dryRun) {
                continue;
            }

            $this->driver->disable($policy, $subject, $now);

            $this->dispatch(new SubjectDisabled($policy, $subject, $now, $policy->grace?->add($now)));
        }
    }

    private function erase(Policy $policy, RunOptions $options, Report $report, \DateTimeImmutable $now): void
    {
        $selection = new Selection(Step::Erase, $policy->eraseCutoff($now), limit: $options->limit);

        foreach ($this->driver->candidates($policy, $selection) as $subject) {
            $report->add($policy, Step::Erase, $subject);

            if ($options->dryRun) {
                continue;
            }

            if ($policy->ending === Ending::Delete) {
                $this->driver->delete($policy, $subject);
                $this->dispatch(new SubjectDeleted($policy, $subject, $now));

                continue;
            }

            $values = $this->anonymiser->values($policy, $subject);

            $this->driver->anonymise($policy, $subject, $values, $now);
            $this->dispatch(new SubjectAnonymised($policy, $subject, $values, $now));
        }
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
