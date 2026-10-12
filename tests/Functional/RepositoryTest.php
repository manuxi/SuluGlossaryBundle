<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Manuxi\SuluGlossaryBundle\Exception\GlossaryEntryNotFoundException;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class RepositoryTest extends SuluTestCase
{
    /**
     * @var array<string, string>
     */
    private static array $ids = [];

    protected function setUp(): void
    {
        $client = static::createAuthenticatedClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        if ([] === self::$ids) {
            $schemaTool = new SchemaTool($entityManager);
            $schemaTool->dropDatabase();
            $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

            foreach (['WHO' => 'abbreviation', 'NATO' => 'acronym', 'Glossary' => 'term'] as $title => $type) {
                $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en', [
                    'template' => 'default',
                    'title' => $title,
                    'url' => '/glossary/' . strtolower($title),
                    'type' => $type,
                    'explanation' => 'x',
                ]);
                $id = json_decode((string) $client->getResponse()->getContent(), true)['id'];
                self::$ids[$title] = $id;
                $client->jsonRequest('POST', '/admin/api/glossary-entries/' . $id . '?locale=en&action=publish');
            }
        }
    }

    private function repository(): GlossaryEntryRepositoryInterface
    {
        /** @var GlossaryEntryRepositoryInterface $repository */
        $repository = static::getContainer()->get(GlossaryEntryRepositoryInterface::class);

        return $repository;
    }

    public function testCountByLiveEntries(): void
    {
        $this->assertSame(3, $this->repository()->countBy(['locale' => 'en', 'stage' => 'live']));
    }

    public function testFilterByTypes(): void
    {
        $uuids = $this->repository()->findIdentifiersBy(['locale' => 'en', 'stage' => 'live', 'types' => ['abbreviation', 'acronym']]);

        $this->assertEqualsCanonicalizing([self::$ids['WHO'], self::$ids['NATO']], [...$uuids]);
    }

    public function testFilterByUuids(): void
    {
        $uuids = $this->repository()->findIdentifiersBy(['locale' => 'en', 'stage' => 'live', 'uuids' => [self::$ids['Glossary']]]);

        $this->assertSame([self::$ids['Glossary']], [...$uuids]);
    }

    public function testPagination(): void
    {
        $uuids = $this->repository()->findIdentifiersBy(['locale' => 'en', 'stage' => 'live', 'limit' => 2, 'page' => 1], ['uuid' => 'asc']);

        $this->assertCount(2, [...$uuids]);
    }

    public function testFindByLoadsContent(): void
    {
        $entries = [...$this->repository()->findBy(
            ['locale' => 'en', 'stage' => 'live', 'uuid' => self::$ids['WHO']],
            [],
            [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => true]
        )];

        $this->assertCount(1, $entries);
        $this->assertSame('WHO', $entries[0]->getDimensionContents()->filter(static fn ($dc) => 'live' === $dc->getStage() && 'en' === $dc->getLocale())->first()->getTitle());
    }

    public function testGetOneByThrowsForUnknownUuid(): void
    {
        $this->expectException(GlossaryEntryNotFoundException::class);

        $this->repository()->getOneBy(['uuid' => 'does-not-exist']);
    }

    public function testFindOneByReturnsNullForUnknownUuid(): void
    {
        $this->assertNull($this->repository()->findOneBy(['uuid' => 'does-not-exist']));
    }

    public function testCreateNewKeepsTheGivenUuid(): void
    {
        $this->assertSame('abc', $this->repository()->createNew('abc')->getUuid());
    }
}
