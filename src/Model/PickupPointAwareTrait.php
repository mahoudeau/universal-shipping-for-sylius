<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Use in your Shipment entity, together with PickupPointAwareInterface.
 */
trait PickupPointAwareTrait
{
    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'universal_shipping_pickup_point', type: Types::JSON, nullable: true)]
    protected ?array $pickupPoint = null;

    public function getPickupPoint(): ?PickupPoint
    {
        return null === $this->pickupPoint ? null : PickupPoint::fromArray($this->pickupPoint);
    }

    public function setPickupPoint(?PickupPoint $pickupPoint): void
    {
        $this->pickupPoint = $pickupPoint?->toArray();
    }
}
