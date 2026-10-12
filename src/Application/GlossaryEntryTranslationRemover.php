<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Application;

use Manuxi\SuluGlossaryBundle\Domain\Event\TranslationRemovedEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;

class GlossaryEntryTranslationRemover
{
    public function __construct(
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly ?TrashManagerInterface $trashManager = null,
    ) {
    }

    /**
     * Removes draft, live and version dimension contents of the given locale. Ghost dimension contents which
     * pointed to the locale fall back to the next available locale.
     */
    public function remove(GlossaryEntry $glossaryEntry, string $locale): void
    {
        $title = $this->findTitle($glossaryEntry, $locale);

        $this->trashManager?->store(GlossaryEntry::RESOURCE_KEY, $glossaryEntry, ['locale' => $locale]);

        foreach ($glossaryEntry->getDimensionContents()->toArray() as $dimensionContent) {
            if ($dimensionContent->getLocale() === $locale) {
                $glossaryEntry->removeDimensionContent($dimensionContent);
                $this->repository->removeDimensionContent($dimensionContent);

                continue;
            }

            if ($dimensionContent->getGhostLocale() === $locale) {
                $this->removeGhostLocale($glossaryEntry, $dimensionContent, $locale);

                continue;
            }

            if (null === $dimensionContent->getLocale()) {
                $dimensionContent->removeAvailableLocale($locale);
            }
        }

        $this->domainEventCollector->collect(new TranslationRemovedEvent($glossaryEntry->getUuid(), $title, $locale));
    }

    private function removeGhostLocale(GlossaryEntry $glossaryEntry, GlossaryEntryDimensionContent $dimensionContent, string $locale): void
    {
        $availableLocales = array_values(array_diff($dimensionContent->getAvailableLocales() ?? [], [$locale]));

        if ([] === $availableLocales) {
            $glossaryEntry->removeDimensionContent($dimensionContent);
            $this->repository->removeDimensionContent($dimensionContent);

            return;
        }

        $dimensionContent->setGhostLocale($availableLocales[0]);
        $dimensionContent->removeAvailableLocale($locale);
    }

    private function findTitle(GlossaryEntry $glossaryEntry, string $locale): ?string
    {
        foreach ($glossaryEntry->getDimensionContents() as $dimensionContent) {
            if ($dimensionContent->getLocale() === $locale && null !== $dimensionContent->getTitle()) {
                return $dimensionContent->getTitle();
            }
        }

        return null;
    }
}
