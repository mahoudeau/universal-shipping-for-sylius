<?php

declare(strict_types=1);

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOptionRegistry;
use Mahoudeau\UniversalShipping\Form\Extension\CheckoutShipmentTypeExtension;
use Mahoudeau\UniversalShipping\Form\Extension\ShippingMethodTypeExtension;
use Mahoudeau\UniversalShipping\Provider\AsPickupPointProvider;
use Mahoudeau\UniversalShipping\Provider\Fake\FakePickupPointProvider;
use Mahoudeau\UniversalShipping\Provider\PickupPointFinder;
use Mahoudeau\UniversalShipping\Provider\PickupPointProviderRegistry;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudClient;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudPickupPointProvider;
use Mahoudeau\UniversalShipping\Twig\MapExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
    ;

    $services->set(DeliveryOptionRegistry::class)
        ->args([param('universal_shipping.delivery_options')]);

    $services->set(PickupPointProviderRegistry::class)
        ->args([tagged_locator(AsPickupPointProvider::TAG, 'code')]);

    $services->set(PickupPointFinder::class)
        ->args([
            service(PickupPointProviderRegistry::class),
            service('cache.app'),
            param('universal_shipping.cache_ttl'),
            service('logger')->nullOnInvalid(),
        ])
        ->tag('monolog.logger', ['channel' => 'universal_shipping']);

    $services->set(SendcloudClient::class)
        ->args([
            service('http_client'),
            param('universal_shipping.sendcloud.public_key'),
            param('universal_shipping.sendcloud.secret_key'),
            param('universal_shipping.sendcloud.service_points_url'),
        ]);

    $services->set(SendcloudPickupPointProvider::class);
    $services->set(FakePickupPointProvider::class);

    $services->set(CheckoutShipmentTypeExtension::class)
        ->args([
            service(DeliveryOptionRegistry::class),
            service(PickupPointFinder::class),
            service('sylius.repository.shipping_method'),
            service('translator'),
        ]);

    $services->set(ShippingMethodTypeExtension::class);

    $services->set(MapExtension::class)
        ->args([param('universal_shipping.map')]);
};
