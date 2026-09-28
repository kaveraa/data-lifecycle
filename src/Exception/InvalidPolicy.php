<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Exception;

use InvalidArgumentException;

final class InvalidPolicy extends InvalidArgumentException implements DataLifecycleException
{
    public static function noEnd(string $subject): self
    {
        return new self(sprintf(
            'The policy of "%s" says how long to keep the data but not what to do next. Add #[ThenAnonymise(...)] or #[ThenDelete].',
            $subject,
        ));
    }

    public static function twoEndings(string $subject): self
    {
        return new self(sprintf('The policy of "%s" cannot both anonymise and delete. Choose one.', $subject));
    }

    public static function missingKeepFor(string $subject): self
    {
        return new self(sprintf('The policy of "%s" needs #[KeepFor(...)] to say how long the data is kept.', $subject));
    }

    public static function unknownSubject(string $subject): self
    {
        return new self(sprintf('No lifecycle policy is registered for "%s".', $subject));
    }
}
