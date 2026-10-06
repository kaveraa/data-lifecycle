<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Event\SubjectReactivated;
use Kaveraa\DataLifecycle\Exception\InvalidPolicy;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The entry point for the application: run the cycle, know where a row stands,
 * and bring back someone who had been away.
 */
final class Lifecycle
{
    public function __construct(
        private readonly PolicyRegistry $policies,
        private readonly Runner $runner,
        private readonly Driver $driver,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    public function run(RunOptions $options = new RunOptions()): Report
    {
        return $this->runner->runAll($this->policies->all(), $options);
    }

    /**
     * Write nothing, only say what would happen.
     */
    public function observe(int $limit = 1000): Report
    {
        return $this->run(RunOptions::observe($limit));
    }

    public function runFor(string $subject, RunOptions $options = new RunOptions()): Report
    {
        return $this->runner->run($this->policies->get($subject), $options);
    }

    public function policies(): PolicyRegistry
    {
        return $this->policies;
    }

    /**
     * Where this row stands in its lifecycle.
     */
    public function stageOf(object $entity): Stage
    {
        $policy = $this->policyOf($entity);
        $subject = $this->driver->subject($policy, $entity);

        // We read only the columns the policy uses: an entity that is never
        // anonymised has no anonymisation column.
        if ($policy->ending === Ending::Anonymise && $subject->date($policy->fields->anonymisedAt) !== null) {
            return Stage::Erased;
        }

        if ($policy->hasDisableStep() && $subject->date($policy->fields->disabledAt) !== null) {
            return Stage::Disabled;
        }

        if ($policy->warnBefore !== [] && ((int) $subject->get($policy->fields->warnStage)) > 0) {
            return Stage::Warned;
        }

        return Stage::Active;
    }

    /**
     * Date when this row will be disabled, or erased if there is no disable
     * step. Null if the last sign of life is unknown.
     */
    public function dueAt(object $entity): ?DateTimeImmutable
    {
        $policy = $this->policyOf($entity);
        $since = $this->driver->subject($policy, $entity)->date($policy->fields->since);

        return $since === null ? null : $policy->dueAt($since);
    }

    /**
     * The person came back: we clear the reminders and the disable date.
     * A row already anonymised does not come back.
     */
    public function reactivate(object $entity): bool
    {
        $policy = $this->policyOf($entity);
        $subject = $this->driver->subject($policy, $entity);

        // A row already anonymised does not come back. We read the column only if
        // the policy anonymises: otherwise the entity has no such property.
        if ($policy->ending === Ending::Anonymise && $subject->date($policy->fields->anonymisedAt) !== null) {
            return false;
        }

        $now = $this->clock->now();

        $this->driver->reactivate($policy, $subject, $now);
        $this->events?->dispatch(new SubjectReactivated($policy, $subject, $now));

        return true;
    }

    private function policyOf(object $entity): Policy
    {
        return $this->policies->for($entity) ?? throw InvalidPolicy::unknownSubject($entity::class);
    }
}
