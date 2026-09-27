<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider;

use Sylius\Component\Addressing\Model\AddressInterface;

/**
 * Where to look for pickup points: a free-text address the customer typed,
 * or the order's own address. With the address module on, also its coordinates.
 */
final readonly class PickupPointQuery
{
    public function __construct(
        public string $countryCode,
        public string $address,
        /** Set when the address was geocoded. Providers that can search around a point should prefer it. */
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
    }

    public static function fromAddress(AddressInterface $address): self
    {
        return new self(
            (string) $address->getCountryCode(),
            trim(sprintf('%s, %s %s', $address->getStreet(), $address->getPostcode(), $address->getCity()), ', '),
        );
    }

    public function withCoordinates(float $latitude, float $longitude): self
    {
        return new self($this->countryCode, $this->address, $latitude, $longitude);
    }

    public function hasCoordinates(): bool
    {
        return null !== $this->latitude && null !== $this->longitude;
    }

    public function isEmpty(): bool
    {
        return '' === trim($this->address) && !$this->hasCoordinates();
    }

    public function cacheKey(): string
    {
        $key = $this->countryCode . '|' . mb_strtolower(trim($this->address));
        if ($this->hasCoordinates()) {
            $key .= sprintf('|%.5f,%.5f', $this->latitude, $this->longitude);
        }

        return hash('xxh128', $key);
    }
}
