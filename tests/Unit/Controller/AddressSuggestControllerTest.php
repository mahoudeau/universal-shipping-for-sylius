<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Controller;

use Mahoudeau\UniversalShipping\Address\AddressFinder;
use Mahoudeau\UniversalShipping\Address\AddressSuggestion;
use Mahoudeau\UniversalShipping\Controller\AddressSuggestController;
use Mahoudeau\UniversalShipping\Tests\Unit\Address\FakeAddressProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(AddressSuggestController::class)]
#[CoversClass(AddressSuggestion::class)]
final class AddressSuggestControllerTest extends TestCase
{
    private FakeAddressProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new FakeAddressProvider();
    }

    public function testAnswersSuggestionsWithoutCoordinates(): void
    {
        $response = $this->call(['q' => '18 rue Francis', 'country' => 'FR']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame([
            'supported' => true,
            'suggestions' => [[
                'label' => '18 Rue francis de pressense 13001 Marseille',
                'streetLine' => '18 Rue francis de pressense',
                'houseNumber' => '18',
                'street' => 'Rue francis de pressense',
                'postcode' => '13001',
                'city' => 'Marseille',
                'countryCode' => 'FR',
            ]],
        ], self::json($response));
    }

    public function testTheAnswerMayOnlyBeCachedByTheBrowser(): void
    {
        $response = $this->call(['q' => '18 rue Francis', 'country' => 'FR']);

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertSame('300', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function testTheCountryDefaultsToFranceAndIgnoresCase(): void
    {
        self::assertTrue(self::json($this->call(['q' => '18 rue Francis']))['supported']);
        self::assertTrue(self::json($this->call(['q' => '18 rue Francis', 'country' => 'fr']))['supported']);
    }

    public function testAQueryTooShortIsNotAnErrorAndNotSent(): void
    {
        $response = $this->call(['q' => ' 18 ', 'country' => 'FR']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['supported' => true, 'suggestions' => []], self::json($response));
        self::assertSame(0, $this->provider->calls);
    }

    public function testAnUnsupportedCountrySaysSoSoTheScriptStopsAsking(): void
    {
        $response = $this->call(['q' => 'Kerkstraat 12', 'country' => 'NL']);

        self::assertSame(['supported' => false, 'suggestions' => []], self::json($response));
        self::assertSame(0, $this->provider->calls);
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('badRequests')]
    public function testMalformedInputIsRefused(array $query): void
    {
        $response = $this->call($query);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertArrayHasKey('error', self::json($response));
        self::assertSame(0, $this->provider->calls);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function badRequests(): iterable
    {
        yield 'country is not a code' => [['q' => '18 rue Francis', 'country' => 'France']];
        yield 'country with digits' => [['q' => '18 rue Francis', 'country' => 'F1']];
        yield 'query too long' => [['q' => str_repeat('a', AddressSuggestController::MAX_LENGTH + 1), 'country' => 'FR']];
        yield 'query as an array' => [['q' => ['18 rue Francis'], 'country' => 'FR']];
        yield 'country as an array' => [['q' => '18 rue Francis', 'country' => ['FR']]];
    }

    public function testAnOutageIsAnEmptyListNotAnError(): void
    {
        $this->provider->down = true;

        $response = $this->call(['q' => '18 rue Francis', 'country' => 'FR']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['supported' => true, 'suggestions' => []], self::json($response));
    }

    public function testTheRouteIsNotFoundWhenAutocompleteIsOff(): void
    {
        $response = (new AddressSuggestController(null))(Request::create('/universal-shipping/address/suggest', 'GET', ['q' => '18 rue Francis']));

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /** @param array<string, mixed> $query */
    private function call(array $query): Response
    {
        $controller = new AddressSuggestController(new AddressFinder($this->provider, new ArrayAdapter()));

        return $controller(Request::create('/universal-shipping/address/suggest', 'GET', $query));
    }

    /** @return array<string, mixed> */
    private static function json(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true);
        self::assertIsArray($data);

        return $data;
    }
}
