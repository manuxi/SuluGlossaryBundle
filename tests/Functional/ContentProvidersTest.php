<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Manuxi\SuluGlossaryBundle\Content\GlossaryEntrySmartContentProvider;
use Manuxi\SuluGlossaryBundle\Link\GlossaryEntryLinkProvider;
use Manuxi\SuluGlossaryBundle\Sitemap\GlossaryEntrySitemapProvider;
use Manuxi\SuluGlossaryBundle\Teaser\GlossaryEntryTeaserProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class ContentProvidersTest extends SuluTestCase
{
    /**
     * @var array<string, string>
     */
    private static array $ids = [];

    protected function setUp(): void
    {
        $client = static::createAuthenticatedClient();

        if ([] !== self::$ids) {
            return;
        }

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        foreach (['WHO' => 'abbreviation', 'NATO' => 'acronym', 'Glossary' => 'term', 'Hidden' => 'term'] as $title => $type) {
            $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en&action=publish', [
                'template' => 'default',
                'title' => $title,
                'url' => '/glossary/' . strtolower($title),
                'type' => $type,
                'explanation' => 'Long form of ' . $title,
                'seoHideInSitemap' => 'Hidden' === $title,
            ]);
            self::$ids[$title] = json_decode((string) $client->getResponse()->getContent(), true)['id'];
        }

        $client->request('POST', '/admin/api/glossary-entries/' . self::$ids['WHO'] . '?locale=en&action=copy_locale&src=en&dest=de');
        $client->request('POST', '/admin/api/glossary-entries/' . self::$ids['WHO'] . '?locale=de&action=publish');
    }

    public function testTeaserProviderReturnsTeasersInTheRequestedOrder(): void
    {
        /** @var GlossaryEntryTeaserProvider $provider */
        $provider = static::getContainer()->get(GlossaryEntryTeaserProvider::class);

        $teasers = $provider->find([self::$ids['NATO'], self::$ids['WHO']], 'en');

        $this->assertSame(['NATO', 'WHO'], array_map(static fn ($teaser) => $teaser->getTitle(), $teasers));
        $this->assertSame('Long form of NATO', $teasers[0]->getDescription());
        $this->assertSame('/glossary/nato', $teasers[0]->getUrl());
    }

    public function testLinkProviderPreloadsLinks(): void
    {
        /** @var GlossaryEntryLinkProvider $provider */
        $provider = static::getContainer()->get(GlossaryEntryLinkProvider::class);

        $items = [...$provider->preload([self::$ids['WHO']], 'en')];

        $this->assertCount(1, $items);
        $this->assertSame('WHO', $items[0]->getTitle());
        $this->assertSame('/glossary/who', $items[0]->getUrl());
    }

    public function testSmartContentProviderFiltersByType(): void
    {
        /** @var GlossaryEntrySmartContentProvider $provider */
        $provider = static::getContainer()->get(GlossaryEntrySmartContentProvider::class);
        $filters = [
            'categories' => [], 'categoryOperator' => 'OR', 'websiteCategories' => [], 'websiteCategoryOperator' => 'OR',
            'tags' => [], 'tagOperator' => 'OR', 'websiteTags' => [], 'websiteTagOperator' => 'OR',
            'types' => ['abbreviation', 'acronym'], 'typesOperator' => 'OR',
            'locale' => 'en', 'dataSource' => null, 'limit' => null, 'offset' => 0, 'includeSubFolders' => false, 'excludeDuplicates' => false,
        ];

        $items = $provider->findFlatBy($filters, ['title' => 'asc']);

        $this->assertSame(['NATO', 'WHO'], array_column($items, 'title'));
        $this->assertSame(2, $provider->countBy($filters));
        $this->assertSame(4, $provider->countBy(['types' => []] + $filters));
        $this->assertSame('glossary_entries', $provider->getType());
        $this->assertSame('glossary_entry', $provider->getResourceLoaderKey());
    }

    public function testSmartContentConfigurationOffersTheTypes(): void
    {
        /** @var GlossaryEntrySmartContentProvider $provider */
        $provider = static::getContainer()->get(GlossaryEntrySmartContentProvider::class);

        $types = array_map(static fn ($parameter) => $parameter->getValue(), $provider->getConfiguration()->getTypes() ?? []);

        $this->assertSame(['abbreviation', 'acronym', 'term'], $types);
    }

    public function testSitemapContainsPublishedVisibleEntriesWithAlternates(): void
    {
        /** @var GlossaryEntrySitemapProvider $provider */
        $provider = static::getContainer()->get(GlossaryEntrySitemapProvider::class);

        $urls = $provider->build(1, 'http', 'sulu.lo');
        $locs = array_map(static fn ($url) => $url->getLoc(), $urls);

        $this->assertContains('http://sulu.lo/en/glossary/who', $locs);
        $this->assertContains('http://sulu.lo/en/glossary/nato', $locs);
        $this->assertNotContains('http://sulu.lo/en/glossary/hidden', $locs);

        $who = $urls[array_search('http://sulu.lo/en/glossary/who', $locs, true)];
        $this->assertNotEmpty($who->getAlternateLinks());
        $this->assertSame('glossary_entries', $provider->getAlias());
    }
}
