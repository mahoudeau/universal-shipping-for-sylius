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
 */
#[AsLabelProvider('sendcloud')]
final readonly class SendcloudLabelProvider implements LabelProviderInterface
{
    /** Sendcloud's free test option: a real label, never charged. */
    public const TEST_SHIPPING_OPTION = 'sendcloud:letter';

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
        $payload = $this->payload($request, $option);
        // Sendcloud refuses a shipment without one, though its API reference says optional.
        $payload['from_address'] = ['sender_address_id' => $this->senderAddressId ?? $this->onlySenderAddressId()];

        $data = $this->client->announce($payload);

        $parcel = $data['parcels'][0] ?? null;
        if (!\is_array($parcel) || !isset($data['id'], $parcel['id'])) {
            throw new LabelException(self::errors($data) ?? 'Sendcloud did not return a parcel.');
        }

        if ('ANNOUNCEMENT_FAILED' === ($parcel['status']['code'] ?? null)) {
            throw new LabelException(self::errors($data) ?? 'The carrier refused the parcel.');
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

        return $payload;
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
