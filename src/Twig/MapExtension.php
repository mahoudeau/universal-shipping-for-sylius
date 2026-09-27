<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes the map settings to templates as universal_shipping_map.
 */
final class MapExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param array{enabled: bool, style: string, theme: array{accent: ?string, pin_text: ?string, colors: array<string, ?string>}} $map
     */
    public function __construct(private readonly array $map)
    {
    }

    public function getGlobals(): array
    {
        return ['universal_shipping_map' => $this->map];
    }
}
