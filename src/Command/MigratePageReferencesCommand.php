<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Command;

use Manuxi\SuluGlossaryBundle\Migration\ProviderAliasMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'sulu:glossary:migrate-page-references', description: 'Rewrites links to abbreviations in pages, snippets and articles to the glossary entry link provider')]
class MigratePageReferencesCommand extends Command
{
    public function __construct(
        private readonly ProviderAliasMigrator $migrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only count the affected rows');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $changed = $this->migrator->migrate($dryRun);

        if ([] === $changed) {
            $io->warning('None of the content tables of pages, snippets or articles exists.');

            return Command::SUCCESS;
        }

        $io->table(['Table', $dryRun ? 'Rows to change' : 'Rows changed'], array_map(static fn (string $table, int $count): array => [$table, $count], array_keys($changed), $changed));
        $io->success($dryRun ? 'Dry run finished.' : 'Done. Clear the HTTP cache and rebuild the search index.');

        return Command::SUCCESS;
    }
}
