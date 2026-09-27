<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOptionRegistry;
use Mahoudeau\UniversalShipping\Model\Parcel;
use Mahoudeau\UniversalShipping\Model\ParcelAwareInterface;
use Mahoudeau\UniversalShipping\Model\PickupPointAwareInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Shipping\Model\ShipmentInterface as BaseShipmentInterface;

/**
 * Makes, prints and cancels the label of a Sylius shipment. Does not flush: the caller does.
 */
final readonly class LabelManager
{
    public function __construct(
        private DeliveryOptionRegistry $deliveryOptions,
        private LabelProviderRegistry $providers,
        private LabelRequestFactory $requestFactory,
    ) {
    }

    /** The shipment's method is linked to a delivery option whose provider makes labels. */
    public function supports(ShipmentInterface $shipment): bool
    {
        if (!$shipment instanceof ParcelAwareInterface) {
            return false;
        }

        $option = $this->deliveryOptions->forShippingMethod($shipment->getMethod());

        return null !== $option && $this->providers->supports($option);
    }

    /** Paid, not shipped yet, and without a label in use. */
    public function canCreate(ShipmentInterface $shipment): bool
    {
        return $this->supports($shipment) &&
            BaseShipmentInterface::STATE_READY === $shipment->getState() &&
            OrderPaymentStates::STATE_PAID === $shipment->getOrder()?->getPaymentState() &&
            !($this->parcelOf($shipment)?->status->isActive() ?? false);
    }

    public function create(ShipmentInterface $shipment): Parcel
    {
        if (!$this->canCreate($shipment)) {
            throw new LabelException('This shipment cannot get a label: it needs to be paid, not shipped, and without a label already.');
        }
        \assert($shipment instanceof ParcelAwareInterface);

        $option = $this->optionOf($shipment);
        if ($option->needsPickupPoint() && !($shipment instanceof PickupPointAwareInterface && null !== $shipment->getPickupPoint())) {
            throw new LabelException('The customer did not choose a pickup point.');
        }

        $parcel = $this->providers->forOption($option)->createLabel($this->requestFactory->create($shipment), $option);

        $shipment->setParcel($parcel);
        if (null !== $parcel->trackingNumber) {
            // Sylius prints it in the "shipped" email and on the customer's order page.
            $shipment->setTracking($parcel->trackingNumber);
        }

        return $parcel;
    }

    public function label(ShipmentInterface $shipment): string
    {
        $parcel = $this->parcelOf($shipment);
        if (null === $parcel || !$parcel->status->isActive()) {
            throw new LabelException('This shipment has no label.');
        }

        return $this->providers->forOption($this->optionOf($shipment))->label($parcel);
    }

    public function cancel(ShipmentInterface $shipment): Parcel
    {
        $parcel = $this->parcelOf($shipment);
        if (null === $parcel || !$parcel->status->isActive()) {
            throw new LabelException('This shipment has no label to cancel.');
        }
        if ($parcel->status->isHandedOver()) {
            throw new LabelException('The carrier already has the parcel.');
        }
        \assert($shipment instanceof ParcelAwareInterface);

        $cancelled = $this->providers->forOption($this->optionOf($shipment))->cancel($parcel);

        $shipment->setParcel($cancelled);
        if ($shipment->getTracking() === $parcel->trackingNumber) {
            $shipment->setTracking(null);
        }

        return $cancelled;
    }

    private function parcelOf(ShipmentInterface $shipment): ?Parcel
    {
        return $shipment instanceof ParcelAwareInterface ? $shipment->getParcel() : null;
    }

    private function optionOf(ShipmentInterface $shipment): DeliveryOption
    {
        return $this->deliveryOptions->forShippingMethod($shipment->getMethod())
            ?? throw new LabelException('The shipping method is not linked to a delivery option.');
    }
}
