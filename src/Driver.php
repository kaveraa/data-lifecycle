<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use DateTimeImmutable;

/**
 * Le pont entre le paquet et votre base : une implémentation par ORM.
 *
 * The bridge between the package and your database: one implementation per ORM.
 */
interface Driver
{
    /**
     * Les lignes à traiter pour cette étape.
     *
     * Warn    : dernier signe de vie <= cutoff, et rappel numéro $warnIndex pas encore envoyé.
     * Disable : dernier signe de vie <= cutoff, pas encore désactivée ni effacée.
     * Erase   : date de désactivation <= cutoff (ou dernier signe de vie s'il n'y a pas
     *           d'étape de désactivation), pas encore effacée.
     *
     * @return iterable<Subject>
     */
    public function candidates(Policy $policy, Selection $selection): iterable;

    public function markWarned(Policy $policy, Subject $subject, int $warnIndex, DateTimeImmutable $at): void;

    public function disable(Policy $policy, Subject $subject, DateTimeImmutable $at): void;

    /**
     * @param array<string, mixed> $values
     */
    public function anonymise(Policy $policy, Subject $subject, array $values, DateTimeImmutable $at): void;

    public function delete(Policy $policy, Subject $subject): void;

    /**
     * Remet la ligne à zéro : plus de rappel envoyé, plus de désactivation.
     */
    public function reactivate(Policy $policy, Subject $subject, DateTimeImmutable $at): void;

    /**
     * Retrouve une ligne par son identifiant, pour les actions à l'unité.
     */
    public function find(Policy $policy, int|string $id): ?Subject;

    /**
     * Enveloppe un objet déjà chargé par l'application.
     */
    public function subject(Policy $policy, object $entity): Subject;
}
