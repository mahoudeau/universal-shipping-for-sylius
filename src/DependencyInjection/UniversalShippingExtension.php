<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\DependencyInjection;

use Mahoudeau\UniversalShipping\Address\AddressFinder;
use Mahoudeau\UniversalShipping\Address\Ban\BanAddressProvider;
use Mahoudeau\UniversalShipping\Label\AsLabelProvider;
use Mahoudeau\UniversalShipping\Provider\AsPickupPointProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class UniversalShippingExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('universal_shipping.delivery_options', $config['delivery_options']);
        $container->setParameter('universal_shipping.cache_ttl', $config['cache_ttl']);
        $container->setParameter('universal_shipping.map', $config['map']);
        $container->setParameter('universal_shipping.sendcloud.public_key', $config['sendcloud']['public_key']);
        $container->setParameter('universal_shipping.sendcloud.secret_key', $config['sendcloud']['secret_key']);
        $container->setParameter('universal_shipping.sendcloud.service_points_url', $config['sendcloud']['service_points_url']);
        $container->setParameter('universal_shipping.sendcloud.api_url', $config['sendcloud']['api_url']);
        $container->setParameter('universal_shipping.sendcloud.test_labels', $config['sendcloud']['test_labels']);
        $container->setParameter('universal_shipping.sendcloud.sender_address_id', $config['sendcloud']['sender_address_id']);
        $container->setParameter('universal_shipping.sendcloud.paper_size', $config['sendcloud']['paper_size']);
        $container->setParameter('universal_shipping.labels.weight_unit', $config['labels']['weight_unit']);
        $container->setParameter('universal_shipping.labels.default_weight', $config['labels']['default_weight']);

        $container->registerAttributeForAutoconfiguration(
            AsPickupPointProvider::class,
            static function (ChildDefinition $definition, AsPickupPointProvider $attribute): void {
                $definition->addTag(AsPickupPointProvider::TAG, ['code' => $attribute->code]);
            },
        );
        $container->registerAttributeForAutoconfiguration(
            AsLabelProvider::class,
            static function (ChildDefinition $definition, AsLabelProvider $attribute): void {
                $definition->addTag(AsLabelProvider::TAG, ['code' => $attribute->code]);
            },
        );

        $address = $config['address'];
        $container->setParameter('universal_shipping.address.url', $address['url']);
        $container->setParameter('universal_shipping.address.cache_ttl', $address['cache_ttl']);
        $container->setParameter('universal_shipping.address.autocomplete', $address['enabled'] && $address['autocomplete']);

        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('services.php');

        $this->loadAddressModule($address, $container);
    }

    /**
     * Nothing calls an address provider unless the module is on, and then only for
     * the features that are on: the finder is removed, or the aliases the other
     * services look for are pointed at it.
     *
     * @param array{enabled: bool, provider: string, autocomplete: bool, geocode_pickup_search: bool} $address
     */
    private function loadAddressModule(array $address, ContainerBuilder $container): void
    {
        if (!$address['enabled']) {
            $container->removeDefinition(AddressFinder::class);

            return;
        }

        $container->setAlias(
            'universal_shipping.address.provider',
            'ban' === $address['provider'] ? BanAddressProvider::class : $address['provider'],
        );

        if ($address['autocomplete']) {
            $container->setAlias('universal_shipping.address.autocomplete', AddressFinder::class);
        }
        if ($address['geocode_pickup_search']) {
            $container->setAlias('universal_shipping.address.geocoder', AddressFinder::class);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('twig_hooks.yaml');
    }
}
