<?php

declare(strict_types=1);

namespace App\Application\Pipeline;

use App\Application\Message\EmbedCallMessage;
use App\Application\Provider\EmbeddingClient;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use App\Infrastructure\Doctrine\Repository\UtteranceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Final stage: embed the call's utterances, then complete the call. (Vectors are
 * persisted to the pgvector column from M5; for now embedding just marks each
 * utterance embedded so the pipeline reaches `completed`.) Audio-retention
 * deletion is wired in M8.
 */
#[AsMessageHandler]
final class EmbedCallHandler
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly UtteranceRepository $utterances,
        private readonly EmbeddingClient $embedding,
        private readonly StepRunner $step,
    ) {
    }

    public function __invoke(EmbedCallMessage $message): void
    {
        $call = $this->calls->get(Uuid::fromString($message->callId));
        if ($call === null) {
            return;
        }

        $this->step->run($call, 'embed', 'start_embedding', 'complete', function () use ($call) {
            $utterances = $this->utterances->findForCall($call);
            $texts = array_map(static fn ($u) => $u->text(), $utterances);

            if ($texts !== []) {
                $this->embedding->embed($texts); // vectors stored on utterances in M5
                foreach ($utterances as $utterance) {
                    $utterance->markEmbedded();
                }
            }
        });
    }
}
