<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Sitemap;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Sulu\Bundle\WebsiteBundle\Sitemap\AbstractSitemapProvider;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapAlternateLink;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapUrl;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\PortalInformation;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * @phpstan-type Row array{
 *     lastModified: \DateTimeImmutable|null,
 *     changed: \DateTimeImmutable,
 *     locale: string,
 *     availableLocales: string[]|null,
 *     slug: string,
 *     uuid: string
 * }
 * @phpstan-type AlternateRow array{locale: string, slug: string, uuid: string}
 */
class GlossaryEntrySitemapProvider extends AbstractSitemapProvider
{
    public const ALIAS = 'glossary_entries';

    /**
     * @var EntityRepository<GlossaryEntry>
     */
    private readonly EntityRepository $entityRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly string $environment,
    ) {
        $this->entityRepository = $entityManager->getRepository(GlossaryEntry::class);
    }

    /**
     * @return SitemapUrl[]
     */
    public function build($page, $scheme, $host): array
    {
        $result = [];

        foreach ($this->webspaceManager->findPortalInformationsByHostIncludingSubdomains($host, $this->environment) as $portalInformation) {
            /** @var Localization|null $localization */
            $localization = $portalInformation->getLocalization();
            if (null === $localization) {
                continue;
            }

            $locale = $localization->getLocale();

            $alternateRoutes = [];
            /** @var AlternateRow $alternateRow */
            foreach ($this->getAlternateRows($locale) as $alternateRow) {
                $alternateRoutes[$alternateRow['uuid']][$alternateRow['locale']][] = $alternateRow['slug'];
            }

            /** @var Row $row */
            foreach ($this->getRows($locale) as $row) {
                $alternates = [];
                foreach (array_diff($row['availableLocales'] ?? [], [$locale]) as $availableLocale) {
                    if (isset($alternateRoutes[$row['uuid']][$availableLocale])) {
                        $alternates[$availableLocale] = $alternateRoutes[$row['uuid']][$availableLocale];
                    }
                }

                $sitemapUrl = $this->generateSitemapUrl($row, $alternates, $portalInformation, $host, $scheme);
                if (null !== $sitemapUrl) {
                    $result[] = $sitemapUrl;
                }
            }
        }

        return $result;
    }

    public function getAlias(): string
    {
        return self::ALIAS;
    }

    /**
     * @return iterable<Row>
     */
    private function getRows(string $locale): iterable
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('glossaryEntry');
        $queryBuilder->distinct()
            ->join('glossaryEntry.dimensionContents', 'dimensionContent', 'WITH', '
                dimensionContent.locale = :locale
                AND dimensionContent.stage = :stage
                AND dimensionContent.version = :version
                AND dimensionContent.seoHideInSitemap = :hide
            ')
            ->leftJoin('glossaryEntry.dimensionContents', 'unlocalizedDimensionContent', 'WITH', '
                unlocalizedDimensionContent.locale IS NULL
                AND unlocalizedDimensionContent.stage = :stage
                AND unlocalizedDimensionContent.version = :version
            ')
            ->join('dimensionContent.route', 'route')
            ->setParameter('locale', $locale)
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->setParameter('hide', false)
            ->select('dimensionContent.lastModified')
            ->addSelect('dimensionContent.changed')
            ->addSelect('dimensionContent.locale')
            ->addSelect('unlocalizedDimensionContent.availableLocales')
            ->addSelect('route.slug')
            ->addSelect('glossaryEntry.uuid')
            ->orderBy('route.slug', 'ASC');

        /** @var iterable<Row> $rows */
        $rows = $queryBuilder->getQuery()->toIterable();

        return $rows;
    }

    /**
     * @return iterable<AlternateRow>
     */
    private function getAlternateRows(string $locale): iterable
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('glossaryEntry');
        $queryBuilder->distinct()
            ->join('glossaryEntry.dimensionContents', 'dimensionContent', 'WITH', '
                dimensionContent.locale != :locale
                AND dimensionContent.locale IS NOT NULL
                AND dimensionContent.stage = :stage
                AND dimensionContent.version = :version
                AND dimensionContent.seoHideInSitemap = :hide
            ')
            ->join('dimensionContent.route', 'route')
            ->setParameter('locale', $locale)
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->setParameter('hide', false)
            ->select('dimensionContent.locale')
            ->addSelect('route.slug')
            ->addSelect('glossaryEntry.uuid')
            ->orderBy('route.slug', 'ASC');

        /** @var iterable<AlternateRow> $rows */
        $rows = $queryBuilder->getQuery()->toIterable();

        return $rows;
    }

    /**
     * @param Row                     $row
     * @param array<string, string[]> $alternates
     */
    private function generateSitemapUrl(array $row, array $alternates, PortalInformation $portalInformation, string $host, string $scheme): ?SitemapUrl
    {
        /** @var string|null $webspaceKey */
        $webspaceKey = $portalInformation->getWebspaceKey();

        $url = $this->webspaceManager->findUrlByResourceLocator($row['slug'], $this->environment, $row['locale'], $webspaceKey, $host, $scheme);
        if (!$url) {
            return null;
        }

        $sitemapUrl = new SitemapUrl(
            $url,
            $row['locale'],
            $portalInformation->getWebspace()->getDefaultLocalization()->getLocale(Localization::DASH),
            $row['lastModified'] ?? $row['changed'],
        );

        foreach ($alternates as $alternateLocale => $slugs) {
            foreach ($slugs as $slug) {
                $alternateUrl = $this->webspaceManager->findUrlByResourceLocator($slug, $this->environment, $alternateLocale, $webspaceKey, $host, $scheme);
                if ($alternateUrl) {
                    $sitemapUrl->addAlternateLink(new SitemapAlternateLink($alternateUrl, $alternateLocale));
                }
            }
        }

        return $sitemapUrl;
    }
}
