<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

use Sylius\Component\Core\Model\OrderItemUnitInterface;

/**
 * How much of a unit was refunded, in the order's currency (cents). A unit refunded in full
 * doesn't go in the parcel: the label leaves its weight out, and its value out of the
 * declared and insured amounts.
 *
 * The plugin doesn't depend on a refund module. By default nothing is refunded
 * (NoRefundedAmountProvider). A shop using sylius/refund-plugin points this interface at a
 * service reading RemainingTotalProviderInterface: see the README, "Partly refunded orders".
 */
interface RefundedAmountProviderInterface
{
    public function refundedAmount(OrderItemUnitInterface $unit): int;
}
