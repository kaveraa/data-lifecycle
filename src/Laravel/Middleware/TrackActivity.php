<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Laravel\Middleware;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Kaveraa\DataLifecycle\Fields;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le signal d'activité : met à jour la date du dernier signe de vie, au plus une
 * fois toutes les N minutes. Une écriture à chaque requête serait inacceptable.
 *
 * The activity signal: updates the last sign of life, at most once every N
 * minutes. Writing on every request would be unacceptable.
 */
final class TrackActivity
{
    public function __construct(
        private readonly PolicyRegistry $policies,
        private readonly Fields $fields,
        private readonly ClockInterface $clock,
        private readonly int $throttle = 15,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->touch($request->user());

        return $next($request);
    }

    /**
     * Personne n'est connecté : on ne fait rien du tout.
     */
    private function touch(mixed $user): void
    {
        // Une fenetre a zero eteint le signal : c'est l'interrupteur du reglage.
        if ($this->throttle <= 0) {
            return;
        }

        if (!$user instanceof Model) {
            return;
        }

        $field = $this->policies->for($user)?->fields->since ?? $this->fields->since;
        $now = $this->clock->now();

        // La valeur est déjà chargée : on décide sans aller voir la base.
        if (!$this->isStale($user->getAttribute($field), $now)) {
            return;
        }

        $user->newModelQuery()->toBase()
            ->where($user->getKeyName(), '=', $user->getKey())
            ->update([$field => $now]);

        // Pas de save() : updated_at ne doit pas bouger.
        $user->forceFill([$field => $now]);
        $user->syncOriginal();
    }

    private function isStale(mixed $last, DateTimeImmutable $now): bool
    {
        $moment = $this->toDate($last);

        if ($moment === null) {
            return true;
        }

        return $moment <= $now->sub(new DateInterval('PT' . $this->throttle . 'M'));
    }

    private function toDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value);
        }

        if (is_int($value)) {
            return (new DateTimeImmutable())->setTimestamp($value);
        }

        return null;
    }
}
