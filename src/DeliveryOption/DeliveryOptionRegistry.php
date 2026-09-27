<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\DeliveryOption;

use Mahoudeau\UniversalShipping\Model\DeliveryMode;
use Mahoudeau\UniversalShipping\Model\DeliveryOptionAwareInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;

final class DeliveryOptionRegistry
{
    /** @var array<string, DeliveryOption> */
    private array $options = [];

    /**
     * @param array<string, array{label: string, provider: string, carrier: string, delivery: string, options: array<string, mixed>}> $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $code => $option) {
            $this->options[$code] = new DeliveryOption(
                code: $code,
                label: $option['label'],
                provider: $option['provider'],
                carrier: $option['carrier'],
                deliveryMode: DeliveryMode::from($option['delivery']),
                options: $option['options'],
            );
        }
    }

    public function get(string $code): ?DeliveryOption
    {
        return $this->options[$code] ?? null;
    }

    /** @return array<string, DeliveryOption> */
    public function all(): array
    {
        return $this->options;
    }

    public function forShippingMethod(?ShippingMethodInterface $method): ?DeliveryOption
    {
        if (!$method instanceof DeliveryOptionAwareInterface || null === $method->getDeliveryOptionCode()) {
            return null;
        }

        return $this->get($method->getDeliveryOptionCode());
    }
}
