<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\EventSubscriber;

use Manuxi\SuluGlossaryBundle\Domain\Event\AbstractEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\PublishedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\RemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\TranslationRemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\UnpublishedEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GlossaryEntryCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public const LIST_REFERENCE_ID = 'list';

    public function __construct(
        private readonly ?CacheManagerInterface $cacheManager,
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly ContentAggregatorInterface $contentAggregator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PublishedEvent::class => 'onPublishedOrUnpublished',
            UnpublishedEvent::class => 'onPublishedOrUnpublished',
            RemovedEvent::class => 'onRemoved',
            TranslationRemovedEvent::class => 'onRemoved',
        ];
    }

    public function onPublishedOrUnpublished(PublishedEvent|UnpublishedEvent $event): void
    {
        if (null === $this->cacheManager) {
            return;
        }

        $this->cacheManager->invalidateTag($event->getResourceId());
        $this->cacheManager->invalidateReference(GlossaryEntry::RESOURCE_KEY, self::LIST_REFERENCE_ID);
        $this->invalidateTaxonomies($event);
    }

    public function onRemoved(AbstractEvent $event): void
    {
        $this->cacheManager?->invalidateTag($event->getResourceId());
        $this->cacheManager?->invalidateReference(GlossaryEntry::RESOURCE_KEY, self::LIST_REFERENCE_ID);
    }

    private function invalidateTaxonomies(AbstractEvent $event): void
    {
        $locale = $event->getLocale();
        if (null === $this->cacheManager || null === $locale) {
            return;
        }

        $glossaryEntry = $this->repository->findOneBy(['uuid' => $event->getResourceId()]);
        if (null === $glossaryEntry) {
            return;
        }

        try {
            $dimensionContent = $this->contentAggregator->aggregate($glossaryEntry, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ]);
        } catch (ContentNotFoundException) {
            return;
        }

        foreach ($dimensionContent->getExcerptTags() as $tag) {
            $this->cacheManager->invalidateReference(TagInterface::RESOURCE_KEY, (string) $tag->getId());
        }

        foreach ($dimensionContent->getExcerptCategoryIds() as $categoryId) {
            $this->cacheManager->invalidateReference(CategoryInterface::RESOURCE_KEY, (string) $categoryId);
        }
    }
}
