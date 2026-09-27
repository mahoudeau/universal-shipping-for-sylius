<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\DeliveryOption;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOptionRegistry;
use Mahoudeau\UniversalShipping\Model\DeliveryMode;
use Mahoudeau\UniversalShipping\Model\DeliveryOptionAwareInterface;
use Mahoudeau\UniversalShipping\Model\DeliveryOptionAwareTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShippingMethodInterface;

#[CoversClass(DeliveryOptionRegistry::class)]
final class DeliveryOptionRegistryTest extends TestCase
{
    private DeliveryOptionRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new DeliveryOptionRegistry([
            'mondial_relay_point' => [
                'label' => 'Mondial Relay, relay point',
                'provider' => 'sendcloud',
                'carrier' => 'mondial_relay',
                'delivery' => 'pickup_point',
                'options' => ['point_types' => ['servicepoint']],
            ],
            'mondial_relay_home' => [
                'label' => 'Mondial Relay, home',
                'provider' => 'sendcloud',
                'carrier' => 'mondial_relay',
                'delivery' => 'home',
                'options' => [],
            ],
        ]);
    }

    public function testItBuildsOptionsFromConfig(): void
    {
        $option = $this->registry->get('mondial_relay_point');

        self::assertNotNull($option);
        self::assertSame(DeliveryMode::PickupPoint, $option->deliveryMode);
        self::assertTrue($option->needsPickupPoint());
        self::assertSame(['point_types' => ['servicepoint']], $option->options);
        self::assertFalse($this->registry->get('mondial_relay_home')?->needsPickupPoint());
        self::assertNull($this->registry->get('unknown'));
        self::assertCount(2, $this->registry->all());
    }

    public function testItResolvesTheOptionOfAShippingMethod(): void
    {
        $method = $this->createMock(DeliveryOptionAwareInterface::class);
        $method->method('getDeliveryOptionCode')->willReturn('mondial_relay_point');

        self::assertSame('mondial_relay_point', $this->registry->forShippingMethod($method)?->code);
    }

    public function testTheShippingMethodTraitStoresTheOptionCode(): void
    {
        $method = new class() {
            use DeliveryOptionAwareTrait;
        };

        self::assertNull($method->getDeliveryOptionCode());
        $method->setDeliveryOptionCode('mondial_relay_point');
        self::assertSame('mondial_relay_point', $method->getDeliveryOptionCode());
    }

    public function testPlainSyliusMethodsHaveNoOption(): void
    {
        $unlinked = $this->createMock(DeliveryOptionAwareInterface::class);
        $unlinked->method('getDeliveryOptionCode')->willReturn(null);

        $stale = $this->createMock(DeliveryOptionAwareInterface::class);
        $stale->method('getDeliveryOptionCode')->willReturn('removed_from_config');

        self::assertNull($this->registry->forShippingMethod($unlinked));
        self::assertNull($this->registry->forShippingMethod($stale));
        self::assertNull($this->registry->forShippingMethod($this->createMock(ShippingMethodInterface::class)));
        self::assertNull($this->registry->forShippingMethod(null));
    }
}
