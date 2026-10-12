<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Application;

use Manuxi\SuluGlossaryBundle\Application\GlossaryEntryTranslationRemover;
use Manuxi\SuluGlossaryBundle\Domain\Event\TranslationRemovedEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

class GlossaryEntryTranslationRemoverTest extends TestCase
{
    private GlossaryEntryRepositoryInterface&MockObject $repository;
    private DomainEventCollectorInterface&MockObject $collector;
    private TrashManagerInterface&MockObject $trashManager;
    private GlossaryEntryTranslationRemover $remover;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->collector = $this->createMock(DomainEventCollectorInterface::class);
        $this->trashManager = $this->createMock(TrashManagerInterface::class);
        $this->remover = new GlossaryEntryTranslationRemover($this->repository, $this->collector, $this->trashManager);
    }

    public function testRemovesAllDimensionContentsOfTheLocaleAndKeepsOthers(): void
    {
        $entry = new GlossaryEntry();
        $unlocalized = $this->dimensionContent($entry, null, DimensionContentInterface::STAGE_DRAFT, ['en', 'de']);
        $english = $this->dimensionContent($entry, 'en', DimensionContentInterface::STAGE_DRAFT, null, 'WHO');
        $englishLive = $this->dimensionContent($entry, 'en', DimensionContentInterface::STAGE_LIVE);
        $german = $this->dimensionContent($entry, 'de', DimensionContentInterface::STAGE_DRAFT);

        $removed = [];
        $this->repository->method('removeDimensionContent')->willReturnCallback(static function ($dimensionContent) use (&$removed): void {
            $removed[] = $dimensionContent;
        });

        $this->trashManager->expects($this->once())->method('store')->with(GlossaryEntry::RESOURCE_KEY, $entry, ['locale' => 'en']);
        $this->collector->expects($this->once())->method('collect')->with($this->callback(
            static fn (TranslationRemovedEvent $event): bool => 'WHO' === $event->getResourceTitle() && 'en' === $event->getLocale()
        ));

        $this->remover->remove($entry, 'en');

        $this->assertSame([$english, $englishLive], $removed);
        $this->assertSame(['de'], array_values($unlocalized->getAvailableLocales() ?? []));
        $this->assertContains($german, $entry->getDimensionContents());
        $this->assertCount(2, $entry->getDimensionContents());
    }

    public function testGhostDimensionContentFallsBackToNextAvailableLocale(): void
    {
        $entry = new GlossaryEntry();
        $ghost = $this->dimensionContent($entry, 'fr', DimensionContentInterface::STAGE_DRAFT);
        $ghost->setGhostLocale('en');
        $ghost->addAvailableLocale('en');
        $ghost->addAvailableLocale('de');

        $this->repository->expects($this->never())->method('removeDimensionContent');

        $this->remover->remove($entry, 'en');

        $this->assertSame('de', $ghost->getGhostLocale());
        $this->assertSame(['de'], array_values($ghost->getAvailableLocales() ?? []));
    }

    public function testGhostDimensionContentIsRemovedWhenNoLocaleRemains(): void
    {
        $entry = new GlossaryEntry();
        $ghost = $this->dimensionContent($entry, 'fr', DimensionContentInterface::STAGE_DRAFT);
        $ghost->setGhostLocale('en');
        $ghost->addAvailableLocale('en');

        $this->repository->expects($this->once())->method('removeDimensionContent')->with($ghost);

        $this->remover->remove($entry, 'en');

        $this->assertCount(0, $entry->getDimensionContents());
    }

    public function testWorksWithoutTrashManager(): void
    {
        $entry = new GlossaryEntry();
        $this->dimensionContent($entry, 'en', DimensionContentInterface::STAGE_DRAFT);

        $remover = new GlossaryEntryTranslationRemover($this->repository, $this->collector);
        $remover->remove($entry, 'en');

        $this->assertCount(0, $entry->getDimensionContents());
    }

    /**
     * @param string[]|null $availableLocales
     */
    private function dimensionContent(GlossaryEntry $entry, ?string $locale, string $stage, ?array $availableLocales = null, ?string $title = null): GlossaryEntryDimensionContent
    {
        $dimensionContent = $entry->createDimensionContent();
        $dimensionContent->setLocale($locale);
        $dimensionContent->setStage($stage);
        foreach ($availableLocales ?? [] as $availableLocale) {
            $dimensionContent->addAvailableLocale($availableLocale);
        }
        if (null !== $title) {
            $dimensionContent->setTemplateData(['title' => $title]);
        }
        $entry->addDimensionContent($dimensionContent);

        return $dimensionContent;
    }
}
