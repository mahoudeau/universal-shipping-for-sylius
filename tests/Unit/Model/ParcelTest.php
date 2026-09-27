<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Model;

use Mahoudeau\UniversalShipping\Model\Parcel;
use Mahoudeau\UniversalShipping\Model\ParcelAwareTrait;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parcel::class)]
#[CoversClass(ParcelStatus::class)]
final class ParcelTest extends TestCase
{
    public function testItSurvivesTheRoundTripThroughTheShipmentColumn(): void
    {
        $parcel = self::parcel();

        self::assertEquals($parcel, Parcel::fromArray($parcel->toArray()));
    }

    public function testTheShipmentTraitKeepsTheParcelIdApartForWebhooks(): void
    {
        $shipment = new class() {
            use ParcelAwareTrait;

            public function parcelId(): ?string
            {
                return $this->parcelId;
            }
        };

        $shipment->setParcel(self::parcel());
        self::assertSame('383707309', $shipment->getParcel()?->id);
        self::assertSame('383707309', $shipment->parcelId());

        $shipment->setParcel(null);
        self::assertNull($shipment->getParcel());
        self::assertNull($shipment->parcelId());
    }

    public function testStatusAndTrackingChangesKeepTheRest(): void
    {
        $parcel = self::parcel()
            ->withStatus(ParcelStatus::ReadyForPickup, 'Awaiting customer pickup', 1790000000.0)
            ->withTracking(null, 'https://tracking.test/new');

        self::assertSame(ParcelStatus::ReadyForPickup, $parcel->status);
        self::assertSame('Awaiting customer pickup', $parcel->statusText);
        self::assertSame(1790000000.0, $parcel->statusChangedAt);
        self::assertSame('12345678', $parcel->trackingNumber, 'A missing tracking number does not erase ours');
        self::assertSame('https://tracking.test/new', $parcel->trackingUrl);
        self::assertSame('b7c1a5de', $parcel->reference);
    }

    public function testAnUnknownStoredStatusFallsBackToAnnounced(): void
    {
        self::assertSame(ParcelStatus::Announced, Parcel::fromArray(['status' => 'lost_in_space'] + self::parcel()->toArray())->status);
    }

    public function testWhichStatusesLockTheLabel(): void
    {
        self::assertTrue(ParcelStatus::Announced->isActive());
        self::assertTrue(ParcelStatus::Problem->isActive());
        self::assertFalse(ParcelStatus::Cancelling->isActive());
        self::assertFalse(ParcelStatus::Cancelled->isActive());

        self::assertFalse(ParcelStatus::Announced->isHandedOver());
        self::assertTrue(ParcelStatus::InTransit->isHandedOver());
        self::assertTrue(ParcelStatus::Delivered->isHandedOver());
    }

    private static function parcel(): Parcel
    {
        return new Parcel(
            provider: 'sendcloud',
            id: '383707309',
            reference: 'b7c1a5de',
            carrier: 'mondial_relay',
            trackingNumber: '12345678',
            trackingUrl: 'https://tracking.test/12345678',
            status: ParcelStatus::Announced,
            statusText: 'Ready to send',
            statusChangedAt: 1789000000.5,
        );
    }
}
