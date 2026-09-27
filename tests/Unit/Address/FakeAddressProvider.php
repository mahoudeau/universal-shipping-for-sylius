<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Address;

use Mahoudeau\UniversalShipping\Address\AddressProviderInterface;
use Mahoudeau\UniversalShipping\Address\AddressSuggestion;
use Mahoudeau\UniversalShipping\Provider\ProviderUnavailableException;

/** France only, one known address, counts its calls, and can be taken down. */
final class FakeAddressProvider implements AddressProviderInterface
{
    public int $calls = 0;

    public bool $down = false;

    public ?AddressSuggestion $match;

    public function __construct()
    {
        $this->match = self::marseille();
    }

    public static function marseille(): AddressSuggestion
    {
        return new AddressSuggestion('18 Rue francis de pressense 13001 Marseille', '18', 'Rue francis de pressense', '13001', 'Marseille', 'FR', 43.300778, 5.376726);
    }

    public function supports(string $countryCode): bool
    {
        return 'FR' === $countryCode;
    }

    public function suggest(string $query, string $countryCode, int $limit = 5): array
    {
        ++$this->calls;
        if ($this->down) {
            throw new ProviderUnavailableException('Address provider "fake" is unavailable: down');
        }

        return null === $this->match ? [] : [$this->match];
    }

    public function geocode(string $address, string $countryCode, ?string $postcode = null): ?AddressSuggestion
    {
        ++$this->calls;
        if ($this->down) {
            throw new ProviderUnavailableException('Address provider "fake" is unavailable: down');
        }

        return $this->match;
    }
}
