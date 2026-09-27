<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

/**
 * A relay point or locker, as the carrier described it when the customer chose it.
 *
 * Stored as a copy on the shipment, never as a reference: the address on the order
 * must not change if the carrier edits or closes the point later.
 */
final readonly class PickupPoint
{
    /**
     * @param array<int, list<string>> $openingHours Day index (0 = Monday) to time ranges, e.g. ['09:00 - 12:30']
     */
    public function __construct(
        /** Provider that found the point, e.g. "sendcloud". */
        public string $provider,
        /** Provider's own identifier, used to fetch the point again. */
        public string $id,
        /** Carrier code, e.g. "mondial_relay". */
        public string $carrier,
        /** Carrier's own point number, printed on the label, e.g. "FR00111". */
        public string $code,
        public string $name,
        public string $street,
        public string $postcode,
        public string $city,
        public string $countryCode,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public array $openingHours = [],
        /** Metres from the searched address. Only meaningful in search results. */
        public ?int $distance = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'id' => $this->id,
            'carrier' => $this->carrier,
            'code' => $this->code,
            'name' => $this->name,
            'street' => $this->street,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'country_code' => $this->countryCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'opening_hours' => $this->openingHours,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string) $data['provider'],
            id: (string) $data['id'],
            carrier: (string) $data['carrier'],
            code: (string) $data['code'],
            name: (string) $data['name'],
            street: (string) $data['street'],
            postcode: (string) $data['postcode'],
            city: (string) $data['city'],
            countryCode: (string) $data['country_code'],
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            openingHours: $data['opening_hours'] ?? [],
        );
    }

    public function withoutDistance(): self
    {
        return self::fromArray($this->toArray());
    }
}
