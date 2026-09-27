<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Provider;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Model\PickupPoint;
use Mahoudeau\UniversalShipping\Provider\PickupPointProviderInterface;
use Mahoudeau\UniversalShipping\Provider\PickupPointQuery;

/** Returns one point and counts how often the carrier was asked. */
final class CountingPickupPointProvider implements PickupPointProviderInterface
{
    public int $searches = 0;

    public function search(PickupPointQuery $query, DeliveryOption $option): array
    {
        ++$this->searches;

        return [$this->point()];
    }

    public function find(string $id, DeliveryOption $option): PickupPoint
    {
        return $this->point();
    }

    private function point(): PickupPoint
    {
        return new PickupPoint('sendcloud', 'A', 'mondial_relay', 'FR1', 'Point', 'Street', '13001', 'Marseille', 'FR', distance: 100);
    }
}
