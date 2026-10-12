<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Command;

use Manuxi\SuluGlossaryBundle\Migration\FromTwoMigrator;
use Manuxi\SuluGlossaryBundle\Migration\MigrationResult;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'sulu:glossary:migrate-from-2x', description: 'Migrates the data of SuluAbbreviationsBundle 2.0 to glossary entries')]
class MigrateFromTwoCommand extends Command
{
    public function __construct(
        private readonly FromTwoMigrator $migrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only count what would be migrated')
            ->addOption('routes-table', null, InputOption::VALUE_REQUIRED, 'Table with the routes of Sulu 2.6', FromTwoMigrator::DEFAULT_ROUTES_TABLE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        /** @var string $routesTable */
        $routesTable = $input->getOption('routes-table');

        try {
            $result = $this->migrator->migrate($dryRun, $routesTable);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->report($io, $result, $dryRun);

        return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;
    }

    private function report(SymfonyStyle $io, MigrationResult $result, bool $dryRun): void
    {
        $io->definitionList(
            ['Mode' => $dryRun ? 'dry run, nothing was written' : 'migrated'],
            ['Entries' => $result->entries],
            ['Already migrated (skipped)' => $result->skipped],
            ['Translations' => $result->translations],
            ['Published translations' => $result->published],
            ['Versions' => $result->versions],
            ['Routes' => $result->routes],
            ['Settings copied' => $result->settings ? 'yes' : 'no'],
        );

        foreach ($result->errors as $id => $message) {
            $io->warning(\sprintf('%s: %s', $id, $message));
        }

        if (!$result->hasErrors()) {
            $io->success($dryRun ? 'Dry run finished.' : 'Migration finished. Rebuild the search index and run sulu:reference:refresh glossary_entries.');
        }
    }
}
