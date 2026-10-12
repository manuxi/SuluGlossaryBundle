<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Repository;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Exception\GlossaryEntryNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;

interface GlossaryEntryRepositoryInterface
{
    public const GROUP_SELECT_GLOSSARY_ENTRY_ADMIN = 'glossary_entry_admin';
    public const GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE = 'glossary_entry_website';

    public const SELECT_GLOSSARY_ENTRY_CONTENT = 'with-glossary-entry-content';

    public function createNew(?string $uuid = null): GlossaryEntry;

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $selects
     *
     * @throws GlossaryEntryNotFoundException
     */
    public function getOneBy(array $filters, array $selects = []): GlossaryEntry;

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $selects
     */
    public function findOneBy(array $filters, array $selects = []): ?GlossaryEntry;

    /**
     * Supported filters: uuid, uuids, locale, stage, types, categoryIds, categoryKeys, categoryOperator, tagIds,
     * tagNames, tagOperator, templateKeys, loadGhost, page, limit. The "types" filter requires locale and stage.
     *
     * @param array<string, mixed>        $filters
     * @param array<string, 'asc'|'desc'> $sortBy
     * @param array<string, mixed>        $selects
     *
     * @return iterable<GlossaryEntry>
     */
    public function findBy(array $filters = [], array $sortBy = [], array $selects = []): iterable;

    /**
     * @param array<string, mixed>        $filters
     * @param array<string, 'asc'|'desc'> $sortBy
     *
     * @return iterable<string>
     */
    public function findIdentifiersBy(array $filters = [], array $sortBy = []): iterable;

    /**
     * @param array<string, mixed> $filters
     */
    public function countBy(array $filters = []): int;

    /**
     * Returns the stored versions (draft stage, version greater than zero) of the given locale.
     *
     * @return GlossaryEntryDimensionContent[]
     */
    public function findVersions(GlossaryEntry $glossaryEntry, string $locale): array;

    public function add(GlossaryEntry $glossaryEntry): void;

    public function remove(GlossaryEntry $glossaryEntry): void;

    /**
     * @param DimensionContentInterface<GlossaryEntry> $dimensionContent
     */
    public function removeDimensionContent(DimensionContentInterface $dimensionContent): void;
}
