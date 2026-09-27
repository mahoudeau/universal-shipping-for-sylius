<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider\Sendcloud;

use Mahoudeau\UniversalShipping\Model\ParcelStatus;

/**
 * Sendcloud's parcel status ids, from GET /api/v2/parcels/statuses, as sent in the
 * parcel_status_changed webhook.
 */
final class SendcloudParcelStatus
{
    private const MAP = [
        1 => ParcelStatus::Announced,          // Announced
        13 => ParcelStatus::Announced,         // Announced: not collected
        1000 => ParcelStatus::Announced,       // Ready to send
        1001 => ParcelStatus::Announced,       // Being announced
        3 => ParcelStatus::InTransit,          // En route to sorting center
        4 => ParcelStatus::InTransit,          // Delivery delayed
        5 => ParcelStatus::InTransit,          // Sorted
        7 => ParcelStatus::InTransit,          // Being sorted
        22 => ParcelStatus::InTransit,         // Shipment picked up by driver
        91 => ParcelStatus::InTransit,         // Parcel en route
        92 => ParcelStatus::InTransit,         // Driver en route
        62989 => ParcelStatus::InTransit,      // At customs
        62990 => ParcelStatus::InTransit,      // At sorting centre
        62993 => ParcelStatus::InTransit,      // Delivery method changed
        62994 => ParcelStatus::InTransit,      // Delivery date changed
        62995 => ParcelStatus::InTransit,      // Delivery address changed
        12 => ParcelStatus::ReadyForPickup,    // Awaiting customer pickup
        11 => ParcelStatus::Delivered,         // Delivered
        93 => ParcelStatus::Delivered,         // Shipment collected by customer
        62991 => ParcelStatus::Returned,       // Refused by recipient
        62992 => ParcelStatus::Returned,       // Returned to sender
        6 => ParcelStatus::Problem,            // Not sorted
        8 => ParcelStatus::Problem,            // Delivery attempt failed
        15 => ParcelStatus::Problem,           // Error collecting
        80 => ParcelStatus::Problem,           // Unable to deliver
        94 => ParcelStatus::Problem,           // Parcel cancellation failed
        999 => ParcelStatus::Problem,          // No label
        1002 => ParcelStatus::Problem,         // Announcement failed
        62996 => ParcelStatus::Problem,        // Exception
        62997 => ParcelStatus::Problem,        // Address invalid
        1998 => ParcelStatus::Cancelling,      // Cancelled upstream
        1999 => ParcelStatus::Cancelling,      // Cancellation requested
        2001 => ParcelStatus::Cancelling,      // Submitting cancellation request
        2000 => ParcelStatus::Cancelled,       // Cancelled
    ];

    /** Null for ids we do not know, such as 1337 "Unknown status": keep what we had. */
    public static function fromId(int $id): ?ParcelStatus
    {
        return self::MAP[$id] ?? null;
    }
}
