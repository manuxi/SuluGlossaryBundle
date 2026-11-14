<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\DependencyInjection;

use Manuxi\SuluDashboardBundle\Widget\DashboardWidgetInterface;
use Manuxi\SuluGlossaryBundle\Dashboard\GlossaryWidget;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DashboardRegistrationTest extends TestCase
{
    public function testTileServiceIsAutoconfiguredAndOnlyNeedsExistingServices(): void
    {
        if (!interface_exists(DashboardWidgetInterface::class)) {
            self::markTestSkipped('manuxi/sulu-dashboard-bundle is not available (set SULU_DASHBOARD_BUNDLE_PATH).');
        }

        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config'));
        $loader->load('dashboard.yaml');

        $definition = $container->getDefinition(GlossaryWidget::class);
        self::assertTrue($definition->isAutoconfigured());
        self::assertCount(2, $definition->getArguments());
        self::assertSame('sulu.core.localization_manager', (string) $definition->getArgument(1));
    }

    public function testDashboardIsNotRequired(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../../../composer.json'), true);

        self::assertArrayHasKey('manuxi/sulu-dashboard-bundle', $composer['suggest']);
        self::assertArrayNotHasKey('manuxi/sulu-dashboard-bundle', $composer['require']);
    }
}
