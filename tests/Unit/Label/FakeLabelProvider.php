<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Label;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Label\LabelProviderInterface;
use Mahoudeau\UniversalShipping\Label\LabelRequest;
use Mahoudeau\UniversalShipping\Model\Parcel;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;

final class FakeLabelProvider implements LabelProviderInterface
{
    /** @var list<LabelRequest> */
    public array $requests = [];

    public function createLabel(LabelRequest $request, DeliveryOption $option): Parcel
    {
        $this->requests[] = $request;

        return new Parcel('sendcloud', '383707309', 'b7c1a5de', $option->carrier, '12345678', null, ParcelStatus::Announced, 'Ready to send', microtime(true));
    }

    public function label(Parcel $parcel): string
    {
        return '%PDF';
    }

    public function cancel(Parcel $parcel): Parcel
    {
        return $parcel->withStatus(ParcelStatus::Cancelled, 'Cancelled', microtime(true));
    }
}
