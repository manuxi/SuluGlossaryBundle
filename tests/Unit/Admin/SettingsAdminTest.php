<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Admin;

use Manuxi\SuluGlossaryBundle\Admin\GlossaryEntryAdmin;
use Manuxi\SuluGlossaryBundle\Admin\SettingsAdmin;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

class SettingsAdminTest extends TestCase
{
    private function createAdmin(bool $allowed): SettingsAdmin
    {
        $securityChecker = $this->createMock(SecurityCheckerInterface::class);
        $securityChecker->method('hasPermission')->with(GlossaryEntrySettings::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn($allowed);

        return new SettingsAdmin(new ViewBuilderFactory(), $securityChecker);
    }

    public function testNavigationItemIsAddedBelowTheGlossaryItem(): void
    {
        $collection = new NavigationItemCollection();
        $collection->add(new NavigationItem(GlossaryEntryAdmin::NAV_ITEM));

        $this->createAdmin(true)->configureNavigationItems($collection);

        $children = $collection->get(GlossaryEntryAdmin::NAV_ITEM)->getChildren();
        $this->assertCount(1, $children);
        $this->assertSame(SettingsAdmin::TAB_VIEW, array_values($children)[0]->getView());
    }

    public function testNothingIsAddedWithoutPermission(): void
    {
        $collection = new NavigationItemCollection();
        $views = new ViewCollection();
        $admin = $this->createAdmin(false);

        $admin->configureNavigationItems($collection);
        $admin->configureViews($views);

        $this->assertSame([], $views->all());
    }

    public function testViewsAreRegisteredWithPermission(): void
    {
        $views = new ViewCollection();

        $this->createAdmin(true)->configureViews($views);

        $this->assertTrue($views->has(SettingsAdmin::TAB_VIEW));
        $this->assertTrue($views->has(SettingsAdmin::FORM_VIEW));
    }

    public function testSecurityContexts(): void
    {
        $contexts = $this->createAdmin(true)->getSecurityContexts();

        $this->assertSame(
            [PermissionTypes::VIEW, PermissionTypes::EDIT],
            $contexts['Sulu']['Glossary'][GlossaryEntrySettings::SECURITY_CONTEXT]
        );
    }
}
