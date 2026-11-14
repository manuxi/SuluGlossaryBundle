<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Dashboard;

use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Manuxi\SuluDashboardBundle\Widget\WidgetColumns;
use Manuxi\SuluDashboardBundle\Widget\WidgetTypes;
use Manuxi\SuluGlossaryBundle\Admin\GlossaryEntryAdmin;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Languages;

/**
 * Tile for the dashboard of manuxi/sulu-dashboard-bundle: number of glossary entries, how many of them are not
 * published and, per language, how many are translated.
 *
 * This class is only loaded when the dashboard bundle is installed (see SuluGlossaryExtension), so the dashboard stays
 * an optional dependency ("suggest"). The data is read through the repository of the bundle.
 *
 * The recent changes are not repeated here: the "Recent activity" tile of the dashboard shows the glossary events.
 */
final class GlossaryWidget implements DashboardWidgetInterface
{
    public const KEY = 'glossary';

    /** Rows of "What is missing?". */
    public const MISSING_LIMIT = 5;

    public function __construct(
        private readonly GlossaryEntryRepositoryInterface $repository,
        private readonly LocalizationManagerInterface $localizationManager,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getTitle(): string
    {
        return 'sulu_glossary.glossary';
    }

    public function getType(): string
    {
        return WidgetTypes::PROGRESS;
    }

    public function getSecurityContext(): string
    {
        return GlossaryEntry::SECURITY_CONTEXT;
    }

    public function getColumn(): string
    {
        return WidgetColumns::SIDE;
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function getData(string $locale): array
    {
        $locales = array_values($this->localizationManager->getLocales());
        $total = $this->repository->countBy();

        if ([] === $locales) {
            return $this->result($this->summary($total, null, null, $locale), [], []);
        }

        $translated = [];
        $unpublished = [];
        foreach ($locales as $entryLocale) {
            $translated[$entryLocale] = $this->repository->countBy(['locale' => $entryLocale, 'stage' => DimensionContentInterface::STAGE_DRAFT]);
            $live = $this->repository->countBy(['locale' => $entryLocale, 'stage' => DimensionContentInterface::STAGE_LIVE]);
            $unpublished[$entryLocale] = max(0, $translated[$entryLocale] - $live);
        }

        $current = \in_array($locale, $locales, true) ? $locale : $locales[0];
        $multiple = \count($locales) > 1;
        $summary = $this->summary($total, $unpublished[$current], $multiple ? $current : null, $locale);

        if (!$multiple || 0 === $total) {
            return $this->result($summary, [], []);
        }

        $items = [];
        foreach ($locales as $entryLocale) {
            $missing = max(0, $total - $translated[$entryLocale]);
            $item = [
                'label' => $this->languageName($entryLocale, $locale),
                'value' => $translated[$entryLocale],
                'max' => $total,
                'text' => [
                    'key' => 'sulu_glossary.dashboard_translated',
                    'parameters' => ['done' => $translated[$entryLocale], 'total' => $total],
                ],
                'skin' => 0 === $missing ? 'success' : 'warning',
            ];

            if ($unpublished[$entryLocale] > 0) {
                $item['note'] = [
                    'key' => 'sulu_glossary.dashboard_unpublished',
                    'parameters' => ['count' => $unpublished[$entryLocale]],
                ];
            }

            $items[] = $item;
        }

        return $this->result($summary, $items, $this->missingRows($locales));
    }

    /**
     * @param list<string> $locales
     *
     * @return list<array<string, mixed>>
     */
    private function missingRows(array $locales): array
    {
        $allIds = array_values(array_map('strval', [...$this->repository->findIdentifiersBy()]));
        $missing = [];
        $count = 0;

        foreach ($locales as $entryLocale) {
            $present = array_map('strval', [...$this->repository->findIdentifiersBy(
                ['locale' => $entryLocale, 'stage' => DimensionContentInterface::STAGE_DRAFT]
            )]);
            $ids = \array_slice(array_values(array_diff($allIds, $present)), 0, self::MISSING_LIMIT - $count);

            if ([] === $ids) {
                continue;
            }

            $missing[$entryLocale] = $ids;
            $count += \count($ids);

            if ($count >= self::MISSING_LIMIT) {
                break;
            }
        }

        if ([] === $missing) {
            return [];
        }

        $titles = $this->titles(array_merge(...array_values($missing)), $locales[0]);
        $rows = [];
        foreach ($missing as $entryLocale => $ids) {
            foreach ($ids as $id) {
                $rows[] = [
                    'title' => \sprintf('%s (%s)', $titles[$id] ?? $id, strtoupper($entryLocale)),
                    'icon' => 'su-tag-pen',
                    'link' => [
                        'view' => GlossaryEntryAdmin::EDIT_TABS_VIEW,
                        'attributes' => ['id' => $id, 'locale' => $entryLocale],
                    ],
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function titles(array $ids, string $sourceLocale): array
    {
        $titles = [];
        $entries = $this->repository->findBy(
            ['uuids' => $ids, 'locale' => $sourceLocale, 'stage' => DimensionContentInterface::STAGE_DRAFT, 'loadGhost' => true],
            [],
            [GlossaryEntryRepositoryInterface::GROUP_SELECT_GLOSSARY_ENTRY_ADMIN => true]
        );

        foreach ($entries as $entry) {
            $candidates = [];
            foreach ($entry->getDimensionContents() as $dimensionContent) {
                if ($dimensionContent instanceof GlossaryEntryDimensionContent
                    && DimensionContentInterface::STAGE_DRAFT === $dimensionContent->getStage()
                    && \is_string($dimensionContent->getTitle())
                    && '' !== $dimensionContent->getTitle()
                ) {
                    // the original language first, then any other
                    $candidates[$dimensionContent->getLocale() === $sourceLocale ? 0 : 1][] = $dimensionContent->getTitle();
                }
            }

            ksort($candidates);
            $first = reset($candidates);
            if (false !== $first) {
                $titles[$entry->getUuid()] = $first[0];
            }
        }

        return $titles;
    }

    /**
     * @return array<string, mixed>|string
     */
    private function summary(int $total, ?int $unpublished, ?string $language, string $uiLocale): array|string
    {
        if (0 === $total) {
            return 'sulu_glossary.dashboard_empty';
        }

        if (null === $unpublished || 0 === $unpublished) {
            return ['key' => 'sulu_glossary.dashboard_summary', 'parameters' => ['total' => $total]];
        }

        return [
            'key' => null === $language ? 'sulu_glossary.dashboard_summary_unpublished' : 'sulu_glossary.dashboard_summary_unpublished_language',
            'parameters' => [
                'total' => $total,
                'unpublished' => $unpublished,
                'language' => null === $language ? '' : $this->languageName($language, $uiLocale),
            ],
        ];
    }

    /**
     * @param array<string, mixed>|string $text
     * @param list<array<string, mixed>>  $items
     * @param list<array<string, mixed>>  $missing
     *
     * @return array<string, mixed>
     */
    private function result(array|string $text, array $items, array $missing): array
    {
        $data = ['text' => $text, 'display' => 'bar', 'items' => $items];

        if ([] !== $missing) {
            $data['footer'] = ['title' => 'sulu_glossary.dashboard_missing', 'rows' => $missing];
        }

        return $data;
    }

    private function languageName(string $language, string $uiLocale): string
    {
        $code = strtolower(explode('_', str_replace('-', '_', $language))[0]);

        try {
            return ucfirst(Languages::getName($code, explode('_', str_replace('-', '_', $uiLocale))[0]));
        } catch (MissingResourceException) {
            return strtoupper($language);
        }
    }
}
