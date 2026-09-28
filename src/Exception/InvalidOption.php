<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Exception;

use InvalidArgumentException;

final class InvalidOption extends InvalidArgumentException implements DataLifecycleException
{
    public static function limit(int $limit): self
    {
        return new self(sprintf('The limit must be at least 1, %d given.', $limit));
    }

    public static function samples(int $samples): self
    {
        return new self(sprintf('The number of samples cannot be negative, %d given.', $samples));
    }
}
