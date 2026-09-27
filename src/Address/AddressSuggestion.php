<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Address;

/**
 * One address an address provider knows, as a customer would pick it from a list.
 */
final readonly class AddressSuggestion
{
    public function __construct(
        /** The whole address on one line, as shown in the list. */
        public string $label,
        /** "12", "12 bis", or null for a street or a place without numbers. */
        public ?string $houseNumber,
        /** The street, or the place name when there is no street. */
        public string $street,
        public string $postcode,
        public string $city,
        public string $countryCode,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
    }

    /** What goes into Sylius's street field: number first, the French way. */
    public function streetLine(): string
    {
        return trim(sprintf('%s %s', $this->houseNumber ?? '', $this->street));
    }

    public function hasCoordinates(): bool
    {
        return null !== $this->latitude && null !== $this->longitude;
    }

    /**
     * What the browser gets. Coordinates stay on the server: the form doesn't need them.
     *
     * @return array{label: string, streetLine: string, houseNumber: ?string, street: string, postcode: string, city: string, countryCode: string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'streetLine' => $this->streetLine(),
            'houseNumber' => $this->houseNumber,
            'street' => $this->street,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'countryCode' => $this->countryCode,
        ];
    }
}
