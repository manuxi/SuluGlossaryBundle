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
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * @phpstan-type Row array{
 *     uuid: string,
 *     changed: \DateTimeImmutable,
 *     authored: \DateTimeImmutable|null,
 *     title: string|null,
 *     explanation: string|null,
 *     type: string|null,
 *     locale: string,
 *     templateData: array<string, mixed>,
 *     slug: string|null,
 * }
 */
class GlossaryEntryWebsiteReindexProvider implements ReindexProviderInterface
{
    public const INDEX = 'website';

    /**
     * @var EntityRepository<GlossaryEntryDimensionContent>
     */
    private readonly EntityRepository $dimensionContentRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly WebspaceManagerInterface $webspaceManager,
    ) {
        $this->dimensionContentRepository = $entityManager->getRepository(GlossaryEntryDimensionContent::class);
    }

    public function total(): ?int
    {
        return null;
    }

    public function provide(ReindexConfig $reindexConfig): Generator
    {
        $webspaces = [];
        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            $webspaces[] = $webspace->getKey();
        }

        /** @var Row $row */
        foreach ($this->loadRows($reindexConfig->getIdentifiers()) as $row) {
            if (null === $row['slug'] || null === $row['title'] || '' === $row['title']) {
                continue;
            }

            $image = $row['templateData']['image'] ?? null;
            $description = $row['templateData']['description'] ?? null;
            $content = array_values(array_filter([
                $row['explanation'],
                \is_string($description) ? trim(strip_tags($description)) : null,
            ], static fn (?string $value): bool => null !== $value && '' !== $value));

            yield [
                'id' => GlossaryEntry::RESOURCE_KEY . '__' . $row['uuid'] . '__' . $row['locale'],
                'resourceKey' => GlossaryEntry::RESOURCE_KEY,
                'resourceId' => $row['uuid'],
                'locale' => $row['locale'],
                'webspaces' => $webspaces,
                'title' => $row['title'],
                'url' => $row['slug'],
                'content' => $content,
                'mediaId' => \is_array($image) && isset($image['id']) ? (int) $image['id'] : '',
                'authoredAt' => ($row['authored'] ?? $row['changed'])->format('c'),
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
            ->leftJoin('dimensionContent.route', 'route')
            ->select('IDENTITY(dimensionContent.glossaryEntry) AS uuid')
            ->addSelect('dimensionContent.changed')
            ->addSelect('dimensionContent.authored')
            ->addSelect('dimensionContent.title')
            ->addSelect('dimensionContent.explanation')
            ->addSelect('dimensionContent.type')
            ->addSelect('dimensionContent.locale')
            ->addSelect('dimensionContent.templateData')
            ->addSelect('route.slug')
            ->where('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.locale IS NOT NULL')
            ->andWhere('dimensionContent.version = :version')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
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
