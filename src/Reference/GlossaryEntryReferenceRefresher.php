<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Reference;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Generator;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Sulu\Bundle\ReferenceBundle\Application\Collector\ReferenceCollector;
use Sulu\Bundle\ReferenceBundle\Application\Refresh\ReferenceRefresherInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Application\ContentResolver\ContentViewResolver\ContentViewResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Content\Domain\Model\DimensionContentInterface;

class GlossaryEntryReferenceRefresher implements ReferenceRefresherInterface
{
    /**
     * @var EntityRepository<GlossaryEntryDimensionContent>
     */
    private readonly EntityRepository $dimensionContentRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly ReferenceRepositoryInterface $referenceRepository,
        private readonly ContentViewResolverInterface $contentViewResolver,
        private readonly ContentMergerInterface $contentMerger,
    ) {
        $this->dimensionContentRepository = $entityManager->getRepository(GlossaryEntryDimensionContent::class);
    }

    public static function getResourceKey(): string
    {
        return GlossaryEntry::RESOURCE_KEY;
    }

    public function refresh(?array $filter = null): Generator
    {
        $currentResourceId = null;
        /** @var GlossaryEntryDimensionContent[] $group */
        $group = [];

        foreach ($this->loadDimensionContents($filter) as $dimensionContent) {
            $resourceId = $dimensionContent->getResource()->getUuid();

            if (null !== $currentResourceId && $resourceId !== $currentResourceId) {
                yield from $this->processGroup($group);
                $group = [];
            }

            $currentResourceId = $resourceId;
            $group[] = $dimensionContent;
        }

        if ([] !== $group) {
            yield from $this->processGroup($group);
        }
    }

    /**
     * @param GlossaryEntryDimensionContent[] $group
     *
     * @return Generator<GlossaryEntryDimensionContent>
     */
    private function processGroup(array $group): Generator
    {
        foreach ($this->merge($group) as $merged) {
            $this->collectReferences($merged);

            yield $merged;
        }
    }

    private function collectReferences(GlossaryEntryDimensionContent $dimensionContent): void
    {
        $referenceCollector = new ReferenceCollector(
            referenceRepository: $this->referenceRepository,
            referenceResourceKey: $dimensionContent::getResourceKey(),
            referenceResourceId: $dimensionContent->getResource()->getUuid(),
            referenceLocale: $dimensionContent->getLocale() ?? '',
            referenceTitle: $dimensionContent->getTitle() ?? '',
            referenceContext: $dimensionContent->getStage(),
            referenceRouterAttributes: ['locale' => $dimensionContent->getLocale() ?? ''],
        );

        foreach ($this->contentViewResolver->getContentViews(dimensionContent: $dimensionContent) as $key => $contentView) {
            $basePath = 'template' !== $key ? (string) $key : '';

            foreach ($contentView->getAllReferencesRecursively($basePath) as $reference) {
                $referenceCollector->addReference($reference->getResourceKey(), (string) $reference->getResourceId(), $reference->getPath());
            }
        }

        $referenceCollector->persistReferences();
    }

    /**
     * @param array{resourceId: string, resourceKey: string, locale: string, stage: string}|null $filter
     *
     * @return iterable<GlossaryEntryDimensionContent>
     */
    private function loadDimensionContents(?array $filter): iterable
    {
        $queryBuilder = $this->dimensionContentRepository->createQueryBuilder('dimensionContent')
            ->where('dimensionContent.version = :version')
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->orderBy('dimensionContent.glossaryEntry', 'ASC');

        if (null !== $filter) {
            $queryBuilder
                ->join('dimensionContent.glossaryEntry', 'glossaryEntry', Join::WITH, 'glossaryEntry.uuid = :resourceId')
                ->andWhere('dimensionContent.locale = :locale OR dimensionContent.locale IS NULL')
                ->andWhere('dimensionContent.stage = :stage')
                ->setParameter('resourceId', $filter['resourceId'])
                ->setParameter('locale', $filter['locale'])
                ->setParameter('stage', $filter['stage']);
        }

        /** @var iterable<GlossaryEntryDimensionContent> $result */
        $result = $queryBuilder->getQuery()->toIterable();

        return $result;
    }

    /**
     * @param GlossaryEntryDimensionContent[] $dimensionContents
     *
     * @return Generator<GlossaryEntryDimensionContent>
     */
    private function merge(array $dimensionContents): Generator
    {
        $byStageAndLocale = [];
        foreach ($dimensionContents as $dimensionContent) {
            $byStageAndLocale[$dimensionContent->getStage()][$dimensionContent->getLocale() ?? ''] = $dimensionContent;
        }

        foreach ($byStageAndLocale as $stage => $byLocale) {
            $unlocalized = $byLocale[''] ?? null;

            foreach ($byLocale as $locale => $dimensionContent) {
                if ('' === $locale) {
                    continue;
                }

                /** @var GlossaryEntryDimensionContent $merged */
                $merged = $this->contentMerger->merge(
                    new DimensionContentCollection(
                        new ArrayCollection(null !== $unlocalized ? [$dimensionContent, $unlocalized] : [$dimensionContent]),
                        $dimensionContent::getEffectiveDimensionAttributes(['locale' => (string) $locale, 'stage' => (string) $stage]),
                        GlossaryEntryDimensionContent::class,
                    )
                );

                yield $merged;
            }
        }
    }
}
