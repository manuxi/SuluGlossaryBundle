<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;

class TrashRoundTripTest extends SuluTestCase
{
    public function testDeletedEntryCanBeRestoredFromTheTrash(): void
    {
        $client = static::createAuthenticatedClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en', [
            'template' => 'default',
            'title' => 'ISO',
            'url' => '/glossary/iso',
            'type' => 'acronym',
            'explanation' => 'International Organization for Standardization',
        ]);
        $id = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $client->request('DELETE', '/admin/api/glossary-entries/' . $id . '?locale=en');
        $this->assertSame(204, $client->getResponse()->getStatusCode());

        /** @var GlossaryEntryRepositoryInterface $repository */
        $repository = static::getContainer()->get(GlossaryEntryRepositoryInterface::class);
        $this->assertNull($repository->findOneBy(['uuid' => $id]));

        /** @var TrashItemRepositoryInterface $trashItems */
        $trashItems = static::getContainer()->get('sulu_trash.trash_item_repository');
        $trashItem = $trashItems->findOneBy(['resourceKey' => GlossaryEntry::RESOURCE_KEY, 'resourceId' => $id]);
        $this->assertNotNull($trashItem);
        $this->assertSame('ISO', $trashItem->getResourceTitle('en'));

        $client->request('POST', '/admin/api/trash-items/' . $trashItem->getId() . '?action=restore');

        $client->request('GET', '/admin/api/glossary-entries/' . $id . '?locale=en');
        $this->assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertSame('ISO', $data['title']);
        $this->assertSame('acronym', $data['type']);
        $this->assertSame('/glossary/iso', $data['url']);
    }
}
