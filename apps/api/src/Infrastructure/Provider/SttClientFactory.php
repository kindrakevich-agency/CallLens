<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Application\Provider\SpeechToTextClient;
use App\Infrastructure\Provider\Deepgram\DeepgramSpeechToText;
use App\Infrastructure\Provider\Fake\FakeSpeechToText;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Selects the active speech-to-text provider from AI_STT_PROVIDER (spec §10).
 * `fake` (default) keeps the pipeline runnable with no paid calls;
 * `deepgram` is the first real provider (M3). AssemblyAI/Gladia are planned.
 */
final class SttClientFactory
{
    public function __construct(
        #[Autowire('%env(AI_STT_PROVIDER)%')] private readonly string $provider,
        private readonly FakeSpeechToText $fake,
        private readonly DeepgramSpeechToText $deepgram,
    ) {
    }

    public function create(): SpeechToTextClient
    {
        return match ($this->provider) {
            'deepgram' => $this->deepgram,
            'fake', '' => $this->fake,
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported AI_STT_PROVIDER "%s" (supported: deepgram, fake; assemblyai/gladia planned).',
                $this->provider,
            )),
        };
    }
}
