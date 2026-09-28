<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Exception\InvalidPolicy;

/**
 * La règle de conservation d'une entité : combien de temps on garde,
 * quand on prévient, quand on désactive, et ce qu'on fait à la fin.
 *
 * The retention rule of one entity: how long it is kept, when the person is
 * warned, when the row is disabled, and what happens at the end.
 */
final class Policy
{
    /** @var list<Duration> du plus grand au plus petit / from the largest to the smallest */
    public readonly array $warnBefore;

    /**
     * @param list<Duration|string>      $warnBefore
     * @param array<string, Strategy>    $anonymise
     */
    public function __construct(
        public readonly string $subject,
        public readonly Duration $keepFor,
        public readonly Ending $ending,
        array $warnBefore = [],
        public readonly ?Duration $grace = null,
        public readonly array $anonymise = [],
        public readonly bool $forceDelete = false,
        public readonly Fields $fields = new Fields(),
    ) {
        $durations = array_map(static fn (Duration|string $one): Duration => Duration::parse($one), $warnBefore);
        usort($durations, static fn (Duration $a, Duration $b): int => $b->approximateSeconds() <=> $a->approximateSeconds());

        $this->warnBefore = array_values($durations);

        if ($ending === Ending::Anonymise && $this->anonymise === []) {
            throw InvalidPolicy::noEnd($subject);
        }
    }

    /**
     * Construit une règle depuis un tableau de configuration.
     *
     * @param array<string, mixed> $options
     */
    public static function fromArray(string $subject, array $options, ?Fields $defaults = null): self
    {
        if (!isset($options['keep_for'])) {
            throw InvalidPolicy::missingKeepFor($subject);
        }

        $anonymise = [];

        foreach ((array) ($options['anonymise'] ?? []) as $field => $strategy) {
            if (is_int($field)) {
                $anonymise[(string) $strategy] = Strategy::Auto;

                continue;
            }

            $anonymise[$field] = $strategy instanceof Strategy ? $strategy : Strategy::from((string) $strategy);
        }

        if ($anonymise !== [] && ($options['delete'] ?? false) === true) {
            throw InvalidPolicy::twoEndings($subject);
        }

        if ($anonymise === [] && ($options['delete'] ?? false) !== true) {
            throw InvalidPolicy::noEnd($subject);
        }

        $warn = $options['warn_before'] ?? [];

        return new self(
            subject: $subject,
            keepFor: Duration::parse((string) $options['keep_for']),
            ending: $anonymise === [] ? Ending::Delete : Ending::Anonymise,
            warnBefore: array_map(static fn (mixed $one): string => (string) $one, is_array($warn) ? array_values($warn) : [$warn]),
            grace: isset($options['grace']) ? Duration::parse((string) $options['grace']) : null,
            anonymise: $anonymise,
            forceDelete: (bool) ($options['force_delete'] ?? false),
            fields: Fields::fromArray((array) ($options['fields'] ?? []), $defaults),
        );
    }

    public function hasDisableStep(): bool
    {
        return $this->grace instanceof Duration;
    }

    /**
     * Date à laquelle la ligne devra être désactivée (ou effacée s'il n'y a pas
     * d'étape de désactivation), pour un dernier signe de vie donné.
     */
    public function dueAt(DateTimeImmutable $since): DateTimeImmutable
    {
        return $this->keepFor->add($since);
    }

    /**
     * Toutes les lignes dont le dernier signe de vie est antérieur à cette date
     * doivent recevoir le rappel numéro $index.
     */
    public function warnCutoff(int $index, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $warn = $this->warnBefore[$index] ?? null;

        return $warn?->add($this->keepFor->sub($now));
    }

    public function disableCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $this->keepFor->sub($now);
    }

    /**
     * Avec une étape de désactivation, on compare la date de désactivation au
     * délai de grâce. Sans, on compare le dernier signe de vie à la durée de
     * conservation.
     */
    public function eraseCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $this->hasDisableStep() ? $this->grace->sub($now) : $this->keepFor->sub($now);
    }

    public function shortName(): string
    {
        $parts = explode('\\', $this->subject);

        return end($parts) ?: $this->subject;
    }
}
