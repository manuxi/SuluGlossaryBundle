<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Dashboard;

use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Manuxi\SuluGlossaryBundle\Admin\GlossaryEntryAdmin;
use Manuxi\SuluGlossaryBundle\Dashboard\GlossaryWidget;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntryDimensionContent;
use Manuxi\SuluGlossaryBundle\Repository\GlossaryEntryRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Content\Domain\Model\DimensionContentInterface;

class GlossaryWidgetTest extends TestCase
{
    /** @var GlossaryEntryRepositoryInterface&MockObject */
    private GlossaryEntryRepositoryInterface $repository;

    /**
     * Entries by locale and stage: ['de' => ['draft' => [uuid, ...], 'live' => [uuid, ...]]].
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $stored = [];

    /** @var list<string> */
    private array $all = [];

    /** @var array<string, array<string, string>> titles by uuid and locale */
    private array $titles = [];

    protected function setUp(): void
    {
        if (!interface_exists(DashboardWidgetInterface::class)) {
            self::markTestSkipped('manuxi/sulu-dashboard-bundle is not available (set SULU_DASHBOARD_BUNDLE_PATH).');
        }

        $this->repository = $this->createMock(GlossaryEntryRepositoryInterface::class);
        $this->repository->method('countBy')->willReturnCallback(function (array $filters = []): int {
            return isset($filters['locale'], $filters['stage'])
                ? \count($this->stored[$filters['locale']][$filters['stage']] ?? [])
                : \count($this->all);
        });
        $this->repository->method('findIdentifiersBy')->willReturnCallback(function (array $filters = []): array {
            return isset($filters['locale'], $filters['stage'])
                ? ($this->stored[$filters['locale']][$filters['stage']] ?? [])
                : $this->all;
        });
        $this->repository->method('findBy')->willReturnCallback(function (array $filters): array {
            $entries = [];
            foreach ($filters['uuids'] as $uuid) {
                $entry = new GlossaryEntry($uuid);
                foreach ($this->titles[$uuid] ?? [] as $locale => $title) {
                    $content = new GlossaryEntryDimensionContent($entry);
                    $content->setLocale($locale);
                    $content->setStage(DimensionContentInterface::STAGE_DRAFT);
                    $content->setTemplateData(['title' => $title]);
                    $entry->addDimensionContent($content);
                }
                $entries[] = $entry;
            }

            return $entries;
        });
    }

    public function testMetaData(): void
    {
        $widget = $this->widget(['de']);

        self::assertInstanceOf(DashboardWidgetInterface::class, $widget);
        self::assertSame('glossary', $widget->getKey());
        self::assertSame('progress', $widget->getType());
        self::assertSame('side', $widget->getColumn());
        self::assertSame(GlossaryEntry::SECURITY_CONTEXT, $widget->getSecurityContext());
        self::assertSame('sulu_glossary.glossary', $widget->getTitle());
    }

    public function testEmptyGlossary(): void
    {
        $data = $this->widget(['de', 'en'])->getData('de');

        self::assertSame('sulu_glossary.dashboard_empty', $data['text']);
        self::assertSame([], $data['items']);
        self::assertArrayNotHasKey('footer', $data);
    }

    public function testSingleLanguageShowsOnlyTheSummary(): void
    {
        $this->add('a', ['de' => ['draft', 'live']]);
        $this->add('b', ['de' => ['draft']]);

        $data = $this->widget(['de'])->getData('de');

        self::assertSame(
            ['key' => 'sulu_glossary.dashboard_summary_unpublished', 'parameters' => ['total' => 2, 'unpublished' => 1, 'language' => '']],
            $data['text']
        );
        self::assertSame([], $data['items']);
        self::assertArrayNotHasKey('footer', $data);
    }

    public function testEverythingPublishedAndTranslated(): void
    {
        $this->add('a', ['de' => ['draft', 'live'], 'en' => ['draft', 'live']]);

        $data = $this->widget(['de', 'en'])->getData('de');

        self::assertSame(['key' => 'sulu_glossary.dashboard_summary', 'parameters' => ['total' => 1]], $data['text']);
        self::assertCount(2, $data['items']);
        self::assertSame('success', $data['items'][0]['skin']);
        self::assertSame('success', $data['items'][1]['skin']);
        self::assertArrayNotHasKey('note', $data['items'][0]);
        self::assertArrayNotHasKey('footer', $data);
    }

