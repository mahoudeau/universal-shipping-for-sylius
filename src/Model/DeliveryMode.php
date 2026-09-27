<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

enum DeliveryMode: string
{
    /** The customer picks a relay point or a locker at checkout. */
    case PickupPoint = 'pickup_point';

    /** Delivered to the shipping address, nothing to pick. */
    case Home = 'home';
}
