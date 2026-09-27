<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Address;

use Mahoudeau\UniversalShipping\Provider\ProviderUnavailableException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * What the rest of the plugin uses to reach the address provider, like PickupPointFinder
 * for carriers: answers are cached (autocomplete asks on every keystroke, the checkout
 * re-renders on every change) and an outage becomes an empty answer and a log line.
 */
final readonly class AddressFinder
{
    public function __construct(
        private AddressProviderInterface $provider,
        private CacheInterface $cache,
        private int $cacheTtl = 86400,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function supports(string $countryCode): bool
    {
        return $this->provider->supports($countryCode);
    }

    /** @return list<AddressSuggestion> */
    public function suggest(string $query, string $countryCode, int $limit = 5): array
    {
        $query = trim((string) preg_replace('/\s+/u', ' ', $query));
        if ('' === $query || !$this->provider->supports($countryCode)) {
            return [];
        }

        try {
            return $this->cache->get(
                self::key('suggest', $countryCode, $query, (string) $limit),
                function (ItemInterface $item) use ($query, $countryCode, $limit): array {
                    $item->expiresAfter($this->cacheTtl);

                    return $this->provider->suggest($query, $countryCode, $limit);
                },
            );
        } catch (ProviderUnavailableException $exception) {
            $this->logger->warning($exception->getMessage(), ['country' => $countryCode]);

            return [];
        }
    }

    public function geocode(string $address, string $countryCode, ?string $postcode = null): ?AddressSuggestion
    {
        $address = trim((string) preg_replace('/\s+/u', ' ', $address));
        if ('' === $address || !$this->provider->supports($countryCode)) {
            return null;
        }

        try {
            // Cached as a one-item list, so that "no match" is cached too.
            $found = $this->cache->get(
                self::key('geocode', $countryCode, $address, (string) $postcode),
                function (ItemInterface $item) use ($address, $countryCode, $postcode): array {
                    $item->expiresAfter($this->cacheTtl);

                    $suggestion = $this->provider->geocode($address, $countryCode, $postcode);

                    return null === $suggestion ? [] : [$suggestion];
                },
            );

            return $found[0] ?? null;
        } catch (ProviderUnavailableException $exception) {
            $this->logger->warning($exception->getMessage(), ['country' => $countryCode]);

            return null;
        }
    }

    private static function key(string $operation, string $countryCode, string $text, string $extra): string
    {
        return sprintf(
            'universal_shipping.address.%s.%s',
            $operation,
            hash('xxh128', strtoupper($countryCode) . '|' . mb_strtolower($text) . '|' . $extra),
        );
    }
}
