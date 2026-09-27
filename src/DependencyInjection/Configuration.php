<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\DependencyInjection;

use Mahoudeau\UniversalShipping\Address\Ban\BanAddressProvider;
use Mahoudeau\UniversalShipping\Model\DeliveryMode;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /** OpenFreeMap: OpenStreetMap data, worldwide, free, no key. */
    public const DEFAULT_MAP_STYLE = 'https://tiles.openfreemap.org/styles/positron';

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
                ->arrayNode('map')
                    ->info('A map next to the pickup point list, drawn with MapLibre. Off by default: map tiles come from a third-party server unless you host them.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('style')
                            ->info('URL of a MapLibre style. The style names its tile source and credits. pmtiles:// sources work, for a self-hosted Protomaps file.')
                            ->defaultValue(self::DEFAULT_MAP_STYLE)
                        ->end()
                        ->arrayNode('theme')
                            ->info('Colours as #rgb or #rrggbb. Leave one out to keep the style\'s own.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->append(self::color('accent', 'Pins and the chosen pin. Defaults to the Bootstrap primary colour.'))
                                ->append(self::color('pin_text', 'Number on the chosen pin.'))
                                ->arrayNode('colors')
                                    ->info('Recolours the map itself. Works with OpenMapTiles styles (OpenFreeMap and most free styles), partly with others.')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->append(self::color('background', 'Land.'))
                                        ->append(self::color('water', 'Sea, lakes, rivers.'))
                                        ->append(self::color('parks', 'Parks, woods, grass.'))
                                        ->append(self::color('roads', 'Streets and roads.'))
                                        ->append(self::color('buildings', 'Buildings.'))
                                        ->append(self::color('labels', 'Street and place names.'))
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('address')
                    ->info('Address autocomplete at checkout, and geocoding for the pickup point search. Off by default. The shop server calls the provider; the browser only talks to the shop.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('provider')
                            ->info('"ban" for the French national address base, or the id of your own service implementing AddressProviderInterface.')
                            ->defaultValue('ban')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('url')
                            ->info('Where the BAN is served. The IGN Géoplateforme by default.')
                            ->defaultValue(BanAddressProvider::DEFAULT_URL)
                            ->cannotBeEmpty()
                        ->end()
                        ->booleanNode('autocomplete')
                            ->info('Suggest addresses under the street fields of the checkout\'s address step.')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('geocode_pickup_search')
                            ->info('Locate the customer\'s address first, and search pickup points around those coordinates. Falls back to the address as text.')
                            ->defaultTrue()
                        ->end()
                        ->integerNode('cache_ttl')
                            ->info('Seconds an address answer stays cached.')
                            ->defaultValue(86400)
                            ->min(0)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('labels')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('weight_unit')
                            ->info('Unit of the weights typed on product variants.')
                            ->values(['kg', 'g'])
                            ->defaultValue('kg')
                        ->end()
                        ->floatNode('default_weight')
                            ->info('Parcel weight when no product in it has one, in weight_unit.')
                            ->defaultValue(0.5)
                            ->min(0.001)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('sendcloud')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('public_key')->defaultValue('')->end()
                        ->scalarNode('secret_key')
                            ->info('Also signs the tracking webhook.')
                            ->defaultValue('')
                        ->end()
                        ->booleanNode('test_labels')
                            ->info('Make every label a free "Unstamped letter", addressed to the customer. Nothing is charged. For development and staging.')
                            ->defaultFalse()
                        ->end()
                        ->integerNode('sender_address_id')
                            ->info('A sender address from your Sendcloud settings. Only needed when the account has several.')
                            ->defaultNull()
                        ->end()
                        ->enumNode('paper_size')
                            ->info('Label size: A4 for a home printer, A6 for a label printer. Null keeps the carrier\'s size.')
                            ->values([null, 'A4', 'A5', 'A6'])
                            ->defaultNull()
                        ->end()
                        ->scalarNode('service_points_url')->defaultValue('https://servicepoints.sendcloud.sc/api/v2')->end()
                        ->scalarNode('api_url')->defaultValue('https://panel.sendcloud.sc/api/v3')->end()
                    ->end()
                ->end()
                ->arrayNode('delivery_options')
                    ->info('Ways of delivering a parcel with one carrier, linked to Sylius shipping methods in the admin.')
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->isRequired()->cannotBeEmpty()->info('Shown in the admin.')->end()
                            ->scalarNode('provider')->isRequired()->cannotBeEmpty()->info('e.g. "sendcloud" or "fake".')->end()
                            ->scalarNode('label_provider')
                                ->info('Makes the labels, when not the provider above. "fake" gives real points and fake labels, for development.')
                                ->defaultNull()
                            ->end()
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

    private static function color(string $name, string $info): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition($name);
        $node
            ->info($info)
            ->defaultNull()
            ->validate()
                ->ifTrue(static fn (mixed $value): bool => null !== $value && 1 !== preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $value))
                ->thenInvalid('%s is not a colour. Use #rgb or #rrggbb.')
            ->end()
        ;

        return $node;
    }
}
