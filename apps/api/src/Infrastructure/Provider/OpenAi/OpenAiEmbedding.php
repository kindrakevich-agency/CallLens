<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\OpenAi;

use App\Application\Provider\EmbeddingClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI embeddings (spec §10, M5). Batches texts to /v1/embeddings and reduces
 * to EMBEDDING_DIM via the `dimensions` param so vectors match the
 * `utterance.embedding vector(1024)` column.
 */
final class OpenAiEmbedding implements EmbeddingClient
{
    private const ENDPOINT = 'https://api.openai.com/v1/embeddings';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(OPENAI_API_KEY)%')] private readonly string $apiKey,
        #[Autowire('%env(OPENAI_EMBEDDING_MODEL)%')] private readonly string $model,
        #[Autowire('%env(int:EMBEDDING_DIM)%')] private readonly int $dimension,
    ) {
    }

    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }
        if ($this->apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $this->model,
                'input' => array_values($texts),
                'dimensions' => $this->dimension,
            ],
            'timeout' => 120,
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf(
                'OpenAI embeddings returned %d: %s',
                $response->getStatusCode(),
                substr($response->getContent(false), 0, 500),
            ));
        }

        $data = $response->toArray()['data'] ?? [];
        // Sort by index to guarantee input order, then return the vectors.
        usort($data, static fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(static fn ($row) => array_map('floatval', $row['embedding'] ?? []), $data);
    }

    public function dimension(): int
    {
        return $this->dimension;
    }
}
