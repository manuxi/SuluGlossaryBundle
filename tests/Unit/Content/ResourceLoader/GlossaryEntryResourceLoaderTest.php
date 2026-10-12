<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Content\ResourceLoader;

use Manuxi\SuluGlossaryBundle\Content\ResourceLoader\GlossaryEntryResourceLoader;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

class GlossaryEntryResourceLoaderTest extends TestCase
{
    private GlossaryEntryRepositoryInterface&MockObject $repository;
    private GlossaryEntryResourceLoader $loader;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->loader = new GlossaryEntryResourceLoader($this->repository);
    }

    public function testKey(): void
    {
        $this->assertSame('glossary_entry', GlossaryEntryResourceLoader::getKey());
    }

    public function testLoadsLiveEntriesIndexedByUuid(): void
    {
        $first = new GlossaryEntry('uuid-1');
        $second = new GlossaryEntry('uuid-2');
        $this->repository->expects($this->once())->method('findBy')->with(
            ['uuids' => ['uuid-1', 'uuid-2'], 'locale' => 'en', 'stage' => 'live'],
            [],
            [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => true]
        )->willReturn([$first, $second]);

        $this->assertSame(['uuid-1' => $first, 'uuid-2' => $second], $this->loader->load(['uuid-1', 'uuid-2'], 'en'));
    }

    public function testReturnsNothingWithoutLocaleOrIds(): void
    {
        $this->repository->expects($this->never())->method('findBy');

        $this->assertSame([], $this->loader->load(['uuid-1'], null));
        $this->assertSame([], $this->loader->load([], 'en'));
    }

    public function testContentViewEnhancementExposesUuidTemplateAndType(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $dimensionContent = $entry->createDimensionContent();
        $dimensionContent->setTemplateKey('default');
        $dimensionContent->setType('acronym');

        $view = $this->loader->resolveContentViewEnhancement($dimensionContent);

        $this->assertSame(['uuid' => 'uuid-1', 'template' => 'default', 'type' => 'acronym'], $view->getView());
    }

    public function testContentViewEnhancementIgnoresOtherResources(): void
    {
        $view = $this->loader->resolveContentViewEnhancement(new stdClass());

        $this->assertSame([], $view->getView());
    }
}
