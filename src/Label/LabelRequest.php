<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

use Mahoudeau\UniversalShipping\Model\PickupPoint;

/**
 * What a carrier needs to know to make a label, taken from a Sylius shipment.
 */
final readonly class LabelRequest
{
    public function __construct(
        /** Our own id for the shipment, stored by the carrier and sent back. */
        public string $reference,
        public string $orderNumber,
        public Recipient $recipient,
        public ?PickupPoint $pickupPoint,
        public int $weightInGrams,
        /** In the currency's smallest unit, like every Sylius amount. */
        public int $orderTotal,
        public string $currencyCode,
    ) {
    }
}
