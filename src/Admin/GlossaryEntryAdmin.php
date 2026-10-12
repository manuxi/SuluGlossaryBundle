<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Admin;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\View\ActivityViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactoryInterface;
use Sulu\Bundle\ReferenceBundle\Infrastructure\Sulu\Admin\View\ReferenceViewBuilderFactoryInterface;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;

class GlossaryEntryAdmin extends Admin
{
    public const NAV_ITEM = 'sulu_glossary.glossary';

    public const LIST_VIEW = 'sulu_glossary.glossary_entry.list';
    public const ADD_TABS_VIEW = 'sulu_glossary.glossary_entry.add_tabs';
    public const EDIT_TABS_VIEW = 'sulu_glossary.glossary_entry.edit_tabs';
    public const EDIT_INSIGHTS_VIEW = self::EDIT_TABS_VIEW . '.insights';
    public const EDIT_AUTOMATION_VIEW = self::EDIT_TABS_VIEW . '.automation';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly ContentViewBuilderFactoryInterface $contentViewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly LocalizationManagerInterface $localizationManager,
        private readonly ActivityViewBuilderFactoryInterface $activityViewBuilderFactory,
        private readonly ReferenceViewBuilderFactoryInterface $referenceViewBuilderFactory,
        private readonly ?AutomationViewBuilderFactoryInterface $automationViewBuilderFactory = null,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if (!$this->hasPermission(PermissionTypes::EDIT)) {
            return;
        }

        $rootNavigationItem = new NavigationItem(static::NAV_ITEM);
        $rootNavigationItem->setIcon('su-tag-pen');
        $rootNavigationItem->setPosition(31);
        $rootNavigationItem->setView(static::LIST_VIEW);

        $entriesNavigationItem = new NavigationItem(static::NAV_ITEM);
        $entriesNavigationItem->setPosition(10);
        $entriesNavigationItem->setView(static::LIST_VIEW);

        $rootNavigationItem->addChild($entriesNavigationItem);

        $navigationItemCollection->add($rootNavigationItem);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->hasPermission(PermissionTypes::EDIT)) {
            return;
        }

        $locales = $this->localizationManager->getLocales();
        $resourceKey = GlossaryEntry::RESOURCE_KEY;

        $listToolbarActions = [];
        if ($this->hasPermission(PermissionTypes::ADD)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.add');
        }
        if ($this->hasPermission(PermissionTypes::DELETE)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }
        if ($this->hasPermission(PermissionTypes::VIEW)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.export');
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder(static::LIST_VIEW, '/' . $resourceKey . '/:locale')
                ->setResourceKey($resourceKey)
                ->setListKey(GlossaryEntry::LIST_KEY)
                ->setTitle('sulu_glossary.glossary_entries')
                ->addListAdapters(['table'])
                ->addLocales($locales)
                ->setDefaultLocale($locales[0])
                ->setAddView(static::ADD_TABS_VIEW)
                ->setEditView(static::EDIT_TABS_VIEW)
                ->addToolbarActions($listToolbarActions)
        );

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(static::ADD_TABS_VIEW, '/' . $resourceKey . '/:locale/add')
                ->setResourceKey($resourceKey)
                ->addLocales($locales)
                ->setBackView(static::LIST_VIEW)
        );

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(static::EDIT_TABS_VIEW, '/' . $resourceKey . '/:locale/:id')
                ->setResourceKey($resourceKey)
                ->addLocales($locales)
                ->setBackView(static::LIST_VIEW)
                ->setTitleProperty('title')
        );

        foreach ($this->contentViewBuilderFactory->createViews(
            GlossaryEntry::class,
            static::EDIT_TABS_VIEW,
            static::ADD_TABS_VIEW,
            GlossaryEntry::SECURITY_CONTEXT,
            $this->getFormToolbarActions(\count($locales) > 1),
        ) as $viewBuilder) {
            $viewCollection->add($viewBuilder);
        }

        $this->configureInsightsViews($viewCollection);
        $this->configureAutomationView($viewCollection);
    }

    /**
     * @return mixed[]
     */
    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Glossary' => [
                    GlossaryEntry::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                        PermissionTypes::LIVE,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, ToolbarAction>
     */
    private function getFormToolbarActions(bool $multipleLocales): array
    {
        $toolbarActions = $this->contentViewBuilderFactory->getDefaultToolbarActions(GlossaryEntry::class);

        $deleteVisibleCondition = '(!_permissions || _permissions.delete)';
        $toolbarActions['delete'] = new DropdownToolbarAction(
            'sulu_admin.delete',
            'su-trash-alt',
            [
                new ToolbarAction('sulu_admin.delete', ['visible_condition' => $deleteVisibleCondition]),
                new ToolbarAction('sulu_admin.delete', [
                    'visible_condition' => $deleteVisibleCondition . ' && id != null',
                    'delete_locale' => true,
                ]),
            ]
        );

        $editActions = [];
        if ($multipleLocales) {
            $editActions[] = new ToolbarAction('sulu_admin.copy_locale', [
                'visible_condition' => '(!_permissions || _permissions.edit) && id != null',
            ]);
        }

        $editToolbarAction = $toolbarActions['edit'] ?? null;
        if ($editToolbarAction instanceof DropdownToolbarAction) {
            /** @var ToolbarAction[] $defaultEditActions */
            $defaultEditActions = $editToolbarAction->getOptions()['toolbarActions'] ?? [];
            $editActions = array_merge($editActions, $defaultEditActions);
        }

        $toolbarActions['edit'] = new DropdownToolbarAction('sulu_admin.edit', 'su-pen', $editActions);

        return $toolbarActions;
    }

    private function configureInsightsViews(ViewCollection $viewCollection): void
    {
        if ($this->activityViewBuilderFactory->hasActivityListPermission()) {
            $viewCollection->add(
                $this->activityViewBuilderFactory
                    ->createActivityListViewBuilder(
                        static::EDIT_INSIGHTS_VIEW . '.activity',
                        '/activities',
                        GlossaryEntry::RESOURCE_KEY
                    )
                    ->setParent(static::EDIT_INSIGHTS_VIEW)
            );
        }

        if ($this->referenceViewBuilderFactory->hasReferenceListPermission()) {
            $viewCollection->add(
                $this->referenceViewBuilderFactory
                    ->createReferenceListViewBuilder(
                        static::EDIT_INSIGHTS_VIEW . '.references',
                        '/references',
                        GlossaryEntry::RESOURCE_KEY
                    )
                    ->setParent(static::EDIT_INSIGHTS_VIEW)
            );
        }
    }

    private function configureAutomationView(ViewCollection $viewCollection): void
    {
        if (null === $this->automationViewBuilderFactory || !$this->hasPermission(PermissionTypes::LIVE)) {
            return;
        }

        $viewCollection->add(
            $this->automationViewBuilderFactory
                ->createTaskListViewBuilder(static::EDIT_AUTOMATION_VIEW, '/automation', GlossaryEntry::class)
                ->setTabOrder(5120)
                ->setParent(static::EDIT_TABS_VIEW)
        );
    }

    private function hasPermission(string $permission): bool
    {
        return $this->securityChecker->hasPermission(GlossaryEntry::SECURITY_CONTEXT, $permission);
    }
}
