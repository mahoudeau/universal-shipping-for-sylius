<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider;

use Sylius\Component\Addressing\Model\AddressInterface;

/**
 * Where to look for pickup points: a free-text address the customer typed,
 * or the order's own address.
 */
final readonly class PickupPointQuery
{
    public function __construct(
        public string $countryCode,
        public string $address,
    ) {
    }

    public static function fromAddress(AddressInterface $address): self
    {
        return new self(
            (string) $address->getCountryCode(),
            trim(sprintf('%s, %s %s', $address->getStreet(), $address->getPostcode(), $address->getCity()), ', '),
        );
    }

    public function isEmpty(): bool
    {
        return '' === trim($this->address);
    }

    public function cacheKey(): string
    {
        return hash('xxh128', $this->countryCode . '|' . mb_strtolower(trim($this->address)));
    }
}
