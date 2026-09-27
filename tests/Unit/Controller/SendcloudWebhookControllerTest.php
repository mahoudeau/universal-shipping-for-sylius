<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Controller;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Mahoudeau\UniversalShipping\Controller\SendcloudWebhookController;
use Mahoudeau\UniversalShipping\Model\Parcel;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;
use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudParcelStatus;
use Mahoudeau\UniversalShipping\Tests\Unit\Label\TestShipment;
use Mahoudeau\UniversalShipping\Tracking\ParcelTracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(SendcloudWebhookController::class)]
#[CoversClass(ParcelTracker::class)]
#[CoversClass(SendcloudParcelStatus::class)]
final class SendcloudWebhookControllerTest extends TestCase
{
    private const SECRET = 'secretkey';

    private TestShipment $shipment;

    private int $flushes = 0;

    protected function setUp(): void
    {
        $this->shipment = new TestShipment();
        $this->shipment->setParcel(new Parcel('sendcloud', '383707309', 'b7c1a5de', 'mondial_relay', null, null, ParcelStatus::Announced, 'Ready to send', 1789000000.0));
    }

    public function testTheSignatureMatchesSendcloudsOwnExample(): void
    {
        // From Sendcloud's docs: body {"key": "value"}, secret "secretkey".
        $response = $this->controller()($this->request('{"key": "value"}', '1eed4b3d41f4653ac64fd56f1bf1cbfd349e4482cbc11dff7134bd93e5da4b0a'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ignored', $response->getContent());
    }

    public function testAnUnsignedOrForgedCallIsRefused(): void
    {
        $body = $this->payload(12, 1789000100000);

        self::assertSame(401, $this->controller()($this->request($body, null))->getStatusCode());
        self::assertSame(401, $this->controller()($this->request($body, hash_hmac('sha256', $body, 'another secret')))->getStatusCode());
        self::assertSame(ParcelStatus::Announced, $this->shipment->getParcel()?->status);
    }

    public function testWithoutASecretTheEndpointDoesNotExist(): void
    {
        $controller = new SendcloudWebhookController($this->tracker(), '');

        self::assertSame(404, $controller($this->request('{}', ''))->getStatusCode());
    }

    public function testAStatusChangeUpdatesTheParcelAndTheTracking(): void
    {
        $response = $this->controller()($this->signed($this->payload(12, 1789000100000)));

        self::assertSame('ok', $response->getContent());
        $parcel = $this->shipment->getParcel();
        self::assertNotNull($parcel);
        self::assertSame(ParcelStatus::ReadyForPickup, $parcel->status);
        self::assertSame('Awaiting customer pickup', $parcel->statusText);
        self::assertSame(1789000100.0, $parcel->statusChangedAt, 'Milliseconds become seconds');
        self::assertSame('12345678', $parcel->trackingNumber);
        self::assertSame('12345678', $this->shipment->getTracking(), 'Sylius shows it to the customer');
        self::assertSame(1, $this->flushes);
    }

    public function testAnOlderUpdateArrivingLateIsIgnored(): void
    {
        $this->controller()($this->signed($this->payload(11, 1789000200000)));
        $response = $this->controller()($this->signed($this->payload(12, 1789000100000)));

        self::assertSame('ignored', $response->getContent());
        self::assertSame(ParcelStatus::Delivered, $this->shipment->getParcel()?->status);
    }

    public function testUnknownParcelsAndStatusesAreAcknowledgedButIgnored(): void
    {
        $unknownParcel = json_decode($this->payload(12, 1789000100000), true);
        $unknownParcel['parcel']['id'] = 1;

        self::assertSame('ignored', $this->controller()($this->signed((string) json_encode($unknownParcel)))->getContent());
        self::assertSame('ignored', $this->controller()($this->signed($this->payload(1337, 1789000100000)))->getContent());
        self::assertSame(0, $this->flushes);
    }

    private function controller(): SendcloudWebhookController
    {
        return new SendcloudWebhookController($this->tracker(), self::SECRET);
    }

    private function tracker(): ParcelTracker
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteria): ?TestShipment => $criteria === ['parcelId' => '383707309'] ? $this->shipment : null,
        );

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushes;
        });

        return new ParcelTracker($repository, $manager);
    }

    private function payload(int $statusId, int $timestamp): string
    {
        return (string) json_encode([
            'action' => 'parcel_status_changed',
            'timestamp' => $timestamp,
            'parcel' => [
                'id' => 383707309,
                'tracking_number' => '12345678',
                'tracking_url' => 'https://tracking.test/12345678',
                'status' => ['id' => $statusId, 'message' => 12 === $statusId ? 'Awaiting customer pickup' : 'Something'],
            ],
        ]);
    }

    private function signed(string $body): Request
    {
        return $this->request($body, hash_hmac('sha256', $body, self::SECRET));
    }

    private function request(string $body, ?string $signature): Request
    {
        $request = Request::create('/universal-shipping/webhooks/sendcloud', 'POST', content: $body);
        if (null !== $signature) {
            $request->headers->set('Sendcloud-Signature', $signature);
        }

        return $request;
    }
}
