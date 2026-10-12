<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Exception\GlossaryEntryNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Webmozart\Assert\Assert;

class GlossaryEntryRepository implements GlossaryEntryRepositoryInterface
{
    private const SELECTS = [
        self::GROUP_SELECT_GLOSSARY_ENTRY_ADMIN => [
            self::SELECT_GLOSSARY_ENTRY_CONTENT => [
                DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true,
            ],
        ],
        self::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => [
            self::SELECT_GLOSSARY_ENTRY_CONTENT => [
                DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_WEBSITE => true,
            ],
        ],
    ];

    /**
     * @var EntityRepository<GlossaryEntry>
     */
    private EntityRepository $entityRepository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DimensionContentQueryEnhancer $dimensionContentQueryEnhancer,
    ) {
        $this->entityRepository = $entityManager->getRepository(GlossaryEntry::class);
    }

    public function createNew(?string $uuid = null): GlossaryEntry
    {
        return new GlossaryEntry($uuid);
    }

    public function getOneBy(array $filters, array $selects = []): GlossaryEntry
    {
        $queryBuilder = $this->createQueryBuilder($filters, [], $selects);

        try {
            /** @var GlossaryEntry $glossaryEntry */
            $glossaryEntry = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            throw new GlossaryEntryNotFoundException($filters, 0, $e);
        }

        return $glossaryEntry;
    }

    public function getOneWithContent(string $uuid, string $locale, ?int $version = null): GlossaryEntry
    {
        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
        ];

        if (null !== $version) {
            $dimensionAttributes['version'] = [$version, DimensionContentInterface::CURRENT_VERSION];
        }

        return $this->getOneBy(
            ['uuid' => $uuid, 'locale' => $locale],
            [
                self::SELECT_GLOSSARY_ENTRY_CONTENT => [
                    'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                    'dimensionAttributes' => $dimensionAttributes,
                ],
            ]
        );
    }

    public function findOneBy(array $filters, array $selects = []): ?GlossaryEntry
    {
        try {
            return $this->getOneBy($filters, $selects);
        } catch (GlossaryEntryNotFoundException) {
            return null;
        }
    }

    public function findBy(array $filters = [], array $sortBy = [], array $selects = []): iterable
    {
        /** @var iterable<GlossaryEntry> $glossaryEntries */
        $glossaryEntries = $this->createQueryBuilder($filters, $sortBy, $selects)->getQuery()->getResult();

        foreach ($glossaryEntries as $glossaryEntry) {
            yield $glossaryEntry;
        }
    }

    public function findIdentifiersBy(array $filters = [], array $sortBy = []): iterable
    {
        $queryBuilder = $this->createQueryBuilder($filters, $sortBy);
        $queryBuilder->select('DISTINCT glossaryEntry.uuid');

        /** @var OrderBy[] $orderBys */
        $orderBys = $queryBuilder->getDQLPart('orderBy');
        foreach ($orderBys as $orderBy) {
            $queryBuilder->addSelect(explode(' ', $orderBy->getParts()[0])[0]);
        }

        /** @var array<array{uuid: string}> $result */
        $result = $queryBuilder->getQuery()->getResult();

        return array_column($result, 'uuid');
    }

    public function countBy(array $filters = []): int
    {
        unset($filters['page'], $filters['limit']);

        $queryBuilder = $this->createQueryBuilder($filters);
        $queryBuilder->select('COUNT(DISTINCT glossaryEntry.uuid)');

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function findVersions(GlossaryEntry $glossaryEntry, string $locale): array
    {
        /** @var GlossaryEntryDimensionContent[] $versions */
        $versions = $this->entityManager->createQueryBuilder()
            ->select('dimensionContent')
            ->from(GlossaryEntryDimensionContent::class, 'dimensionContent')
            ->where('dimensionContent.glossaryEntry = :glossaryEntry')
            ->andWhere('dimensionContent.locale = :locale')
            ->andWhere('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.version > :version')
            ->setParameter('glossaryEntry', $glossaryEntry->getUuid())
            ->setParameter('locale', $locale)
            ->setParameter('stage', DimensionContentInterface::STAGE_DRAFT)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->orderBy('dimensionContent.version', 'DESC')
            ->getQuery()
            ->getResult();

        return $versions;
    }

    public function add(GlossaryEntry $glossaryEntry): void
    {
        $this->entityManager->persist($glossaryEntry);
    }

    public function remove(GlossaryEntry $glossaryEntry): void
    {
        $this->entityManager->remove($glossaryEntry);
    }

    /**
     * @param DimensionContentInterface<GlossaryEntry> $dimensionContent
     */
    public function removeDimensionContent(DimensionContentInterface $dimensionContent): void
    {
        $this->entityManager->remove($dimensionContent);
    }

    /**
     * @param array<string, mixed>        $filters
     * @param array<string, 'asc'|'desc'> $sortBy
     * @param array<string, mixed>        $selects
     */
    public function createQueryBuilder(array $filters, array $sortBy = [], array $selects = []): QueryBuilder
    {
        foreach ($selects as $selectGroup => $value) {
            if ($value && isset(self::SELECTS[$selectGroup])) {
                $selects = array_replace_recursive($selects, self::SELECTS[$selectGroup]);
            }
        }

        $queryBuilder = $this->entityRepository->createQueryBuilder('glossaryEntry');

        $uuid = $filters['uuid'] ?? null;
        if (null !== $uuid) {
            Assert::string($uuid);
            $queryBuilder->andWhere('glossaryEntry.uuid = :uuid')->setParameter('uuid', $uuid);
        }

        $uuids = $filters['uuids'] ?? null;
        if (null !== $uuids) {
            Assert::isArray($uuids);
            $queryBuilder->andWhere('glossaryEntry.uuid IN(:uuids)')->setParameter('uuids', $uuids);
        }

        $limit = $filters['limit'] ?? null;
        if (null !== $limit) {
            Assert::integer($limit);
            $queryBuilder->setMaxResults($limit);
        }

        $page = $filters['page'] ?? null;
        if (null !== $page) {
            Assert::integer($page);
            Assert::notNull($limit);
            $queryBuilder->setFirstResult($limit * ($page - 1));
        }

        if (
            (\array_key_exists('locale', $filters) && \array_key_exists('stage', $filters))
            || ([] === $filters && [] !== $sortBy)
        ) {
            $this->dimensionContentQueryEnhancer->addFilters(
                $queryBuilder,
                'glossaryEntry',
                GlossaryEntryDimensionContent::class,
                $filters,
                $sortBy
            );

            $types = $filters['types'] ?? null;
            if (!empty($types)) {
                Assert::isArray($types);
                $queryBuilder->andWhere('filterDimensionContent.type IN (:types)')->setParameter('types', $types);
            }
        }

        foreach ($sortBy as $field => $order) {
            if ('uuid' === $field) {
                $queryBuilder->addOrderBy('glossaryEntry.uuid', $order);
            }
        }

        if ($selects[self::SELECT_GLOSSARY_ENTRY_CONTENT] ?? null) {
            /** @var array{dimensionAttributes?: array<string, mixed>, selects?: array<string, bool>} $contentConfig */
            $contentConfig = $selects[self::SELECT_GLOSSARY_ENTRY_CONTENT];
            $queryBuilder->leftJoin('glossaryEntry.dimensionContents', 'dimensionContent');

            if (isset($contentConfig['dimensionAttributes'])) {
                $contentSelects = $contentConfig['selects'] ?? [];
                $dimensionAttributes = $contentConfig['dimensionAttributes'];
            } else {
                /** @var array<string, bool> $contentSelects */
                $contentSelects = $contentConfig;
                $dimensionAttributes = $filters;
            }

            $this->dimensionContentQueryEnhancer->addSelects(
                $queryBuilder,
                GlossaryEntryDimensionContent::class,
                $dimensionAttributes,
                $contentSelects
            );
        }

        return $queryBuilder;
    }
}
