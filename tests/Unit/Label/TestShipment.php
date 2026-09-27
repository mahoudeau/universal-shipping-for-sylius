<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Label;

use Mahoudeau\UniversalShipping\Model\ParcelAwareInterface;
use Mahoudeau\UniversalShipping\Model\ParcelAwareTrait;
use Mahoudeau\UniversalShipping\Model\PickupPointAwareInterface;
use Mahoudeau\UniversalShipping\Model\PickupPointAwareTrait;
use Sylius\Component\Core\Model\Shipment;

/** A shop's Shipment entity, as the README tells it to be. */
class TestShipment extends Shipment implements ParcelAwareInterface, PickupPointAwareInterface
{
    use ParcelAwareTrait;
    use PickupPointAwareTrait;

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
