<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Domain\Event;

use Manuxi\SuluGlossaryBundle\Domain\Event\AbstractEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\CopiedLanguageEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\CreatedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\DraftRemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\ModifiedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\PublishedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\RemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\RestoredEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\TranslationRemovedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\TranslationRestoredEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\UnpublishedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\VersionRestoredEvent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GlossaryEntryEventsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<AbstractEvent>, string}>
     */
    public static function eventProvider(): iterable
    {
        yield 'created' => [CreatedEvent::class, 'created'];
        yield 'modified' => [ModifiedEvent::class, 'modified'];
        yield 'published' => [PublishedEvent::class, 'published'];
        yield 'unpublished' => [UnpublishedEvent::class, 'unpublished'];
        yield 'removed' => [RemovedEvent::class, 'removed'];
        yield 'restored' => [RestoredEvent::class, 'restored'];
        yield 'draft removed' => [DraftRemovedEvent::class, 'draft_removed'];
        yield 'translation removed' => [TranslationRemovedEvent::class, 'translation_removed'];
        yield 'translation restored' => [TranslationRestoredEvent::class, 'translation_restored'];
        yield 'copied language' => [CopiedLanguageEvent::class, 'copied_language'];
        yield 'version restored' => [VersionRestoredEvent::class, 'version_restored'];
    }

    /**
     * @param class-string<AbstractEvent> $class
     */
    #[DataProvider('eventProvider')]
    public function testEventExposesResourceInformation(string $class, string $type): void
    {
        $event = new $class('uuid-1', 'WHO', 'en', ['a' => 1]);

        $this->assertSame($type, $event->getEventType());
        $this->assertSame(GlossaryEntry::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('uuid-1', $event->getResourceId());
        $this->assertSame('uuid-1', $event->getGlossaryEntryId());
        $this->assertSame('WHO', $event->getResourceTitle());
        $this->assertSame('en', $event->getResourceLocale());
        $this->assertSame('en', $event->getResourceTitleLocale());
        $this->assertSame('en', $event->getLocale());
        $this->assertSame(['a' => 1], $event->getEventPayload());
        $this->assertSame(GlossaryEntry::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }
}
