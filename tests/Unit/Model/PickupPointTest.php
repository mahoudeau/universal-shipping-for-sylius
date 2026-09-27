<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Model;

use Mahoudeau\UniversalShipping\Model\PickupPoint;
use Mahoudeau\UniversalShipping\Model\PickupPointAwareTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PickupPoint::class)]
final class PickupPointTest extends TestCase
{
    public function testItSurvivesTheRoundTripThroughTheShipmentColumn(): void
    {
        $point = self::point();

        self::assertEquals($point->withoutDistance(), PickupPoint::fromArray($point->toArray()));
    }

    public function testTheDistanceIsNotStored(): void
    {
        self::assertArrayNotHasKey('distance', self::point()->toArray());
        self::assertNull(self::point()->withoutDistance()->distance);
    }

    public function testTheShipmentTraitStoresACopy(): void
    {
        $shipment = new class() {
            use PickupPointAwareTrait;
        };

        self::assertNull($shipment->getPickupPoint());

        $shipment->setPickupPoint(self::point());
        self::assertSame('FR00111', $shipment->getPickupPoint()?->code);

        $shipment->setPickupPoint(null);
        self::assertNull($shipment->getPickupPoint());
    }

    private static function point(): PickupPoint
    {
        return new PickupPoint(
            provider: 'sendcloud',
            id: '10459634',
            carrier: 'mondial_relay',
            code: 'FR00111',
            name: 'Porte d\'Aix Netphone',
            street: '18 rue Francis de Pressensé',
            postcode: '13001',
            city: 'Marseille',
            countryCode: 'FR',
            latitude: 43.300716,
            longitude: 5.376711,
            openingHours: [0 => ['08:45 - 12:15', '14:00 - 18:00'], 6 => []],
            distance: 131,
        );
    }
}
