<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Address;

use Mahoudeau\UniversalShipping\Provider\ProviderUnavailableException;

/**
 * A source of addresses: the French national address base (BAN) today, others later.
 *
 * Providers only talk to their service. AddressFinder, in front of them, caches
 * answers and turns outages into empty answers, so the checkout never breaks.
 */
interface AddressProviderInterface
{
    /** Countries this provider has addresses for. Asked before every call. */
    public function supports(string $countryCode): bool;

    /**
     * Addresses matching what the customer has typed so far, best first.
     *
     * @return list<AddressSuggestion>
     *
     * @throws ProviderUnavailableException when the service can't be reached or answers with an error
     */
    public function suggest(string $query, string $countryCode, int $limit = 5): array;

    /**
     * The one address that best matches a complete address, with its coordinates,
     * or null when nothing matches well enough to be trusted.
     *
     * @throws ProviderUnavailableException when the service can't be reached or answers with an error
     */
    public function geocode(string $address, string $countryCode, ?string $postcode = null): ?AddressSuggestion;
}