    public function testMissingTranslationsAndUnpublishedEntries(): void
    {
        $this->add('a', ['de' => ['draft', 'live'], 'en' => ['draft']], ['de' => 'Alpha', 'en' => 'Alpha']);
        $this->add('b', ['de' => ['draft', 'live']], ['de' => 'Beta']);
        $this->add('c', ['de' => ['draft']], ['de' => 'Gamma']);

        $data = $this->widget(['de', 'en'])->getData('en');

        self::assertSame('sulu_glossary.dashboard_summary_unpublished_language', $data['text']['key']);
        self::assertSame(['total' => 3, 'unpublished' => 1, 'language' => 'EN'], $data['text']['parameters']);
        self::assertSame('bar', $data['display']);

        [$de, $en] = $data['items'];
        self::assertSame('DE', $de['label']);
        self::assertSame(3, $de['value']);
        self::assertSame(3, $de['max']);
        self::assertSame('success', $de['skin']);
        self::assertSame(['count' => 1], $de['note']['parameters']);
        self::assertSame(1, $en['value']);
        self::assertSame('warning', $en['skin']);
        self::assertSame(['done' => 1, 'total' => 3], $en['text']['parameters']);
        self::assertSame(['count' => 1], $en['note']['parameters']);

        self::assertSame('sulu_glossary.dashboard_missing', $data['footer']['title']);
        $rows = $data['footer']['rows'];
        self::assertCount(2, $rows);
        self::assertSame('Beta (EN)', $rows[0]['title']);
        self::assertSame('Gamma (EN)', $rows[1]['title']);
        self::assertSame(
            ['view' => GlossaryEntryAdmin::EDIT_TABS_VIEW, 'attributes' => ['id' => 'b', 'locale' => 'en']],
            $rows[0]['link']
        );
    }

    public function testMissingListIsLimited(): void
    {
        for ($i = 1; $i <= 8; ++$i) {
            $this->add('e' . $i, ['de' => ['draft', 'live']], ['de' => 'Eintrag ' . $i]);
        }

        $data = $this->widget(['de', 'en'])->getData('de');

        self::assertCount(GlossaryWidget::MISSING_LIMIT, $data['footer']['rows']);
        self::assertSame('warning', $data['items'][1]['skin']);
        self::assertSame(0, $data['items'][1]['value']);
    }

    public function testTitleComesFromTheDefaultLanguageOfTheFirstWebspace(): void
    {
        $this->add('a', ['de' => ['draft', 'live'], 'en' => ['draft', 'live']], ['de' => 'Alpha DE', 'en' => 'Alpha EN']);
        $this->add('b', ['de' => ['draft', 'live']], ['de' => 'Beta DE']);
        $this->add('c', ['en' => ['draft', 'live']], ['en' => 'Gamma EN']);

        $rows = $this->widget(['de', 'en'], 'en')->getData('de')['footer']['rows'];

        self::assertSame(['Gamma EN (DE)', 'Beta DE (EN)'], array_column($rows, 'title'));
    }

    public function testEntryWithoutTitleFallsBackToItsId(): void
    {
        $this->add('only-id', ['de' => ['draft', 'live']]);

        $rows = $this->widget(['de', 'en'])->getData('de')['footer']['rows'];

        self::assertSame('only-id (EN)', $rows[0]['title']);
    }

    /**
     * @param array<string, list<string>> $stages stages per locale
     * @param array<string, string>       $titles
     */
    private function add(string $uuid, array $stages, array $titles = []): void
    {
        $this->all[] = $uuid;
        foreach ($stages as $locale => $list) {
            foreach ($list as $stage) {
                $this->stored[$locale][$stage][] = $uuid;
            }
        }
        $this->titles[$uuid] = $titles;
    }

    /**
     * @param list<string> $locales
     */
    private function widget(array $locales, ?string $defaultLocale = null): GlossaryWidget
    {
        $webspaces = [];
        if (null !== $defaultLocale) {
            $localization = new Localization($defaultLocale);
            $webspace = $this->createMock(Webspace::class);
            $webspace->method('getDefaultLocalization')->willReturn($localization);
            $webspaces['test'] = $webspace;
        }
        $webspaceManager = $this->createMock(WebspaceManagerInterface::class);
        $webspaceManager->method('getWebspaceCollection')->willReturn(new WebspaceCollection($webspaces));

        $localizationManager = $this->createMock(LocalizationManagerInterface::class);
        $localizationManager->method('getLocales')->willReturn($locales);

        return new GlossaryWidget($this->repository, $localizationManager, $webspaceManager);
    }
}
