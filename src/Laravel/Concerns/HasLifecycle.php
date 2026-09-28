<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Laravel\Concerns;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Fields;
use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Stage;

/**
 * À poser sur un modèle soumis à une règle de conservation : trois filtres et
 * trois raccourcis vers le service Lifecycle.
 *
 * To put on a model covered by a retention policy: three query filters and
 * three shortcuts to the Lifecycle service.
 */
trait HasLifecycle
{
    /**
     * Ni désactivée, ni anonymisée.
     *
     * @param Builder<static> $query
     */
    public function scopeActive(Builder $query): void
    {
        $policy = $this->lifecyclePolicy();
        $fields = $this->lifecycleFields();

        if ($policy === null || $policy->hasDisableStep()) {
            $query->whereNull($fields->disabledAt);
        }

        if ($policy === null || $policy->ending === Ending::Anonymise) {
            $query->whereNull($fields->anonymisedAt);
        }
    }

    /**
     * @param Builder<static> $query
     */
    public function scopeDisabled(Builder $query): void
    {
        $query->whereNotNull($this->lifecycleFields()->disabledAt);
    }

    /**
     * @param Builder<static> $query
     */
    public function scopeAnonymised(Builder $query): void
    {
        $query->whereNotNull($this->lifecycleFields()->anonymisedAt);
    }

    public function lifecycleStage(): Stage
    {
        return app(Lifecycle::class)->stageOf($this);
    }

    /**
     * Date de désactivation prévue, ou d'effacement s'il n'y a pas d'étape de
     * désactivation. Null si le dernier signe de vie est inconnu.
     */
    public function lifecycleDueAt(): ?DateTimeImmutable
    {
        return app(Lifecycle::class)->dueAt($this);
    }

    /**
     * La personne est revenue. Faux si la ligne est déjà anonymisée.
     */
    public function reactivate(): bool
    {
        return app(Lifecycle::class)->reactivate($this);
    }

    private function lifecyclePolicy(): ?Policy
    {
        return app(PolicyRegistry::class)->for($this);
    }

    private function lifecycleFields(): Fields
    {
        return $this->lifecyclePolicy()?->fields ?? app(Fields::class);
    }
}
