<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Symfony;

/**
 * Le stockage de jeton de Symfony Security, réduit à ce que le paquet en lit :
 * le composant security n'est pas une dépendance.
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
