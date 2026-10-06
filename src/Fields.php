<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * The columns the package reads and writes on your tables.
 */
final class Fields
{
    public function __construct(
        /** Date of the last sign of life. The package reads it; it writes it only when the person comes back (reactivate). */
        public readonly string $since = 'last_active_at',
        /** Number of reminders already sent. Useful only with #[WarnBefore]. */
        public readonly string $warnStage = 'lifecycle_warn_stage',
        /** Date of the last reminder sent. For information only. */
        public readonly string $warnedAt = 'lifecycle_warned_at',
        /** Disable date. Useful only with #[DisableFirst]. */
        public readonly string $disabledAt = 'disabled_at',
        /** Anonymisation date. Useful only with #[ThenAnonymise]. */
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
