<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider;

use Mahoudeau\UniversalShipping\Address\AddressFinder;
use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Model\PickupPoint;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The single entry point the rest of the plugin uses: caches searches (the checkout
 * re-renders on every change) and turns carrier outages into an empty, logged answer.
 *
 * With a geocoder (address.geocode_pickup_search), the address is located first and
 * the carrier searches around its coordinates. When geocoding finds nothing or fails,
 * the carrier gets the address as text, as without the module.
 */
final readonly class PickupPointFinder
{
    public function __construct(
        private PickupPointProviderRegistry $providers,
        private CacheInterface $cache,
        private int $cacheTtl = 600,
        private LoggerInterface $logger = new NullLogger(),
        private ?AddressFinder $geocoder = null,
    ) {
    }

    /** @return list<PickupPoint> */
    public function search(PickupPointQuery $query, DeliveryOption $option): array
    {
        if ($query->isEmpty()) {
            return [];
        }

        try {
            return $this->cache->get(
                sprintf('universal_shipping.search.%s.%s', $option->code, $query->cacheKey()),
                function (ItemInterface $item) use ($query, $option): array {
                    $item->expiresAfter($this->cacheTtl);

                    // Inside the cache callback: a cached search needs no geocoding either.
                    return $this->providers->forOption($option)->search($this->locate($query), $option);
                },
            );
        } catch (ProviderUnavailableException $exception) {
            $this->logger->warning($exception->getMessage(), ['delivery_option' => $option->code]);

            return [];
        }
    }

    private function locate(PickupPointQuery $query): PickupPointQuery
    {
        if (null === $this->geocoder || $query->hasCoordinates()) {
            return $query;
        }

        $found = $this->geocoder->geocode($query->address, $query->countryCode);
        if (null === $found?->latitude || null === $found->longitude) {
            return $query;
        }

        return $query->withCoordinates($found->latitude, $found->longitude);
    }

    /**
     * Looks the point up again with the carrier: what the customer submitted is only an id.
     */
    public function find(string $id, DeliveryOption $option): ?PickupPoint
    {
        try {
            return $this->providers->forOption($option)->find($id, $option)?->withoutDistance();
        } catch (ProviderUnavailableException $exception) {
            $this->logger->warning($exception->getMessage(), ['delivery_option' => $option->code, 'point' => $id]);

            return null;
        }
    }
}
