<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Label;

use Mahoudeau\UniversalShipping\Label\LabelRequestFactory;
use Mahoudeau\UniversalShipping\Label\NoRefundedAmountProvider;
use Mahoudeau\UniversalShipping\Label\RefundedAmountProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\ProductVariant;

#[CoversClass(LabelRequestFactory::class)]
#[CoversClass(NoRefundedAmountProvider::class)]
final class LabelRequestFactoryTest extends TestCase
{
    public function testWithoutRefundsTheWholeShipmentIsWeighedAndValued(): void
    {
        [$shipment] = self::shipment();

        $request = (new LabelRequestFactory('kg', 0.5))->create($shipment);

        self::assertSame(300, $request->weightInGrams);
        self::assertSame(9590, $request->orderTotal);
    }

    public function testAUnitRefundedInFullStaysOutOfTheParcel(): void
    {
        [$shipment, $refundedUnit] = self::shipment();

        $request = (new LabelRequestFactory('kg', 0.5, self::refunds([$refundedUnit, 4795])))->create($shipment);

        self::assertSame(150, $request->weightInGrams, 'Only the piece still going out is weighed');
        self::assertSame(4795, $request->orderTotal, 'Nor is the refunded piece declared or insured');
    }

    public function testAPartialRefundLowersTheValueButTheUnitStillShips(): void
    {
        [$shipment, $unit] = self::shipment();

        $request = (new LabelRequestFactory('kg', 0.5, self::refunds([$unit, 1000])))->create($shipment);

        self::assertSame(300, $request->weightInGrams);
        self::assertSame(8590, $request->orderTotal);
    }

    /** @return array{TestShipment, OrderItemUnitInterface} a shipment of two 47.95 € pieces of 150 g, and its first unit */
    private static function shipment(): array
    {
        $address = new Address();
        $address->setFirstName('Camille');
        $address->setLastName('Martin');
        $address->setStreet('12 rue de la Paix');
        $address->setPostcode('13001');
        $address->setCity('Marseille');
        $address->setCountryCode('FR');

        $order = new Order();
        $order->setNumber('000000042');
        $order->setCurrencyCode('EUR');
        $order->setShippingAddress($address);

        $variant = new ProductVariant();
        $variant->setWeight(0.15);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(4795);
        $units = [new OrderItemUnit($item), new OrderItemUnit($item)];
        $order->addItem($item);

        $shipment = new TestShipment();
        $shipment->setId(17);
        $shipment->setOrder($order);
        foreach ($units as $unit) {
            $shipment->addUnit($unit);
        }

        return [$shipment, $units[0]];
    }

    /** @param array{OrderItemUnitInterface, int} $refund a unit and the amount refunded on it */
    private static function refunds(array $refund): RefundedAmountProviderInterface
    {
        return new class($refund) implements RefundedAmountProviderInterface {
            /** @param array{OrderItemUnitInterface, int} $refund */
            public function __construct(private array $refund)
            {
            }

            public function refundedAmount(OrderItemUnitInterface $unit): int
            {
                return $unit === $this->refund[0] ? $this->refund[1] : 0;
            }
        };
    }
}
