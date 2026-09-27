<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

use Sylius\Component\Core\Model\ShipmentInterface;

interface ParcelAwareInterface extends ShipmentInterface
{
    public function getParcel(): ?Parcel;

    public function setParcel(?Parcel $parcel): void;
}
