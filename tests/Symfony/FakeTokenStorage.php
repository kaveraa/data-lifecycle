<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Symfony;

/**
 * The Symfony Security token storage, reduced to what the package reads from it:
 * the security component is not a dependency.
 */
final class FakeTokenStorage
{
    public function __construct(private readonly ?object $user = null)
    {
    }

    public function getToken(): ?object
    {
        if ($this->user === null) {
            return null;
        }

        return new class($this->user) {
            public function __construct(private readonly object $user)
            {
            }

            public function getUser(): object
            {
                return $this->user;
            }
        };
    }
}
