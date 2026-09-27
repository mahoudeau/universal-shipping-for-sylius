<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Controller;

use Mahoudeau\UniversalShipping\Provider\Sendcloud\SendcloudParcelStatus;
use Mahoudeau\UniversalShipping\Tracking\ParcelTracker;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sendcloud calls this on every parcel status change, signed with the integration's secret key.
 * https://sendcloud.dev/docs/webhooks
 *
 * Answers 200 to anything signed, even what it ignores: Sendcloud retries every
 * other answer up to ten times.
 */
final readonly class SendcloudWebhookController
{
    public function __construct(
        private ParcelTracker $tracker,
        private string $secretKey,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if ('' === $this->secretKey) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $body = $request->getContent();
        $signature = (string) $request->headers->get('Sendcloud-Signature', '');

        if (!hash_equals(hash_hmac('sha256', $body, $this->secretKey), $signature)) {
            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($body, true);
        if (!\is_array($payload) || 'parcel_status_changed' !== ($payload['action'] ?? null) || !\is_array($payload['parcel'] ?? null)) {
            return new Response('ignored');
        }

        $parcel = $payload['parcel'];
        $statusId = (int) ($parcel['status']['id'] ?? 0);
        $status = SendcloudParcelStatus::fromId($statusId);

        if (null === $status || !isset($parcel['id'])) {
            $this->logger->info('Sendcloud parcel status {status} ignored.', ['status' => $statusId]);

            return new Response('ignored');
        }

        $updated = $this->tracker->update(
            provider: 'sendcloud',
            parcelId: (string) $parcel['id'],
            status: $status,
            statusText: isset($parcel['status']['message']) ? (string) $parcel['status']['message'] : null,
            changedAt: self::seconds($payload['timestamp'] ?? null),
            trackingNumber: self::nonEmpty($parcel['tracking_number'] ?? null),
            trackingUrl: self::nonEmpty($parcel['tracking_url'] ?? null),
        );

        return new Response($updated ? 'ok' : 'ignored');
    }

    /** Sendcloud's examples use milliseconds (1525271885993); take seconds too, in case. */
    private static function seconds(mixed $timestamp): float
    {
        if (!is_numeric($timestamp)) {
            return microtime(true);
        }

        $timestamp = (float) $timestamp;

        return $timestamp > 100_000_000_000 ? $timestamp / 1000 : $timestamp;
    }

    private static function nonEmpty(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }
}
