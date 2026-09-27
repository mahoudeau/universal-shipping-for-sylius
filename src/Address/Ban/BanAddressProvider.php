<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Address\Ban;

use Mahoudeau\UniversalShipping\Address\AddressProviderInterface;
use Mahoudeau\UniversalShipping\Address\AddressSuggestion;
use Mahoudeau\UniversalShipping\Provider\ProviderUnavailableException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Base Adresse Nationale (BAN), France's official address base, through the
 * IGN Géoplateforme geocoding service. Free, open licence, no key.
 *
 * The historic api-adresse.data.gouv.fr was folded into the Géoplateforme in 2025
 * and announced as closing at the end of January 2026. Checked on 28 September 2026:
 *   https://data.geopf.fr/geocodage/search       (reference: https://data.geopf.fr/geocodage/openapi.yaml)
 *   q, index=address, limit (max 50), autocomplete=1|0, postcode, depcode, type, lat/lon
 *   GeoJSON features: properties label, housenumber, street, name, postcode, city,
 *   citycode, context, type (housenumber|street|locality|municipality), score;
 *   geometry coordinates as [longitude, latitude].
 * Limit: 50 requests per second per IP. AddressFinder caches every answer.
 */
final readonly class BanAddressProvider implements AddressProviderInterface
{
    public const DEFAULT_URL = 'https://data.geopf.fr/geocodage';

    /** Below this BAN score a geocoded address is a guess, not a match. */
    public const MIN_GEOCODE_SCORE = 0.5;

    /**
     * Metropolitan France, and the overseas departments Sylius knows as countries of
     * their own, with the postcode prefix that keeps a search inside them. The BAN
     * files every overseas department under depcode 97, so the search asks for 97
     * and the prefix sorts the answers out.
     */
    private const COUNTRIES = [
        'FR' => null,
        'GP' => '971',
        'MQ' => '972',
        'GF' => '973',
        'RE' => '974',
        'YT' => '976',
    ];

    /** A municipality alone doesn't fill a street field, nor locate a customer. */
    private const ADDRESS_TYPES = ['housenumber', 'street', 'locality'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url = self::DEFAULT_URL,
    ) {
    }

    public function supports(string $countryCode): bool
    {
        return \array_key_exists(strtoupper($countryCode), self::COUNTRIES);
    }

    public function suggest(string $query, string $countryCode, int $limit = 5): array
    {
        $query = trim($query);
        // The BAN refuses queries under 3 characters.
        if (!$this->supports($countryCode) || mb_strlen($query) < 3) {
            return [];
        }

        $limit = max(1, $limit);
        $overseas = null !== self::COUNTRIES[strtoupper($countryCode)];

        $suggestions = $this->search([
            'q' => $query,
            'index' => 'address',
            'autocomplete' => '1',
            // Spares, since municipalities and other overseas departments are dropped.
            'limit' => min(50, $overseas ? $limit * 3 : $limit + 3),
        ], $countryCode, 3);

        return \array_slice($suggestions, 0, $limit);
    }

    public function geocode(string $address, string $countryCode, ?string $postcode = null): ?AddressSuggestion
    {
        $address = trim($address);
        if (!$this->supports($countryCode) || mb_strlen($address) < 3) {
            return null;
        }

        $postcode = null !== $postcode && 1 === preg_match('/^\d{5}$/', $postcode) ? $postcode : null;
        $overseas = null !== self::COUNTRIES[strtoupper($countryCode)];

        $best = $this->search([
            'q' => $address,
            'index' => 'address',
            'autocomplete' => '0',
            // Overseas: the best answer may be in another department of the 97 group.
            'limit' => $overseas && null === $postcode ? 5 : 1,
            'postcode' => $postcode,
        ], $countryCode, 5)[0] ?? null;

        return null !== $best && $best->hasCoordinates() ? $best : null;
    }

    /**
     * @param array<string, scalar|null> $query
     *
     * @return list<AddressSuggestion> in the BAN's order, best first
     */
    private function search(array $query, string $countryCode, int $timeout): array
    {
        $prefix = self::COUNTRIES[strtoupper($countryCode)];
        if (null !== $prefix) {
            $query['depcode'] = '97';
        }
        $geocoding = '0' === ($query['autocomplete'] ?? null);

        try {
            $response = $this->httpClient->request('GET', rtrim($this->url, '/') . '/search', [
                'query' => array_filter($query, static fn (mixed $value): bool => null !== $value),
                'headers' => ['Accept' => 'application/json'],
                'timeout' => $timeout,
                'max_duration' => $timeout,
            ]);

            $features = $response->toArray()['features'] ?? [];
        } catch (ExceptionInterface $exception) {
            throw new ProviderUnavailableException(sprintf('Address provider "ban" is unavailable: %s', $exception->getMessage()), 0, $exception);
        }

        $suggestions = [];
        foreach ((array) $features as $feature) {
            if (!\is_array($feature)) {
                continue;
            }
            if ($geocoding && (float) ($feature['properties']['score'] ?? 0) < self::MIN_GEOCODE_SCORE) {
                continue;
            }
            $suggestion = $this->map($feature, $countryCode);
            if (null !== $suggestion && (null === $prefix || str_starts_with($suggestion->postcode, $prefix))) {
                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }

    /** @param array<mixed> $feature */
    private function map(array $feature, string $countryCode): ?AddressSuggestion
    {
        $properties = $feature['properties'] ?? null;
        if (!\is_array($properties) || !\in_array($properties['type'] ?? null, self::ADDRESS_TYPES, true)) {
            return null;
        }

        $houseNumber = self::string($properties['housenumber'] ?? null);
        $street = self::string($properties['street'] ?? null) ?? self::string($properties['name'] ?? null);
        $postcode = self::string($properties['postcode'] ?? null);
        $city = self::string($properties['city'] ?? null);
        if (null === $street || null === $postcode || null === $city) {
            return null;
        }

        $coordinates = $feature['geometry']['coordinates'] ?? null;
        $hasCoordinates = \is_array($coordinates) && is_numeric($coordinates[0] ?? null) && is_numeric($coordinates[1] ?? null);

        return new AddressSuggestion(
            label: self::string($properties['label'] ?? null) ?? trim(sprintf('%s %s %s %s', $houseNumber ?? '', $street, $postcode, $city)),
            houseNumber: $houseNumber,
            street: $street,
            postcode: $postcode,
            city: $city,
            countryCode: strtoupper($countryCode),
            // GeoJSON order: longitude first.
            latitude: $hasCoordinates ? (float) $coordinates[1] : null,
            longitude: $hasCoordinates ? (float) $coordinates[0] : null,
        );
    }

    private static function string(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
