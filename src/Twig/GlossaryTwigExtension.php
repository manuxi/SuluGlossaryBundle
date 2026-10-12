<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Twig;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\EventSubscriber\GlossaryEntryCacheInvalidationSubscriber;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class GlossaryTwigExtension extends AbstractExtension
{
    private const DEFAULT_LOCALE = 'en';

    /**
     * @var array<string, array<string, string>>
     */
    private array $titleCache = [];

    public function __construct(
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly ContentAggregatorInterface $contentAggregator,
        private readonly ContentManagerInterface $contentManager,
        private readonly RequestStack $requestStack,
        private readonly bool $autoAbbr = false,
        private readonly ?ReferenceStoreInterface $referenceStore = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sulu_resolve_glossary_entry', [$this, 'resolveGlossaryEntry']),
            new TwigFunction('sulu_get_glossary_entries', [$this, 'getGlossaryEntries']),
        ];
    }

    public function getFilters(): array
    {
        if (!$this->autoAbbr) {
            return [];
        }

        return [
            new TwigFilter('abbr', [$this, 'wrapAbbreviations'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Returns the normalized live content of a glossary entry or null if it is not published in the locale.
     *
     * @return array<string, mixed>|null
     */
    public function resolveGlossaryEntry(string $uuid, ?string $locale = null): ?array
    {
        $locale = $this->resolveLocale($locale);

        $glossaryEntry = $this->repository->findOneBy(
            ['uuid' => $uuid, 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
            [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => true]
        );

        if (null === $glossaryEntry) {
            return null;
        }

        return $this->normalize($glossaryEntry, $locale);
    }

    /**
     * Returns the normalized live content of the published glossary entries ordered by title.
     *
     * @param string[] $types restrict the result to these types (abbreviation, acronym, term)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGlossaryEntries(int $limit = 100, ?string $locale = null, array $types = []): array
    {
        $locale = $this->resolveLocale($locale);

        $filters = ['locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE, 'limit' => $limit];
        if ([] !== $types) {
            $filters['types'] = $types;
        }

        $result = [];
        foreach ($this->repository->findBy($filters, ['title' => 'asc'], [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_WEBSITE => true]) as $glossaryEntry) {
            $normalized = $this->normalize($glossaryEntry, $locale);
            if (null !== $normalized) {
                $result[] = $normalized;
            }
        }

        $this->referenceStore?->add(GlossaryEntryCacheInvalidationSubscriber::LIST_REFERENCE_ID, GlossaryEntry::RESOURCE_KEY);

        return $result;
    }

    /**
     * Wraps every known term of the text nodes in an abbr tag. Tags like abbr, a, code, pre, script and style are skipped.
     *
     * @param string[] $types only entries of these types are used
     */
    public function wrapAbbreviations(?string $html, ?string $locale = null, array $types = [GlossaryEntryDimensionContent::TYPE_ABBREVIATION, GlossaryEntryDimensionContent::TYPE_ACRONYM]): string
    {
        if (null === $html || '' === $html) {
            return '';
        }

        $titles = $this->loadTitles($this->resolveLocale($locale), $types);
        if ([] === $titles) {
            return $html;
        }

        uksort($titles, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $pattern = '/(?<![\p{L}\p{N}])(' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), array_keys($titles))) . ')(?![\p{L}\p{N}])/u';

        $parts = preg_split('/(<[^>]*>)/', $html, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
        if (false === $parts) {
            return $html;
        }

        $result = '';
        $skip = 0;
        foreach ($parts as $part) {
            if (1 === preg_match('/^<\s*(abbr|a|script|style|code|pre)\b/i', $part)) {
                ++$skip;
                $result .= $part;

                continue;
            }

            if (1 === preg_match('/^<\s*\/\s*(abbr|a|script|style|code|pre)\b/i', $part)) {
                $skip = max(0, $skip - 1);
                $result .= $part;

                continue;
            }

            if ($skip > 0 || str_starts_with($part, '<')) {
                $result .= $part;

                continue;
            }

            $result .= preg_replace_callback(
                $pattern,
                static fn (array $match): string => \sprintf('<abbr title="%s">%s</abbr>', htmlspecialchars($titles[$match[1]], \ENT_QUOTES), $match[1]),
                $part
            ) ?? $part;
        }

        return $result;
    }

    /**
     * @param string[] $types
     *
     * @return array<string, string>
     */
    private function loadTitles(string $locale, array $types): array
    {
        $cacheKey = $locale . '|' . implode(',', $types);
        if (isset($this->titleCache[$cacheKey])) {
            return $this->titleCache[$cacheKey];
        }

        $titles = [];
        foreach ($this->getGlossaryEntries(1000, $locale, $types) as $entry) {
            $title = $entry['title'] ?? null;
            $explanation = $entry['explanation'] ?? null;
            if (\is_string($title) && '' !== $title && \is_string($explanation) && '' !== $explanation) {
                $titles[$title] = $explanation;
            }
        }

        return $this->titleCache[$cacheKey] = $titles;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalize(GlossaryEntry $glossaryEntry, string $locale): ?array
    {
        try {
            $dimensionContent = $this->contentAggregator->aggregate($glossaryEntry, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ]);
        } catch (ContentNotFoundException) {
            return null;
        }

        $this->referenceStore?->add($glossaryEntry->getUuid(), GlossaryEntry::RESOURCE_KEY);

        return $this->contentManager->normalize($dimensionContent);
    }

    private function resolveLocale(?string $locale): string
    {
        if (null !== $locale && '' !== $locale) {
            return $locale;
        }

        return $this->requestStack->getCurrentRequest()?->getLocale() ?? self::DEFAULT_LOCALE;
    }
}
