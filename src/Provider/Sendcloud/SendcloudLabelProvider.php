<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider\Sendcloud;

use Mahoudeau\UniversalShipping\Address\HouseNumber;
use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Label\AsLabelProvider;
use Mahoudeau\UniversalShipping\Label\LabelException;
use Mahoudeau\UniversalShipping\Label\LabelProviderInterface;
use Mahoudeau\UniversalShipping\Label\LabelRequest;
use Mahoudeau\UniversalShipping\Model\Parcel;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;

/**
 * Labels through Sendcloud's shipments API v3. The v2 parcels API is closed to accounts
 * opened since April 2026.
 *
 * Delivery option settings:
 *   shipping_option  Sendcloud shipping option code, e.g. "mondial_relay:service_point,dualapi/size=l,c2c".
 *                    POST /api/v3/shipping-options lists the ones your account can use.
 *   contract_id      Only when you have several contracts with the same carrier.
 *   insure_above     Orders above this total, in euros, get Sendcloud's extra insurance (charged per label).
 *   carrier_cover    What the carrier already covers, in euros, taken off the insured amount.
 */
#[AsLabelProvider('sendcloud')]
final readonly class SendcloudLabelProvider implements LabelProviderInterface
{
    /** Sendcloud's free test option: a real label, never charged. */
    public const TEST_SHIPPING_OPTION = 'sendcloud:letter';

    /** Sendcloud's bounds for additional_insured_price, in euros. */
    private const MIN_INSURED = 2.0;

    private const MAX_INSURED = 5000.0;

    public function __construct(
        private SendcloudClient $client,
        /** Every label becomes an unstamped letter, whatever the delivery option says. */
        private bool $testMode = false,
        /** A sender address saved in Sendcloud. Without one, the account's only sender address. */
        private ?int $senderAddressId = null,
        /** A4, A5 or A6; null keeps the carrier's own size. */
        private ?string $paperSize = null,
    ) {
    }

    public function createLabel(LabelRequest $request, DeliveryOption $option): Parcel
    {
        // A label is paid for. If an earlier try announced the shipment but its answer never
        // arrived (a timeout), Sendcloud has it: take that one instead of paying twice.
        $existing = $this->existingShipment($request);
        if (null !== $existing) {
            return $this->parcelFrom($existing, $option);
        }

        $payload = $this->payload($request, $option);
        // Sendcloud refuses a shipment without one, though its API reference says optional.
        $payload['from_address'] = ['sender_address_id' => $this->senderAddressId ?? $this->onlySenderAddressId()];

        $data = $this->client->announce($payload);

        $parcel = $data['parcels'][0] ?? null;
        if (\is_array($parcel) && 'ANNOUNCEMENT_FAILED' === ($parcel['status']['code'] ?? null)) {
            throw new LabelException(self::errors($data) ?? 'The carrier refused the parcel.');
        }

        return $this->parcelFrom($data, $option);
    }

    /**
     * The live shipment Sendcloud already has for this request: same order number, same
     * reference (the Sylius shipment id), not cancelled nor refused by the carrier.
     *
     * @return array<string, mixed>|null
     */
    private function existingShipment(LabelRequest $request): ?array
    {
        foreach ($this->client->shipmentsForOrder($request->orderNumber) as $shipment) {
            $reference = $shipment['reference'] ?? null;
            if (!\is_scalar($reference) || (string) $reference !== $request->reference) {
                continue;
            }
            $parcel = $shipment['parcels'][0] ?? null;
            $code = \is_array($parcel) ? ($parcel['status']['code'] ?? null) : null;
            if (!\is_string($code) || 'ANNOUNCEMENT_FAILED' === $code || str_starts_with($code, 'CANCEL')) {
                continue;
            }

            return $shipment;
        }

        return null;
    }

    /** @param array<string, mixed> $data a v3 shipment, from the announce answer or the shipments list */
    private function parcelFrom(array $data, DeliveryOption $option): Parcel
    {
        $parcel = $data['parcels'][0] ?? null;
        if (!\is_array($parcel) || !isset($data['id'], $parcel['id'])) {
            throw new LabelException(self::errors($data) ?? 'Sendcloud did not return a parcel.');
        }

        return new Parcel(
            provider: 'sendcloud',
            id: (string) $parcel['id'],
            reference: (string) $data['id'],
            carrier: (string) ($data['carrier']['code'] ?? $option->carrier),
            trackingNumber: self::nonEmpty($parcel['tracking_number'] ?? null),
            trackingUrl: self::nonEmpty($parcel['tracking_url'] ?? null),
            status: ParcelStatus::Announced,
            statusText: self::nonEmpty($parcel['status']['message'] ?? null),
            statusChangedAt: microtime(true),
        );
    }

    public function label(Parcel $parcel): string
    {
        return $this->client->parcelDocument($parcel->id, 'label', $this->paperSize);
    }

    public function cancel(Parcel $parcel): Parcel
    {
        $status = $this->client->cancelShipment($parcel->reference);

        return 'cancelled' === $status
            ? $parcel->withStatus(ParcelStatus::Cancelled, 'Cancelled', microtime(true))
            : $parcel->withStatus(ParcelStatus::Cancelling, 'Cancellation requested', microtime(true));
    }

    /** @return array<string, mixed> */
    public function payload(LabelRequest $request, DeliveryOption $option): array
    {
        $shippingOption = $this->testMode ? self::TEST_SHIPPING_OPTION : ($option->options['shipping_option'] ?? null);
        if (!\is_string($shippingOption) || '' === $shippingOption) {
            throw new LabelException(sprintf('Delivery option "%s" has no Sendcloud shipping_option.', $option->code));
        }

        $properties = ['shipping_option_code' => $shippingOption];
        if (!$this->testMode && isset($option->options['contract_id'])) {
            $properties['contract_id'] = (int) $option->options['contract_id'];
        }

        $recipient = $request->recipient;
        // Sylius keeps the number in the street line; carriers read it best on its own.
        [$houseNumber, $street] = HouseNumber::split($recipient->street);
        $payload = [
            'ship_with' => ['type' => 'shipping_option_code', 'properties' => $properties],
            'to_address' => array_filter([
                'name' => $recipient->name,
                'company_name' => $recipient->company,
                'address_line_1' => $street,
                'house_number' => $houseNumber,
                'postal_code' => $recipient->postcode,
                'city' => $recipient->city,
                'country_code' => $recipient->countryCode,
                'email' => $recipient->email,
                'phone_number' => $recipient->phoneNumber,
            ], static fn (?string $value): bool => null !== $value && '' !== $value),
            'parcels' => [[
                'weight' => ['value' => number_format($request->weightInGrams / 1000, 3, '.', ''), 'unit' => 'kg'],
            ]],
            'order_number' => $request->orderNumber,
            'total_order_price' => [
                'value' => number_format($request->orderTotal / 100, 2, '.', ''),
                'currency' => $request->currencyCode,
            ],
            'reference' => $request->reference,
            'label_details' => ['mime_type' => 'application/pdf'],
        ];

        // An unstamped letter goes to an address, never to a pickup point.
        if (!$this->testMode && null !== $request->pickupPoint) {
            $payload['to_service_point'] = ['id' => (int) $request->pickupPoint->id];
        }

        $insured = $this->testMode ? null : self::insuredAmount($request, $option);
        if (null !== $insured) {
            $payload['parcels'][0]['additional_insured_price'] = [
                'value' => number_format($insured, 2, '.', ''),
                'currency' => $request->currencyCode,
            ];
        }

        return $payload;
    }

    /**
     * Sendcloud's extra cover (through its insurer, on top of the carrier's own) for orders
     * above `insure_above`: the order total minus `carrier_cover`, within the 2 to 5000 euros
     * Sendcloud accepts. Both settings in euros; no `insure_above`, no extra cover.
     */
    private static function insuredAmount(LabelRequest $request, DeliveryOption $option): ?float
    {
        $above = $option->options['insure_above'] ?? null;
        $total = $request->orderTotal / 100;
        if (!is_numeric($above) || $total <= (float) $above) {
            return null;
        }

        $cover = $option->options['carrier_cover'] ?? 0;
        $amount = min(self::MAX_INSURED, $total - (is_numeric($cover) ? (float) $cover : 0.0));

        return $amount >= self::MIN_INSURED ? $amount : null;
    }

    private function onlySenderAddressId(): int
    {
        $ids = $this->client->senderAddressIds();

        return match (\count($ids)) {
            1 => $ids[0],
            0 => throw new LabelException('No sender address in Sendcloud. Add one under Settings > Addresses.'),
            default => throw new LabelException(sprintf(
                'Sendcloud has %d sender addresses. Choose one with universal_shipping.sendcloud.sender_address_id: %s.',
                \count($ids),
                implode(', ', $ids),
            )),
        };
    }

    /** @param array<string, mixed> $data */
    private static function errors(array $data): ?string
    {
        $messages = [];
        foreach ((array) ($data['errors'] ?? []) as $error) {
            if (\is_array($error)) {
                $messages[] = (string) ($error['detail'] ?? $error['message'] ?? $error['code'] ?? '');
            }
        }
        foreach ((array) ($data['parcels'][0]['errors'] ?? []) as $error) {
            if (\is_array($error)) {
                $messages[] = (string) ($error['detail'] ?? $error['message'] ?? $error['code'] ?? '');
            }
        }

        $messages = array_filter($messages);

        return [] === $messages ? null : 'Sendcloud: ' . implode('; ', $messages);
    }

    private static function nonEmpty(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }
}
