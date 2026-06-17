<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Application\Provider\ScoringClient;
use App\Infrastructure\Provider\Fake\FakeScoring;
use App\Infrastructure\Provider\OpenAi\OpenAiScoring;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Selects the active LLM scoring provider from AI_LLM_PROVIDER (spec §10).
 * `fake` (default) keeps the pipeline runnable with no paid calls; `openai`
 * is the first real provider (M4). Gemini/Anthropic are planned.
 * The result is wrapped by EvidenceValidatingScoringClient (see services.yaml).
 */
final class ScoringClientFactory
{
    public function __construct(
        #[Autowire('%env(AI_LLM_PROVIDER)%')] private readonly string $provider,
        private readonly FakeScoring $fake,
        private readonly OpenAiScoring $openai,
    ) {
    }

    public function create(): ScoringClient
    {
        return match ($this->provider) {
            'openai' => $this->openai,
            'fake', '' => $this->fake,
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported AI_LLM_PROVIDER "%s" (supported: openai, fake; gemini/anthropic planned).',
                $this->provider,
            )),
        };
    }
}
