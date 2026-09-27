<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Model;

/**
 * A parcel announced to a carrier: its label, tracking number and last known status.
 * Stored on the shipment as JSON, like the pickup point.
 */
final readonly class Parcel
{
    public function __construct(
        public string $provider,
        /** The provider's id for the parcel, e.g. to download its label or match a webhook. */
        public string $id,
        /** The provider's id for the whole shipment, when it differs, e.g. to cancel it. */
        public string $reference,
        public string $carrier,
        public ?string $trackingNumber,
        public ?string $trackingUrl,
        public ParcelStatus $status,
        /** The provider's own wording for the status, shown in the admin. */
        public ?string $statusText,
        /** Unix time of the last status, to ignore updates that arrive out of order. */
        public float $statusChangedAt,
    ) {
    }

    public function withStatus(ParcelStatus $status, ?string $statusText, float $changedAt): self
    {
        return new self(
            $this->provider,
            $this->id,
            $this->reference,
            $this->carrier,
            $this->trackingNumber,
            $this->trackingUrl,
            $status,
            $statusText,
            $changedAt,
        );
    }

    public function withTracking(?string $trackingNumber, ?string $trackingUrl): self
    {
        return new self(
            $this->provider,
            $this->id,
            $this->reference,
            $this->carrier,
            $trackingNumber ?? $this->trackingNumber,
            $trackingUrl ?? $this->trackingUrl,
            $this->status,
            $this->statusText,
            $this->statusChangedAt,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'id' => $this->id,
            'reference' => $this->reference,
            'carrier' => $this->carrier,
            'tracking_number' => $this->trackingNumber,
            'tracking_url' => $this->trackingUrl,
            'status' => $this->status->value,
            'status_text' => $this->statusText,
            'status_changed_at' => $this->statusChangedAt,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string) $data['provider'],
            id: (string) $data['id'],
            reference: (string) $data['reference'],
            carrier: (string) $data['carrier'],
            trackingNumber: isset($data['tracking_number']) ? (string) $data['tracking_number'] : null,
            trackingUrl: isset($data['tracking_url']) ? (string) $data['tracking_url'] : null,
            status: ParcelStatus::tryFrom((string) ($data['status'] ?? '')) ?? ParcelStatus::Announced,
            statusText: isset($data['status_text']) ? (string) $data['status_text'] : null,
            statusChangedAt: (float) ($data['status_changed_at'] ?? 0),
        );
    }
}
