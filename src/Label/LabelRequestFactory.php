<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

use Mahoudeau\UniversalShipping\Model\PickupPointAwareInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\ShipmentInterface;

final readonly class LabelRequestFactory
{
    /**
     * @param 'kg'|'g' $weightUnit    Unit of the weights typed on product variants
     * @param float    $defaultWeight Used when no variant in the shipment has a weight, in that same unit
     * @param RefundedAmountProviderInterface $refunds What was refunded per unit, so a partly refunded order's label
     *                                                 weighs and values only what still goes in the parcel
     */
    public function __construct(
        private string $weightUnit = 'kg',
        private float $defaultWeight = 0.5,
        private RefundedAmountProviderInterface $refunds = new NoRefundedAmountProvider(),
    ) {
    }

    public function create(ShipmentInterface $shipment): LabelRequest
    {
        $order = $shipment->getOrder();
        $address = $order?->getShippingAddress();

        if (null === $order || null === $address) {
            throw new LabelException('The order has no shipping address.');
        }

        // A unit refunded in full stays home: no weight. Every refund, whole or partial,
        // comes off the value the label declares and insures.
        $weight = 0.0;
        $refunded = 0;
        foreach ($shipment->getUnits() as $unit) {
            if ($unit instanceof OrderItemUnitInterface) {
                $amount = $this->refunds->refundedAmount($unit);
                $refunded += $amount;
                if ($amount > 0 && $amount >= $unit->getTotal()) {
                    continue;
                }
            }
            $weight += (float) $unit->getShippable()?->getShippingWeight();
        }
        if ($weight <= 0.0) {
            $weight = $this->defaultWeight;
        }

        return new LabelRequest(
            reference: (string) $shipment->getId(),
            orderNumber: (string) $order->getNumber(),
            recipient: new Recipient(
                name: trim((string) $address->getFullName()),
                company: $address->getCompany() ?: null,
                street: (string) $address->getStreet(),
                postcode: (string) $address->getPostcode(),
                city: (string) $address->getCity(),
                countryCode: (string) $address->getCountryCode(),
                email: $order->getCustomer()?->getEmail() ?: null,
                phoneNumber: $address->getPhoneNumber() ?: null,
            ),
            pickupPoint: $shipment instanceof PickupPointAwareInterface ? $shipment->getPickupPoint() : null,
            weightInGrams: max(1, (int) round('kg' === $this->weightUnit ? $weight * 1000 : $weight)),
            orderTotal: max(0, $order->getTotal() - $refunded),
            currencyCode: (string) $order->getCurrencyCode(),
        );
    }
}
