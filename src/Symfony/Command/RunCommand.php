<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony\Command;

use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\Report;
use Kaveraa\DataLifecycle\RunOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Joue le cycle de vie : prévenir, désactiver, effacer.
 *
 * Plays the lifecycle: warn, disable, erase.
 */
#[AsCommand(
    name: 'lifecycle:run',
    description: 'Applique les règles de conservation : rappels, désactivations, anonymisations ou suppressions.',
)]
final class RunCommand extends Command
{
    public function __construct(
        private readonly Lifecycle $lifecycle,
        /** data_lifecycle.dry_run : le mode observation est imposé par la configuration. */
        private readonly bool $alwaysObserve = false,
        private readonly int $defaultLimit = 1000,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Ne rien écrire : seulement dire ce qui se passerait.')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limiter à ces entités (classe complète ou nom court).')
            ->addOption('step', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limiter à ces étapes : warn, disable, erase.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre de lignes maximum par étape et par règle.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $asked = (bool) $input->getOption('dry-run');
        $observe = $asked || $this->alwaysObserve;

        if ($this->alwaysObserve && !$asked) {
            $io->note('data_lifecycle.dry_run est actif : cette exécution observe seulement, rien n\'est écrit.');
        }

        /** @var list<string> $steps */
        $steps = $input->getOption('step');
        /** @var list<string> $subjects */
        $subjects = $input->getOption('subject');
        $limit = ConsoleSupport::limit($io, $input->getOption('limit'), $this->defaultLimit);

        if ($limit === null) {
            return Command::FAILURE;
        }

        $options = new RunOptions(
            dryRun: $observe,
            limit: $limit,
            steps: ConsoleSupport::steps($steps),
        );

        $report = $this->reportFor(ConsoleSupport::subjects($this->lifecycle->policies(), $subjects), $options);

        $io->title($observe ? 'Cycle de vie des données : observation seulement' : 'Cycle de vie des données');

        ConsoleSupport::table($io, $report);

        if ($report->isEmpty()) {
            return Command::SUCCESS;
        }

        if ($observe) {
            $io->success(sprintf('%d ligne(s) seraient traitées. Rien n\'a été écrit.', $report->total()));

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d ligne(s) traitées.', $report->total()));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $subjects
     */
    private function reportFor(array $subjects, RunOptions $options): Report
    {
        if ($subjects === []) {
            return $this->lifecycle->run($options);
        }

        $report = new Report($options->dryRun, $options->samples);

        foreach ($subjects as $subject) {
            $report->merge($this->lifecycle->runFor($subject, $options));
        }

        return $report;
    }
}
