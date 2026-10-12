<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\DependencyInjection;

use Exception;
use Manuxi\SuluGlossaryBundle\Admin\GlossaryEntryAdmin;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntry;
use Manuxi\SuluGlossaryBundle\Entity\GlossaryEntrySettings;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluGlossaryExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('sulu_glossary.versioning.max_versions', $config['versioning']['max_versions']);
        $container->setParameter('sulu_glossary.twig.auto_abbr', $config['twig']['auto_abbr']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
        $loader->load('services-controller.yaml');

        if (interface_exists(\Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface::class)) {
            $loader->load('services-automation.yaml');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'SuluGlossaryBundle' => [
                            'type' => 'xml',
                            'dir' => __DIR__ . '/../Resources/config/doctrine',
                            'prefix' => 'Manuxi\SuluGlossaryBundle\Entity',
                            'alias' => 'SuluGlossaryBundle',
                        ],
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('sulu_admin')) {
            $container->prependExtensionConfig('sulu_admin', [
                'lists' => [
                    'directories' => [__DIR__ . '/../Resources/config/lists'],
                ],
                'forms' => [
                    'directories' => [__DIR__ . '/../Resources/config/forms'],
                ],
                'templates' => [
                    GlossaryEntry::TEMPLATE_TYPE => [
                        'default_type' => GlossaryEntry::DEFAULT_TEMPLATE,
                        'directories' => [
                            'bundle' => __DIR__ . '/../Resources/config/templates/glossary_entries',
                            'app' => '%kernel.project_dir%/config/templates/glossary_entries',
                        ],
                    ],
                ],
                'resources' => [
                    GlossaryEntry::RESOURCE_KEY => [
                        'routes' => [
                            'list' => 'sulu_glossary.get_glossary_entries',
                            'detail' => 'sulu_glossary.get_glossary_entry',
                        ],
                    ],
                    GlossaryEntry::VERSIONS_RESOURCE_KEY => [
                        'routes' => [
                            'list' => 'sulu_glossary.get_glossary_entry_versions',
                            'detail' => 'sulu_glossary.get_glossary_entry',
                        ],
                    ],
                    GlossaryEntrySettings::RESOURCE_KEY => [
                        'routes' => [
                            'detail' => 'sulu_glossary.get_glossary_settings',
                        ],
                    ],
                ],
                'field_type_options' => [
                    'selection' => [
                        'glossary_entry_selection' => [
                            'default_type' => 'list_overlay',
                            'resource_key' => GlossaryEntry::RESOURCE_KEY,
                            'view' => [
                                'name' => GlossaryEntryAdmin::EDIT_TABS_VIEW,
                                'result_to_view' => ['id' => 'id'],
                            ],
                            'types' => [
                                'list_overlay' => [
                                    'adapter' => 'table',
                                    'list_key' => GlossaryEntry::LIST_KEY,
                                    'display_properties' => ['title'],
                                    'icon' => 'su-tag-pen',
                                    'label' => 'sulu_glossary.glossary_entry_selection_label',
                                    'overlay_title' => 'sulu_glossary.select_glossary_entries',
                                ],
                            ],
                        ],
                    ],
                    'single_selection' => [
                        'single_glossary_entry_selection' => [
                            'default_type' => 'list_overlay',
                            'resource_key' => GlossaryEntry::RESOURCE_KEY,
                            'view' => [
                                'name' => GlossaryEntryAdmin::EDIT_TABS_VIEW,
                                'result_to_view' => ['id' => 'id'],
                            ],
                            'types' => [
                                'list_overlay' => [
                                    'adapter' => 'table',
                                    'list_key' => GlossaryEntry::LIST_KEY,
                                    'display_properties' => ['title'],
                                    'icon' => 'su-tag-pen',
                                    'empty_text' => 'sulu_glossary.no_glossary_entry_selected',
                                    'overlay_title' => 'sulu_glossary.select_glossary_entry',
                                ],
                                'auto_complete' => [
                                    'display_property' => 'title',
                                    'search_properties' => ['title'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }
    }
}
