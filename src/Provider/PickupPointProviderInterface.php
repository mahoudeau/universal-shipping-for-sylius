<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Model\PickupPoint;

/**
 * What a carrier integration implements to offer pickup points.
 * Register it with #[AsPickupPointProvider('code')].
 */
interface PickupPointProviderInterface
{
    /**
     * Points near the query, nearest first, restricted to what the delivery option
     * allows (e.g. relay points but not lockers).
     *
     * @return list<PickupPoint>
     *
     * @throws ProviderUnavailableException when the carrier cannot be reached
     */
    public function search(PickupPointQuery $query, DeliveryOption $option): array;

    /**
     * One point by the provider's id, or null if it no longer exists.
     *
     * @throws ProviderUnavailableException when the carrier cannot be reached
     */
    public function find(string $id, DeliveryOption $option): ?PickupPoint;
}
