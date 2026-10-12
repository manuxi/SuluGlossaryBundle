<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Routing;

use InvalidArgumentException;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Manuxi\SuluGlossaryBundle\Routing\GlossaryEntryRouteDefaultsProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\CacheLifetimeMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TemplateMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeRequestStore;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeResolverInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Route\Domain\Model\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GlossaryEntryRouteDefaultsProviderTest extends TestCase
{
    private GlossaryEntryRepositoryInterface&MockObject $repository;
    private ContentAggregatorInterface&MockObject $aggregator;
    private MetadataProviderInterface&MockObject $formMetadataProvider;
    private CacheLifetimeResolverInterface&MockObject $cacheLifetimeResolver;
    private GlossaryEntryRouteDefaultsProvider $provider;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->aggregator = $this->createMock(ContentAggregatorInterface::class);
        $this->formMetadataProvider = $this->createMock(MetadataProviderInterface::class);
        $this->cacheLifetimeResolver = $this->createMock(CacheLifetimeResolverInterface::class);

        $registry = $this->createMock(MetadataProviderRegistry::class);
        $registry->method('getMetadataProvider')->with('form')->willReturn($this->formMetadataProvider);

        $this->provider = new GlossaryEntryRouteDefaultsProvider($this->repository, $this->aggregator, $registry, $this->cacheLifetimeResolver);
    }

    private function route(): Route
    {
        $route = $this->createMock(Route::class);
        $route->method('getResourceId')->willReturn('uuid-1');
        $route->method('getLocale')->willReturn('en');

        return $route;
    }

    private function dimensionContent(?string $templateKey = 'default', string $locale = 'en'): GlossaryEntryDimensionContent
    {
        $dimensionContent = new GlossaryEntryDimensionContent(new GlossaryEntry('uuid-1'));
        $dimensionContent->setLocale($locale);
        if (null !== $templateKey) {
            $dimensionContent->setTemplateKey($templateKey);
        }

        return $dimensionContent;
    }

    private function givenTemplate(?CacheLifetimeMetadata $cacheLifetime = null): void
    {
        $form = new FormMetadata();
        $form->setTemplate(new TemplateMetadata('Some\Controller::indexAction', '@SuluGlossary/glossary_entries/default', $cacheLifetime));
        $typed = new TypedFormMetadata();
        $typed->addForm('default', $form);
        $this->formMetadataProvider->method('getMetadata')->with('glossary_entry', 'en', [])->willReturn($typed);
    }

    public function testThrowsNotFoundWhenTheEntryDoesNotExist(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->provider->getDefaults($this->route());
    }

    public function testThrowsNotFoundWhenNoLiveContentExists(): void
    {
        $this->repository->method('findOneBy')->willReturn(new GlossaryEntry('uuid-1'));
        $this->aggregator->method('aggregate')->willThrowException(new ContentNotFoundException(new GlossaryEntry('uuid-1'), []));

        $this->expectException(NotFoundHttpException::class);

        $this->provider->getDefaults($this->route());
    }

    public function testThrowsNotFoundWhenTheContentHasNoTemplate(): void
    {
        $this->repository->method('findOneBy')->willReturn(new GlossaryEntry('uuid-1'));
        $this->aggregator->method('aggregate')->willReturn($this->dimensionContent(null));

        $this->expectException(NotFoundHttpException::class);

        $this->provider->getDefaults($this->route());
    }

    public function testReturnsObjectViewAndController(): void
    {
        $dimensionContent = $this->dimensionContent();
        $this->repository->method('findOneBy')->willReturn(new GlossaryEntry('uuid-1'));
        $this->aggregator->method('aggregate')->willReturn($dimensionContent);
        $this->givenTemplate();

        $defaults = $this->provider->getDefaults($this->route());

        $this->assertSame($dimensionContent, $defaults['object']);
        $this->assertSame('@SuluGlossary/glossary_entries/default', $defaults['view']);
        $this->assertSame('Some\Controller::indexAction', $defaults['_controller']);
        $this->assertArrayNotHasKey(CacheLifetimeRequestStore::ATTRIBUTE_KEY, $defaults);
    }

    public function testResolvesTheCacheLifetime(): void
    {
        $this->repository->method('findOneBy')->willReturn(new GlossaryEntry('uuid-1'));
        $this->aggregator->method('aggregate')->willReturn($this->dimensionContent());
        $this->givenTemplate(new CacheLifetimeMetadata('seconds', '600'));
        $this->cacheLifetimeResolver->method('supports')->with('seconds', '600')->willReturn(true);
        $this->cacheLifetimeResolver->method('resolve')->with('seconds', '600')->willReturn(600);

        $defaults = $this->provider->getDefaults($this->route());

        $this->assertSame(600, $defaults[CacheLifetimeRequestStore::ATTRIBUTE_KEY]);
    }

    public function testRejectsAnUnsupportedCacheLifetime(): void
    {
        $this->repository->method('findOneBy')->willReturn(new GlossaryEntry('uuid-1'));
        $this->aggregator->method('aggregate')->willReturn($this->dimensionContent());
        $this->givenTemplate(new CacheLifetimeMetadata('unknown', '1'));
        $this->cacheLifetimeResolver->method('supports')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);

        $this->provider->getDefaults($this->route());
    }
}
