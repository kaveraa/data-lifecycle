<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony;

/**
 * La personne connectée, lue dans le stockage de jeton de Symfony Security.
 *
 * The connected person, read from the Symfony Security token storage.
 *
 * Le jeton est lu sans dépendre du composant security : le paquet marche aussi
 * dans une application qui ne l'installe pas, et renvoie alors toujours null.
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
