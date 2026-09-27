<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Address\Ban;

use Mahoudeau\UniversalShipping\Address\AddressSuggestion;
use Mahoudeau\UniversalShipping\Address\Ban\BanAddressProvider;
use Mahoudeau\UniversalShipping\Provider\ProviderUnavailableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(BanAddressProvider::class)]
#[CoversClass(AddressSuggestion::class)]
final class BanAddressProviderTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    public function testSuggestAsksTheGeoplateformeInAutocompleteMode(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture('search_rue_de_la_paix'))]);

        $provider->suggest('12 rue de la Paix Marseille', 'FR', 5);

        self::assertCount(1, $this->requests);
        self::assertSame('GET', $this->requests[0]['method']);
        $url = $this->requests[0]['url'];
        self::assertStringStartsWith('https://data.geopf.fr/geocodage/search?', $url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame([
            'q' => '12 rue de la Paix Marseille',
            'index' => 'address',
            'autocomplete' => '1',
            'limit' => '8',
        ], $query, 'No department filter for metropolitan France');
        self::assertSame(3.0, $this->requests[0]['options']['timeout']);
    }

    public function testSuggestionsAreMappedFromTheGeoJson(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture('search_rue_de_la_paix'))]);

        $suggestions = $provider->suggest('12 rue de la Paix Marseille', 'FR', 5);

        self::assertCount(5, $suggestions);

        $first = $suggestions[0];
        self::assertSame('12 Rue de la Paix 34340 Marseillan', $first->label);
        self::assertSame('12', $first->houseNumber);
        self::assertSame('Rue de la Paix', $first->street, 'The street without its number');
        self::assertSame('12 Rue de la Paix', $first->streetLine());
        self::assertSame('34340', $first->postcode);
        self::assertSame('Marseillan', $first->city);
        self::assertSame('FR', $first->countryCode);
        self::assertSame(43.35369, $first->latitude, 'GeoJSON puts the longitude first');
        self::assertSame(3.528171, $first->longitude);

        $street = $suggestions[2];
        self::assertNull($street->houseNumber);
        self::assertSame('Rue de la paix marcel paul', $street->streetLine());
        self::assertSame('13001', $street->postcode);
    }

    public function testSuggestRespectsTheLimitAndDropsMunicipalities(): void
    {
        $answer = json_decode($this->fixture('search_rue_de_la_paix'), true);
        array_unshift($answer['features'], [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [5.405, 43.282]],
            'properties' => ['label' => 'Marseille', 'name' => 'Marseille', 'postcode' => '13001', 'city' => 'Marseille', 'type' => 'municipality', 'score' => 0.9],
        ]);
        $provider = $this->provider([new MockResponse((string) json_encode($answer))]);

        $suggestions = $provider->suggest('Marseille', 'FR', 2);

        self::assertSame(
            ['12 Rue de la Paix 34340 Marseillan', '12 Rue de Marseille 44800 Saint-Herblain'],
            array_map(static fn (AddressSuggestion $suggestion): string => $suggestion->label, $suggestions),
        );
    }

    public function testOtherCountriesAndShortQueriesNeverReachTheBan(): void
    {
        $provider = $this->provider([]);

        self::assertSame([], $provider->suggest('12 rue de la Paix', 'BE'));
        self::assertSame([], $provider->suggest('12', 'FR'));
        self::assertNull($provider->geocode('Rue de la Loi 16, 1000 Bruxelles', 'BE'));
        self::assertFalse($provider->supports('DE'));
        self::assertSame([], $this->requests);
    }

    public function testOverseasDepartmentsOnlyGetTheirOwnAddresses(): void
    {
        // As the BAN answered on 28 September 2026: every overseas department is filed under depcode 97.
        $answer = ['type' => 'FeatureCollection', 'features' => [
            self::feature('Rue de Paris 97400 Saint-Denis', 'Rue de Paris', '97400', 'Saint-Denis'),
            self::feature('rue de paris 97500 Saint-Pierre', 'rue de paris', '97500', 'Saint-Pierre'),
            self::feature('Rue de Paris 97450 Saint-Louis', 'Rue de Paris', '97450', 'Saint-Louis'),
        ]];
        $provider = $this->provider([new MockResponse((string) json_encode($answer))]);

        self::assertTrue($provider->supports('re'));
        $suggestions = $provider->suggest('rue de Paris Saint', 'RE', 5);

        parse_str((string) parse_url($this->requests[0]['url'], \PHP_URL_QUERY), $query);
        self::assertSame('97', $query['depcode']);
        self::assertSame('15', $query['limit'], 'Room for the other departments of the group');
        self::assertSame(['97400', '97450'], array_map(static fn (AddressSuggestion $suggestion): string => $suggestion->postcode, $suggestions));
        self::assertSame('RE', $suggestions[0]->countryCode);
    }

    public function testGeocodeReturnsTheBestMatchWithItsCoordinates(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture('geocode_marseille'))]);

        $found = $provider->geocode('18 rue Francis de Pressensé, 13001 Marseille', 'FR', '13001');

        self::assertNotNull($found);
        self::assertSame(43.300778, $found->latitude);
        self::assertSame(5.376726, $found->longitude);
        self::assertSame('18', $found->houseNumber);

        parse_str((string) parse_url($this->requests[0]['url'], \PHP_URL_QUERY), $query);
        self::assertSame([
            'q' => '18 rue Francis de Pressensé, 13001 Marseille',
            'index' => 'address',
            'autocomplete' => '0',
            'limit' => '1',
            'postcode' => '13001',
        ], $query);
    }

    public function testAMalformedPostcodeIsNotSentAsAFilter(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture('geocode_marseille'))]);

        $provider->geocode('18 rue Francis de Pressensé Marseille', 'FR', '13 001');

        parse_str((string) parse_url($this->requests[0]['url'], \PHP_URL_QUERY), $query);
        self::assertArrayNotHasKey('postcode', $query);
    }

    public function testALowScoreIsAGuessNotAMatch(): void
    {
        $answer = json_decode($this->fixture('geocode_marseille'), true);
        $answer['features'][0]['properties']['score'] = 0.31;
        $provider = $this->provider([new MockResponse((string) json_encode($answer))]);

        self::assertNull($provider->geocode('somewhere in Marseille', 'FR'));
    }

    public function testNothingFoundIsNull(): void
    {
        $provider = $this->provider([new MockResponse('{"type":"FeatureCollection","features":[],"query":"xyz"}')]);

        self::assertNull($provider->geocode('nowhere at all', 'FR'));
    }

    public function testErrorsAndTimeoutsBecomeProviderUnavailable(): void
    {
        $provider = $this->provider([
            new MockResponse('{"code":429,"message":"Too many requests"}', ['http_code' => 429]),
            new MockResponse('', ['error' => 'Idle timeout reached']),
            new MockResponse('<html>Bad gateway</html>', ['http_code' => 200]),
        ]);

        foreach (['rate limited', 'timeout', 'not json'] as $case) {
            try {
                $provider->suggest('12 rue de la Paix', 'FR');
                self::fail('Expected ProviderUnavailableException: ' . $case);
            } catch (ProviderUnavailableException $exception) {
                self::assertStringStartsWith('Address provider "ban" is unavailable', $exception->getMessage(), $case);
            }
        }
    }

    public function testTheUrlCanPointAtAnotherServer(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture('geocode_marseille'))], 'https://ban.example/');

        $provider->geocode('18 rue Francis de Pressensé Marseille', 'FR');

        self::assertStringStartsWith('https://ban.example/search?', $this->requests[0]['url']);
    }

    /** @return array<string, mixed> */
    private static function feature(string $label, string $street, string $postcode, string $city): array
    {
        return [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [55.45, -20.88]],
            'properties' => ['label' => $label, 'name' => $street, 'street' => $street, 'postcode' => $postcode, 'city' => $city, 'type' => 'street', 'score' => 0.8, 'depcode' => '97'],
        ];
    }

    /** @param list<MockResponse> $responses */
    private function provider(array $responses, string $url = BanAddressProvider::DEFAULT_URL): BanAddressProvider
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses) ?? throw new \LogicException('Unexpected request to ' . $url);
        });

        return new BanAddressProvider($httpClient, $url);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../Fixtures/ban/' . $name . '.json');
    }
}
