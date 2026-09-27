<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Controller;

use Mahoudeau\UniversalShipping\Address\AddressFinder;
use Mahoudeau\UniversalShipping\Address\AddressSuggestion;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /universal-shipping/address/suggest?q=12 rue de la&country=FR
 *
 * What the autocomplete script asks while the customer types. The browser only ever
 * talks to the shop: this controller calls the address provider, server to server,
 * and answers from the cache when it can.
 *
 * Answers {"supported": bool, "suggestions": [...]}. "supported": false tells the
 * script to stop asking for that country.
 */
final readonly class AddressSuggestController
{
    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 200;

    public const LIMIT = 5;

    public function __construct(
        /** Null when the address module or its autocomplete is off. */
        private ?AddressFinder $finder,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (null === $this->finder) {
            return new JsonResponse(['error' => 'Address autocomplete is off.'], Response::HTTP_NOT_FOUND);
        }

        $parameters = $request->query->all();
        $query = $parameters['q'] ?? '';
        $country = $parameters['country'] ?? 'FR';
        if (!\is_string($query) || !\is_string($country)) {
            return self::badRequest('q and country must be strings.');
        }

        $query = trim((string) preg_replace('/\s+/u', ' ', $query));
        $country = strtoupper(trim($country));

        if (1 !== preg_match('/^[A-Z]{2}$/', $country)) {
            return self::badRequest('country must be a two-letter ISO code.');
        }
        if (mb_strlen($query) > self::MAX_LENGTH) {
            return self::badRequest(sprintf('q must be %d characters or fewer.', self::MAX_LENGTH));
        }

        if (!$this->finder->supports($country)) {
            return self::answer(false, []);
        }

        // Too short to mean anything yet: not an error, the customer is still typing.
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return self::answer(true, []);
        }

        return self::answer(true, $this->finder->suggest($query, $country, self::LIMIT));
    }

    /** @param list<AddressSuggestion> $suggestions */
    private static function answer(bool $supported, array $suggestions): JsonResponse
    {
        $response = new JsonResponse([
            'supported' => $supported,
            'suggestions' => array_map(static fn (AddressSuggestion $suggestion): array => $suggestion->toArray(), $suggestions),
        ]);

        // The browser may keep it while the customer types and deletes; shared caches may not.
        $response->setPrivate();
        $response->setMaxAge(300);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    private static function badRequest(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
    }
}
