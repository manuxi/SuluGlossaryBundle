<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Versioning;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Manuxi\SuluGlossaryBundle\Versioning\VersionPruner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class VersionPrunerTest extends TestCase
{
    private GlossaryEntryRepositoryInterface&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->repository->method('findVersions')->willReturn([]);
    }

    private function version(GlossaryEntry $entry, string $locale, string $stage, int $version): GlossaryEntryDimensionContent
    {
        $dimensionContent = $entry->createDimensionContent();
        $dimensionContent->setLocale($locale);
        $dimensionContent->setStage($stage);
        $dimensionContent->setVersion($version);
        $entry->addDimensionContent($dimensionContent);

        return $dimensionContent;
    }

    private function event(GlossaryEntryDimensionContent $subject): CompletedEvent
    {
        return new CompletedEvent($subject, new Marking(), new Transition('publish', 'draft', 'published'));
    }

    public function testRemovesVersionsBeyondTheLimitOldestFirst(): void
    {
        $entry = new GlossaryEntry();
        $draft = $this->version($entry, 'en', 'draft', 0);
        $this->version($entry, 'en', 'live', 0);
        $newest = $this->version($entry, 'en', 'draft', 300);
        $middle = $this->version($entry, 'en', 'draft', 200);
        $oldest = $this->version($entry, 'en', 'draft', 100);
        $otherLocale = $this->version($entry, 'de', 'draft', 50);

        $removed = [];
        $this->repository->method('removeDimensionContent')->willReturnCallback(static function (object $object) use (&$removed): void {
            $removed[] = $object;
        });

        (new VersionPruner($this->repository, 2))->onPublish($this->event($draft));

        $this->assertSame([$oldest], $removed);
        $this->assertContains($newest, $entry->getDimensionContents());
        $this->assertContains($middle, $entry->getDimensionContents());
        $this->assertContains($otherLocale, $entry->getDimensionContents());
        $this->assertNotContains($oldest, $entry->getDimensionContents());
    }

    public function testAlsoPrunesStoredVersionsWhichAreNotLoadedOnTheEntry(): void
    {
        $entry = new GlossaryEntry();
        $draft = $this->version($entry, 'en', 'draft', 0);
        $newest = $this->version($entry, 'en', 'draft', 300);
        $stored = (new GlossaryEntry())->createDimensionContent();
        $stored->setLocale('en');
        $stored->setStage('draft');
        $stored->setVersion(200);
        $obsolete = (new GlossaryEntry())->createDimensionContent();
        $obsolete->setLocale('en');
        $obsolete->setStage('draft');
        $obsolete->setVersion(100);

        $repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $repository->method('findVersions')->with($entry, 'en')->willReturn([$stored, $obsolete]);
        $repository->expects($this->once())->method('removeDimensionContent')->with($obsolete);

        (new VersionPruner($repository, 2))->onPublish($this->event($draft));

        $this->assertContains($newest, $entry->getDimensionContents());
    }

    public function testKeepsEverythingWhenNothingExceedsTheLimit(): void
    {
        $entry = new GlossaryEntry();
        $draft = $this->version($entry, 'en', 'draft', 0);
        $this->version($entry, 'en', 'draft', 100);

        $this->repository->expects($this->never())->method('removeDimensionContent');

        (new VersionPruner($this->repository, 5))->onPublish($this->event($draft));
    }

    public function testZeroKeepsAllVersions(): void
    {
        $entry = new GlossaryEntry();
        $draft = $this->version($entry, 'en', 'draft', 0);
        $this->version($entry, 'en', 'draft', 100);
        $this->version($entry, 'en', 'draft', 200);

        $this->repository->expects($this->never())->method('removeDimensionContent');

        (new VersionPruner($this->repository, 0))->onPublish($this->event($draft));
    }

    public function testIgnoresForeignSubjects(): void
    {
        $this->repository->expects($this->never())->method('removeDimensionContent');

        (new VersionPruner($this->repository, 1))->onPublish(new CompletedEvent(new stdClass(), new Marking(), new Transition('publish', 'draft', 'published')));
    }
}
