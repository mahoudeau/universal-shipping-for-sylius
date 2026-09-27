<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

use Sylius\Component\Core\Model\ShipmentInterface;

interface PickupPointAwareInterface extends ShipmentInterface
{
    public function getPickupPoint(): ?PickupPoint;

    public function setPickupPoint(?PickupPoint $pickupPoint): void;
}
