<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Versioning;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Keeps only the configured number of versions per glossary entry and locale. The versions are created
 * by Sulu when an entry is published, older ones are removed afterwards.
 */
class VersionPruner
{
    public function __construct(
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly int $maxVersions,
    ) {
    }

    public function onPublish(CompletedEvent $event): void
    {
        $subject = $event->getSubject();
        $locale = $subject instanceof GlossaryEntryDimensionContent ? $subject->getLocale() : null;

        if ($this->maxVersions <= 0 || null === $locale) {
            return;
        }

        /** @var GlossaryEntryDimensionContent $subject */
        $glossaryEntry = $subject->getResource();

        /** @var array<int, GlossaryEntryDimensionContent> $versions */
        $versions = [];
        foreach ($this->repository->findVersions($glossaryEntry, $locale) as $version) {
            $versions[spl_object_id($version)] = $version;
        }

        // The version created by the publish transition is not flushed yet and only known by the entry itself.
        foreach ($glossaryEntry->getDimensionContents() as $dimensionContent) {
            if ($dimensionContent->getLocale() === $locale
                && DimensionContentInterface::STAGE_DRAFT === $dimensionContent->getStage()
                && DimensionContentInterface::CURRENT_VERSION !== $dimensionContent->getVersion()
            ) {
                $versions[spl_object_id($dimensionContent)] = $dimensionContent;
            }
        }

        usort(
            $versions,
            static fn (GlossaryEntryDimensionContent $a, GlossaryEntryDimensionContent $b): int => $b->getVersion() <=> $a->getVersion()
        );

        foreach (\array_slice($versions, $this->maxVersions) as $obsoleteVersion) {
            $glossaryEntry->removeDimensionContent($obsoleteVersion);
            $this->repository->removeDimensionContent($obsoleteVersion);
        }
    }
}
