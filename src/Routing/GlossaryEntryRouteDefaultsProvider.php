<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Routing;

use InvalidArgumentException;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use RuntimeException;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\CacheLifetimeMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TemplateMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeRequestStore;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeResolverInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Route\Application\Routing\Matcher\RouteDefaultsProviderInterface;
use Sulu\Route\Domain\Model\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GlossaryEntryRouteDefaultsProvider implements RouteDefaultsProviderInterface
{
    public function __construct(
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly ContentAggregatorInterface $contentAggregator,
        private readonly MetadataProviderRegistry $metadataProviderRegistry,
        private readonly CacheLifetimeResolverInterface $cacheLifetimeResolver,
    ) {
    }

    public function getDefaults(Route $route): array
    {
        $id = $route->getResourceId();
        $locale = $route->getLocale();

        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'version' => DimensionContentInterface::CURRENT_VERSION,
        ];

        $glossaryEntry = $this->repository->findOneBy(
            ['uuid' => $id],
            [
                GlossaryEntryRepositoryInterface::SELECT_GLOSSARY_ENTRY_CONTENT => [
                    'dimensionAttributes' => $dimensionAttributes,
                    'selects' => [
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_TAGS => true,
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES => true,
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES_TRANSLATION => true,
                    ],
                ],
            ],
        );

        if (null === $glossaryEntry) {
            throw new NotFoundHttpException(\sprintf('No glossary entry found for id "%s" and locale "%s"', $id, $locale));
        }

        try {
            $dimensionContent = $this->contentAggregator->aggregate($glossaryEntry, $dimensionAttributes);
        } catch (ContentNotFoundException $exception) {
            throw new NotFoundHttpException(\sprintf('No glossary entry found for id "%s" and locale "%s"', $id, $locale), $exception);
        }

        $contentLocale = $dimensionContent->getLocale();
        $templateKey = $dimensionContent->getTemplateKey();
        if (!$contentLocale || !$templateKey) {
            throw new NotFoundHttpException(\sprintf('No published content found for id "%s" and locale "%s"', $id, $locale));
        }

        $templateMetadata = $this->resolveTemplateMetadata($dimensionContent::getTemplateType(), $templateKey, $contentLocale);

        $defaults = [
            'object' => $dimensionContent,
            'view' => $templateMetadata->getView(),
            '_controller' => $templateMetadata->getController(),
        ];

        $cacheLifetime = $this->getCacheLifetime($templateMetadata);
        if (null !== $cacheLifetime) {
            $defaults[CacheLifetimeRequestStore::ATTRIBUTE_KEY] = $cacheLifetime;
        }

        return $defaults;
    }

    private function getCacheLifetime(TemplateMetadata $templateMetadata): ?int
    {
        $cacheLifetime = $templateMetadata->getCacheLifetime();
        if (!$cacheLifetime instanceof CacheLifetimeMetadata) {
            return null;
        }

        $type = $cacheLifetime->getType();
        $value = $cacheLifetime->getValue();

        if (!$this->cacheLifetimeResolver->supports($type, $value)) {
            throw new InvalidArgumentException(\sprintf('Invalid cacheLifeTime in route default provider: %s', json_encode(['type' => $type, 'value' => $value], \JSON_THROW_ON_ERROR)));
        }

        return $this->cacheLifetimeResolver->resolve($type, $value);
    }

    private function resolveTemplateMetadata(string $type, string $templateKey, string $locale): TemplateMetadata
    {
        $typedMetadata = $this->metadataProviderRegistry->getMetadataProvider('form')->getMetadata($type, $locale, []);

        if (!$typedMetadata instanceof TypedFormMetadata) {
            throw new RuntimeException(\sprintf('Could not find metadata "%s" of type "%s".', 'form', $type));
        }

        $metadata = $typedMetadata->getForms()[$templateKey] ?? null;
        if (!$metadata instanceof FormMetadata) {
            throw new RuntimeException(\sprintf('Could not find form metadata "%s" of type "%s".', $templateKey, $type));
        }

        $templateMetadata = $metadata->getTemplate();
        if (!$templateMetadata instanceof TemplateMetadata) {
            throw new RuntimeException(\sprintf('Could not find template metadata "%s" of type "%s".', $templateKey, $type));
        }

        return $templateMetadata;
    }
}
