<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Content\ResourceLoader;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ResourceLoader\Loader\ResourceLoaderContentViewEnhancementInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

class GlossaryEntryResourceLoader implements ResourceLoaderContentViewEnhancementInterface
{
    public const RESOURCE_LOADER_KEY = 'glossary_entry';

    public function __construct(
        private readonly GlossaryEntryRepositoryInterface $repository,
    ) {
    }

    /**
     * @param string[] $ids
     */
    public function load(array $ids, ?string $locale, array $params = []): array
    {
        if (null === $locale || [] === $ids) {
            return [];
        }

        $result = $this->repository->findBy(
            ['uuids' => $ids, 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
            [],
            [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => true]
        );

        $mappedResult = [];
        foreach ($result as $glossaryEntry) {
            $mappedResult[$glossaryEntry->getUuid()] = $glossaryEntry;
        }

        return $mappedResult;
    }

    public function resolveContentViewEnhancement(mixed $resource): ContentView
    {
        if (!$resource instanceof GlossaryEntryDimensionContent) {
            return ContentView::create([], []);
        }

        return ContentView::create([], [
            'uuid' => $resource->getResource()->getUuid(),
            'template' => $resource->getTemplateKey(),
            'type' => $resource->getType(),
        ]);
    }

    public static function getKey(): string
    {
        return self::RESOURCE_LOADER_KEY;
    }
}
