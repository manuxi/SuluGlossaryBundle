<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Search;

use ArrayObject;
use CmsIg\Seal\Reindex\ReindexConfig;
use Manuxi\SuluGlossaryBundle\Domain\Event\ModifiedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\PublishedEvent;
use Manuxi\SuluGlossaryBundle\Domain\Event\RemovedEvent;
use Manuxi\SuluGlossaryBundle\Search\GlossaryEntryIndexListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class GlossaryEntryIndexListenerTest extends TestCase
{
    /**
     * @return array{GlossaryEntryIndexListener, ArrayObject<int, ReindexConfig>}
     */
    private function createListener(): array
    {
        /** @var ArrayObject<int, ReindexConfig> $dispatched */
        $dispatched = new ArrayObject();
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use ($dispatched): Envelope {
            if ($message instanceof ReindexConfig) {
                $dispatched[] = $message;
            }

            return new Envelope($message);
        });

        return [new GlossaryEntryIndexListener($bus), $dispatched];
    }

    public function testDraftChangeReindexesTheAdminIndexOfTheLocale(): void
    {
        [$listener, $dispatched] = $this->createListener();

        $listener->onDraftChanged(new ModifiedEvent('uuid-1', 'WHO', 'en'));

        $this->assertCount(1, $dispatched);
        $this->assertSame('admin', $dispatched[0]->getIndex());
        $this->assertSame(['glossary_entries__uuid-1__en'], $dispatched[0]->getIdentifiers());
    }

    public function testLiveChangeReindexesTheWebsiteIndex(): void
    {
        [$listener, $dispatched] = $this->createListener();

        $listener->onLiveChanged(new PublishedEvent('uuid-1', 'WHO', 'de'));

        $this->assertSame('website', $dispatched[0]->getIndex());
        $this->assertSame(['glossary_entries__uuid-1__de'], $dispatched[0]->getIdentifiers());
    }

    public function testRemovedEntriesReindexAllFormerLocales(): void
    {
        [$listener, $dispatched] = $this->createListener();

        $listener->onDraftChanged(new RemovedEvent('uuid-1', 'WHO', 'en', [], ['locales' => ['en', 'de']]));

        $this->assertSame(['glossary_entries__uuid-1__en', 'glossary_entries__uuid-1__de'], $dispatched[0]->getIdentifiers());
    }

    public function testNothingIsDispatchedWithoutLocale(): void
    {
        [$listener, $dispatched] = $this->createListener();

        $listener->onDraftChanged(new ModifiedEvent('uuid-1', 'WHO'));

        $this->assertCount(0, $dispatched);
    }
}
