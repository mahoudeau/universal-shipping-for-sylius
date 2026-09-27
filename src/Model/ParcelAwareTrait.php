<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Use in your Shipment entity, together with ParcelAwareInterface.
 */
trait ParcelAwareTrait
{
    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'universal_shipping_parcel', type: Types::JSON, nullable: true)]
    protected ?array $parcel = null;

    /** The parcel id on its own, so a tracking webhook can find its shipment. */
    #[ORM\Column(name: 'universal_shipping_parcel_id', type: Types::STRING, length: 64, nullable: true)]
    protected ?string $parcelId = null;

    public function getParcel(): ?Parcel
    {
        return null === $this->parcel ? null : Parcel::fromArray($this->parcel);
    }

    public function setParcel(?Parcel $parcel): void
    {
        $this->parcel = $parcel?->toArray();
        $this->parcelId = $parcel?->id;
    }
}
