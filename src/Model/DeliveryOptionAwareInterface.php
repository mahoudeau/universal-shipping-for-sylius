<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

use Sylius\Component\Core\Model\ShippingMethodInterface;

interface DeliveryOptionAwareInterface extends ShippingMethodInterface
{
    /** Code of an option declared under universal_shipping.delivery_options, or null for a plain Sylius method. */
    public function getDeliveryOptionCode(): ?string;

    public function setDeliveryOptionCode(?string $deliveryOptionCode): void;
}
