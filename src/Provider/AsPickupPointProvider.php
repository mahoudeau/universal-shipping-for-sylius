<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsPickupPointProvider
{
    public const TAG = 'universal_shipping.pickup_point_provider';

    public function __construct(public string $code)
    {
    }
}
