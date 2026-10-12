<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Migration;

use Doctrine\DBAL\Connection;

/**
 * Rewrites the link provider of links to abbreviations (text editor links and link properties) in the
 * content of pages, snippets and articles to the provider alias of the glossary entries.
 */
class ProviderAliasMigrator
{
    public const OLD_PROVIDERS = ['abbreviations', 'abbreviation'];
    public const NEW_PROVIDER = 'glossary_entries';

    /**
     * @var array<string, string> table => json column
     */
    private const TARGETS = [
        'pa_page_dimension_contents' => 'templateData',
        'sn_snippet_dimension_contents' => 'templateData',
        'ar_article_dimension_contents' => 'templateData',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, int> number of changed rows per table
     */
    public function migrate(bool $dryRun = false): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $changed = [];

        foreach (self::TARGETS as $table => $column) {
            if (!$schemaManager->tablesExist([$table])) {
                continue;
            }

            $changed[$table] = $this->migrateTable($table, $column, $dryRun);
        }

        return $changed;
    }

    public function rewrite(mixed $value, bool &$changed): mixed
    {
        if (\is_string($value)) {
            $rewritten = preg_replace('/provider=(["\'])(?:' . implode('|', self::OLD_PROVIDERS) . ')\1/', 'provider=$1' . self::NEW_PROVIDER . '$1', $value);

            if (null !== $rewritten && $rewritten !== $value) {
                $changed = true;

                return $rewritten;
            }

            return $value;
        }

        if (!\is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if ('provider' === $key && \is_string($item) && \in_array($item, self::OLD_PROVIDERS, true)) {
                $value[$key] = self::NEW_PROVIDER;
                $changed = true;

                continue;
            }

            $value[$key] = $this->rewrite($item, $changed);
        }

        return $value;
    }

    private function migrateTable(string $table, string $column, bool $dryRun): int
    {
        $count = 0;

        /** @var array<array{id: int|string, data: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, ' . $column . ' AS data FROM ' . $table . ' WHERE ' . $column . ' LIKE ?',
            ['%abbreviation%'],
        );

        foreach ($rows as $row) {
            $data = json_decode($row['data'], true);
            if (!\is_array($data)) {
                continue;
            }

            $changed = false;
            $data = $this->rewrite($data, $changed);

            if (!$changed) {
                continue;
            }

            ++$count;

            if (!$dryRun) {
                $this->connection->update($table, [$column => json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)], ['id' => $row['id']]);
            }
        }

        return $count;
    }
}
