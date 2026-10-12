<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\Tests\Unit\DependencyInjection;

use Manuxi\SuluGlossaryBundle\DependencyInjection\SuluGlossaryExtension;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class SuluGlossaryExtensionTest extends TestCase
{
    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/project');

        foreach (['doctrine', 'sulu_admin'] as $alias) {
            $container->registerExtension(new class($alias) extends Extension {
                public function __construct(private readonly string $alias)
                {
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }

        return $container;
    }

    public function testPrependRegistersDoctrineMapping(): void
    {
        $container = $this->createContainer();

        (new SuluGlossaryExtension())->prepend($container);

        $config = $container->getExtensionConfig('doctrine')[0];
        $mapping = $config['orm']['mappings']['SuluGlossaryBundle'];
        $this->assertSame('xml', $mapping['type']);
        $this->assertSame('Manuxi\SuluGlossaryBundle\Entity', $mapping['prefix']);
        $this->assertDirectoryExists($mapping['dir']);
    }

    public function testPrependRegistersAdminConfiguration(): void
    {
        $container = $this->createContainer();

        (new SuluGlossaryExtension())->prepend($container);

        $config = $container->getExtensionConfig('sulu_admin')[0];
        $this->assertDirectoryExists($config['lists']['directories'][0]);
        $this->assertDirectoryExists($config['forms']['directories'][0]);
        $this->assertDirectoryExists($config['templates'][GlossaryEntry::TEMPLATE_TYPE]['directories']['bundle']);
        $this->assertSame('default', $config['templates'][GlossaryEntry::TEMPLATE_TYPE]['default_type']);
        $this->assertSame('sulu_glossary.get_glossary_entries', $config['resources'][GlossaryEntry::RESOURCE_KEY]['routes']['list']);
        $this->assertSame('sulu_glossary.get_glossary_entry_versions', $config['resources'][GlossaryEntry::VERSIONS_RESOURCE_KEY]['routes']['list']);
        $this->assertArrayHasKey('glossary_entry_selection', $config['field_type_options']['selection']);
        $this->assertArrayHasKey('single_glossary_entry_selection', $config['field_type_options']['single_selection']);
    }

    public function testPrependSkipsMissingExtensions(): void
    {
        $container = new ContainerBuilder();

        (new SuluGlossaryExtension())->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('doctrine'));
    }

    public function testLoadSetsParametersAndServices(): void
    {
        $container = new ContainerBuilder();

        (new SuluGlossaryExtension())->load([['versioning' => ['max_versions' => 3], 'twig' => ['auto_abbr' => true]]], $container);

        $this->assertSame(3, $container->getParameter('sulu_glossary.versioning.max_versions'));
        $this->assertTrue($container->getParameter('sulu_glossary.twig.auto_abbr'));
        $this->assertTrue($container->hasDefinition('Manuxi\SuluGlossaryBundle\Controller\Admin\GlossaryEntryController'));
    }
}
