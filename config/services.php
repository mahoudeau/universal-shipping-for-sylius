<?php

declare(strict_types=1);

use Mahoudeau\UniversalShipping\Command\FakeTrackingCommand;
use Mahoudeau\UniversalShipping\Controller\Admin\LabelController;
use Mahoudeau\UniversalShipping\Controller\SendcloudWebhookController;
use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOptionRegistry;
use Mahoudeau\UniversalShipping\Form\Extension\CheckoutShipmentTypeExtension;
use Mahoudeau\UniversalShipping\Form\Extension\ShippingMethodTypeExtension;
use Mahoudeau\UniversalShipping\Label\AsLabelProvider;
use Mahoudeau\UniversalShipping\Label\LabelManager;
use Mahoudeau\UniversalShipping\Label\LabelProviderRegistry;
use Mahoudeau\UniversalShipping\Label\LabelRequestFactory;
use Mahoudeau\UniversalShipping\Provider\AsPickupPointProvider;
use Mahoudeau\UniversalShipping\Provider\Fake\FakeLabelProvider;
use Mahoudeau\UniversalShipping\Provider\Fake\FakePickupPointProvider;
use Mahoudeau\UniversalShipping\Provider\PickupPointFinder;
use Mahoudeau\UniversalShipping\Provider\PickupPointProviderRegistry;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudClient;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudLabelProvider;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudPickupPointProvider;
use Mahoudeau\UniversalShipping\Tracking\ParcelTracker;
use Mahoudeau\UniversalShipping\Twig\LabelExtension;
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
            param('universal_shipping.sendcloud.api_url'),
        ]);

    $services->set(SendcloudPickupPointProvider::class);
    $services->set(FakePickupPointProvider::class);

    $services->set(SendcloudLabelProvider::class)
        ->args([
            service(SendcloudClient::class),
            param('universal_shipping.sendcloud.test_labels'),
            param('universal_shipping.sendcloud.sender_address_id'),
            param('universal_shipping.sendcloud.paper_size'),
        ]);

    $services->set(FakeLabelProvider::class);

    $services->set(FakeTrackingCommand::class)
        ->args([service(ParcelTracker::class)]);

    $services->set(LabelProviderRegistry::class)
        ->args([tagged_locator(AsLabelProvider::TAG, 'code')]);

    $services->set(LabelRequestFactory::class)
        ->args([
            param('universal_shipping.labels.weight_unit'),
            param('universal_shipping.labels.default_weight'),
        ]);

    $services->set(LabelManager::class)
        ->args([
            service(DeliveryOptionRegistry::class),
            service(LabelProviderRegistry::class),
            service(LabelRequestFactory::class),
        ]);

    $services->set(ParcelTracker::class)
        ->args([
            service('sylius.repository.shipment'),
            service('sylius.manager.shipment'),
        ]);

    $services->set(LabelController::class)
        ->args([
            service('sylius.repository.shipment'),
            service('sylius.manager.shipment'),
            service(LabelManager::class),
            service('security.csrf.token_manager'),
            service('router'),
            service('logger')->nullOnInvalid(),
        ])
        ->public()
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'universal_shipping']);

    $services->set(SendcloudWebhookController::class)
        ->args([
            service(ParcelTracker::class),
            param('universal_shipping.sendcloud.secret_key'),
            service('logger')->nullOnInvalid(),
        ])
        ->public()
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'universal_shipping']);

    $services->set(LabelExtension::class);

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
