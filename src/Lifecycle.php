<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Event\SubjectReactivated;
use Kaveraa\DataLifecycle\Exception\InvalidPolicy;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Le point d'entrée de l'application : lancer le cycle, savoir où en est une
 * ligne, et faire revenir quelqu'un qui s'était absenté.
 *
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
     * Ne rien écrire, seulement dire ce qui se passerait.
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
     * Où en est cette ligne dans son cycle de vie.
     */
    public function stageOf(object $entity): Stage
    {
        $policy = $this->policyOf($entity);
        $subject = $this->driver->subject($policy, $entity);

        // On ne lit que les colonnes que la regle utilise : une entite qui n'est
        // jamais anonymisee n'a pas de colonne d'anonymisation.
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
     * Date à laquelle cette ligne sera désactivée, ou effacée s'il n'y a pas
     * d'étape de désactivation. Null si le dernier signe de vie est inconnu.
     */
    public function dueAt(object $entity): ?DateTimeImmutable
    {
        $policy = $this->policyOf($entity);
        $since = $this->driver->subject($policy, $entity)->date($policy->fields->since);

        return $since === null ? null : $policy->dueAt($since);
    }

    /**
     * La personne est revenue : on efface les rappels et la désactivation.
     * Une ligne déjà anonymisée ne revient pas.
     */
    public function reactivate(object $entity): bool
    {
        $policy = $this->policyOf($entity);
        $subject = $this->driver->subject($policy, $entity);

        // Une ligne deja anonymisee ne revient pas. On ne lit la colonne que si
        // la regle anonymise : sinon l'entite n'a pas cette propriete.
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
