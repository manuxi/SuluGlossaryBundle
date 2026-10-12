<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Link;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Link\GlossaryEntryLinkProvider;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Route\Domain\Model\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryEntryLinkProviderTest extends TestCase
{
    private GlossaryEntryRepositoryInterface&MockObject $repository;
    private ContentManagerInterface&MockObject $contentManager;
    private ReferenceStoreInterface&MockObject $referenceStore;
    private GlossaryEntryLinkProvider $provider;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->referenceStore = $this->createMock(ReferenceStoreInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->provider = new GlossaryEntryLinkProvider($this->contentManager, $this->repository, $this->referenceStore, $translator);
    }

    public function testConfigurationUsesTheResourceKey(): void
    {
        $configuration = $this->provider->getConfigurationBuilder()->getLinkConfiguration();

        $this->assertSame('glossary_entries', $configuration->getResourceKey());
        $this->assertSame(['title'], (new ReflectionProperty($configuration, 'displayProperties'))->getValue($configuration));
    }

    public function testPreloadReturnsLinkItemsAndRegistersReferences(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $dimensionContent = $entry->createDimensionContent();
        $dimensionContent->setTemplateData(['title' => 'WHO']);
        $route = $this->createMock(Route::class);
        $route->method('getSlug')->willReturn('/glossary/who');
        $dimensionContent->setRoute($route);

        $this->repository->expects($this->once())->method('findBy')->with(
            ['locale' => 'en', 'stage' => 'live', 'uuids' => ['uuid-1']],
            [],
            [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => true]
        )->willReturn([$entry]);
        $this->contentManager->method('resolve')->with($entry, ['locale' => 'en', 'stage' => 'live'])->willReturn($dimensionContent);
        $this->referenceStore->expects($this->once())->method('add')->with('uuid-1', 'glossary_entries');

        $items = [...$this->provider->preload(['uuid-1'], 'en')];

        $this->assertCount(1, $items);
        $this->assertSame('uuid-1', $items[0]->getId());
        $this->assertSame('WHO', $items[0]->getTitle());
        $this->assertSame('/glossary/who', $items[0]->getUrl());
        $this->assertTrue($items[0]->isPublished());
    }

    public function testPreloadUsesTheDraftStageForUnpublishedLinks(): void
    {
        $this->repository->expects($this->once())->method('findBy')->with(
            $this->callback(static fn (array $filters): bool => 'draft' === $filters['stage']),
            $this->anything(),
            $this->anything()
        )->willReturn([]);

        $this->assertSame([], [...$this->provider->preload(['uuid-1'], 'en', false)]);
    }

    public function testEntriesWithoutRouteAreSkipped(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('findBy')->willReturn([$entry]);
        $this->contentManager->method('resolve')->willReturn($entry->createDimensionContent());

        $this->assertSame([], [...$this->provider->preload(['uuid-1'], 'en')]);
    }
}
