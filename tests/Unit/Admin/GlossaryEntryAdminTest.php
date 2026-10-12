<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\Admin;

use Manuxi\SuluGlossaryBundle\Admin\GlossaryEntryAdmin;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\View\ActivityViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\ReferenceBundle\Infrastructure\Sulu\Admin\View\ReferenceViewBuilderFactoryInterface;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;

class GlossaryEntryAdminTest extends TestCase
{
    private SecurityCheckerInterface&MockObject $securityChecker;
    private ContentViewBuilderFactoryInterface&MockObject $contentViewBuilderFactory;
    private LocalizationManagerInterface&MockObject $localizationManager;
    private ActivityViewBuilderFactoryInterface&MockObject $activityViewBuilderFactory;
    private ReferenceViewBuilderFactoryInterface&MockObject $referenceViewBuilderFactory;

    protected function setUp(): void
    {
        $this->securityChecker = $this->createMock(SecurityCheckerInterface::class);
        $this->contentViewBuilderFactory = $this->createMock(ContentViewBuilderFactoryInterface::class);
        $this->localizationManager = $this->createMock(LocalizationManagerInterface::class);
        $this->activityViewBuilderFactory = $this->createMock(ActivityViewBuilderFactoryInterface::class);
        $this->referenceViewBuilderFactory = $this->createMock(ReferenceViewBuilderFactoryInterface::class);

        $this->localizationManager->method('getLocales')->willReturn(['en', 'de']);
        $this->contentViewBuilderFactory->method('getDefaultToolbarActions')->willReturn([
            'save' => new ToolbarAction('sulu_admin.save_with_publishing'),
            'delete' => new ToolbarAction('sulu_admin.delete'),
            'edit' => new DropdownToolbarAction('sulu_admin.edit', 'su-pen', [
                new ToolbarAction('sulu_admin.delete_draft'),
                new ToolbarAction('sulu_admin.set_unpublished'),
            ]),
        ]);
        $this->contentViewBuilderFactory->method('createViews')->willReturn([]);
    }

    private function createAdmin(): GlossaryEntryAdmin
    {
        return new GlossaryEntryAdmin(
            new ViewBuilderFactory(),
            $this->contentViewBuilderFactory,
            $this->securityChecker,
            $this->localizationManager,
            $this->activityViewBuilderFactory,
            $this->referenceViewBuilderFactory,
        );
    }

    /**
     * @param string[] $permissions
     */
    private function grant(array $permissions): void
    {
        $this->securityChecker->method('hasPermission')->willReturnCallback(
            static fn (string $context, string $permission): bool => GlossaryEntry::SECURITY_CONTEXT === $context && \in_array($permission, $permissions, true)
        );
    }

    public function testNavigationItemIsAddedWithEditPermission(): void
    {
        $this->grant([PermissionTypes::EDIT]);
        $collection = new NavigationItemCollection();

        $this->createAdmin()->configureNavigationItems($collection);

        $this->assertTrue($collection->has(GlossaryEntryAdmin::NAV_ITEM));
        $this->assertSame(GlossaryEntryAdmin::LIST_VIEW, $collection->get(GlossaryEntryAdmin::NAV_ITEM)->getView());
    }

    public function testNavigationItemIsMissingWithoutEditPermission(): void
    {
        $this->grant([PermissionTypes::VIEW]);
        $collection = new NavigationItemCollection();

        $this->createAdmin()->configureNavigationItems($collection);

        $this->assertFalse($collection->has(GlossaryEntryAdmin::NAV_ITEM));
    }

    public function testNoViewsWithoutEditPermission(): void
    {
        $this->grant([PermissionTypes::VIEW]);
        $views = new ViewCollection();

        $this->createAdmin()->configureViews($views);

        $this->assertSame([], $views->all());
    }

    public function testListAddAndEditViewsAreRegistered(): void
    {
        $this->grant([PermissionTypes::EDIT, PermissionTypes::ADD, PermissionTypes::DELETE, PermissionTypes::VIEW]);
        $views = new ViewCollection();

        $this->createAdmin()->configureViews($views);

        $this->assertTrue($views->has(GlossaryEntryAdmin::LIST_VIEW));
        $this->assertTrue($views->has(GlossaryEntryAdmin::ADD_TABS_VIEW));
        $this->assertTrue($views->has(GlossaryEntryAdmin::EDIT_TABS_VIEW));
    }

    public function testListToolbarDependsOnPermissions(): void
    {
        $this->grant([PermissionTypes::EDIT, PermissionTypes::ADD]);
        $views = new ViewCollection();

        $this->createAdmin()->configureViews($views);

        $listView = $views->get(GlossaryEntryAdmin::LIST_VIEW)->getView();
        $types = array_map(static fn (ToolbarAction $action): string => $action->getType(), $listView->getOption('toolbarActions'));
        $this->assertSame(['sulu_admin.add'], $types);
    }

    public function testActivityAndReferenceViewsAreAddedWhenPermitted(): void
    {
        $this->grant([PermissionTypes::EDIT]);
        $this->activityViewBuilderFactory->method('hasActivityListPermission')->willReturn(true);
        $this->referenceViewBuilderFactory->method('hasReferenceListPermission')->willReturn(true);
        $this->activityViewBuilderFactory->method('createActivityListViewBuilder')->willReturnCallback(
            static fn (string $name, string $path, string $resourceKey) => (new ViewBuilderFactory())->createListViewBuilder($name, $path)->setResourceKey($resourceKey)->setListKey($resourceKey)
        );
        $this->referenceViewBuilderFactory->method('createReferenceListViewBuilder')->willReturnCallback(
            static fn (string $name, string $path, string $resourceKey) => (new ViewBuilderFactory())->createListViewBuilder($name, $path)->setResourceKey($resourceKey)->setListKey($resourceKey)
        );
        $views = new ViewCollection();

        $this->createAdmin()->configureViews($views);

        $this->assertTrue($views->has(GlossaryEntryAdmin::EDIT_INSIGHTS_VIEW . '.activity'));
        $this->assertTrue($views->has(GlossaryEntryAdmin::EDIT_INSIGHTS_VIEW . '.references'));
    }

    public function testSecurityContextsContainAllPermissionTypes(): void
    {
        $contexts = $this->createAdmin()->getSecurityContexts();

        $this->assertEqualsCanonicalizing(
            [PermissionTypes::VIEW, PermissionTypes::ADD, PermissionTypes::EDIT, PermissionTypes::DELETE, PermissionTypes::LIVE],
            $contexts['Sulu']['Glossary'][GlossaryEntry::SECURITY_CONTEXT]
        );
    }
}
