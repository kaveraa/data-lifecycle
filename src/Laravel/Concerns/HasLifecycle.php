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
 * To put on a model covered by a retention policy: three query filters and
 * three shortcuts to the Lifecycle service.
 */
trait HasLifecycle
{
    /**
     * Neither disabled nor anonymised.
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
     * Planned disable date, or erase date if there is no disable step.
     * Null if the last sign of life is unknown.
     */
    public function lifecycleDueAt(): ?DateTimeImmutable
    {
        return app(Lifecycle::class)->dueAt($this);
    }

    /**
     * The person came back. False if the row is already anonymised.
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
