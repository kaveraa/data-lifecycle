<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Laravel\Console;

use Illuminate\Console\Command;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\RunOptions;
use Kaveraa\DataLifecycle\Step;

/**
 * Says what would happen, without writing anything.
 */
final class ReportCommand extends Command
{
    protected $signature = 'lifecycle:report
        {--limit= : Nombre maximum de lignes par étape et par règle}';

    protected $description = 'Montre ce que le cycle de vie ferait, sans rien écrire';

    public function handle(Lifecycle $lifecycle): int
    {
        $limit = $this->limit();

        if ($limit === null) {
            return self::FAILURE;
        }

        $this->info('Règles de conservation :');

        foreach ($lifecycle->policies()->all() as $policy) {
            $this->line(sprintf('  %s : %s', $policy->shortName(), $this->summary($policy)));
        }

        $this->newLine();

        $report = $lifecycle->run(new RunOptions(dryRun: true, limit: $limit));

        if ($report->isEmpty()) {
            $this->info('Rien à faire : aucune ligne n\'a atteint son échéance.');

            return self::SUCCESS;
        }

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

        $this->newLine();
        $this->warn('Essai à blanc : la base n\'a pas été modifiée.');

        return self::SUCCESS;
    }

    /**
     * Null when the given value makes no sense: the message is already shown.
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

    private function summary(Policy $policy): string
    {
        $bits = ['conservation ' . $policy->keepFor];

        $bits[] = $policy->warnBefore === []
            ? 'aucun rappel'
            : 'rappels ' . implode(' puis ', array_map(strval(...), $policy->warnBefore));

        $bits[] = $policy->hasDisableStep()
            ? 'grâce ' . $policy->grace
            : 'pas de désactivation';

        $bits[] = $policy->ending === Ending::Anonymise
            ? 'fin : anonymisation de ' . implode(', ', array_keys($policy->anonymise))
            : 'fin : suppression' . ($policy->forceDelete ? ' définitive' : '');

        return implode(', ', $bits);
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
