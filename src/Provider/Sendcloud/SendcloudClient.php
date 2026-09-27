<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider\Sendcloud;

use Mahoudeau\UniversalShipping\Label\LabelException;
use Mahoudeau\UniversalShipping\Provider\ProviderUnavailableException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper over Sendcloud's service point API (v2) and shipments API (v3).
 * https://sendcloud.dev/api/v2/service-points
 * https://sendcloud.dev/api/v3/shipments
 */
final readonly class SendcloudClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $publicKey,
        private string $secretKey,
        private string $servicePointsUrl = 'https://servicepoints.sendcloud.sc/api/v2',
        private string $apiUrl = 'https://panel.sendcloud.sc/api/v3',
    ) {
    }

    /**
     * Creates a shipment and announces it to the carrier in one call.
     * For a single parcel the answer carries the label too.
     *
     * @param array<string, mixed> $shipment
     *
     * @return array<string, mixed> the "data" node
     */
    public function announce(array $shipment): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->v3('POST', '/shipments/announce', ['json' => $shipment, 'timeout' => 30])['data'] ?? [];

        return $data;
    }

    /** @return list<int> ids of the sender addresses saved in the Sendcloud account */
    public function senderAddressIds(): array
    {
        $answer = $this->v3('GET', '/addresses/sender-addresses', ['query' => ['page_size' => 100], 'timeout' => 10]);

        $ids = [];
        foreach ((array) ($answer['data'] ?? []) as $address) {
            if (\is_array($address) && isset($address['id'])) {
                $ids[] = (int) $address['id'];
            }
        }

        return $ids;
    }

    /** @return string the document as a PDF */
    public function parcelDocument(string $parcelId, string $type, ?string $paperSize = null): string
    {
        try {
            $response = $this->httpClient->request('GET', sprintf('%s/parcels/%s/documents/%s', $this->apiUrl, rawurlencode($parcelId), $type), [
                'auth_basic' => [$this->publicKey, $this->secretKey],
                'headers' => ['Accept' => 'application/pdf'],
                'query' => array_filter(['paper_size' => $paperSize]),
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() >= 400) {
                throw new LabelException(self::errorMessage($response->toArray(false), $response->getStatusCode()));
            }

            return $response->getContent();
        } catch (ExceptionInterface $exception) {
            throw LabelException::because('Sendcloud', $exception);
        }
    }

    /** @return string "cancelled", or "queued" when Sendcloud cancels it later */
    public function cancelShipment(string $shipmentId): string
    {
        $answer = $this->v3('POST', sprintf('/shipments/%s/cancel', rawurlencode($shipmentId)), ['timeout' => 15]);

        return (string) ($answer['data']['status'] ?? 'queued');
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return list<array<string, mixed>>
     */
    public function servicePoints(array $query): array
    {
        // The trailing slash matters: without it Sendcloud answers with a redirect.
        /** @var list<array<string, mixed>> $points */
        $points = array_values($this->request('/service-points/', $query));

        return $points;
    }

    /** @return array<string, mixed>|null */
    public function servicePoint(string $id): ?array
    {
        if (!ctype_digit($id)) {
            return null;
        }

        try {
            return $this->request(sprintf('/service-points/%s/', $id));
        } catch (ProviderUnavailableException $exception) {
            if (404 === $exception->getCode()) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<mixed>
     */
    private function request(string $path, array $query = []): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->servicePointsUrl . $path, [
                'auth_basic' => [$this->publicKey, $this->secretKey],
                'query' => $query,
                'timeout' => 5,
            ]);

            if (404 === $response->getStatusCode()) {
                throw new ProviderUnavailableException('Not found', 404);
            }

            return $response->toArray();
        } catch (ExceptionInterface $exception) {
            throw ProviderUnavailableException::because('sendcloud', $exception);
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    private function v3(string $method, string $path, array $options): array
    {
        try {
            $response = $this->httpClient->request($method, $this->apiUrl . $path, [
                'auth_basic' => [$this->publicKey, $this->secretKey],
            ] + $options);

            $answer = $response->toArray(false);
            if ($response->getStatusCode() >= 400) {
                throw new LabelException(self::errorMessage($answer, $response->getStatusCode()));
            }

            return $answer;
        } catch (ExceptionInterface $exception) {
            throw LabelException::because('Sendcloud', $exception);
        }
    }

    /**
     * v3 errors: {"errors": [{"detail": "...", "source": {"pointer": "/to_address/postal_code"}}]}
     *
     * @param array<mixed> $answer
     */
    private static function errorMessage(array $answer, int $statusCode): string
    {
        $messages = [];
        foreach ((array) ($answer['errors'] ?? []) as $error) {
            if (!\is_array($error)) {
                continue;
            }
            $pointer = $error['source']['pointer'] ?? null;
            $detail = (string) ($error['detail'] ?? $error['title'] ?? $error['code'] ?? '');
            $messages[] = \is_string($pointer) && '' !== $pointer ? sprintf('%s (%s)', $detail, ltrim($pointer, '/')) : $detail;
        }

        $messages = array_filter($messages);

        return [] === $messages ? sprintf('Sendcloud answered with HTTP %d.', $statusCode) : 'Sendcloud: ' . implode('; ', $messages);
    }
}
