<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Search;

use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Generator;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * @phpstan-type Row array{
 *     uuid: string,
 *     changed: \DateTimeImmutable,
 *     created: \DateTimeImmutable,
 *     title: string|null,
 *     type: string|null,
 *     locale: string,
 * }
 */
class GlossaryEntryAdminReindexProvider implements ReindexProviderInterface
{
    public const INDEX = 'admin';

    /**
     * @var EntityRepository<GlossaryEntryDimensionContent>
     */
    private readonly EntityRepository $dimensionContentRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->dimensionContentRepository = $entityManager->getRepository(GlossaryEntryDimensionContent::class);
    }

    public function total(): ?int
    {
        return null;
    }

    public function provide(ReindexConfig $reindexConfig): Generator
    {
        /** @var Row $row */
        foreach ($this->loadRows($reindexConfig->getIdentifiers()) as $row) {
            yield [
                'id' => GlossaryEntry::RESOURCE_KEY . '__' . $row['uuid'] . '__' . $row['locale'],
                'resourceKey' => GlossaryEntry::RESOURCE_KEY,
                'resourceId' => $row['uuid'],
                'changedAt' => $row['changed']->format('c'),
                'createdAt' => $row['created']->format('c'),
                'title' => (string) $row['title'],
                'locale' => $row['locale'],
                'securityContext' => GlossaryEntry::SECURITY_CONTEXT,
                'metadata' => ['type' => $row['type']],
            ];
        }
    }

    public static function getIndex(): string
    {
        return self::INDEX;
    }

    /**
     * @param string[] $identifiers
     *
     * @return iterable<Row>
     */
    private function loadRows(array $identifiers): iterable
    {
        $queryBuilder = $this->dimensionContentRepository->createQueryBuilder('dimensionContent')
            ->select('IDENTITY(dimensionContent.glossaryEntry) AS uuid')
            ->addSelect('dimensionContent.created')
            ->addSelect('dimensionContent.changed')
            ->addSelect('dimensionContent.title')
            ->addSelect('dimensionContent.type')
            ->addSelect('dimensionContent.locale')
            ->where('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.locale IS NOT NULL')
            ->andWhere('dimensionContent.version = :version')
            ->setParameter('stage', DimensionContentInterface::STAGE_DRAFT)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION);

        if ([] !== $identifiers) {
            $conditions = [];
            foreach ($identifiers as $index => $identifier) {
                [$resourceKey, $uuid, $locale] = array_pad(explode('__', $identifier), 3, '');
                if (GlossaryEntry::RESOURCE_KEY !== $resourceKey) {
                    continue;
                }

                $conditions[] = \sprintf('(dimensionContent.glossaryEntry = :id%1$d AND dimensionContent.locale = :locale%1$d)', $index);
                $queryBuilder->setParameter('id' . $index, $uuid)->setParameter('locale' . $index, $locale);
            }

            if ([] === $conditions) {
                return [];
            }

            $queryBuilder->andWhere(implode(' OR ', $conditions));
        }

        /** @var iterable<Row> $rows */
        $rows = $queryBuilder->getQuery()->toIterable();

        return $rows;
    }
}
