<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Command;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Mahoudeau\UniversalShipping\Command\FakeTrackingCommand;
use Mahoudeau\UniversalShipping\Model\Parcel;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;
use Mahoudeau\UniversalShipping\Tests\Unit\Label\TestShipment;
use Mahoudeau\UniversalShipping\Tracking\ParcelTracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(FakeTrackingCommand::class)]
final class FakeTrackingCommandTest extends TestCase
{
    public function testItMovesAFakeParcelAlong(): void
    {
        $shipment = self::shipment('fake');
        $tester = $this->tester($shipment);

        $tester->execute(['parcel' => 'fake-3f9a1c2b7e', 'status' => 'ready_for_pickup', 'message' => 'Arrived at the relay']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $parcel = $shipment->getParcel();
        self::assertNotNull($parcel);
        self::assertSame(ParcelStatus::ReadyForPickup, $parcel->status);
        self::assertSame('Arrived at the relay', $parcel->statusText);
    }

    public function testARealCarriersParcelIsLeftAlone(): void
    {
        $shipment = self::shipment('sendcloud');
        $tester = $this->tester($shipment);

        $tester->execute(['parcel' => 'fake-3f9a1c2b7e', 'status' => 'delivered']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame(ParcelStatus::Announced, $shipment->getParcel()?->status);
    }

    public function testAnUnknownStatusListsTheRightOnes(): void
    {
        $tester = $this->tester(self::shipment('fake'));

        $tester->execute(['parcel' => 'fake-3f9a1c2b7e', 'status' => 'lost']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('in_transit', $tester->getDisplay());
    }

    private function tester(TestShipment $shipment): CommandTester
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn($shipment);

        return new CommandTester(new FakeTrackingCommand(new ParcelTracker($repository, $this->createMock(ObjectManager::class))));
    }

    private static function shipment(string $provider): TestShipment
    {
        $shipment = new TestShipment();
        $shipment->setParcel(new Parcel($provider, 'fake-3f9a1c2b7e', 'fake-3f9a1c2b7e', 'mondial_relay', 'FK0000000001', null, ParcelStatus::Announced, null, 1789000000.0));

        return $shipment;
    }
}
