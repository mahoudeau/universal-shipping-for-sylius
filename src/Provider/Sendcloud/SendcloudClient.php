<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider\Sendcloud;

use Mahoudeau\UniversalShipping\Provider\ProviderUnavailableException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper over Sendcloud's service point API.
 * https://api.sendcloud.dev/docs/sendcloud-public-api/branches/v2/service-points
 */
final readonly class SendcloudClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $publicKey,
        private string $secretKey,
        private string $servicePointsUrl = 'https://servicepoints.sendcloud.sc/api/v2',
    ) {
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
}
