<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Psr\Container\ContainerInterface;

/**
 * Providers are fetched lazily: a carrier whose client cannot even be built
 * never breaks a page that does not use it.
 */
final readonly class PickupPointProviderRegistry
{
    public function __construct(private ContainerInterface $providers)
    {
    }

    public function forOption(DeliveryOption $option): PickupPointProviderInterface
    {
        if (!$this->providers->has($option->provider)) {
            throw new \LogicException(sprintf(
                'Delivery option "%s" uses provider "%s", which is not registered.',
                $option->code,
                $option->provider,
            ));
        }

        $provider = $this->providers->get($option->provider);
        \assert($provider instanceof PickupPointProviderInterface);

        return $provider;
    }
}
