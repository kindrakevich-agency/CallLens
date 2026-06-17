<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Application\Provider\EmbeddingClient;
use App\Infrastructure\Provider\Fake\FakeEmbedding;
use App\Infrastructure\Provider\OpenAi\OpenAiEmbedding;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Selects the active embeddings provider from AI_EMBEDDINGS_PROVIDER (spec §10).
 * `fake` (default) keeps the pipeline runnable with no paid calls; `openai`
 * is the first real provider (M5). Voyage is planned.
 */
final class EmbeddingClientFactory
{
    public function __construct(
        #[Autowire('%env(AI_EMBEDDINGS_PROVIDER)%')] private readonly string $provider,
        private readonly FakeEmbedding $fake,
        private readonly OpenAiEmbedding $openai,
    ) {
    }

    public function create(): EmbeddingClient
    {
        return match ($this->provider) {
            'openai' => $this->openai,
            'fake', '' => $this->fake,
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported AI_EMBEDDINGS_PROVIDER "%s" (supported: openai, fake; voyage planned).',
                $this->provider,
            )),
        };
    }
}
