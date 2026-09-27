<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Provider\Sendcloud;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Model\DeliveryMode;
use Mahoudeau\UniversalShipping\Provider\PickupPointQuery;
use Mahoudeau\UniversalShipping\Provider\ProviderUnavailableException;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudClient;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudPickupPointProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(SendcloudPickupPointProvider::class)]
#[CoversClass(SendcloudClient::class)]
final class SendcloudPickupPointProviderTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    public function testSearchSendsOneAddressLineAndFiltersByPointType(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture())]);

        $provider->search(new PickupPointQuery('FR', '12 rue de la Paix, 13001 Marseille'), $this->option(['servicepoint']));

        self::assertCount(1, $this->requests);
        $url = $this->requests[0]['url'];
        self::assertStringStartsWith('https://servicepoints.test/api/v2/service-points/?', $url, 'Trailing slash, or Sendcloud redirects');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame([
            'country' => 'FR',
            'carrier' => 'mondial_relay',
            'address' => '12 rue de la Paix, 13001 Marseille',
            'radius' => '5000',
            'general_shop_type' => 'servicepoint',
        ], $query);
        self::assertContains('Authorization: Basic ' . base64_encode('public:secret'), $this->requests[0]['options']['headers']);
    }

    public function testSearchKeepsOnlyActiveRelayPointsNearestFirst(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture())]);

        $points = $provider->search(new PickupPointQuery('FR', 'Marseille'), $this->option(['servicepoint']));

        self::assertSame(['FR00111', 'FR36091'], array_map(static fn ($point) => $point->code, $points));
    }

    public function testSearchWithoutPointTypesKeepsLockersToo(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture())]);

        $points = $provider->search(new PickupPointQuery('FR', 'Marseille'), $this->option([]));

        self::assertSame(['FR00111', 'FR23059', 'FR36091'], array_map(static fn ($point) => $point->code, $points));
    }

    public function testSearchRespectsTheLimit(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture())]);

        $points = $provider->search(new PickupPointQuery('FR', 'Marseille'), $this->option([], ['limit' => 1]));

        self::assertCount(1, $points);
    }

    public function testPointsAreMappedAndCleanedUp(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture())]);

        $point = $provider->search(new PickupPointQuery('FR', 'Marseille'), $this->option(['servicepoint']))[0];

        self::assertSame('sendcloud', $point->provider);
        self::assertSame('10459634', $point->id);
        self::assertSame('FR00111', $point->code);
        self::assertSame('mondial_relay', $point->carrier);
        self::assertSame('PORTE DAIX NETPHONE', $point->name);
        self::assertSame("18 RUE FRANCIS DE PRESSENSE (PORTE D'AIX)", $point->street, 'Runs of spaces and doubled apostrophes removed');
        self::assertSame('13001', $point->postcode);
        self::assertSame('MARSEILLE', $point->city);
        self::assertSame('FR', $point->countryCode);
        self::assertSame(43.300716, $point->latitude);
        self::assertSame(131, $point->distance);
        self::assertSame(['08:45 - 12:15', '14:00 - 18:00'], $point->openingHours[0]);
        self::assertSame([], $point->openingHours[6]);
    }

    public function testFindReturnsThePoint(): void
    {
        $data = json_decode($this->fixture(), true)[2];
        $provider = $this->provider([new MockResponse((string) json_encode($data))]);

        $point = $provider->find('10459634', $this->option(['servicepoint']));

        self::assertSame('FR00111', $point?->code);
        self::assertStringEndsWith('/service-points/10459634/', $this->requests[0]['url']);
    }

    public function testFindRejectsAPointOfAnotherCarrierOrType(): void
    {
        $data = json_decode($this->fixture(), true)[1];
        $provider = $this->provider([
            new MockResponse((string) json_encode($data)),
            new MockResponse((string) json_encode(['carrier' => 'colissimo'] + $data)),
        ]);

        self::assertNull($provider->find('12397514', $this->option(['servicepoint'])), 'A locker is not a relay point');
        self::assertNull($provider->find('12397514', $this->option([])), 'Another carrier');
    }

    public function testFindReturnsNullForAnUnknownOrMalformedId(): void
    {
        $provider = $this->provider([new MockResponse('{"error":"not found"}', ['http_code' => 404])]);

        self::assertNull($provider->find('123', $this->option([])));
        self::assertNull($provider->find('../../user', $this->option([])), 'Never sent to Sendcloud');
        self::assertCount(1, $this->requests);
    }

    public function testCarrierErrorsBecomeProviderUnavailable(): void
    {
        $provider = $this->provider([new MockResponse('{"error":"unauthorized"}', ['http_code' => 401])]);

        $this->expectException(ProviderUnavailableException::class);

        $provider->search(new PickupPointQuery('FR', 'Marseille'), $this->option([]));
    }

    /** @param list<MockResponse> $responses */
    private function provider(array $responses): SendcloudPickupPointProvider
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses) ?? throw new \LogicException('Unexpected request to ' . $url);
        });

        return new SendcloudPickupPointProvider(new SendcloudClient($httpClient, 'public', 'secret', 'https://servicepoints.test/api/v2'));
    }

    /**
     * @param list<string> $pointTypes
     * @param array<string, mixed> $options
     */
    private function option(array $pointTypes, array $options = []): DeliveryOption
    {
        return new DeliveryOption('mondial_relay', 'Mondial Relay', 'sendcloud', 'mondial_relay', DeliveryMode::PickupPoint, ['point_types' => $pointTypes] + $options);
    }

    private function fixture(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../Fixtures/sendcloud/service_points_marseille.json');
    }
}
