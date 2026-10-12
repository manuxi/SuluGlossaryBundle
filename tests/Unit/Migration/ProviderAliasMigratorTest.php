<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Manuxi\SuluGlossaryBundle\Migration\ProviderAliasMigrator;
use PHPUnit\Framework\TestCase;

class ProviderAliasMigratorTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE pa_page_dimension_contents (id INTEGER PRIMARY KEY, templateData TEXT NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE sn_snippet_dimension_contents (id INTEGER PRIMARY KEY, templateData TEXT NOT NULL)');
    }

    private function insert(string $table, int $id, string $json): void
    {
        $this->connection->insert($table, ['id' => $id, 'templateData' => $json]);
    }

    private function load(string $table, int $id): string
    {
        return (string) $this->connection->fetchOne('SELECT templateData FROM ' . $table . ' WHERE id = ?', [$id]);
    }

    public function testRewritesTextEditorLinksAndLinkProperties(): void
    {
        $this->insert('pa_page_dimension_contents', 1, (string) json_encode([
            'article' => '<p><sulu-link provider="abbreviations" href="uuid-1" target="_self">WHO</sulu-link></p>',
            'link' => ['provider' => 'abbreviations', 'href' => 'uuid-1', 'target' => '_self'],
            'blocks' => [['text' => "<sulu-link provider='abbreviation' href='uuid-2'>x</sulu-link>"]],
            'untouched' => 'abbreviations',
        ]));

        $changed = (new ProviderAliasMigrator($this->connection))->migrate();

        $this->assertSame(['pa_page_dimension_contents' => 1, 'sn_snippet_dimension_contents' => 0], $changed);
        $data = json_decode($this->load('pa_page_dimension_contents', 1), true);
        $this->assertSame('<p><sulu-link provider="glossary_entries" href="uuid-1" target="_self">WHO</sulu-link></p>', $data['article']);
        $this->assertSame('glossary_entries', $data['link']['provider']);
        $this->assertSame("<sulu-link provider='glossary_entries' href='uuid-2'>x</sulu-link>", $data['blocks'][0]['text']);
        $this->assertSame('abbreviations', $data['untouched']);
    }

    public function testDryRunDoesNotWrite(): void
    {
        $json = (string) json_encode(['link' => ['provider' => 'abbreviations', 'href' => 'uuid-1']]);
        $this->insert('sn_snippet_dimension_contents', 1, $json);

        $changed = (new ProviderAliasMigrator($this->connection))->migrate(true);

        $this->assertSame(1, $changed['sn_snippet_dimension_contents']);
        $this->assertSame($json, $this->load('sn_snippet_dimension_contents', 1));
    }

    public function testRowsWithoutLinksAndInvalidJsonAreIgnored(): void
    {
        $this->insert('pa_page_dimension_contents', 1, '{"text":"abbreviations are great"}');
        $this->insert('pa_page_dimension_contents', 2, 'not json abbreviation');

        $changed = (new ProviderAliasMigrator($this->connection))->migrate();

        $this->assertSame(0, $changed['pa_page_dimension_contents']);
    }

    public function testMissingTablesAreSkipped(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $this->assertSame([], (new ProviderAliasMigrator($connection))->migrate());
    }

    public function testMigrationIsIdempotent(): void
    {
        $this->insert('pa_page_dimension_contents', 1, (string) json_encode(['link' => ['provider' => 'abbreviations']]));
        $migrator = new ProviderAliasMigrator($this->connection);

        $migrator->migrate();

        $this->assertSame(0, $migrator->migrate()['pa_page_dimension_contents']);
    }
}
