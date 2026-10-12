<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\EventSubscriber;

use Manuxi\SuluGlossaryBundle\Domain\Event\PublishedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\RemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\TranslationRemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\UnpublishedEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\EventSubscriber\GlossaryEntryCacheInvalidationSubscriber;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;

class GlossaryEntryCacheInvalidationSubscriberTest extends TestCase
{
    private CacheManagerInterface&MockObject $cacheManager;
    private GlossaryEntryRepositoryInterface&MockObject $repository;
    private ContentAggregatorInterface&MockObject $aggregator;
    private GlossaryEntryCacheInvalidationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cacheManager = $this->createMock(CacheManagerInterface::class);
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->aggregator = $this->createMock(ContentAggregatorInterface::class);
        $this->subscriber = new GlossaryEntryCacheInvalidationSubscriber($this->cacheManager, $this->repository, $this->aggregator);
    }

    public function testSubscribedEvents(): void
    {
        $this->assertSame(
            [PublishedEvent::class, UnpublishedEvent::class, RemovedEvent::class, TranslationRemovedEvent::class],
            array_keys(GlossaryEntryCacheInvalidationSubscriber::getSubscribedEvents())
        );
    }

    public function testPublishInvalidatesTheUuidTagAndTheTaxonomyReferences(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $dimensionContent = $entry->createDimensionContent();
        $tag = $this->createMock(TagInterface::class);
        $tag->method('getId')->willReturn(5);
        $dimensionContent->setExcerptTags([$tag]);
        $this->repository->method('findOneBy')->willReturn($entry);
        $this->aggregator->method('aggregate')->with($entry, ['locale' => 'en', 'stage' => 'live'])->willReturn($dimensionContent);

        $this->cacheManager->expects($this->once())->method('invalidateTag')->with('uuid-1');
        $references = [];
        $this->cacheManager->expects($this->exactly(2))->method('invalidateReference')->willReturnCallback(static function (string $resourceKey, string $id) use (&$references): void {
            $references[] = [$resourceKey, $id];
        });

        $this->subscriber->onPublishedOrUnpublished(new PublishedEvent('uuid-1', 'WHO', 'en'));

        $this->assertSame([['glossary_entries', 'list'], ['tags', '5']], $references);
    }

    public function testUnpublishWithoutLiveContentOnlyInvalidatesTheTag(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('findOneBy')->willReturn($entry);
        $this->aggregator->method('aggregate')->willThrowException(new ContentNotFoundException($entry, []));

        $this->cacheManager->expects($this->once())->method('invalidateTag')->with('uuid-1');
        $this->cacheManager->expects($this->once())->method('invalidateReference')->with('glossary_entries', 'list');

        $this->subscriber->onPublishedOrUnpublished(new UnpublishedEvent('uuid-1', 'WHO', 'en'));
    }

    public function testRemovalInvalidatesTheTag(): void
    {
        $this->cacheManager->expects($this->once())->method('invalidateTag')->with('uuid-1');
        $this->cacheManager->expects($this->once())->method('invalidateReference')->with('glossary_entries', 'list');

        $this->subscriber->onRemoved(new RemovedEvent('uuid-1', 'WHO', 'en'));
    }

    public function testDoesNothingWithoutCacheManager(): void
    {
        $subscriber = new GlossaryEntryCacheInvalidationSubscriber(null, $this->repository, $this->aggregator);
        $this->repository->expects($this->never())->method('findOneBy');

        $subscriber->onPublishedOrUnpublished(new PublishedEvent('uuid-1', 'WHO', 'en'));
        $subscriber->onRemoved(new RemovedEvent('uuid-1', 'WHO', 'en'));

        $this->addToAssertionCount(1);
    }
}
