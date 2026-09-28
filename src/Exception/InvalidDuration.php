<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Exception;

use InvalidArgumentException;

final class InvalidDuration extends InvalidArgumentException implements DataLifecycleException
{
    public static function empty(): self
    {
        return new self('A duration cannot be empty. Write it like "3 years" or "30 days".');
    }

    public static function cannotRead(string $value): self
    {
        return new self(sprintf('Cannot read the duration "%s". Write it like "3 years", "30 days" or "P30D".', $value));
    }

    public static function unknownUnit(string $unit): self
    {
        return new self(sprintf('Unknown duration unit "%s". Use second, minute, hour, day, week, month or year.', $unit));
    }

    public static function negative(int $amount): self
    {
        return new self(sprintf('A duration cannot be negative, %d given.', $amount));
    }
}
