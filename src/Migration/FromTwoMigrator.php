<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Migration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use RuntimeException;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Throwable;

/**
 * Migrates the data of SuluAbbreviationsBundle 2.0 (tables abbr_*) to glossary entries. The content is written through
 * the content manager, so routes, SEO, excerpt and workflow are created by the same code as in the admin.
 */
class FromTwoMigrator
{
    public const DEFAULT_ROUTES_TABLE = 'ro_routes_old';

    private const ENTRIES_TABLE = 'abbr_abbreviations';
    private const DIMENSION_CONTENTS_TABLE = 'abbr_abbreviation_dimension_contents';
    private const TAGS_TABLE = 'abbr_abbreviation_dimension_content_excerpt_tags';
    private const CATEGORIES_TABLE = 'abbr_abbreviation_dimension_content_excerpt_categories';
    private const VERSIONS_TABLE = 'abbr_abbreviation_versions';
    private const SETTINGS_TABLE = 'app_abbreviation_settings';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly ContentManagerInterface $contentManager,
        private readonly ContentWorkflowInterface $contentWorkflow,
    ) {
    }

    public function migrate(bool $dryRun = false, string $routesTable = self::DEFAULT_ROUTES_TABLE): MigrationResult
    {
        $result = new MigrationResult();
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::ENTRIES_TABLE, self::DIMENSION_CONTENTS_TABLE])) {
            throw new RuntimeException(\sprintf('The tables "%s" and "%s" of version 2.0 do not exist.', self::ENTRIES_TABLE, self::DIMENSION_CONTENTS_TABLE));
        }

        $hasVersions = $schemaManager->tablesExist([self::VERSIONS_TABLE]);
        $routes = $schemaManager->tablesExist([$routesTable]) ? $this->loadRoutes($routesTable) : [];

        /** @var string[] $ids */
        $ids = $this->connection->fetchFirstColumn('SELECT id FROM ' . self::ENTRIES_TABLE . ' ORDER BY id');

        foreach ($ids as $id) {
            if (null !== $this->repository->findOneBy(['uuid' => $id])) {
                ++$result->skipped;

                continue;
            }

            try {
                $source = $this->loadSource($id, $hasVersions);

                if ($dryRun) {
                    $this->count($source, $routes[$id] ?? [], $result);

                    continue;
                }

                $this->migrateEntry($id, $source, $routes[$id] ?? [], $result);
                $this->entityManager->flush();
            } catch (Throwable $exception) {
                $result->errors[$id] = $exception->getMessage();

                if (!$this->entityManager->isOpen()) {
                    $result->errors['aborted'] = 'The entity manager was closed by a database error, run the command again to continue.';

                    break;
                }
            }

            $this->entityManager->clear();
        }

        if (!$dryRun) {
            $result->settings = $this->migrateSettings($schemaManager->tablesExist([self::SETTINGS_TABLE]));
        }

        return $result;
    }

    /**
     * @return array<string, array<string, string>> slugs of the current routes by entry id and locale
     */
    private function loadRoutes(string $routesTable): array
    {
        $routes = [];

        /** @var array<array{entity_id: string, locale: string, path: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT entity_id, locale, path FROM ' . $routesTable . ' WHERE entity_class LIKE ? AND history = ? ORDER BY id',
            ['%Abbreviation', 0],
        );

        foreach ($rows as $row) {
            $routes[$row['entity_id']][$row['locale']] = $row['path'];
        }

        return $routes;
    }

    /**
     * @return array{rows: array<string, array<string, array<string, mixed>>>, versions: array<string, array<int, array<string, mixed>>>}
     */
    private function loadSource(string $id, bool $hasVersions): array
    {
        $rows = [];

        /** @var array<array<string, mixed>> $dimensionContents */
        $dimensionContents = $this->connection->fetchAllAssociative('SELECT * FROM ' . self::DIMENSION_CONTENTS_TABLE . ' WHERE abbreviationId = ? AND locale IS NOT NULL', [$id]);
        foreach ($dimensionContents as $row) {
            $row['excerptTags'] = $this->connection->fetchFirstColumn('SELECT tag_id FROM ' . self::TAGS_TABLE . ' WHERE abbreviation_dimension_content_id = ?', [$row['id']]);
            $row['excerptCategories'] = $this->connection->fetchFirstColumn('SELECT category_id FROM ' . self::CATEGORIES_TABLE . ' WHERE abbreviation_dimension_content_id = ?', [$row['id']]);
            $rows[(string) $row['locale']][(string) $row['stage']] = $row;
        }

        $versions = [];
        if ($hasVersions) {
            /** @var array<array<string, mixed>> $versionRows */
            $versionRows = $this->connection->fetchAllAssociative('SELECT locale, version, data FROM ' . self::VERSIONS_TABLE . ' WHERE abbreviationId = ? ORDER BY version ASC', [$id]);
            foreach ($versionRows as $versionRow) {
                $versions[(string) $versionRow['locale']][(int) $versionRow['version']] = $this->decode($versionRow['data']);
            }
        }

        return ['rows' => $rows, 'versions' => $versions];
    }

    /**
     * @param array{rows: array<string, array<string, array<string, mixed>>>, versions: array<string, array<int, array<string, mixed>>>} $source
     * @param array<string, string>                                                                                                      $routes
     */
    private function count(array $source, array $routes, MigrationResult $result): void
    {
        ++$result->entries;

        foreach ($source['rows'] as $locale => $stages) {
            ++$result->translations;
            $result->published += isset($stages[DimensionContentInterface::STAGE_LIVE]) ? 1 : 0;
            $result->versions += \count($source['versions'][$locale] ?? []);
            $result->routes += isset($routes[$locale]) ? 1 : 0;
        }
    }

    /**
     * @param array{rows: array<string, array<string, array<string, mixed>>>, versions: array<string, array<int, array<string, mixed>>>} $source
     * @param array<string, string>                                                                                                      $routes
     */
    private function migrateEntry(string $id, array $source, array $routes, MigrationResult $result): void
    {
        $entry = $this->repository->createNew($id);
        $this->repository->add($entry);

        foreach ($source['rows'] as $locale => $stages) {
            $draft = $stages[DimensionContentInterface::STAGE_DRAFT] ?? null;
            $live = $stages[DimensionContentInterface::STAGE_LIVE] ?? null;
            $slug = $routes[$locale] ?? null;

            if (null === $draft && null === $live) {
                continue;
            }

            foreach ($source['versions'][$locale] ?? [] as $version => $snapshot) {
                $data = $this->buildData($snapshot, $locale, $slug);
                if (null === $data) {
                    continue;
                }

                $this->contentManager->persist($entry, $data, ['locale' => $locale]);
                $this->contentManager->copy(
                    $entry,
                    ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => $locale],
                    $entry,
                    ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => $locale, 'version' => $version],
                    ['ignoredAttributes' => ['url']],
                );
                ++$result->versions;
            }

            $liveData = null !== $live ? $this->buildData($live, $locale, $slug) : null;
            $draftData = null !== $draft ? $this->buildData($draft, $locale, $slug) : null;

            if (null === $liveData && null === $draftData) {
                $result->errors[$id . '/' . $locale] = 'The translation has no title and was skipped.';

                continue;
            }

            if (null !== $liveData) {
                $this->contentManager->persist($entry, $liveData, ['locale' => $locale]);
                // the route of the draft has to be stored before the live content can reference it
                $this->entityManager->flush();
                $this->contentWorkflow->apply($entry, ['locale' => $locale], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
                $this->entityManager->flush();
                ++$result->published;
            }

            if (null !== $draftData && $draftData !== $liveData) {
                $this->contentManager->persist($entry, $draftData, ['locale' => $locale]);
            }

            if (null !== $live && \is_string($live['workflowPublished'] ?? null)) {
                $this->restorePublishedDate($entry, $locale, new DateTimeImmutable($live['workflowPublished']));
            }

            ++$result->translations;
            $result->routes += null !== $slug ? 1 : 0;
        }

        ++$result->entries;
    }

    private function restorePublishedDate(GlossaryEntry $entry, string $locale, DateTimeImmutable $published): void
    {
        /** @var GlossaryEntryDimensionContent $dimensionContent */
        foreach ($entry->getDimensionContents() as $dimensionContent) {
            if ($dimensionContent->getLocale() === $locale
                && DimensionContentInterface::CURRENT_VERSION === $dimensionContent->getVersion()
                && null !== $dimensionContent->getWorkflowPublished()
            ) {
                $dimensionContent->setWorkflowPublished($published);
            }
        }
    }

    /**
     * @param array<string, mixed> $source a row of the dimension content table or a stored version
     *
     * @return array<string, mixed>|null null if the source has no title
     */
    private function buildData(array $source, string $locale, ?string $slug): ?array
    {
        $flat = array_merge($source, $this->decode($source['templateData'] ?? null));

        $title = $flat['name'] ?? $flat['title'] ?? null;
        if (!\is_string($title) || '' === trim($title)) {
            return null;
        }

        $data = [
            'template' => 'default',
            'locale' => $locale,
            'type' => GlossaryEntryDimensionContent::TYPE_ABBREVIATION,
            'title' => $title,
            'explanation' => $flat['explanation'] ?? null,
            'description' => $flat['description'] ?? null,
            'image' => $flat['image'] ?? null,
            'link' => $flat['link'] ?? null,
            'seo' => array_filter([
                'title' => $flat['seoTitle'] ?? null,
                'description' => $flat['seoDescription'] ?? null,
                'keywords' => $flat['seoKeywords'] ?? null,
                'canonicalUrl' => $flat['seoCanonicalUrl'] ?? null,
            ], static fn (mixed $value): bool => null !== $value && '' !== $value),
            'seoNoIndex' => (bool) ($flat['seoNoIndex'] ?? false),
            'seoNoFollow' => (bool) ($flat['seoNoFollow'] ?? false),
            'seoHideInSitemap' => (bool) ($flat['seoHideInSitemap'] ?? false),
            'excerpt' => array_filter([
                'title' => $flat['excerptTitle'] ?? null,
                'more' => $flat['excerptMore'] ?? null,
                'description' => $flat['excerptDescription'] ?? null,
                'image' => $this->mediaReference($flat, 'excerptImage'),
                'icon' => $this->mediaReference($flat, 'excerptIcon'),
            ], static fn (mixed $value): bool => null !== $value && '' !== $value),
            'excerptTags' => array_map('intval', (array) ($flat['excerptTags'] ?? [])),
            'excerptCategories' => array_map('intval', (array) ($flat['excerptCategories'] ?? [])),
        ];

        $author = $flat['author_id'] ?? $flat['author'] ?? null;
        if (null !== $author && '' !== $author) {
            $data['author'] = (int) (\is_array($author) ? ($author['id'] ?? 0) : $author);
        }

        if (\is_string($flat['authored'] ?? null)) {
            $data['authored'] = (new DateTimeImmutable($flat['authored']))->format('c');
        }

        $url = $slug ?? ($flat['url'] ?? null);
        if (\is_string($url) && '' !== $url) {
            $data['url'] = $url;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $flat
     *
     * @return array{id: int}|null
     */
    private function mediaReference(array $flat, string $key): ?array
    {
        $value = $flat[$key] ?? null;
        if (\is_array($value) && isset($value['id'])) {
            return ['id' => (int) $value['id']];
        }

        $id = $flat[$key . 'Id'] ?? null;

        return null !== $id && '' !== $id ? ['id' => (int) $id] : null;
    }

    private function migrateSettings(bool $sourceExists): bool
    {
        if (!$sourceExists || null !== $this->entityManager->getRepository(GlossaryEntrySettings::class)->findOneBy([])) {
            return false;
        }

        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::SETTINGS_TABLE . ' ORDER BY id LIMIT 1');
        if (false === $row) {
            return false;
        }

        $settings = new GlossaryEntrySettings();
        $settings->setToggleHeader(isset($row['toggleHeader']) ? (bool) $row['toggleHeader'] : null);
        $settings->setToggleHero(isset($row['toggleHero']) ? (bool) $row['toggleHero'] : null);
        $settings->setToggleBreadcrumbs(isset($row['toggleBreadcrumbs']) ? (bool) $row['toggleBreadcrumbs'] : null);
        $settings->setPageGlossary(isset($row['pageAbbreviations']) ? (string) $row['pageAbbreviations'] : null);

        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $json): array
    {
        if (\is_array($json)) {
            return $json;
        }

        if (!\is_string($json) || '' === $json) {
            return [];
        }

        $decoded = json_decode($json, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
