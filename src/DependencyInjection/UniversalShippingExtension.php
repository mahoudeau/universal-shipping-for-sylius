<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\DependencyInjection;

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

        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('twig_hooks.yaml');
    }
}
