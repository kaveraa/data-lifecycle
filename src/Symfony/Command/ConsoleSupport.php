<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony\Command;

use InvalidArgumentException;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Report;
use Kaveraa\DataLifecycle\Step;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What the two commands share: reading the options and printing the tables.
 */
final class ConsoleSupport
{
    /**
     * @param list<string> $names
     *
     * @return list<Step>
     */
    public static function steps(array $names): array
    {
        $steps = [];

        foreach ($names as $name) {
            $steps[] = Step::tryFrom($name) ?? throw new InvalidArgumentException(sprintf(
                'Unknown step "%s". Use warn, disable or erase.',
                $name,
            ));
        }

        return $steps;
    }

    /**
     * Accepts the full class name or its short name, case does not matter.
     *
     * @param list<string> $names
     *
     * @return list<string>
     */
    public static function subjects(PolicyRegistry $policies, array $names): array
    {
        $found = [];

        foreach ($names as $name) {
            $found[] = self::subject($policies, $name);
        }

        return $found;
    }

    private static function subject(PolicyRegistry $policies, string $name): string
    {
        if ($policies->has($name)) {
            return $name;
        }

        foreach ($policies->all() as $policy) {
            if (strcasecmp($policy->shortName(), $name) === 0) {
                return $policy->subject;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'No lifecycle policy is registered for "%s". Known policies: %s.',
            $name,
            implode(', ', array_map(static fn (Policy $one): string => $one->shortName(), $policies->all())) ?: 'none',
        ));
    }

    /**
     * The requested limit, or the one from the configuration. Null when the
     * given value makes no sense: the message is already shown.
     */
    public static function limit(SymfonyStyle $io, mixed $given, int $default): ?int
    {
        $value = $given === null || $given === '' ? $default : $given;

        if (!is_numeric($value) || (int) $value < 1) {
            $io->error(sprintf('Limite invalide : "%s". Donnez un nombre entier supérieur à zéro.', (string) $value));

            return null;
        }

        return (int) $value;
    }

    /**
     * What was done, or what would be done: one line per entity and per step.
     */
    public static function table(SymfonyStyle $io, Report $report): void
    {
        if ($report->isEmpty()) {
            $io->text('Rien à faire : aucune ligne n\'a atteint son échéance.');

            return;
        }

        $rows = [];

        foreach ($report->lines() as $line) {
            $shown = implode(', ', array_map(static fn (int|string $id): string => (string) $id, $line['ids']));

            if (count($line['ids']) < $line['count']) {
                $shown .= ', ...';
            }

            $rows[] = [self::shortName($line['subject']), self::label($line['step']), $line['count'], $shown];
        }

        $io->table(['Entité', 'Étape', 'Lignes', 'Exemples d\'identifiants'], $rows);
    }

    /**
     * One line per policy: retention duration, reminders, grace and ending.
     *
     * @param list<Policy> $policies
     */
    public static function rules(SymfonyStyle $io, array $policies): void
    {
        if ($policies === []) {
            $io->warning('Aucune règle de conservation n\'est déclarée : rien ne sera jamais anonymisé ni supprimé.');

            return;
        }

        $rows = [];

        foreach ($policies as $policy) {
            $rows[] = [
                $policy->shortName(),
                (string) $policy->keepFor,
                $policy->warnBefore === []
                    ? 'aucun'
                    : implode(', ', array_map(static fn (object $one): string => (string) $one, $policy->warnBefore)),
                $policy->grace === null ? 'pas de désactivation' : (string) $policy->grace,
                self::ending($policy),
            ];
        }

        $io->table(['Entité', 'Conservation', 'Rappels', 'Grâce', 'Fin'], $rows);
    }

    /**
     * The French name of the step, the same as on the Laravel side.
     */
    private static function label(Step $step): string
    {
        return match ($step) {
            Step::Warn => 'rappel',
            Step::Disable => 'désactivation',
            Step::Erase => 'effacement',
        };
    }

    private static function ending(Policy $policy): string
    {
        if ($policy->ending === Ending::Delete) {
            return 'suppression';
        }

        return 'anonymisation (' . implode(', ', array_keys($policy->anonymise)) . ')';
    }

    private static function shortName(string $subject): string
    {
        $parts = explode('\\', $subject);

        return end($parts) ?: $subject;
    }
}
