<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Laravel\Console;

use Illuminate\Console\Command;
use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\Report;
use Kaveraa\DataLifecycle\RunOptions;
use Kaveraa\DataLifecycle\Step;

/**
 * Joue le cycle de vie : prévenir, désactiver, effacer.
 *
 * Plays the lifecycle: warn, disable, erase.
 */
final class RunCommand extends Command
{
    protected $signature = 'lifecycle:run
        {--dry-run : Mode observation : rien n\'est écrit}
        {--subject=* : Ne traiter que ces classes}
        {--step=* : Ne jouer que ces étapes : warn, disable ou erase}
        {--limit= : Nombre maximum de lignes par étape et par règle}';

    protected $description = 'Applique les règles de conservation : rappel, désactivation, anonymisation ou suppression';

    public function handle(Lifecycle $lifecycle): int
    {
        $forced = (bool) $this->laravel->make('config')->get('data-lifecycle.dry_run', false);
        $dryRun = $forced || (bool) $this->option('dry-run');

        $steps = [];

        foreach ((array) $this->option('step') as $name) {
            $step = Step::tryFrom((string) $name);

            if ($step === null) {
                $this->error(sprintf('Étape inconnue : "%s". Choisissez warn, disable ou erase.', (string) $name));

                return self::FAILURE;
            }

            $steps[] = $step;
        }

        $limit = $this->limit();

        if ($limit === null) {
            return self::FAILURE;
        }

        $options = new RunOptions(
            dryRun: $dryRun,
            limit: $limit,
            steps: $steps,
        );

        $subjects = array_values(array_filter(array_map(strval(...), (array) $this->option('subject'))));

        if ($subjects === []) {
            $report = $lifecycle->run($options);
        } else {
            $report = new Report($options->dryRun, $options->samples);

            foreach ($subjects as $subject) {
                $report->merge($lifecycle->runFor($subject, $options));
            }
        }

        $this->show($report, $dryRun, $forced);

        return self::SUCCESS;
    }

    /**
     * Null quand la valeur donnée n'a pas de sens : le message est déjà affiché.
     */
    private function limit(): ?int
    {
        $given = $this->option('limit');

        $value = $given === null || $given === ''
            ? $this->laravel->make('config')->get('data-lifecycle.limit', 1000)
            : $given;

        if (!is_numeric($value) || (int) $value < 1) {
            $this->error(sprintf('Limite invalide : "%s". Donnez un nombre entier supérieur à zéro.', (string) $value));

            return null;
        }

        return (int) $value;
    }

    private function show(Report $report, bool $dryRun, bool $forced): void
    {
        if ($report->isEmpty()) {
            $this->info('Rien à faire : aucune ligne n\'a atteint son échéance.');
        } else {
            $rows = [];

            foreach ($report->lines() as $line) {
                $rows[] = [
                    class_basename($line['subject']),
                    $this->label($line['step']),
                    (string) $line['count'],
                    $line['ids'] === [] ? '-' : implode(', ', array_map(strval(...), $line['ids'])),
                ];
            }

            $this->table(['Entité', 'Étape', 'Lignes', 'Exemples d\'identifiants'], $rows);
            $this->line(sprintf('Total : %d ligne(s).', $report->total()));
        }

        if (!$dryRun) {
            return;
        }

        $this->newLine();
        $this->warn('Essai à blanc : la base n\'a pas été modifiée.');

        if ($forced) {
            $this->line('Le mode observation est imposé par la configuration (data-lifecycle.dry_run).');
        }
    }

    private function label(Step $step): string
    {
        return match ($step) {
            Step::Warn => 'rappel',
            Step::Disable => 'désactivation',
            Step::Erase => 'effacement',
        };
    }
}
