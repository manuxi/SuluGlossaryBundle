<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class GlossaryEntryLifecycleTest extends SuluTestCase
{
    private static bool $schemaCreated = false;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createAuthenticatedClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        if (!self::$schemaCreated) {
            $schemaTool = new SchemaTool($entityManager);
            $schemaTool->dropDatabase();
            $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
            self::$schemaCreated = true;
        }
    }

    public function testCreateReturnsContentAndType(): void
    {
        $data = $this->create('WHO', 'who', 'abbreviation');

        $this->assertSame('WHO', $data['title']);
        $this->assertSame('abbreviation', $data['type']);
        $this->assertSame('World Health Organization', $data['explanation']);
        $this->assertSame('/glossary/who', $data['url']);
        $this->assertSame('draft', $data['stage']);
        $this->assertNotEmpty($data['id']);
    }

    public function testPublishAndUnpublish(): void
    {
        $id = $this->create('NATO', 'nato', 'acronym')['id'];

        $published = $this->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=publish');
        $this->assertSame('published', $published['workflowPlace']);

        $unpublished = $this->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=unpublish');
        $this->assertSame('unpublished', $unpublished['workflowPlace']);
    }

    public function testUpdateChangesTypeAndTitle(): void
    {
        $id = $this->create('NASA', 'nasa', 'abbreviation')['id'];

        $updated = $this->request('PUT', '/admin/api/glossary-entries/' . $id . '?locale=en', [
            'template' => 'default',
            'title' => 'NASA Agency',
            'url' => '/glossary/nasa',
            'type' => 'term',
            'explanation' => 'Space agency',
        ]);

        $this->assertSame('NASA Agency', $updated['title']);
        $this->assertSame('term', $updated['type']);
    }

    public function testListContainsCreatedEntry(): void
    {
        $this->create('UNESCO', 'unesco', 'acronym');

        $list = $this->request('GET', '/admin/api/glossary-entries?locale=en&fields=title,type&limit=100');
        $titles = array_column($list['_embedded']['glossary_entries'], 'title');

        $this->assertContains('UNESCO', $titles);
    }

    public function testVersionsAreCreatedOnPublish(): void
    {
        $id = $this->create('EU', 'eu', 'abbreviation')['id'];
        $this->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=publish');

        $versions = $this->request('GET', '/admin/api/glossary-entries/' . $id . '/versions?locale=en');

        $this->assertCount(1, $versions['_embedded']['glossary_entries_versions']);
    }

    public function testCopyLocale(): void
    {
        $id = $this->create('UN', 'un', 'abbreviation')['id'];

        $copied = $this->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=copy_locale&src=en&dest=de');
        $this->assertSame('UN', $copied['title']);

        $german = $this->request('GET', '/admin/api/glossary-entries/' . $id . '?locale=de');
        $this->assertSame('UN', $german['title']);
        $this->assertSame('de', $german['locale']);
    }

    public function testDeleteLocaleKeepsOtherLocale(): void
    {
        $id = $this->create('IMF', 'imf', 'abbreviation')['id'];
        $this->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=copy_locale&src=en&dest=de');

        $this->client->request('DELETE', '/admin/api/glossary-entries/' . $id . '?locale=en&deleteLocale=true');
        $this->assertSame(204, $this->client->getResponse()->getStatusCode());

        $german = $this->request('GET', '/admin/api/glossary-entries/' . $id . '?locale=de');
        $this->assertSame('IMF', $german['title']);
    }

    public function testDeleteRemovesEntry(): void
    {
        $id = $this->create('WTO', 'wto', 'abbreviation')['id'];

        $this->client->request('DELETE', '/admin/api/glossary-entries/' . $id . '?locale=en');
        $this->assertSame(204, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/admin/api/glossary-entries/' . $id . '?locale=en');
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testRestoreVersion(): void
    {
        $id = $this->create('OECD', 'oecd', 'abbreviation')['id'];
        $this->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=publish');
        $versions = $this->request('GET', '/admin/api/glossary-entries/' . $id . '/versions?locale=en');
        $version = $versions['_embedded']['glossary_entries_versions'][0]['version'];

        $this->request('PUT', '/admin/api/glossary-entries/' . $id . '?locale=en', [
            'template' => 'default',
            'title' => 'OECD changed',
            'url' => '/glossary/oecd',
            'explanation' => 'Changed',
        ]);

        $restored = $this->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=restore&version=' . $version);
        $this->assertSame('OECD', $restored['title']);
    }

    public function testRemoveDraftRestoresLiveContent(): void
    {
        $id = $this->create('ILO', 'ilo', 'abbreviation')['id'];
        $this->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=publish');
        $this->request('PUT', '/admin/api/glossary-entries/' . $id . '?locale=en', [
            'template' => 'default',
            'title' => 'ILO draft',
            'url' => '/glossary/ilo',
            'explanation' => 'Draft',
        ]);

        $reverted = $this->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=remove_draft');
        $this->assertSame('ILO', $reverted['title']);
    }

    /**
     * @return array<string, mixed>
     */
    private function create(string $title, string $slug, string $type): array
    {
        return $this->request('POST', '/admin/api/glossary-entries?locale=en', [
            'template' => 'default',
            'title' => $title,
            'url' => '/glossary/' . $slug,
            'type' => $type,
            'explanation' => 'World Health Organization',
        ], 201);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, ?array $body = null, int $expectedStatus = 200): array
    {
        $this->client->jsonRequest($method, $uri, $body ?? []);

        $response = $this->client->getResponse();
        $this->assertSame($expectedStatus, $response->getStatusCode(), substr((string) $response->getContent(), 0, 1500));

        return json_decode((string) $response->getContent(), true) ?? [];
    }
}
