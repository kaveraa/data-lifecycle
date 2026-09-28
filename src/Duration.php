<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateInterval;
use DateTimeImmutable;
use Kaveraa\DataLifecycle\Exception\InvalidDuration;

/**
 * Une durée écrite en toutes lettres : "3 years", "30 days", "6 months".
 *
 * A duration written in plain words: "3 years", "30 days", "6 months".
 */
final class Duration implements \Stringable
{
    /** @var array<string, string> unité -> lettre du format ISO 8601 */
    private const UNITS = [
        'second' => 'TS',
        'minute' => 'TM',
        'hour' => 'TH',
        'day' => 'D',
        'week' => 'W',
        'month' => 'M',
        'year' => 'Y',
    ];

    /** @var array<string, int> nombre de secondes, approximatif, pour comparer deux durées */
    private const SECONDS = [
        'second' => 1,
        'minute' => 60,
        'hour' => 3600,
        'day' => 86400,
        'week' => 604800,
        'month' => 2629746,
        'year' => 31556952,
    ];

    private function __construct(
        public readonly int $amount,
        public readonly string $unit,
    ) {
    }

    /**
     * Accepte "3 years", "1 day", "P30D", ou une Duration déjà construite.
     */
    public static function parse(self|string $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $text = trim($value);

        if ($text === '') {
            throw InvalidDuration::empty();
        }

        if (preg_match('/^(\d+)\s*(second|minute|hour|day|week|month|year)s?$/i', $text, $found) === 1) {
            return self::of((int) $found[1], strtolower($found[2]));
        }

        if (preg_match('/^P(?:(\d+)Y|(\d+)M|(\d+)W|(\d+)D|T(?:(\d+)H|(\d+)M|(\d+)S))$/', strtoupper($text), $found) === 1) {
            foreach (['year', 'month', 'week', 'day', 'hour', 'minute', 'second'] as $index => $unit) {
                if (($found[$index + 1] ?? '') !== '') {
                    return self::of((int) $found[$index + 1], $unit);
                }
            }
        }

        throw InvalidDuration::cannotRead($text);
    }

    public static function of(int $amount, string $unit): self
    {
        $unit = strtolower(rtrim($unit, 's'));

        if (!isset(self::UNITS[$unit])) {
            throw InvalidDuration::unknownUnit($unit);
        }

        if ($amount < 0) {
            throw InvalidDuration::negative($amount);
        }

        return new self($amount, $unit);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function add(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->add($this->interval());
    }

    public function sub(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->sub($this->interval());
    }

    /**
     * Nombre de secondes approximatif : sert seulement à ranger des durées entre elles.
     */
    public function approximateSeconds(): int
    {
        return $this->amount * self::SECONDS[$this->unit];
    }

    public function interval(): DateInterval
    {
        $letter = self::UNITS[$this->unit];

        return new DateInterval('P' . ($letter[0] === 'T' ? 'T' . $this->amount . $letter[1] : $this->amount . $letter));
    }

    public function __toString(): string
    {
        return $this->amount . ' ' . $this->unit . ($this->amount === 1 ? '' : 's');
    }
}
