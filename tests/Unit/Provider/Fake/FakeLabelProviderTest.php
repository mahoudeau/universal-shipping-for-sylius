<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Provider\Fake;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Label\LabelRequest;
use Mahoudeau\UniversalShipping\Label\Recipient;
use Mahoudeau\UniversalShipping\Model\DeliveryMode;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;
use Mahoudeau\UniversalShipping\Provider\Fake\FakeLabelProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FakeLabelProvider::class)]
final class FakeLabelProviderTest extends TestCase
{
    public function testItMakesAnAnnouncedParcelWithATrackingNumber(): void
    {
        $parcel = (new FakeLabelProvider())->createLabel(self::request(), self::option());

        self::assertSame('fake', $parcel->provider);
        self::assertMatchesRegularExpression('/^fake-[0-9a-f]{10}$/', $parcel->id);
        self::assertMatchesRegularExpression('/^FK\d{10}$/', (string) $parcel->trackingNumber);
        self::assertSame('mondial_relay', $parcel->carrier);
        self::assertSame(ParcelStatus::Announced, $parcel->status);
    }

    public function testTheLabelIsAWellFormedPdf(): void
    {
        $provider = new FakeLabelProvider();
        $parcel = $provider->createLabel(self::request(), self::option());

        $pdf = $provider->label($parcel);

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringEndsWith("%%EOF\n", $pdf);
        self::assertStringContainsString('(TEST LABEL)', $pdf);
        self::assertStringContainsString('(' . $parcel->trackingNumber . ')', $pdf);

        // Every xref entry points at its object: viewers rely on it.
        self::assertSame(1, preg_match('/startxref\n(\d+)/', $pdf, $startxref));
        preg_match_all('/^(\d{10}) 00000 n $/m', substr($pdf, (int) ($startxref[1] ?? 0)), $offsets);
        self::assertCount(5, $offsets[1]);
        foreach ($offsets[1] as $index => $offset) {
            self::assertStringStartsWith(($index + 1) . ' 0 obj', substr($pdf, (int) $offset));
        }
    }

    public function testCancellingIsImmediate(): void
    {
        $provider = new FakeLabelProvider();

        $parcel = $provider->cancel($provider->createLabel(self::request(), self::option()));

        self::assertSame(ParcelStatus::Cancelled, $parcel->status);
    }

    private static function option(): DeliveryOption
    {
        return new DeliveryOption('relay', 'Relay', 'sendcloud', 'mondial_relay', DeliveryMode::PickupPoint, [], 'fake');
    }

    private static function request(): LabelRequest
    {
        return new LabelRequest('17', '000000042', new Recipient('Camille Martin', null, '12 rue de la Paix', '13001', 'Marseille', 'FR', null, null), null, 300, 2828, 'EUR');
    }
}
