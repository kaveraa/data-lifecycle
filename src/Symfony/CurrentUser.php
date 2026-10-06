<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony;

/**
 * The connected person, read from the Symfony Security token storage.
 *
 * The token is read without depending on the security component: the package
 * also works in an application that does not install it, and then always returns null.
 */
final class CurrentUser
{
    public function __construct(private readonly ?object $tokenStorage = null)
    {
    }

    public function __invoke(): ?object
    {
        if ($this->tokenStorage === null || !method_exists($this->tokenStorage, 'getToken')) {
            return null;
        }

        $token = $this->tokenStorage->getToken();

        if (!is_object($token) || !method_exists($token, 'getUser')) {
            return null;
        }

        $user = $token->getUser();

        return is_object($user) ? $user : null;
    }
}
