<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider\Fake;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Label\AsLabelProvider;
use Mahoudeau\UniversalShipping\Label\LabelProviderInterface;
use Mahoudeau\UniversalShipping\Label\LabelRequest;
use Mahoudeau\UniversalShipping\Model\Parcel;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;

/**
 * Labels without a carrier: a made-up tracking number and a PDF that says it is a test.
 * Nothing leaves the shop. Move the parcel along with bin/console universal-shipping:fake-tracking.
 */
#[AsLabelProvider('fake')]
final class FakeLabelProvider implements LabelProviderInterface
{
    public function createLabel(LabelRequest $request, DeliveryOption $option): Parcel
    {
        $id = 'fake-' . bin2hex(random_bytes(5));

        return new Parcel(
            provider: 'fake',
            id: $id,
            reference: $id,
            carrier: $option->carrier,
            trackingNumber: sprintf('FK%010d', random_int(0, 9_999_999_999)),
            trackingUrl: null,
            status: ParcelStatus::Announced,
            statusText: 'Fake label',
            statusChangedAt: microtime(true),
        );
    }

    public function label(Parcel $parcel): string
    {
        return self::pdf($parcel);
    }

    public function cancel(Parcel $parcel): Parcel
    {
        return $parcel->withStatus(ParcelStatus::Cancelled, 'Fake label cancelled', microtime(true));
    }

    /** One A6 page, Helvetica, drawn by hand: enough for a printer and a PDF viewer. */
    private static function pdf(Parcel $parcel): string
    {
        $text = static fn (int $size, int $x, int $y, string $value): string => sprintf(
            "BT /F1 %d Tf %d %d Td (%s) Tj ET\n",
            $size,
            $x,
            $y,
            // ASCII only, with PDF string escapes.
            addcslashes((string) preg_replace('/[^\x20-\x7E]/', '?', $value), '()\\'),
        );

        $content = "1 w 10 10 278 400 re S\n";
        $content .= $text(22, 20, 370, 'TEST LABEL');
        $content .= $text(9, 20, 352, 'Not a real parcel. No carrier was contacted.');
        $content .= $text(11, 20, 315, 'Carrier: ' . $parcel->carrier);
        $content .= $text(11, 20, 297, 'Parcel: ' . $parcel->id);

        $x = 20;
        foreach (str_split((string) $parcel->trackingNumber) as $character) {
            $width = \ord($character) % 3 + 1;
            $content .= sprintf("%d 120 %d 120 re f\n", $x, $width);
            $x += $width + 2;
        }
        $content .= $text(14, 20, 98, (string) $parcel->trackingNumber);
        $content .= $text(8, 20, 24, 'Universal Shipping for Sylius, fake label provider');

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 298 420] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            sprintf("<< /Length %d >>\nstream\n%sendstream", \strlen($content), $content),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = \strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $index + 1, $object);
        }

        $xref = \strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n0000000000 65535 f \n", \count($objects) + 1);
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", \count($objects) + 1, $xref);
    }
}
