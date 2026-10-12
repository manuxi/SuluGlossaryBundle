<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Sulu\Bundle\PreviewBundle\Preview\Object\PreviewObjectProviderRegistryInterface;
use Sulu\Bundle\PreviewBundle\Preview\PreviewContext;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class PreviewProviderTest extends SuluTestCase
{
    public function testPreviewDefaultsAndUpdatedValues(): void
    {
        $client = static::createAuthenticatedClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $client->jsonRequest('POST', '/admin/api/glossary-entries?locale=en', [
            'template' => 'default',
            'title' => 'UNICEF',
            'url' => '/glossary/unicef',
            'type' => 'acronym',
            'explanation' => 'Children fund',
        ]);
        $id = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        /** @var PreviewObjectProviderRegistryInterface $registry */
        $registry = static::getContainer()->get('sulu_preview.preview_object_provider_registry');
        $this->assertTrue($registry->hasPreviewObjectProvider(GlossaryEntry::RESOURCE_KEY));
        $provider = $registry->getPreviewObjectProvider(GlossaryEntry::RESOURCE_KEY);

        $context = new PreviewContext($id, 'en');
        $defaults = $provider->getDefaults($context);

        $this->assertInstanceOf(GlossaryEntryDimensionContent::class, $defaults['object']);
        $this->assertSame('UNICEF', $defaults['object']->getTitle());
        $this->assertSame('@SuluGlossary/glossary_entries/default', $defaults['view']);

        $updated = $provider->updateValues($context, $defaults, ['title' => 'UNICEF changed']);
        $this->assertSame('UNICEF changed', $updated['object']->getTitle());
    }
}
