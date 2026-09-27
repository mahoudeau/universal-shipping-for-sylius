<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Label;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOptionRegistry;
use Mahoudeau\UniversalShipping\Label\LabelException;
use Mahoudeau\UniversalShipping\Label\LabelManager;
use Mahoudeau\UniversalShipping\Label\LabelProviderRegistry;
use Mahoudeau\UniversalShipping\Label\LabelRequestFactory;
use Mahoudeau\UniversalShipping\Model\DeliveryOptionAwareInterface;
use Mahoudeau\UniversalShipping\Model\DeliveryOptionAwareTrait;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;
use Mahoudeau\UniversalShipping\Model\PickupPoint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\ShippingMethod;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(LabelManager::class)]
#[CoversClass(LabelRequestFactory::class)]
#[CoversClass(LabelProviderRegistry::class)]
final class LabelManagerTest extends TestCase
{
    private FakeLabelProvider $provider;

    private LabelManager $labels;

    protected function setUp(): void
    {
        $this->provider = new FakeLabelProvider();
        $this->labels = new LabelManager(
            new DeliveryOptionRegistry([
                'relay' => ['label' => 'Relay', 'provider' => 'sendcloud', 'carrier' => 'mondial_relay', 'delivery' => 'pickup_point', 'options' => []],
                'search_only' => ['label' => 'Search only', 'provider' => 'fake', 'carrier' => 'mondial_relay', 'delivery' => 'pickup_point', 'options' => []],
            ]),
            new LabelProviderRegistry(new ServiceLocator(['sendcloud' => fn (): FakeLabelProvider => $this->provider])),
            new LabelRequestFactory('kg', 0.5),
        );
    }

    public function testAPaidShipmentWaitingToLeaveCanGetALabel(): void
    {
        self::assertTrue($this->labels->canCreate(self::shipment()));
    }

    public function testNoLabelBeforePaymentOrAfterShipping(): void
    {
        self::assertFalse($this->labels->canCreate(self::shipment(paymentState: OrderPaymentStates::STATE_AWAITING_PAYMENT)));
        self::assertFalse($this->labels->canCreate(self::shipment(state: 'shipped')));
    }

    public function testMethodsWithoutALabelProviderShowNoButton(): void
    {
        self::assertFalse($this->labels->supports(self::shipment(option: 'search_only')));
        self::assertFalse($this->labels->supports(self::shipment(option: null)));
    }

    public function testLabelsCanComeFromAnotherProviderThanThePoints(): void
    {
        $labels = new LabelManager(
            new DeliveryOptionRegistry([
                'relay' => ['label' => 'Relay', 'provider' => 'search_only', 'carrier' => 'mondial_relay', 'delivery' => 'pickup_point', 'options' => [], 'label_provider' => 'sendcloud'],
            ]),
            new LabelProviderRegistry(new ServiceLocator(['sendcloud' => fn (): FakeLabelProvider => $this->provider])),
            new LabelRequestFactory(),
        );

        $labels->create(self::shipment());

        self::assertCount(1, $this->provider->requests);
    }

    public function testCreatingSendsTheShipmentAndKeepsTheParcel(): void
    {
        $shipment = self::shipment();

        $parcel = $this->labels->create($shipment);

        $request = $this->provider->requests[0];
        self::assertSame('17', $request->reference);
        self::assertSame('000000042', $request->orderNumber);
        self::assertSame(300, $request->weightInGrams, 'Two units of 0.15 kg');
        self::assertSame(9590, $request->orderTotal);
        self::assertSame('EUR', $request->currencyCode);
        self::assertSame('FR00111', $request->pickupPoint?->code);
        self::assertSame('Camille Martin', $request->recipient->name);
        self::assertSame('camille@example.com', $request->recipient->email);
        self::assertNull($request->recipient->company);

        self::assertSame($parcel->id, $shipment->getParcel()?->id);
        self::assertSame('12345678', $shipment->getTracking());
        self::assertFalse($this->labels->canCreate($shipment), 'One label at a time');
    }

    public function testProductsWithoutWeightUseTheDefault(): void
    {
        $this->labels->create(self::shipment(variantWeight: null));

        self::assertSame(500, $this->provider->requests[0]->weightInGrams);
    }

    public function testARelayOptionNeedsAPoint(): void
    {
        $this->expectException(LabelException::class);

        $this->labels->create(self::shipment(point: false));
    }

    public function testCancellingFreesTheShipmentForANewLabel(): void
    {
        $shipment = self::shipment();
        $this->labels->create($shipment);

        $this->labels->cancel($shipment);

        self::assertSame(ParcelStatus::Cancelled, $shipment->getParcel()?->status);
        self::assertNull($shipment->getTracking());
        self::assertTrue($this->labels->canCreate($shipment));
    }

    public function testAParcelOnItsWayCannotBeCancelled(): void
    {
        $shipment = self::shipment();
        $parcel = $this->labels->create($shipment);
        $shipment->setParcel($parcel->withStatus(ParcelStatus::InTransit, null, microtime(true)));

        $this->expectException(LabelException::class);

        $this->labels->cancel($shipment);
    }

    private static function shipment(
        string $paymentState = OrderPaymentStates::STATE_PAID,
        string $state = 'ready',
        ?string $option = 'relay',
        ?float $variantWeight = 0.15,
        bool $point = true,
    ): TestShipment {
        $customer = new Customer();
        $customer->setEmail('camille@example.com');

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
        $order->setCustomer($customer);
        $order->setShippingAddress($address);
        $order->setPaymentState($paymentState);

        $variant = new ProductVariant();
        $variant->setWeight($variantWeight);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(4795);
        $units = [new OrderItemUnit($item), new OrderItemUnit($item)];
        $order->addItem($item);

        $method = new class() extends ShippingMethod implements DeliveryOptionAwareInterface {
            use DeliveryOptionAwareTrait;
        };
        $method->setDeliveryOptionCode($option);

        $shipment = new TestShipment();
        $shipment->setId(17);
        $shipment->setOrder($order);
        $shipment->setMethod($method);
        $shipment->setState($state);
        foreach ($units as $unit) {
            $shipment->addUnit($unit);
        }
        if ($point) {
            $shipment->setPickupPoint(new PickupPoint('sendcloud', '10459634', 'mondial_relay', 'FR00111', 'Porte d\'Aix Netphone', '18 rue Francis de Pressensé', '13001', 'Marseille', 'FR'));
        }

        return $shipment;
    }
}
