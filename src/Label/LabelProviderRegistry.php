<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Psr\Container\ContainerInterface;

/**
 * Not every provider makes labels: a pickup point search alone is a fine start for a new carrier.
 */
final readonly class LabelProviderRegistry
{
    public function __construct(private ContainerInterface $providers)
    {
    }

    public function supports(DeliveryOption $option): bool
    {
        return $this->providers->has($option->labelProviderCode());
    }

    public function forOption(DeliveryOption $option): LabelProviderInterface
    {
        if (!$this->supports($option)) {
            throw new LabelException(sprintf('Provider "%s" does not make labels.', $option->labelProviderCode()));
        }

        $provider = $this->providers->get($option->labelProviderCode());
        \assert($provider instanceof LabelProviderInterface);

        return $provider;
    }
}
