<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\DataSentryBundle\DependencyInjection;

use Doctrine\ORM\Events;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('paradise_security_data_sentry');

        $rootNode = $treeBuilder->getRootNode();

        $this->addListenersSection($rootNode);
        $this->addEncryptorsSection($rootNode);

        return $treeBuilder;
    }

    private function addListenersSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('listeners')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('entity_manager')
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('entity_class_names')
                                ->scalarPrototype()->end()
                            ->end()
                            ->arrayNode('events')
                            ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode(Events::onClear)->defaultTrue()->end()
                                    ->booleanNode(Events::onFlush)->defaultTrue()->end()
                                    ->booleanNode(Events::postFlush)->defaultTrue()->end()
                                    ->booleanNode(Events::postLoad)->defaultTrue()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addEncryptorsSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('encryptors')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('adapter')
                                ->values(['ciphersweet', 'custom'])
                                ->cannotBeEmpty()
                                ->defaultValue('ciphersweet')
                            ->end()
                            ->arrayNode('adapter_config')
                            ->isRequired()
                                ->children()
                                    ->arrayNode('ciphersweet')
                                        ->children()
                                            ->arrayNode('cryptography')
                                                ->addDefaultsIfNotSet()
                                                ->children()
                                                    ->enumNode('key_provider')
                                                        ->values(['string', 'file', 'key'])
                                                        ->cannotBeEmpty()
                                                        ->defaultValue('file')
                                                    ->end()
                                                    ->scalarNode('key')
                                                        ->isRequired()
                                                        ->cannotBeEmpty()
                                                    ->end()
                                                    ->enumNode('crypto')
                                                        ->values(['boring', 'fips'])
                                                        ->cannotBeEmpty()
                                                        ->defaultValue('boring')
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('custom')
                                        ->children()
                                            ->scalarNode('service')
                                                ->isRequired()
                                                ->cannotBeEmpty()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->enumNode('cache_adapter')
                                ->values(['symfony_cache_pool', 'custom'])
                                ->cannotBeEmpty()
                                ->defaultValue('symfony_cache_pool')
                            ->end()
                            ->arrayNode('cache_adapter_config')
                            ->isRequired()
                            ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('symfony_cache_pool')
                                        ->children()
                                            ->scalarNode('cache_pool')
                                                ->isRequired()
                                                ->cannotBeEmpty()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('custom')
                                        ->children()
                                            ->scalarNode('service')
                                                ->isRequired()
                                                ->cannotBeEmpty()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
