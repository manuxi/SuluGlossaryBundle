<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Content\PropertyResolver;

use Manuxi\SuluGlossaryBundle\Content\ResourceLoader\GlossaryEntryResourceLoader;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\PropertyResolver\Resolver\PropertyResolverInterface;

class SingleGlossaryEntrySelectionPropertyResolver implements PropertyResolverInterface
{
    /**
     * @param array{
     *     resourceLoader?: string,
     *     properties?: array<string, mixed>|null,
     * } $params
     */
    public function resolve(mixed $data, string $locale, array $params = []): ContentView
    {
        if (!\is_string($data) || '' === $data) {
            return ContentView::create(null, ['id' => null, ...$params]);
        }

        /** @var string $resourceLoaderKey */
        $resourceLoaderKey = $params['resourceLoader'] ?? GlossaryEntryResourceLoader::getKey();

        return ContentView::createResolvableWithReferences(
            id: $data,
            resourceKey: GlossaryEntry::RESOURCE_KEY,
            resourceLoaderKey: $resourceLoaderKey,
            view: ['id' => $data, ...$params],
            priority: 100,
            metadata: [
                'properties' => $params['properties'] ?? null,
            ],
        );
    }

    public static function getType(): string
    {
        return 'single_glossary_entry_selection';
    }
}
