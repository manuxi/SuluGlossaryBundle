<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use CmsIg\Seal\Reindex\ReindexConfig;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Manuxi\SuluGlossaryBundle\Search\GlossaryEntryAdminReindexProvider;
use Manuxi\SuluGlossaryBundle\Search\GlossaryEntryWebsiteReindexProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class SearchProvidersTest extends SuluTestCase
{
    private static string $id = '';

    protected function setUp(): void
    {
        $client = static::createAuthenticatedClient();

        if ('' !== self::$id) {
            return;
        }

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en&action=publish', [
            'template' => 'default',
            'title' => 'WHO',
            'url' => '/glossary/who',
            'type' => 'abbreviation',
            'explanation' => 'World Health Organization',
            'description' => '<p>An agency of the <b>United Nations</b></p>',
            'image' => ['id' => 7],
        ]);
        self::$id = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en', [
            'template' => 'default',
            'title' => 'Draft',
            'url' => '/glossary/draft',
            'explanation' => 'Only a draft',
        ]);
    }

    /**
     * @param iterable<array<string, mixed>> $documents
     *
     * @return array<string, array<string, mixed>>
     */
    private function byId(iterable $documents): array
    {
        $result = [];
        foreach ($documents as $document) {
            $result[(string) $document['id']] = $document;
        }

        return $result;
    }

    public function testAdminIndexContainsDraftsOfAllEntries(): void
    {
        /** @var GlossaryEntryAdminReindexProvider $provider */
        $provider = static::getContainer()->get(GlossaryEntryAdminReindexProvider::class);

        $documents = $this->byId($provider->provide(ReindexConfig::create()));

        $this->assertArrayHasKey('glossary_entries__' . self::$id . '__en', $documents);
        $document = $documents['glossary_entries__' . self::$id . '__en'];
        $this->assertSame('WHO', $document['title']);
        $this->assertSame('sulu.glossary.entries', $document['securityContext']);
        $this->assertSame(['type' => 'abbreviation'], $document['metadata']);
        $this->assertCount(2, $documents);
    }

    public function testAdminIndexCanBeLimitedByIdentifiers(): void
    {
        /** @var GlossaryEntryAdminReindexProvider $provider */
        $provider = static::getContainer()->get(GlossaryEntryAdminReindexProvider::class);

        $documents = [...$provider->provide(ReindexConfig::create()->withIdentifiers(['glossary_entries__' . self::$id . '__en', 'other__1__en']))];

        $this->assertCount(1, $documents);
        $this->assertSame([], [...$provider->provide(ReindexConfig::create()->withIdentifiers(['other__1__en']))]);
    }

    public function testWebsiteIndexContainsOnlyPublishedEntries(): void
    {
        /** @var GlossaryEntryWebsiteReindexProvider $provider */
        $provider = static::getContainer()->get(GlossaryEntryWebsiteReindexProvider::class);

        $documents = $this->byId($provider->provide(ReindexConfig::create()));

        $this->assertCount(1, $documents);
        $document = $documents['glossary_entries__' . self::$id . '__en'];
        $this->assertSame('WHO', $document['title']);
        $this->assertSame('/glossary/who', $document['url']);
        $this->assertSame(['sulu-io'], $document['webspaces']);
        $this->assertSame(['World Health Organization', 'An agency of the United Nations'], $document['content']);
        $this->assertSame(7, $document['mediaId']);
        $this->assertSame(['type' => 'abbreviation'], $document['metadata']);
    }
}
