<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Provider\Sendcloud;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Label\LabelException;
use Mahoudeau\UniversalShipping\Label\LabelRequest;
use Mahoudeau\UniversalShipping\Label\Recipient;
use Mahoudeau\UniversalShipping\Model\DeliveryMode;
use Mahoudeau\UniversalShipping\Model\Parcel;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;
use Mahoudeau\UniversalShipping\Model\PickupPoint;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudClient;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudLabelProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(SendcloudLabelProvider::class)]
#[CoversClass(SendcloudClient::class)]
final class SendcloudLabelProviderTest extends TestCase
{
    private const SHIPPING_OPTION = 'mondial_relay:service_point,dualapi/size=l,c2c';

    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    public function testTheAnnouncementCarriesTheShippingOptionThePointAndTheParcel(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture(), ['http_code' => 201])], senderAddressId: 42);

        $provider->createLabel(self::request(), self::option());

        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame('https://panel.test/api/v3/shipments/announce', $this->requests[0]['url']);
        self::assertContains('Authorization: Basic ' . base64_encode('public:secret'), $this->requests[0]['options']['headers']);

        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertSame(['type' => 'shipping_option_code', 'properties' => ['shipping_option_code' => self::SHIPPING_OPTION]], $body['ship_with']);
        self::assertSame(['id' => 10459634], $body['to_service_point']);
        self::assertSame(['sender_address_id' => 42], $body['from_address']);
        self::assertSame([['weight' => ['value' => '0.350', 'unit' => 'kg']]], $body['parcels']);
        self::assertSame(['value' => '95.90', 'currency' => 'EUR'], $body['total_order_price']);
        self::assertSame('000000042', $body['order_number']);
        self::assertSame('17', $body['reference']);
        self::assertSame([
            'name' => 'Camille Martin',
            'address_line_1' => 'rue de la Paix',
            'house_number' => '12',
            'postal_code' => '13001',
            'city' => 'Marseille',
            'country_code' => 'FR',
            'email' => 'camille@example.com',
        ], $body['to_address'], 'Empty fields are left out');
    }

    public function testTheHouseNumberIsSentApartWhenTheStreetLineHasOne(): void
    {
        $provider = $this->provider([]);

        $withBis = $provider->payload(self::request('12 bis rue de la Paix'), self::option())['to_address'];
        self::assertSame('12 bis', $withBis['house_number']);
        self::assertSame('rue de la Paix', $withBis['address_line_1']);

        $without = $provider->payload(self::request('Lieu-dit Les Pins'), self::option())['to_address'];
        self::assertArrayNotHasKey('house_number', $without);
        self::assertSame('Lieu-dit Les Pins', $without['address_line_1'], 'The line stays whole');
    }

    public function testTheAnswerBecomesAParcel(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture(), ['http_code' => 201])]);

        $parcel = $provider->createLabel(self::request(), self::option());

        self::assertSame('sendcloud', $parcel->provider);
        self::assertSame('383707309', $parcel->id);
        self::assertSame('b7c1a5de-9c4e-4a51-8f39-2d2b5f0e7a11', $parcel->reference);
        self::assertSame('mondial_relay', $parcel->carrier);
        self::assertSame('12345678', $parcel->trackingNumber);
        self::assertStringStartsWith('https://tracking.', (string) $parcel->trackingUrl);
        self::assertSame(ParcelStatus::Announced, $parcel->status);
        self::assertSame('Ready to send', $parcel->statusText);
    }

    public function testWithoutAConfiguredSenderTheAccountsOnlyOneIsUsed(): void
    {
        $provider = $this->provider([
            new MockResponse('{"data":[{"id":929030,"city":"Marseille","country_code":"FR"}]}'),
            new MockResponse($this->fixture(), ['http_code' => 201]),
        ], senderAddressId: null);

        $provider->createLabel(self::request(), self::option());

        self::assertSame('https://panel.test/api/v3/addresses/sender-addresses?page_size=100', $this->requests[0]['url']);
        $body = json_decode((string) $this->requests[1]['options']['body'], true);
        self::assertSame(['sender_address_id' => 929030], $body['from_address']);
    }

    public function testSeveralSendersMeanTheShopMustChoose(): void
    {
        $provider = $this->provider([new MockResponse('{"data":[{"id":1},{"id":2}]}')], senderAddressId: null);

        $this->expectException(LabelException::class);
        $this->expectExceptionMessage('sender_address_id: 1, 2');

        $provider->createLabel(self::request(), self::option());
    }

    public function testTestModeSendsAFreeLetterToTheAddressInstead(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture(), ['http_code' => 201])], testMode: true);

        $provider->createLabel(self::request(), self::option(['contract_id' => 7]));

        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertSame(['shipping_option_code' => 'sendcloud:letter'], $body['ship_with']['properties']);
        self::assertArrayNotHasKey('to_service_point', $body);
    }

    public function testTheContractIsSentWhenConfigured(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture(), ['http_code' => 201])]);

        $provider->createLabel(self::request(), self::option(['contract_id' => '7']));

        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertSame(7, $body['ship_with']['properties']['contract_id']);
    }

    public function testAnOrderAboveTheThresholdIsInsuredForWhatTheCarrierDoesNotCover(): void
    {
        $provider = $this->provider([new MockResponse($this->fixture(), ['http_code' => 201])]);

        $provider->createLabel(self::request(), self::option(['insure_above' => 50, 'carrier_cover' => 25]));

        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertSame(['value' => '70.90', 'currency' => 'EUR'], $body['parcels'][0]['additional_insured_price']);
    }

    public function testNoInsuranceBelowTheThresholdOrWithoutOne(): void
    {
        $provider = $this->provider([
            new MockResponse($this->fixture(), ['http_code' => 201]),
            new MockResponse($this->fixture(), ['http_code' => 201]),
        ]);

        $provider->createLabel(self::request(), self::option(['insure_above' => 150]));
        $provider->createLabel(self::request(), self::option());

        foreach ($this->requests as $request) {
            $body = json_decode((string) $request['options']['body'], true);
            self::assertArrayNotHasKey('additional_insured_price', $body['parcels'][0]);
        }
    }

    public function testAnOptionWithoutShippingOptionIsRefusedBeforeCallingSendcloud(): void
    {
        $provider = $this->provider([]);

        try {
            $provider->createLabel(self::request(), new DeliveryOption('mr', 'MR', 'sendcloud', 'mondial_relay', DeliveryMode::PickupPoint));
            self::fail('Expected a LabelException');
        } catch (LabelException $exception) {
            self::assertStringContainsString('shipping_option', $exception->getMessage());
        }

        self::assertSame([], $this->requests);
    }

    public function testSendcloudErrorsReachTheAdminInPlainWords(): void
    {
        $provider = $this->provider([new MockResponse((string) json_encode(['errors' => [[
            'status' => '400',
            'code' => 'invalid',
            'detail' => 'This field is required.',
            'source' => ['pointer' => '/to_address/postal_code'],
        ]]]), ['http_code' => 400])]);

        $this->expectException(LabelException::class);
        $this->expectExceptionMessage('Sendcloud: This field is required. (to_address/postal_code)');

        $provider->createLabel(self::request(), self::option());
    }

    public function testAFailedAnnouncementIsAnError(): void
    {
        $answer = json_decode($this->fixture(), true);
        $answer['data']['parcels'][0]['status'] = ['code' => 'ANNOUNCEMENT_FAILED', 'message' => 'Announcement failed'];
        $answer['data']['errors'] = [['code' => 'parcel_announcement_error', 'detail' => 'Invalid service point']];
        $provider = $this->provider([new MockResponse((string) json_encode($answer), ['http_code' => 201])]);

        $this->expectException(LabelException::class);
        $this->expectExceptionMessage('Invalid service point');

        $provider->createLabel(self::request(), self::option());
    }

    public function testTheLabelIsFetchedAsAPdfInTheConfiguredSize(): void
    {
        $provider = $this->provider([new MockResponse('%PDF-1.4 label', ['response_headers' => ['Content-Type' => 'application/pdf']])], paperSize: 'A4');

        self::assertSame('%PDF-1.4 label', $provider->label(self::parcel()));
        self::assertSame('https://panel.test/api/v3/parcels/383707309/documents/label?paper_size=A4', $this->requests[0]['url']);
        self::assertContains('Accept: application/pdf', $this->requests[0]['options']['headers']);
    }

    public function testCancellingIsImmediateOrQueued(): void
    {
        $provider = $this->provider([
            new MockResponse('{"data":{"status":"cancelled","message":"Shipment has been cancelled"}}'),
            new MockResponse('{"data":{"status":"queued","message":"Shipment cancellation has been queued"}}', ['http_code' => 202]),
        ]);

        self::assertSame(ParcelStatus::Cancelled, $provider->cancel(self::parcel())->status);
        self::assertSame(ParcelStatus::Cancelling, $provider->cancel(self::parcel())->status);
        self::assertSame('https://panel.test/api/v3/shipments/b7c1a5de/cancel', $this->requests[0]['url']);
    }

    public function testARefusedCancellationSaysWhy(): void
    {
        $provider = $this->provider([new MockResponse('{"errors":[{"status":"409","code":"invalid","detail":"This shipment is already being cancelled."}]}', ['http_code' => 409])]);

        $this->expectException(LabelException::class);
        $this->expectExceptionMessage('already being cancelled');

        $provider->cancel(self::parcel());
    }

    /** @param list<MockResponse> $responses */
    private function provider(array $responses, bool $testMode = false, ?int $senderAddressId = 42, ?string $paperSize = null): SendcloudLabelProvider
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses) ?? throw new \LogicException('Unexpected request to ' . $url);
        });

        return new SendcloudLabelProvider(
            new SendcloudClient($httpClient, 'public', 'secret', 'https://servicepoints.test/api/v2', 'https://panel.test/api/v3'),
            $testMode,
            $senderAddressId,
            $paperSize,
        );
    }

    /** @param array<string, mixed> $options */
    private static function option(array $options = []): DeliveryOption
    {
        return new DeliveryOption('mondial_relay', 'Mondial Relay', 'sendcloud', 'mondial_relay', DeliveryMode::PickupPoint, $options + ['shipping_option' => self::SHIPPING_OPTION]);
    }

    private static function request(string $street = '12 rue de la Paix'): LabelRequest
    {
        return new LabelRequest(
            reference: '17',
            orderNumber: '000000042',
            recipient: new Recipient('Camille Martin', null, $street, '13001', 'Marseille', 'FR', 'camille@example.com', ''),
            pickupPoint: new PickupPoint('sendcloud', '10459634', 'mondial_relay', 'FR00111', 'Porte d\'Aix Netphone', '18 rue Francis de Pressensé', '13001', 'Marseille', 'FR'),
            weightInGrams: 350,
            orderTotal: 9590,
            currencyCode: 'EUR',
        );
    }

    private static function parcel(): Parcel
    {
        return new Parcel('sendcloud', '383707309', 'b7c1a5de', 'mondial_relay', '12345678', null, ParcelStatus::Announced, null, 1789000000.0);
    }

    private function fixture(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../Fixtures/sendcloud/announce_mondial_relay.json');
    }
}
