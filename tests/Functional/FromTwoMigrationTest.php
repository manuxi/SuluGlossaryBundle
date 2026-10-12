<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Manuxi\SuluGlossaryBundle\Command\MigrateFromTwoCommand;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use Manuxi\SuluGlossaryBundle\Migration\FromTwoMigrator;
use RuntimeException;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FromTwoMigrationTest extends SuluTestCase
{
    private const ENTRY = '11111111-1111-7111-8111-111111111111';
    private const LONELY = '22222222-2222-7222-8222-222222222222';

    protected function setUp(): void
    {
        static::createAuthenticatedClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $this->createLegacyTables($entityManager->getConnection());
        $this->seedLegacyData($entityManager->getConnection());
    }

    private function createLegacyTables(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE abbr_abbreviations (id VARCHAR(36) NOT NULL PRIMARY KEY, legacy_id INT DEFAULT NULL)');
        $connection->executeStatement('CREATE TABLE abbr_abbreviation_dimension_contents (id INTEGER PRIMARY KEY AUTOINCREMENT, author_id INT DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, stage VARCHAR(16) NOT NULL, locale VARCHAR(7) DEFAULT NULL, ghostLocale VARCHAR(7) DEFAULT NULL, availableLocales TEXT DEFAULT NULL, templateKey VARCHAR(32) DEFAULT NULL, templateData TEXT NOT NULL, seoTitle VARCHAR(255) DEFAULT NULL, seoDescription TEXT DEFAULT NULL, seoKeywords TEXT DEFAULT NULL, seoCanonicalUrl TEXT DEFAULT NULL, seoNoIndex BOOLEAN NOT NULL DEFAULT 0, seoNoFollow BOOLEAN NOT NULL DEFAULT 0, seoHideInSitemap BOOLEAN NOT NULL DEFAULT 0, excerptTitle VARCHAR(255) DEFAULT NULL, excerptMore VARCHAR(64) DEFAULT NULL, excerptDescription TEXT DEFAULT NULL, excerptImageId INT DEFAULT NULL, excerptIconId INT DEFAULT NULL, authored DATETIME DEFAULT NULL, workflowPlace VARCHAR(32) DEFAULT NULL, workflowPublished DATETIME DEFAULT NULL, abbreviationId VARCHAR(36) NOT NULL)');
        $connection->executeStatement('CREATE TABLE abbr_abbreviation_dimension_content_excerpt_tags (abbreviation_dimension_content_id INT NOT NULL, tag_id INT NOT NULL)');
        $connection->executeStatement('CREATE TABLE abbr_abbreviation_dimension_content_excerpt_categories (abbreviation_dimension_content_id INT NOT NULL, category_id INT NOT NULL)');
        $connection->executeStatement('CREATE TABLE abbr_abbreviation_versions (id INTEGER PRIMARY KEY AUTOINCREMENT, locale VARCHAR(7) NOT NULL, version INT NOT NULL, data TEXT NOT NULL, created DATETIME NOT NULL, abbreviationId VARCHAR(36) NOT NULL, idUsersCreator INT DEFAULT NULL)');
        $connection->executeStatement('CREATE TABLE ro_routes_old (id INTEGER PRIMARY KEY AUTOINCREMENT, path VARCHAR(191) NOT NULL, locale VARCHAR(15) NOT NULL, entity_class VARCHAR(191) NOT NULL, entity_id VARCHAR(191) NOT NULL, history BOOLEAN NOT NULL)');
        $connection->executeStatement('CREATE TABLE app_abbreviation_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, toggleHeader BOOLEAN DEFAULT NULL, toggleHero BOOLEAN DEFAULT NULL, toggleBreadcrumbs BOOLEAN DEFAULT NULL, pageAbbreviations VARCHAR(255) DEFAULT NULL)');
    }

    private function seedLegacyData(Connection $connection): void
    {
        $connection->insert('abbr_abbreviations', ['id' => self::ENTRY]);
        $connection->insert('abbr_abbreviations', ['id' => self::LONELY]);

        $row = static fn (string $stage, string $locale, string $name, array $templateData, array $extra = []): array => $extra + [
            'name' => $name, 'stage' => $stage, 'locale' => $locale, 'templateKey' => 'abbreviation_default',
            'templateData' => (string) json_encode(['name' => $name] + $templateData), 'abbreviationId' => self::ENTRY,
        ];

        $connection->insert('abbr_abbreviation_dimension_contents', ['stage' => 'draft', 'locale' => null, 'templateData' => '{}', 'abbreviationId' => self::ENTRY]);
        $connection->insert('abbr_abbreviation_dimension_contents', $row('live', 'en', 'WHO', ['explanation' => 'World Health Organization'], ['workflowPlace' => 'published', 'workflowPublished' => '2026-01-02 03:04:05']));
        $connection->insert('abbr_abbreviation_dimension_contents', $row('draft', 'en', 'WHO', ['explanation' => 'World Health Organization (draft)', 'description' => '<p>Agency</p>'], ['seoTitle' => 'WHO seo', 'excerptTitle' => 'WHO excerpt', 'seoHideInSitemap' => 1, 'workflowPlace' => 'published']));
        $connection->insert('abbr_abbreviation_dimension_contents', $row('draft', 'de', 'WHO', ['explanation' => 'Weltgesundheitsorganisation'], ['workflowPlace' => 'draft']));
        $connection->insert('abbr_abbreviation_dimension_contents', ['name' => '', 'stage' => 'draft', 'locale' => 'fr', 'templateData' => '{}', 'abbreviationId' => self::ENTRY]);

        $connection->insert('abbr_abbreviation_versions', ['locale' => 'en', 'version' => 1700000000, 'data' => (string) json_encode(['name' => 'WHO', 'explanation' => 'First version']), 'created' => '2026-01-01 00:00:00', 'abbreviationId' => self::ENTRY]);
        $connection->insert('abbr_abbreviation_versions', ['locale' => 'en', 'version' => 1700000100, 'data' => (string) json_encode(['name' => 'WHO', 'explanation' => 'Second version']), 'created' => '2026-01-01 00:01:00', 'abbreviationId' => self::ENTRY]);

        $connection->insert('ro_routes_old', ['path' => '/abbreviations/who', 'locale' => 'en', 'entity_class' => 'Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation', 'entity_id' => self::ENTRY, 'history' => 0]);
        $connection->insert('ro_routes_old', ['path' => '/abbreviations/old-who', 'locale' => 'en', 'entity_class' => 'Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation', 'entity_id' => self::ENTRY, 'history' => 1]);
        $connection->insert('ro_routes_old', ['path' => '/abbreviations/who-de', 'locale' => 'de', 'entity_class' => 'Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation', 'entity_id' => self::ENTRY, 'history' => 0]);

        $connection->insert('abbr_abbreviation_dimension_contents', ['name' => 'NATO', 'stage' => 'draft', 'locale' => 'en', 'templateKey' => 'abbreviation_default', 'templateData' => (string) json_encode(['name' => 'NATO', 'explanation' => 'North Atlantic Treaty Organization']), 'abbreviationId' => self::LONELY]);

        $connection->insert('app_abbreviation_settings', ['toggleHeader' => 1, 'toggleHero' => 0, 'toggleBreadcrumbs' => 1, 'pageAbbreviations' => 'page-uuid']);
    }

    private function migrator(): FromTwoMigrator
    {
        /** @var FromTwoMigrator $migrator */
        $migrator = static::getContainer()->get(FromTwoMigrator::class);

        return $migrator;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $id, string $locale): array
    {
        static::ensureKernelShutdown();
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/admin/api/glossary-entries/' . $id . '?locale=' . $locale);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        return json_decode((string) $client->getResponse()->getContent(), true);
    }

    public function testDryRunCountsWithoutWriting(): void
    {
        $result = $this->migrator()->migrate(true);

        $this->assertSame(2, $result->entries);
        $this->assertSame(4, $result->translations);
        $this->assertSame(1, $result->published);
        $this->assertSame(2, $result->versions);
        $this->assertSame(0, (int) static::getEntityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM glossary_entries'));
    }

    public function testMigratesContentRoutesAndWorkflow(): void
    {
        $result = $this->migrator()->migrate();

        $this->assertSame([self::ENTRY . '/fr'], array_keys($result->errors));
        $this->assertSame(2, $result->entries);
        $this->assertSame(3, $result->translations);
        $this->assertTrue($result->settings);

        $english = $this->fetch(self::ENTRY, 'en');
        $this->assertSame('WHO', $english['title']);
        $this->assertSame('abbreviation', $english['type']);
        $this->assertSame('World Health Organization (draft)', $english['explanation']);
        $this->assertSame('/abbreviations/who', $english['url']);
        $this->assertSame('WHO seo', $english['seo']['title']);
        $this->assertSame('WHO excerpt', $english['excerpt']['title']);
        $this->assertTrue($english['seoHideInSitemap']);
        $this->assertSame('draft', $english['workflowPlace']);

        $german = $this->fetch(self::ENTRY, 'de');
        $this->assertSame('Weltgesundheitsorganisation', $german['explanation']);
        $this->assertSame('/abbreviations/who-de', $german['url']);

        $lonely = $this->fetch(self::LONELY, 'en');
        $this->assertSame('NATO', $lonely['title']);
    }

    public function testKeepsTheLiveContentAndTheStoredVersions(): void
    {
        $this->migrator()->migrate();

        /** @var Connection $connection */
        $connection = static::getEntityManager()->getConnection();
        $live = $connection->fetchAssociative("SELECT title, explanation, workflowPublished FROM glossary_entry_dimension_contents WHERE glossaryEntryUuid = ? AND stage = 'live' AND locale = 'en' AND version = 0", [self::ENTRY]);
        $this->assertIsArray($live);
        $this->assertSame('World Health Organization', $live['explanation']);
        $this->assertStringStartsWith('2026-01-02 03:04:05', (string) $live['workflowPublished']);

        /** @var string[] $explanations */
        $explanations = $connection->fetchFirstColumn("SELECT explanation FROM glossary_entry_dimension_contents WHERE glossaryEntryUuid = ? AND stage = 'draft' AND locale = 'en' AND version > 0 ORDER BY version", [self::ENTRY]);
        // max_versions is 2 in the test configuration, so the oldest of the three versions is pruned
        $this->assertCount(2, $explanations);
        $this->assertSame('Second version', $explanations[0]);
    }

    public function testCopiesTheSettings(): void
    {
        $this->migrator()->migrate();

        $settings = static::getEntityManager()->getRepository(GlossaryEntrySettings::class)->findOneBy([]);
        $this->assertInstanceOf(GlossaryEntrySettings::class, $settings);
        $this->assertTrue($settings->getToggleHeader());
        $this->assertFalse($settings->getToggleHero());
        $this->assertSame('page-uuid', $settings->getPageGlossary());
    }

    public function testMigrationIsIdempotent(): void
    {
        $this->migrator()->migrate();

        $second = $this->migrator()->migrate();

        $this->assertSame(0, $second->entries);
        $this->assertSame(2, $second->skipped);
        $this->assertFalse($second->settings);
    }

    public function testCommandReportsErrorsAndSuccess(): void
    {
        /** @var MigrateFromTwoCommand $command */
        $command = static::getContainer()->get(MigrateFromTwoCommand::class);
        $tester = new CommandTester($command);

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('has no title', $tester->getDisplay());

        $this->assertSame(0, $tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('Already migrated', $tester->getDisplay());
    }

    public function testMissingLegacyTablesAreReported(): void
    {
        static::getEntityManager()->getConnection()->executeStatement('DROP TABLE abbr_abbreviation_dimension_contents');

        $this->expectException(RuntimeException::class);

        $this->migrator()->migrate();
    }
}
