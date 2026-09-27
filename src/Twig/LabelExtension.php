<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Twig;

use Mahoudeau\UniversalShipping\Controller\Admin\LabelController;
use Mahoudeau\UniversalShipping\Label\LabelManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LabelExtension extends AbstractExtension
{
    public function __construct(private readonly LabelManager $labels)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('universal_shipping_label_supported', $this->labels->supports(...)),
            new TwigFunction('universal_shipping_can_create_label', $this->labels->canCreate(...)),
            new TwigFunction('universal_shipping_label_csrf_id', static fn (int|string|null $shipmentId): string => LabelController::CSRF_TOKEN_ID . $shipmentId),
        ];
    }
}
