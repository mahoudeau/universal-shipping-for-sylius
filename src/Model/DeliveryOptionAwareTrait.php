<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Use in your ShippingMethod entity, together with DeliveryOptionAwareInterface.
 */
trait DeliveryOptionAwareTrait
{
    #[ORM\Column(name: 'universal_shipping_delivery_option', type: Types::STRING, length: 64, nullable: true)]
    protected ?string $deliveryOptionCode = null;

    public function getDeliveryOptionCode(): ?string
    {
        return $this->deliveryOptionCode;
    }

    public function setDeliveryOptionCode(?string $deliveryOptionCode): void
    {
        $this->deliveryOptionCode = $deliveryOptionCode;
    }
}
