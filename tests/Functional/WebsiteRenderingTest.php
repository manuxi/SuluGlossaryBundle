<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class WebsiteRenderingTest extends SuluTestCase
{
    private function createPublishedEntry(string $title, string $slug): string
    {
        $client = static::createAuthenticatedClient();
        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en&action=publish', [
            'template' => 'default',
            'title' => $title,
            'url' => '/glossary/' . $slug,
            'type' => 'acronym',
            'explanation' => 'Explanation of ' . $title,
        ]);
        $this->assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        return json_decode((string) $client->getResponse()->getContent(), true)['id'];
    }

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

    public function testPublishedEntryIsRenderedOnItsRoute(): void
    {
        $this->createPublishedEntry('ISO', 'iso');
        static::ensureKernelShutdown();

        $client = static::createWebsiteClient();
        $client->request('GET', 'http://sulu.lo/en/glossary/iso');

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getContent(), 0, 1500));
        $this->assertStringContainsString('<h1>ISO</h1>', (string) $response->getContent());
        $this->assertStringContainsString('Explanation of ISO', (string) $response->getContent());
    }

    public function testUnpublishedEntryReturnsNotFound(): void
    {
        $id = $this->createPublishedEntry('DIN', 'din');
        static::ensureKernelShutdown();
        $client = static::createAuthenticatedClient();
        $client->request('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=unpublish');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        static::ensureKernelShutdown();

        $websiteClient = static::createWebsiteClient();
        $websiteClient->request('GET', 'http://sulu.lo/en/glossary/din');

        $this->assertSame(404, $websiteClient->getResponse()->getStatusCode());
    }
}
