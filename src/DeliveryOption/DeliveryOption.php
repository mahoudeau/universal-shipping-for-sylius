<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\DeliveryOption;

use Mahoudeau\UniversalShipping\Model\DeliveryMode;

/**
 * One way of delivering a parcel with one carrier, e.g. Mondial Relay relay points.
 * Declared in config under universal_shipping.delivery_options and linked to
 * Sylius shipping methods in the admin, which add the price, zone and name.
 */
final readonly class DeliveryOption
{
    /**
     * @param array<string, mixed> $options Provider-specific settings, e.g. which point types to show
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $provider,
        public string $carrier,
        public DeliveryMode $deliveryMode,
        public array $options = [],
    ) {
    }

    public function needsPickupPoint(): bool
    {
        return DeliveryMode::PickupPoint === $this->deliveryMode;
    }
}
