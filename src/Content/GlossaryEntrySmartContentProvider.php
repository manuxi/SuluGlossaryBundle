<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Content;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Manuxi\SuluGlossaryBundle\Admin\GlossaryEntryAdmin;
use Manuxi\SuluGlossaryBundle\Content\ResourceLoader\GlossaryEntryResourceLoader;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\Builder;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\BuilderInterface;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentQueryEnhancer;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @phpstan-type SmartContentFilters array{
 *     categories: int[],
 *     categoryOperator: 'AND'|'OR',
 *     websiteCategories: string[],
 *     websiteCategoryOperator: 'AND'|'OR',
 *     tags: int[],
 *     tagOperator: 'AND'|'OR',
 *     websiteTags: string[],
 *     websiteTagOperator: 'AND'|'OR',
 *     types: string[],
 *     typesOperator: 'OR',
 *     locale: string,
 *     dataSource: string|null,
 *     limit: int|null,
 *     offset?: int,
 *     includeSubFolders: bool,
 *     excludeDuplicates: bool,
 *     stage?: string,
 * }
 */
class GlossaryEntrySmartContentProvider implements SmartContentProviderInterface
{
    /**
     * @var EntityRepository<GlossaryEntry>
     */
    private readonly EntityRepository $entityRepository;

    public function __construct(
        private readonly DimensionContentQueryEnhancer $dimensionContentQueryEnhancer,
        private readonly SmartContentQueryEnhancer $smartContentQueryEnhancer,
        EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
        $this->entityRepository = $entityManager->getRepository(GlossaryEntry::class);
    }

    public function getConfiguration(): ProviderConfigurationInterface
    {
        return $this->getConfigurationBuilder()->getConfiguration();
    }

    protected function getConfigurationBuilder(): BuilderInterface
    {
        $types = [];
        foreach (GlossaryEntryDimensionContent::TYPES as $type) {
            $types[] = ['type' => $type, 'title' => $this->translator->trans('sulu_glossary.type.' . $type, [], 'admin')];
        }

        return Builder::create()
            ->enableTags()
            ->enableCategories()
            ->enableLimit()
            ->enablePagination()
            ->enablePresentAs()
            ->enableTypes($types)
            ->enableSorting([
                ['column' => 'title', 'title' => 'sulu_admin.title'],
                ['column' => 'published', 'title' => 'sulu_admin.published'],
                ['column' => 'authored', 'title' => 'sulu_admin.authored'],
                ['column' => 'created', 'title' => 'sulu_admin.created'],
                ['column' => 'changed', 'title' => 'sulu_admin.changed'],
            ])
            ->enableView(GlossaryEntryAdmin::EDIT_TABS_VIEW, ['id' => 'id']);
    }

    /**
     * @param SmartContentFilters  $filters
     * @param array<string, mixed> $params
     */
    public function countBy(array $filters, array $params = []): int
    {
        $filters = $this->enhanceWithDimensionAttributes($filters);

        $alias = 'glossaryEntry';
        $queryBuilder = $this->entityRepository->createQueryBuilder($alias);

        $types = $filters['types'];
        $mappedFilters = $this->mapFilters($filters);
        $this->dimensionContentQueryEnhancer->addFilters($queryBuilder, $alias, GlossaryEntryDimensionContent::class, $mappedFilters, []);
        $this->addInternalFilters($queryBuilder, $filters, $types);

        $queryBuilder->select('COUNT(DISTINCT ' . $alias . '.uuid)');

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @param SmartContentFilters         $filters
     * @param array<string, 'asc'|'desc'> $sortBys
     * @param array<string, mixed>        $params
     *
     * @return array<array{id: string, title: string}>
     */
    public function findFlatBy(array $filters, array $sortBys, array $params = []): array
    {
        $filters = $this->enhanceWithDimensionAttributes($filters);

        $alias = 'glossaryEntry';
        $queryBuilder = $this->entityRepository->createQueryBuilder($alias);

        $types = $filters['types'];
        $mappedFilters = $this->mapFilters($filters);
        $this->dimensionContentQueryEnhancer->addFilters($queryBuilder, $alias, GlossaryEntryDimensionContent::class, $mappedFilters, $this->mapSortBys($sortBys));
        $this->addInternalFilters($queryBuilder, $filters, $types);

        $queryBuilder->select('DISTINCT ' . $alias . '.uuid as id');
        $this->smartContentQueryEnhancer->addOrderBySelects($queryBuilder);
        $this->smartContentQueryEnhancer->addPagination($queryBuilder, $filters['offset'] ?? 0, $filters['limit']);

        /** @var array<array{id: string, title?: string}> $queryResult */
        $queryResult = $queryBuilder->getQuery()->getArrayResult();

        return array_map(
            static fn (array $item): array => ['id' => (string) $item['id'], 'title' => (string) ($item['title'] ?? '')],
            $queryResult
        );
    }

    public function getType(): string
    {
        return GlossaryEntry::RESOURCE_KEY;
    }

    public function getResourceLoaderKey(): string
    {
        return GlossaryEntryResourceLoader::RESOURCE_LOADER_KEY;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    protected function enhanceWithDimensionAttributes(array $filters): array
    {
        return array_merge(['stage' => $filters['stage'] ?? DimensionContentInterface::STAGE_LIVE], $filters);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    protected function mapFilters(array $filters): array
    {
        unset($filters['types']);

        if (!empty($filters['categories'])) {
            $filters['categoryIds'] = $filters['categories'];
        }
        unset($filters['categories']);

        if (!empty($filters['tags'])) {
            $filters['tagIds'] = $filters['tags'];
        }
        unset($filters['tags']);

        return $filters;
    }

    /**
     * @param array<string, 'asc'|'desc'> $sortBys
     *
     * @return array<string, 'asc'|'desc'>
     */
    protected function mapSortBys(array $sortBys): array
    {
        if (\array_key_exists('published', $sortBys)) {
            $sortBys['workflowPublished'] = $sortBys['published'];
            unset($sortBys['published']);
        }

        return $sortBys;
    }

    /**
     * @param array<string, mixed> $filters
     * @param string[]             $types
     */
    protected function addInternalFilters(QueryBuilder $queryBuilder, array $filters, array $types): void
    {
        if ([] !== $types) {
            $queryBuilder->andWhere('filterDimensionContent.type IN (:glossaryTypes)')
                ->setParameter('glossaryTypes', $types);
        }

        /** @var string[] $websiteCategories */
        $websiteCategories = $filters['websiteCategories'] ?? [];
        if ([] !== $websiteCategories) {
            $this->smartContentQueryEnhancer->addJoinFilter(
                $queryBuilder,
                'filterDimensionContent.excerptCategories',
                'websiteFilterCategoryId',
                'id',
                'websiteCategoryIds',
                $websiteCategories,
                $filters['websiteCategoryOperator'] ?? 'OR',
            );
        }

        /** @var string[] $websiteTags */
        $websiteTags = $filters['websiteTags'] ?? [];
        if ([] !== $websiteTags) {
            $this->smartContentQueryEnhancer->addJoinFilter(
                $queryBuilder,
                'filterDimensionContent.excerptTags',
                'websiteFilterTagName',
                'name',
                'websiteTagNames',
                $websiteTags,
                $filters['websiteTagOperator'] ?? 'OR',
            );
        }
    }
}
