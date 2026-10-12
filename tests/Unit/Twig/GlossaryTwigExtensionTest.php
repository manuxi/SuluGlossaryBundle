<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Twig;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Manuxi\SuluGlossaryBundle\Twig\GlossaryTwigExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class GlossaryTwigExtensionTest extends TestCase
{
    private GlossaryEntryRepositoryInterface&MockObject $repository;
    private ContentAggregatorInterface&MockObject $aggregator;
    private ContentManagerInterface&MockObject $contentManager;
    private ReferenceStoreInterface&MockObject $referenceStore;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->aggregator = $this->createMock(ContentAggregatorInterface::class);
        $this->contentManager = $this->createMock(ContentManagerInterface::class);
        $this->referenceStore = $this->createMock(ReferenceStoreInterface::class);
        $this->requestStack = new RequestStack();
        $request = new Request();
        $request->setLocale('de');
        $this->requestStack->push($request);
    }

    private function createExtension(bool $autoAbbr = true): GlossaryTwigExtension
    {
        return new GlossaryTwigExtension($this->repository, $this->aggregator, $this->contentManager, $this->requestStack, $autoAbbr, $this->referenceStore);
    }

    /**
     * @param array<string, array<string, mixed>> $contents normalized content by uuid
     */
    private function givenEntries(array $contents): void
    {
        $entries = array_map(static fn (string $uuid): GlossaryEntry => new GlossaryEntry($uuid), array_keys($contents));
        $this->repository->method('findBy')->willReturn($entries);
        $this->aggregator->method('aggregate')->willReturnCallback(static fn (GlossaryEntry $entry) => $entry->createDimensionContent());
        $this->contentManager->method('normalize')->willReturnCallback(
            static function ($dimensionContent) use ($contents): array {
                return $contents[$dimensionContent->getResource()->getUuid()];
            }
        );
    }

    public function testFunctionsAreRegisteredAndTheFilterOnlyWhenEnabled(): void
    {
        $this->assertSame(['sulu_resolve_glossary_entry', 'sulu_get_glossary_entries'], array_map(static fn ($function) => $function->getName(), $this->createExtension()->getFunctions()));
        $this->assertSame(['abbr'], array_map(static fn ($filter) => $filter->getName(), $this->createExtension(true)->getFilters()));
        $this->assertSame([], $this->createExtension(false)->getFilters());
    }

    public function testResolveReturnsNullForUnpublishedEntries(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);

        $this->assertNull($this->createExtension()->resolveGlossaryEntry('uuid-1'));
    }

    public function testResolveUsesTheRequestLocaleAndRegistersTheReference(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->expects($this->once())->method('findOneBy')->with(['uuid' => 'uuid-1', 'locale' => 'de', 'stage' => 'live'])->willReturn($entry);
        $this->aggregator->method('aggregate')->willReturn($entry->createDimensionContent());
        $this->contentManager->method('normalize')->willReturn(['title' => 'WHO']);
        $this->referenceStore->expects($this->once())->method('add')->with('uuid-1', 'glossary_entries');

        $this->assertSame(['title' => 'WHO'], $this->createExtension()->resolveGlossaryEntry('uuid-1'));
    }

    public function testResolveReturnsNullWithoutLiveContent(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('findOneBy')->willReturn($entry);
        $this->aggregator->method('aggregate')->willThrowException(new ContentNotFoundException($entry, []));

        $this->assertNull($this->createExtension()->resolveGlossaryEntry('uuid-1', 'en'));
    }

    public function testListPassesLimitLocaleAndTypesAndRegistersTheListReference(): void
    {
        $this->repository->expects($this->once())->method('findBy')->with(
            ['locale' => 'en', 'stage' => 'live', 'limit' => 5, 'types' => ['term']],
            ['title' => 'asc'],
            [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => true]
        )->willReturn([]);
        $references = [];
        $this->referenceStore->method('add')->willReturnCallback(static function (string $id, string $resourceKey) use (&$references): void {
            $references[] = [$id, $resourceKey];
        });

        $this->assertSame([], $this->createExtension()->getGlossaryEntries(5, 'en', ['term']));
        $this->assertSame([['list', 'glossary_entries']], $references);
    }

    public function testListReturnsNormalizedEntries(): void
    {
        $this->givenEntries(['a' => ['title' => 'A'], 'b' => ['title' => 'B']]);

        $this->assertSame([['title' => 'A'], ['title' => 'B']], $this->createExtension()->getGlossaryEntries());
    }

    public function testFilterWrapsKnownAbbreviations(): void
    {
        $this->givenEntries([
            'a' => ['title' => 'WHO', 'explanation' => 'World Health Organization'],
            'b' => ['title' => 'WH', 'explanation' => 'Short'],
        ]);

        $result = $this->createExtension()->wrapAbbreviations('<p>The WHO and WH say "hi", not WHOM.</p>');

        $this->assertSame(
            '<p>The <abbr title="World Health Organization">WHO</abbr> and <abbr title="Short">WH</abbr> say "hi", not WHOM.</p>',
            $result
        );
    }

    public function testFilterSkipsProtectedTagsAndEscapesTheTitle(): void
    {
        $this->givenEntries(['a' => ['title' => 'AT', 'explanation' => 'A "quoted" <b>']]);

        $result = $this->createExtension()->wrapAbbreviations('<a href="#">AT</a> <code>AT</code> <abbr>AT</abbr> AT');

        $this->assertSame(
            '<a href="#">AT</a> <code>AT</code> <abbr>AT</abbr> <abbr title="A &quot;quoted&quot; &lt;b&gt;">AT</abbr>',
            $result
        );
    }

    public function testFilterReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', $this->createExtension()->wrapAbbreviations(null));
        $this->assertSame('', $this->createExtension()->wrapAbbreviations(''));
    }

    public function testFilterKeepsTheTextWithoutKnownEntries(): void
    {
        $this->repository->method('findBy')->willReturn([]);

        $this->assertSame('<p>Nothing</p>', $this->createExtension()->wrapAbbreviations('<p>Nothing</p>'));
    }

    public function testFilterLoadsTheEntriesOnlyOncePerLocaleAndTypes(): void
    {
        $this->repository->expects($this->once())->method('findBy')->willReturn([]);
        $extension = $this->createExtension();

        $extension->wrapAbbreviations('a');
        $extension->wrapAbbreviations('b');
    }

    public function testFilterPrefersTheLongestMatch(): void
    {
        $this->givenEntries([
            'a' => ['title' => 'EU', 'explanation' => 'Union'],
            'b' => ['title' => 'EU-Kommission', 'explanation' => 'Commission'],
        ]);

        $this->assertSame('<abbr title="Commission">EU-Kommission</abbr>', $this->createExtension()->wrapAbbreviations('EU-Kommission'));
    }
}
