<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateTimeImmutable;

/**
 * Calcule la valeur de remplacement de chaque champ à anonymiser.
 *
 * Works out the replacement value of every field to anonymise.
 */
final class Anonymiser
{
    public function __construct(
        private readonly string $emailDomain = 'anonymous.invalid',
        private readonly string $redactedText = '[removed]',
        private readonly string $anonymousName = 'Anonymous',
        private readonly string $pepper = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function values(Policy $policy, Subject $subject): array
    {
        $values = [];

        foreach ($policy->anonymise as $field => $strategy) {
            $values[$field] = $this->value($strategy, $field, $subject);
        }

        return $values;
    }

    public function value(Strategy $strategy, string $field, Subject $subject): mixed
    {
        if ($strategy === Strategy::Auto) {
            $strategy = str_contains(strtolower($field), 'mail') ? Strategy::Email : Strategy::Redact;
        }

        return match ($strategy) {
            Strategy::Nullify => null,
            Strategy::Redact => $this->redactedText,
            Strategy::EmptyText => '',
            Strategy::Text => $this->anonymousName,
            Strategy::Email => sprintf('anonymous-%s@%s', $this->slug((string) $subject->id), $this->emailDomain),
            Strategy::Hash => $this->hash($subject->get($field)),
            Strategy::Zero => 0,
            Strategy::YearOnly => $this->firstOfYear($subject->date($field)),
            Strategy::Auto => $this->redactedText,
        };
    }

    private function hash(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            $value = $value->format(DATE_ATOM);
        }

        if (!is_scalar($value)) {
            $value = json_encode($value);
        }

        return substr(hash('sha256', $this->pepper . (string) $value), 0, 32);
    }

    private function firstOfYear(?DateTimeImmutable $date): ?DateTimeImmutable
    {
        return $date?->setDate((int) $date->format('Y'), 1, 1)->setTime(0, 0);
    }

    private function slug(string $id): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]+/', '', $id) ?? '';

        return $clean === '' ? substr(hash('sha256', $id), 0, 12) : $clean;
    }
}
