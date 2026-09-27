<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

use Mahoudeau\UniversalShipping\Model\PickupPointAwareInterface;
use Sylius\Component\Core\Model\ShipmentInterface;

final readonly class LabelRequestFactory
{
    /**
     * @param 'kg'|'g' $weightUnit    Unit of the weights typed on product variants
     * @param float    $defaultWeight Used when no variant in the shipment has a weight, in that same unit
     */
    public function __construct(
        private string $weightUnit = 'kg',
        private float $defaultWeight = 0.5,
    ) {
    }

    public function create(ShipmentInterface $shipment): LabelRequest
    {
        $order = $shipment->getOrder();
        $address = $order?->getShippingAddress();

        if (null === $order || null === $address) {
            throw new LabelException('The order has no shipping address.');
        }

        $weight = 0.0;
        foreach ($shipment->getUnits() as $unit) {
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
            orderTotal: $order->getTotal(),
            currencyCode: (string) $order->getCurrencyCode(),
        );
    }
}
