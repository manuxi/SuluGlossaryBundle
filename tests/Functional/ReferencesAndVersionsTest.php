<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Manuxi\SuluGlossaryBundle\Reference\GlossaryEntryReferenceRefresher;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class ReferencesAndVersionsTest extends SuluTestCase
{
    protected function setUp(): void
    {
        static::createAuthenticatedClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        static::ensureKernelShutdown();
    }

    public function testReferencesAreCollectedOnPublishAndRebuiltByTheRefresher(): void
    {
        $client = static::createAuthenticatedClient();
        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en&action=publish', [
            'template' => 'default', 'title' => 'Target', 'url' => '/glossary/target', 'explanation' => 'x',
        ]);
        $target = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en&action=publish', [
            'template' => 'related', 'title' => 'Hub', 'url' => '/glossary/hub', 'explanation' => 'x', 'related' => [$target], 'main' => $target,
        ]);
        $hub = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $count = static fn (): int => (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM re_references WHERE resourceKey = ? AND resourceId = ? AND referenceResourceId = ?',
            ['glossary_entries', $target, $hub]
        );
        $this->assertGreaterThan(0, $count());

        $connection->executeStatement('DELETE FROM re_references');
        $this->assertSame(0, $count());

        /** @var GlossaryEntryReferenceRefresher $refresher */
        $refresher = static::getContainer()->get(GlossaryEntryReferenceRefresher::class);
        $refreshed = 0;
        foreach ($refresher->refresh() as $dimensionContent) {
            ++$refreshed;
        }
        static::getContainer()->get('doctrine.orm.entity_manager')->flush();

        $this->assertGreaterThan(0, $refreshed);
        $this->assertGreaterThan(0, $count());
        $this->assertSame('glossary_entries', GlossaryEntryReferenceRefresher::getResourceKey());
    }

    public function testOnlyTheConfiguredNumberOfVersionsIsKept(): void
    {
        $client = static::createAuthenticatedClient();
        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en&action=publish', [
            'template' => 'default', 'title' => 'Versioned', 'url' => '/glossary/versioned', 'explanation' => 'v1',
        ]);
        $id = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        foreach (['v2', 'v3', 'v4'] as $explanation) {
            sleep(1);
            $client->jsonRequest('PUT', '/admin/api/glossary-entries/' . $id . '?locale=en&action=publish', [
                'template' => 'default', 'title' => 'Versioned', 'url' => '/glossary/versioned', 'explanation' => $explanation,
            ]);
            $this->assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        }

        $client->request('GET', '/admin/api/glossary-entries/' . $id . '/versions?locale=en');
        $versions = json_decode((string) $client->getResponse()->getContent(), true)['_embedded']['glossary_entries_versions'];

        $this->assertCount(2, $versions);
    }
}
