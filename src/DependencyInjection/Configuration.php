<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\DependencyInjection;

use Mahoudeau\UniversalShipping\Model\DeliveryMode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('universal_shipping');

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('cache_ttl')
                    ->info('Seconds a pickup point search stays cached. The checkout re-renders on every change.')
                    ->defaultValue(600)
                    ->min(0)
                ->end()
                ->arrayNode('sendcloud')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('public_key')->defaultValue('')->end()
                        ->scalarNode('secret_key')->defaultValue('')->end()
                        ->scalarNode('service_points_url')->defaultValue('https://servicepoints.sendcloud.sc/api/v2')->end()
                    ->end()
                ->end()
                ->arrayNode('delivery_options')
                    ->info('Ways of delivering a parcel with one carrier, linked to Sylius shipping methods in the admin.')
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->isRequired()->cannotBeEmpty()->info('Shown in the admin.')->end()
                            ->scalarNode('provider')->isRequired()->cannotBeEmpty()->info('e.g. "sendcloud" or "fake".')->end()
                            ->scalarNode('carrier')->isRequired()->cannotBeEmpty()->info('e.g. "mondial_relay".')->end()
                            ->enumNode('delivery')
                                ->values(array_map(static fn (DeliveryMode $mode): string => $mode->value, DeliveryMode::cases()))
                                ->defaultValue(DeliveryMode::PickupPoint->value)
                            ->end()
                            ->arrayNode('options')
                                ->info('Passed to the provider as is.')
                                ->variablePrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
