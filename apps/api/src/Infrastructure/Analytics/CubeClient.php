<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client for the Cube REST API (spec §14). Mints a short-lived HS256 token
 * carrying the tenant in the security context — Cube's queryRewrite then scopes
 * every query to that tenant. Postgres stays the source of truth.
 */
final class CubeClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(CUBE_URL)%')] private readonly string $cubeUrl,
        #[Autowire('%env(CUBEJS_API_SECRET)%')] private readonly string $apiSecret,
    ) {
    }

    /**
     * @param array<string,mixed> $query a Cube query
     *
     * @return array<int,array<string,mixed>> the data rows
     */
    public function load(array $query, string $tenantId): array
    {
        $token = $this->token($tenantId);
        $url = rtrim($this->cubeUrl, '/') . '/cubejs-api/v1/load';

        // Cube returns {"error":"Continue wait"} while it computes — poll briefly.
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $response = $this->httpClient->request('POST', $url, [
                'auth_bearer' => $token,
                'json' => ['query' => $query],
                'timeout' => 30,
            ]);

            $data = $response->toArray(false);
            if (($data['error'] ?? null) === 'Continue wait') {
                usleep(400_000);
                continue;
            }
            if ($response->getStatusCode() >= 300 || isset($data['error'])) {
                throw new \RuntimeException('Cube error: ' . ($data['error'] ?? $response->getStatusCode()));
            }

            return $data['data'] ?? [];
        }

        throw new \RuntimeException('Cube did not return results in time.');
    }

    private function token(string $tenantId): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = ['tenantId' => $tenantId, 'exp' => time() + 120];

        $segments = [
            $this->base64Url(json_encode($header, \JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode($payload, \JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $this->apiSecret, true);

        return $signingInput . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
