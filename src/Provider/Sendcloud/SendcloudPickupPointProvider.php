<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider\Sendcloud;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Model\PickupPoint;
use Mahoudeau\UniversalShipping\Provider\AsPickupPointProvider;
use Mahoudeau\UniversalShipping\Provider\PickupPointProviderInterface;
use Mahoudeau\UniversalShipping\Provider\PickupPointQuery;

/**
 * Delivery option settings:
 *   point_types  list of Sendcloud general_shop_type values to show,
 *                e.g. ['servicepoint'] for relay points only, ['locker'] for lockers
 *   radius       search radius in metres (default 5000)
 *   limit        how many points to offer (default 10)
 */
#[AsPickupPointProvider('sendcloud')]
final readonly class SendcloudPickupPointProvider implements PickupPointProviderInterface
{
    public function __construct(private SendcloudClient $client)
    {
    }

    public function search(PickupPointQuery $query, DeliveryOption $option): array
    {
        $parameters = [
            'country' => $query->countryCode,
            'carrier' => $option->carrier,
            // A single line: Sendcloud finds nothing when postcode and city are sent apart.
            'address' => $query->address,
            'radius' => (int) ($option->options['radius'] ?? 5000),
        ];

        $types = $option->options['point_types'] ?? [];
        if (1 === \count($types)) {
            $parameters['general_shop_type'] = $types[0];
        }

        $points = [];
        foreach ($this->client->servicePoints($parameters) as $data) {
            if ([] !== $types && !\in_array($data['general_shop_type'] ?? null, $types, true)) {
                continue;
            }
            if (false === ($data['is_active'] ?? true)) {
                continue;
            }

            $points[] = $this->map($data);
        }

        usort($points, static fn (PickupPoint $a, PickupPoint $b): int => ($a->distance ?? \PHP_INT_MAX) <=> ($b->distance ?? \PHP_INT_MAX));

        return \array_slice($points, 0, (int) ($option->options['limit'] ?? 10));
    }

    public function find(string $id, DeliveryOption $option): ?PickupPoint
    {
        $data = $this->client->servicePoint($id);

        if (null === $data || ($data['carrier'] ?? null) !== $option->carrier || false === ($data['is_active'] ?? true)) {
            return null;
        }

        $types = $option->options['point_types'] ?? [];
        if ([] !== $types && !\in_array($data['general_shop_type'] ?? null, $types, true)) {
            return null;
        }

        return $this->map($data);
    }

    /** @param array<string, mixed> $data */
    private function map(array $data): PickupPoint
    {
        $street = trim(sprintf('%s %s', $data['house_number'] ?? '', $data['street'] ?? ''));

        $openingHours = [];
        foreach ($data['formatted_opening_times'] ?? [] as $day => $ranges) {
            $openingHours[(int) $day] = array_values(array_map('strval', (array) $ranges));
        }

        return new PickupPoint(
            provider: 'sendcloud',
            id: (string) $data['id'],
            carrier: (string) $data['carrier'],
            code: (string) $data['code'],
            name: self::clean((string) $data['name']),
            street: self::clean($street),
            postcode: (string) $data['postal_code'],
            city: self::clean((string) $data['city']),
            countryCode: (string) $data['country'],
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            openingHours: $openingHours,
            distance: isset($data['distance']) ? (int) $data['distance'] : null,
        );
    }

    /** Carrier data comes with runs of spaces and doubled apostrophes ("D''AIX"). */
    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace("''", "'", $value)));
    }
}
