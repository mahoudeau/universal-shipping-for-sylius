<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\DependencyInjection;

use Mahoudeau\UniversalShipping\Address\AddressFinder;
use Mahoudeau\UniversalShipping\Address\Ban\BanAddressProvider;
use Mahoudeau\UniversalShipping\Controller\AddressSuggestController;
use Mahoudeau\UniversalShipping\DependencyInjection\Configuration;
use Mahoudeau\UniversalShipping\DependencyInjection\UniversalShippingExtension;
use Mahoudeau\UniversalShipping\Provider\PickupPointFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(Configuration::class)]
#[CoversClass(UniversalShippingExtension::class)]
final class AddressConfigurationTest extends TestCase
{
    public function testTheModuleIsOffByDefaultAndUsesTheBanOnTheGeoplateforme(): void
    {
        /** @var array{address: array<string, mixed>} $config */
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame([
            'enabled' => false,
            'provider' => 'ban',
            'url' => 'https://data.geopf.fr/geocodage',
            'autocomplete' => true,
            'geocode_pickup_search' => true,
            'cache_ttl' => 86400,
        ], $config['address']);
    }

    public function testOffMeansNothingCanReachAnAddressProvider(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(AddressFinder::class));
        self::assertFalse($container->has('universal_shipping.address.autocomplete'));
        self::assertFalse($container->has('universal_shipping.address.geocoder'));
        self::assertFalse($container->getParameter('universal_shipping.address.autocomplete'));
    }

    public function testOnWiresBothFeaturesToTheBan(): void
    {
        $container = $this->load(['address' => ['enabled' => true]]);

        self::assertSame(BanAddressProvider::class, (string) $container->getAlias('universal_shipping.address.provider'));
        self::assertSame(AddressFinder::class, (string) $container->getAlias('universal_shipping.address.autocomplete'));
        self::assertSame(AddressFinder::class, (string) $container->getAlias('universal_shipping.address.geocoder'));
        self::assertTrue($container->getParameter('universal_shipping.address.autocomplete'));
    }

    public function testEachFeatureCanBeTurnedOffAlone(): void
    {
        $container = $this->load(['address' => ['enabled' => true, 'autocomplete' => false]]);
        self::assertFalse($container->has('universal_shipping.address.autocomplete'));
        self::assertTrue($container->has('universal_shipping.address.geocoder'));
        self::assertFalse($container->getParameter('universal_shipping.address.autocomplete'));

        $container = $this->load(['address' => ['enabled' => true, 'geocode_pickup_search' => false]]);
        self::assertTrue($container->has('universal_shipping.address.autocomplete'));
        self::assertFalse($container->has('universal_shipping.address.geocoder'));
    }

    public function testAnotherProviderIsAServiceId(): void
    {
        $container = $this->load(['address' => ['enabled' => true, 'provider' => 'app.photon_address_provider']]);

        self::assertSame('app.photon_address_provider', (string) $container->getAlias('universal_shipping.address.provider'));
    }

    public function testTheCompiledContainerHandsTheFinderOnlyWhereItIsOn(): void
    {
        $on = $this->compile(['address' => ['enabled' => true, 'autocomplete' => false]]);
        self::assertNull($on->getDefinition(AddressSuggestController::class)->getArgument(0));
        $geocoder = $on->getDefinition(PickupPointFinder::class)->getArgument(4);
        self::assertInstanceOf(Definition::class, $geocoder, 'Inlined: the finder has a single user');
        self::assertSame(AddressFinder::class, $geocoder->getClass());

        $both = $this->compile(['address' => ['enabled' => true]]);
        self::assertNotNull($both->getDefinition(AddressSuggestController::class)->getArgument(0));
        self::assertNotNull($both->getDefinition(PickupPointFinder::class)->getArgument(4));

        $off = $this->compile([]);
        self::assertNull($off->getDefinition(AddressSuggestController::class)->getArgument(0));
        self::assertNull($off->getDefinition(PickupPointFinder::class)->getArgument(4));
    }

    /** @param array<string, mixed> $config */
    private function compile(array $config): ContainerBuilder
    {
        $container = $this->load($config);
        foreach (['http_client', 'cache.app', 'router', 'translator', 'security.csrf.token_manager', 'sylius.repository.shipment', 'sylius.manager.shipment', 'sylius.repository.shipping_method'] as $id) {
            $container->register($id)->setSynthetic(true)->setPublic(true);
        }
        // Keep the services under test from being inlined or removed.
        $container->getDefinition(PickupPointFinder::class)->setPublic(true);
        $container->getDefinition(AddressSuggestController::class)->setPublic(true);
        $container->compile();

        return $container;
    }

    /** @param array<string, mixed> $config */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new UniversalShippingExtension())->load([$config], $container);

        return $container;
    }
}
