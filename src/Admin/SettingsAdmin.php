<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Admin;

use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

class SettingsAdmin extends Admin
{
    public const TAB_VIEW = 'sulu_glossary.config';
    public const FORM_VIEW = 'sulu_glossary.config.form';
    public const NAV_ITEM = 'sulu_glossary.config.title.navi';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if (!$this->securityChecker->hasPermission(GlossaryEntrySettings::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            return;
        }

        $module = $navigationItemCollection->get(GlossaryEntryAdmin::NAV_ITEM);
        $settings = new NavigationItem(static::NAV_ITEM);
        $settings->setPosition(20);
        $settings->setView(static::TAB_VIEW);

        $module->addChild($settings);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(GlossaryEntrySettings::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            return;
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(static::TAB_VIEW, '/glossary-settings/:id')
                ->setResourceKey(GlossaryEntrySettings::RESOURCE_KEY)
                ->setAttributeDefault('id', '-')
        );

        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder(static::FORM_VIEW, '/config')
                ->setResourceKey(GlossaryEntrySettings::RESOURCE_KEY)
                ->setFormKey(GlossaryEntrySettings::FORM_KEY)
                ->setTabTitle('sulu_glossary.config.tab')
                ->addToolbarActions([new ToolbarAction('sulu_admin.save')])
                ->setParent(static::TAB_VIEW)
        );
    }

    /**
     * @return mixed[]
     */
    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Glossary' => [
                    GlossaryEntrySettings::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::EDIT,
                    ],
                ],
            ],
        ];
    }
}
