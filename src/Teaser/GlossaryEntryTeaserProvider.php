<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Teaser;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\AdminBundle\Teaser\Configuration\TeaserConfiguration;
use Sulu\Bundle\AdminBundle\Teaser\Provider\TeaserProviderInterface;
use Sulu\Bundle\AdminBundle\Teaser\Teaser;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentEnhancer\ContentEnhancerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Route\Application\Routing\Generator\RouteGeneratorInterface;
use Sulu\Route\Domain\Exception\MissingRequestContextParameterException;
use Sulu\Route\Domain\Model\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryEntryTeaserProvider implements TeaserProviderInterface
{
    public function __construct(
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly ContentAggregatorInterface $contentAggregator,
        private readonly ContentEnhancerInterface $contentEnhancer,
        private readonly RouteGeneratorInterface $routeGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getConfiguration(): TeaserConfiguration
    {
        return new TeaserConfiguration(
            $this->translator->trans('sulu_glossary.glossary_entries', [], 'admin'),
            GlossaryEntry::RESOURCE_KEY,
            'table',
            ['title'],
            $this->translator->trans('sulu_glossary.select_glossary_entries', [], 'admin'),
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return Teaser[]
     */
    public function find(array $ids, $locale): array
    {
        if ([] === $ids) {
            return [];
        }

        $glossaryEntries = [...$this->repository->findBy(
            ['uuids' => $ids, 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
            [],
            [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => true]
        )];

        $positions = array_flip($ids);
        usort(
            $glossaryEntries,
            static fn (GlossaryEntry $a, GlossaryEntry $b): int => ($positions[$a->getUuid()] ?? 0) <=> ($positions[$b->getUuid()] ?? 0)
        );

        $teasers = [];
        foreach ($glossaryEntries as $glossaryEntry) {
            $teaser = $this->createTeaser($glossaryEntry, $locale);
            if (null !== $teaser) {
                $teasers[] = $teaser;
            }
        }

        return $teasers;
    }

    private function generateUrl(Route $route): string
    {
        try {
            return $this->routeGenerator->generate($route->getSlug(), $route->getLocale());
        } catch (MissingRequestContextParameterException) {
            return $route->getSlug();
        }
    }

    private function createTeaser(GlossaryEntry $glossaryEntry, string $locale): ?Teaser
    {
        try {
            $dimensionContent = $this->contentAggregator->aggregate($glossaryEntry, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ]);
        } catch (ContentNotFoundException) {
            return null;
        }

        $enhanced = $this->contentEnhancer->enhance($dimensionContent);
        if (!$enhanced instanceof GlossaryEntryDimensionContent) {
            return null;
        }

        $route = $enhanced->getRoute();
        if (null === $route) {
            return null;
        }

        $title = $enhanced->getExcerptTitle() ?: $enhanced->getTitle();
        if (!\is_string($title) || '' === $title) {
            return null;
        }

        $description = $enhanced->getExcerptDescription();
        if (null === $description || '' === $description) {
            $description = $enhanced->getExplanation() ?? '';
        }

        return new Teaser(
            $glossaryEntry->getUuid(),
            GlossaryEntry::RESOURCE_KEY,
            $locale,
            $title,
            strip_tags($description),
            $enhanced->getExcerptMore() ?? '',
            $this->generateUrl($route),
            $enhanced->getExcerptImage()['id'] ?? null,
            ['type' => $enhanced->getType()],
        );
    }
}
