<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Teaser;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Manuxi\SuluGlossaryBundle\Teaser\GlossaryEntryTeaserProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentEnhancer\ContentEnhancerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Route\Application\Routing\Generator\RouteGeneratorInterface;
use Sulu\Route\Domain\Exception\MissingRequestContextParameterException;
use Sulu\Route\Domain\Model\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryEntryTeaserProviderTest extends TestCase
{
    private GlossaryEntryRepositoryInterface&MockObject $repository;
    private ContentAggregatorInterface&MockObject $aggregator;
    private ContentEnhancerInterface&MockObject $enhancer;
    private RouteGeneratorInterface&MockObject $routeGenerator;
    private GlossaryEntryTeaserProvider $provider;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->aggregator = $this->createMock(ContentAggregatorInterface::class);
        $this->enhancer = $this->createMock(ContentEnhancerInterface::class);
        $this->routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->provider = new GlossaryEntryTeaserProvider($this->repository, $this->aggregator, $this->enhancer, $this->routeGenerator, $translator);
    }

    private function dimensionContent(GlossaryEntry $entry, ?string $title = 'WHO'): GlossaryEntryDimensionContent
    {
        $dimensionContent = $entry->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setTemplateData(['title' => $title, 'explanation' => '<b>World</b> Health Organization']);
        $dimensionContent->setType('abbreviation');
        $route = $this->createMock(Route::class);
        $route->method('getSlug')->willReturn('/glossary/who');
        $route->method('getLocale')->willReturn('en');
        $dimensionContent->setRoute($route);

        return $dimensionContent;
    }

    public function testConfiguration(): void
    {
        $configuration = $this->provider->getConfiguration();

        $this->assertSame('glossary_entries', (new ReflectionProperty($configuration, 'resourceKey'))->getValue($configuration));
        $this->assertSame(['title'], (new ReflectionProperty($configuration, 'displayProperties'))->getValue($configuration));
    }

    public function testFindReturnsNothingForNoIds(): void
    {
        $this->repository->expects($this->never())->method('findBy');

        $this->assertSame([], $this->provider->find([], 'en'));
    }

    public function testFindBuildsTeasersInTheRequestedOrder(): void
    {
        $first = new GlossaryEntry('uuid-1');
        $second = new GlossaryEntry('uuid-2');
        $this->repository->method('findBy')->willReturn([$first, $second]);
        $this->aggregator->method('aggregate')->willReturnCallback(fn (GlossaryEntry $entry) => $this->dimensionContent($entry, 'uuid-1' === $entry->getUuid() ? 'First' : 'Second'));
        $this->enhancer->method('enhance')->willReturnArgument(0);
        $this->routeGenerator->method('generate')->with('/glossary/who', 'en')->willReturn('/en/glossary/who');

        $teasers = $this->provider->find(['uuid-2', 'uuid-1'], 'en');

        $this->assertCount(2, $teasers);
        $this->assertSame('uuid-2', $teasers[0]->getId());
        $this->assertSame('Second', $teasers[0]->getTitle());
        $this->assertSame('uuid-1', $teasers[1]->getId());
        $this->assertSame('World Health Organization', $teasers[1]->getDescription());
        $this->assertSame('/en/glossary/who', $teasers[1]->getUrl());
        $this->assertSame(['type' => 'abbreviation'], $teasers[1]->getAttributes());
    }

    public function testFallsBackToTheSlugWithoutRequestContext(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('findBy')->willReturn([$entry]);
        $this->aggregator->method('aggregate')->willReturn($this->dimensionContent($entry));
        $this->enhancer->method('enhance')->willReturnArgument(0);
        $this->routeGenerator->method('generate')->willThrowException(new MissingRequestContextParameterException('webspace'));

        $teasers = $this->provider->find(['uuid-1'], 'en');

        $this->assertSame('/glossary/who', $teasers[0]->getUrl());
    }

    public function testEntriesWithoutLiveContentAreSkipped(): void
    {
        $entry = new GlossaryEntry('uuid-1');
        $this->repository->method('findBy')->willReturn([$entry]);
        $this->aggregator->method('aggregate')->willThrowException(new ContentNotFoundException($entry, []));

        $this->assertSame([], $this->provider->find(['uuid-1'], 'en'));
    }

    public function testEntriesWithoutRouteOrTitleAreSkipped(): void
    {
        $withoutRoute = new GlossaryEntry('uuid-1');
        $withoutTitle = new GlossaryEntry('uuid-2');
        $this->repository->method('findBy')->willReturn([$withoutRoute, $withoutTitle]);
        $this->aggregator->method('aggregate')->willReturnCallback(function (GlossaryEntry $entry) {
            if ('uuid-1' === $entry->getUuid()) {
                $dimensionContent = $entry->createDimensionContent();
                $dimensionContent->setTemplateData(['title' => 'No route']);

                return $dimensionContent;
            }

            return $this->dimensionContent($entry, '');
        });
        $this->enhancer->method('enhance')->willReturnArgument(0);

        $this->assertSame([], $this->provider->find(['uuid-1', 'uuid-2'], 'en'));
    }
}
