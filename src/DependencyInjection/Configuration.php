<?php

declare(strict_types=1);

namespace Manuxi\SuluGlossaryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sulu_glossary');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->arrayNode('versioning')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_versions')
                            ->min(0)
                            ->defaultValue(20)
                            ->info('Number of versions kept per glossary entry and locale. 0 keeps all versions.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('twig')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('auto_abbr')
                            ->defaultFalse()
                            ->info('Registers the "abbr" twig filter which wraps known abbreviations and acronyms in abbr tags.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
