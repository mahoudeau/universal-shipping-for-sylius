<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

use Sylius\Component\Core\Model\OrderItemUnitInterface;

/** The default: no refund module, so every unit of the shipment goes in the parcel. */
final class NoRefundedAmountProvider implements RefundedAmountProviderInterface
{
    public function refundedAmount(OrderItemUnitInterface $unit): int
    {
        return 0;
    }
}
