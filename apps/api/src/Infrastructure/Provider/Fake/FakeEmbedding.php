<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Fake;

use App\Application\Provider\EmbeddingClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deterministic embeddings for dev/tests — a stable pseudo-vector per text,
 * seeded from its hash so reprocessing yields identical results.
 */
final class FakeEmbedding implements EmbeddingClient
{
    public function __construct(
        #[Autowire('%env(int:EMBEDDING_DIM)%')] private readonly int $dimension = 1024,
    ) {
    }

    public function embed(array $texts): array
    {
        $vectors = [];
        foreach ($texts as $text) {
            $seed = crc32($text);
            $vector = [];
            for ($i = 0; $i < $this->dimension; ++$i) {
                // Deterministic value in [-1, 1].
                $vector[] = (($seed + $i * 2654435761) % 2000) / 1000 - 1.0;
            }
            $vectors[] = $vector;
        }

        return $vectors;
    }

    public function dimension(): int
    {
        return $this->dimension;
    }
}
