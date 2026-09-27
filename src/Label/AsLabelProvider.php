<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsLabelProvider
{
    public const TAG = 'universal_shipping.label_provider';

    public function __construct(public string $code)
    {
    }
}
