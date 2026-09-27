<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Model\Parcel;

/**
 * Announces parcels to a carrier and hands back their labels.
 * Register with #[AsLabelProvider('code')], the same code as the pickup point provider.
 */
interface LabelProviderInterface
{
    /**
     * Announces the parcel. Most carriers charge for it from here on.
     *
     * @throws LabelException when the carrier refuses, with a message fit for the admin
     */
    public function createLabel(LabelRequest $request, DeliveryOption $option): Parcel;

    /**
     * The label, as a PDF.
     *
     * @throws LabelException
     */
    public function label(Parcel $parcel): string;

    /**
     * Asks the carrier to void the label. Returns the parcel as Cancelled, or Cancelling
     * when the carrier answers later.
     *
     * @throws LabelException when the carrier refuses, e.g. the parcel is already on its way
     */
    public function cancel(Parcel $parcel): Parcel;
}
