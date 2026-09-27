<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

/**
 * Where a parcel is, in words that hold for every carrier.
 * Providers map their own status codes onto these.
 */
enum ParcelStatus: string
{
    /** The label exists; the carrier has not scanned the parcel yet. */
    case Announced = 'announced';

    case InTransit = 'in_transit';

    /** Waiting at the pickup point for the customer. */
    case ReadyForPickup = 'ready_for_pickup';

    case Delivered = 'delivered';
    case Returned = 'returned';

    /** Something needs a look: failed delivery, invalid address, carrier exception. */
    case Problem = 'problem';

    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';

    /** The label is still in use: it can be printed, and a new one should not be made. */
    public function isActive(): bool
    {
        return self::Cancelling !== $this && self::Cancelled !== $this;
    }

    /** The carrier has the parcel, so the label can no longer be cancelled. */
    public function isHandedOver(): bool
    {
        return \in_array($this, [self::InTransit, self::ReadyForPickup, self::Delivered, self::Returned], true);
    }
}
