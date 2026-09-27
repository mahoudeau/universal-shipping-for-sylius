<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tracking;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Mahoudeau\UniversalShipping\Model\ParcelAwareInterface;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;

/**
 * Applies a carrier's status update to the shipment that holds the parcel.
 * Carrier-neutral: each provider's webhook turns its payload into a call to update().
 */
final readonly class ParcelTracker
{
    /** @param ObjectRepository<object> $shipments */
    public function __construct(
        private ObjectRepository $shipments,
        private ObjectManager $manager,
    ) {
    }

    /**
     * @return bool false when no shipment holds this parcel, or the update is older than the one we have
     */
    public function update(
        string $provider,
        string $parcelId,
        ParcelStatus $status,
        ?string $statusText,
        float $changedAt,
        ?string $trackingNumber = null,
        ?string $trackingUrl = null,
    ): bool {
        $shipment = $this->shipments->findOneBy(['parcelId' => $parcelId]);
        if (!$shipment instanceof ParcelAwareInterface) {
            return false;
        }

        $parcel = $shipment->getParcel();
        if (null === $parcel || $parcel->provider !== $provider || $parcel->id !== $parcelId) {
            return false;
        }

        // Webhooks can arrive out of order, e.g. after a retry.
        if ($changedAt < $parcel->statusChangedAt) {
            return false;
        }

        $parcel = $parcel->withStatus($status, $statusText, $changedAt)->withTracking($trackingNumber, $trackingUrl);
        $shipment->setParcel($parcel);

        if (null !== $parcel->trackingNumber && (null === $shipment->getTracking() || '' === $shipment->getTracking())) {
            $shipment->setTracking($parcel->trackingNumber);
        }

        $this->manager->flush();

        return true;
    }
}
