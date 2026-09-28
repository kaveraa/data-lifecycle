<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * Les colonnes lues et écrites par le paquet sur vos tables.
 *
 * The columns the package reads and writes on your tables.
 */
final class Fields
{
    public function __construct(
        /** Date du dernier signe de vie. Le paquet la lit ; il ne l'ecrit qu'au retour de la personne (reactivate). */
        public readonly string $since = 'last_active_at',
        /** Nombre de rappels déjà envoyés. Utile seulement avec #[WarnBefore]. */
        public readonly string $warnStage = 'lifecycle_warn_stage',
        /** Date du dernier rappel envoyé. Informative. */
        public readonly string $warnedAt = 'lifecycle_warned_at',
        /** Date de désactivation. Utile seulement avec #[DisableFirst]. */
        public readonly string $disabledAt = 'disabled_at',
        /** Date d'anonymisation. Utile seulement avec #[ThenAnonymise]. */
        public readonly string $anonymisedAt = 'anonymised_at',
    ) {
    }

    /**
     * @param array<string, string> $names
     */
    public static function fromArray(array $names, ?self $defaults = null): self
    {
        $defaults ??= new self();

        return new self(
            since: $names['since'] ?? $defaults->since,
            warnStage: $names['warn_stage'] ?? $defaults->warnStage,
            warnedAt: $names['warned_at'] ?? $defaults->warnedAt,
            disabledAt: $names['disabled_at'] ?? $defaults->disabledAt,
            anonymisedAt: $names['anonymised_at'] ?? $defaults->anonymisedAt,
        );
    }

    public function withSince(string $since): self
    {
        return new self($since, $this->warnStage, $this->warnedAt, $this->disabledAt, $this->anonymisedAt);
    }

    public function withDisabledAt(string $disabledAt): self
    {
        return new self($this->since, $this->warnStage, $this->warnedAt, $disabledAt, $this->anonymisedAt);
    }
}
