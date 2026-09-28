<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony\Command;

use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Report;
use Kaveraa\DataLifecycle\RunOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Le même travail que lifecycle:run --dry-run, plus le rappel des règles.
 * Cette commande n'écrit jamais rien.
 *
 * The same work as lifecycle:run --dry-run, plus a reminder of the rules.
 * This command never writes anything.
 */
#[AsCommand(
    name: 'lifecycle:report',
    description: 'Dit ce que le cycle de vie ferait, sans rien écrire.',
)]
final class ReportCommand extends Command
{
    public function __construct(
        private readonly Lifecycle $lifecycle,
        private readonly PolicyRegistry $policies,
        private readonly int $defaultLimit = 1000,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('subject', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Limiter à ces entités (classe complète ou nom court).')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre de lignes maximum par étape et par règle.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<string> $asked */
        $asked = $input->getOption('subject');
        $limit = ConsoleSupport::limit($io, $input->getOption('limit'), $this->defaultLimit);

        if ($limit === null) {
            return Command::FAILURE;
        }

        $subjects = ConsoleSupport::subjects($this->policies, $asked);
        $options = RunOptions::observe($limit);

        $io->title('Cycle de vie des données : ce qui est gardé, et combien de temps');

        ConsoleSupport::rules($io, $subjects === []
            ? $this->policies->all()
            : array_map(fn (string $subject) => $this->policies->get($subject), $subjects));

        $report = $this->reportFor($subjects, $options);

        ConsoleSupport::table($io, $report);

        $io->info(sprintf('%d ligne(s) seraient traitées. Rien n\'a été écrit.', $report->total()));

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
