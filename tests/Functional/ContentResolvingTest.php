<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class ContentResolvingTest extends SuluTestCase
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

    /**
     * @param array<string, mixed> $data
     */
    private function create(array $data, bool $publish = true): string
    {
        static::ensureKernelShutdown();
        $client = static::createAuthenticatedClient();
        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en' . ($publish ? '&action=publish' : ''), $data);
        $this->assertSame(201, $client->getResponse()->getStatusCode(), substr((string) $client->getResponse()->getContent(), 0, 1500));

        return json_decode((string) $client->getResponse()->getContent(), true)['id'];
    }

    public function testSelectionsAndSmartContentAreResolvedOnTheWebsite(): void
    {
        $first = $this->create(['template' => 'default', 'title' => 'First', 'url' => '/glossary/first', 'type' => 'abbreviation', 'explanation' => 'one']);
        $second = $this->create(['template' => 'default', 'title' => 'Second', 'url' => '/glossary/second', 'type' => 'acronym', 'explanation' => 'two']);
        $draftOnly = $this->create(['template' => 'default', 'title' => 'Draft only', 'url' => '/glossary/draft', 'explanation' => 'three'], false);

        $this->create([
            'template' => 'related',
            'title' => 'Hub',
            'url' => '/glossary/hub',
            'explanation' => 'hub',
            'related' => [$second, $draftOnly, $first],
            'main' => $first,
            'smart' => [
                'dataSource' => null,
                'categories' => [],
                'tags' => [],
                'types' => ['abbreviation'],
                'sortBy' => 'title',
                'sortMethod' => 'asc',
                'presentAs' => null,
                'limitResult' => null,
                'audienceTargeting' => false,
            ],
        ]);

        static::ensureKernelShutdown();
        $client = static::createWebsiteClient();
        $client->request('GET', 'http://sulu.lo/en/glossary/hub');
        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getContent(), 0, 2000));
        $html = (string) $response->getContent();

        $this->assertMatchesRegularExpression('#<ul id="related"><li>Second</li><li>First</li></ul>#', $html);
        $this->assertStringContainsString('<p id="main">First</p>', $html);
        $this->assertMatchesRegularExpression('#<ul id="smart"><li>First</li></ul>#', $html);
    }
}
