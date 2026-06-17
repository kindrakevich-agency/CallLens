<?php

declare(strict_types=1);

namespace App\Application\Provider;

/**
 * Embeddings port (spec §10). Returns one vector per input text (batched). The
 * vectors are stored on utterances for tenant-scoped semantic search (M5).
 */
interface EmbeddingClient
{
    /**
     * @param string[] $texts
     *
     * @return array<int, float[]> one embedding vector per input, in order
     */
    public function embed(array $texts): array;

    /** Embedding dimension (must match the Utterance.embedding column). */
    public function dimension(): int;
}
