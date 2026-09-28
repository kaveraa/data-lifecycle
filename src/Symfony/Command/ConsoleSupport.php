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
 * Ce que les deux commandes partagent : lire les options et écrire les tableaux.
 *
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
     * Accepte le nom complet de la classe ou son nom court, sans tenir compte de la casse.
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
     * La limite demandée, ou celle de la configuration. Null quand la valeur
     * donnée n'a pas de sens : le message est déjà affiché.
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
     * Ce qui a été fait, ou ce qui serait fait : une ligne par entité et par étape.
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
     * Une ligne par règle : durée de conservation, rappels, grâce et fin.
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
     * Le nom francais de l'etape, le meme que du cote Laravel.
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
