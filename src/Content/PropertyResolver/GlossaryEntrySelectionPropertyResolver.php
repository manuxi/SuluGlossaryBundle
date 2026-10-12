<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Content\PropertyResolver;

use Manuxi\SuluGlossaryBundle\Content\ResourceLoader\GlossaryEntryResourceLoader;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\PropertyResolver\Resolver\PropertyResolverInterface;

class GlossaryEntrySelectionPropertyResolver implements PropertyResolverInterface
{
    /**
     * @param array{
     *     resourceLoader?: string,
     *     properties?: array<string, mixed>|null,
     * } $params
     */
    public function resolve(mixed $data, string $locale, array $params = []): ContentView
    {
        if (!\is_array($data) || [] === $data || !array_is_list($data)) {
            return ContentView::create([], ['ids' => [], ...$params]);
        }

        /** @var string $resourceLoaderKey */
        $resourceLoaderKey = $params['resourceLoader'] ?? GlossaryEntryResourceLoader::getKey();

        /** @var string[] $ids */
        $ids = $data;

        return ContentView::createResolvablesWithReferences(
            ids: $ids,
            resourceLoaderKey: $resourceLoaderKey,
            resourceKey: GlossaryEntry::RESOURCE_KEY,
            view: [],
            priority: 100,
            metadata: [
                'properties' => $params['properties'] ?? null,
            ],
        );
    }

    public static function getType(): string
    {
        return 'glossary_entry_selection';
    }
}
