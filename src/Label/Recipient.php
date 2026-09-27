<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

final readonly class Recipient
{
    public function __construct(
        public string $name,
        public ?string $company,
        public string $street,
        public string $postcode,
        public string $city,
        public string $countryCode,
        public ?string $email,
        public ?string $phoneNumber,
    ) {
    }
}
