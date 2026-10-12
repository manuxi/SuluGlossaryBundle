<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Trash;

use Doctrine\Common\Collections\ArrayCollection;
use Manuxi\SuluGlossaryBundle\Admin\GlossaryEntryAdmin;
use Manuxi\SuluGlossaryBundle\Domain\Event\RestoredEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\TranslationRestoredEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfiguration;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfigurationProviderInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\RestoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\StoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Application\ContentNormalizer\ContentNormalizerInterface;
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Webmozart\Assert\Assert;

class GlossaryEntryTrashItemHandler implements StoreTrashItemHandlerInterface, RestoreTrashItemHandlerInterface, RestoreConfigurationProviderInterface
{
    public function __construct(
        private readonly TrashItemRepositoryInterface $trashItemRepository,
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly ContentNormalizerInterface $contentNormalizer,
        private readonly ContentMergerInterface $contentMerger,
        private readonly ContentManagerInterface $contentManager,
        private readonly DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public static function getResourceKey(): string
    {
        return GlossaryEntry::RESOURCE_KEY;
    }

    public function store(object $resource, array $options = []): TrashItemInterface
    {
        Assert::isInstanceOf($resource, GlossaryEntry::class);

        $restoreType = ($options['locale'] ?? null) ? 'translation' : null;

        $unlocalizedDimensionContent = null;
        /** @var array<string, GlossaryEntryDimensionContent> $localizedDimensionContents */
        $localizedDimensionContents = [];

        foreach ($resource->getDimensionContents() as $dimensionContent) {
            if (DimensionContentInterface::CURRENT_VERSION !== $dimensionContent->getVersion()
                || DimensionContentInterface::STAGE_DRAFT !== $dimensionContent->getStage()
            ) {
                continue;
            }

            if (null === $dimensionContent->getLocale()) {
                $unlocalizedDimensionContent = $dimensionContent;

                continue;
            }

            if ('translation' === $restoreType && $dimensionContent->getLocale() !== $options['locale']) {
                continue;
            }

            $localizedDimensionContents[$dimensionContent->getLocale()] = $dimensionContent;
        }

        Assert::notNull($unlocalizedDimensionContent, 'Expected to find an unlocalized dimension content for the glossary entry.');
        Assert::notEmpty($localizedDimensionContents, 'Expected to find at least one localized dimension content for the glossary entry.');

        $data = ['dimensionContents' => []];
        $titles = [];

        foreach ($localizedDimensionContents as $locale => $localizedDimensionContent) {
            $merged = $this->contentMerger->merge(
                new DimensionContentCollection(
                    new ArrayCollection([$unlocalizedDimensionContent, $localizedDimensionContent]),
                    [
                        'locale' => $locale,
                        'stage' => DimensionContentInterface::STAGE_DRAFT,
                        'version' => DimensionContentInterface::CURRENT_VERSION,
                    ],
                    GlossaryEntryDimensionContent::class
                )
            );

            $data['dimensionContents'][] = $this->contentNormalizer->normalize($merged);

            if ($localizedDimensionContent->getTitle()) {
                $titles[$locale] = $localizedDimensionContent->getTitle();
            }
        }

        return $this->trashItemRepository->create(
            GlossaryEntry::RESOURCE_KEY,
            $resource->getUuid(),
            $titles,
            $data,
            $restoreType,
            $options,
            GlossaryEntry::SECURITY_CONTEXT,
            null,
            $resource->getUuid(),
        );
    }

    /**
     * @param array{} $restoreFormData
     */
    public function restore(TrashItemInterface $trashItem, array $restoreFormData = []): object
    {
        $restoreData = $trashItem->getRestoreData();
        $uuid = $trashItem->getResourceId();

        $glossaryEntry = $this->repository->findOneBy(['uuid' => $uuid]);
        if (!$glossaryEntry instanceof GlossaryEntry) {
            $glossaryEntry = $this->repository->createNew($uuid);
            $this->repository->add($glossaryEntry);
        }

        $dimensionContents = $restoreData['dimensionContents'] ?? [];
        Assert::isArray($dimensionContents);

        $title = null;
        $locales = [];

        /** @var array<string, mixed> $dimensionContentData */
        foreach ($dimensionContents as $dimensionContentData) {
            Assert::isArray($dimensionContentData);

            $locale = $dimensionContentData['locale'] ?? null;
            Assert::string($locale);
            $locales[] = $locale;

            if (null === $title && \is_string($dimensionContentData['title'] ?? null)) {
                $title = $dimensionContentData['title'];
            }

            $this->contentManager->persist($glossaryEntry, $dimensionContentData, ['locale' => $locale]);
        }

        if ('translation' === $trashItem->getRestoreType()) {
            foreach ($locales as $locale) {
                $this->domainEventCollector->collect(new TranslationRestoredEvent($uuid, $title, $locale, $restoreData));
            }

            return $glossaryEntry;
        }

        $this->domainEventCollector->collect(new RestoredEvent($uuid, $title, null, ['locales' => $locales] + $restoreData));

        return $glossaryEntry;
    }

    public function getConfiguration(): RestoreConfiguration
    {
        return new RestoreConfiguration(
            null,
            GlossaryEntryAdmin::EDIT_TABS_VIEW,
            ['id' => 'id'],
            null,
        );
    }
}
