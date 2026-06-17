<?php

declare(strict_types=1);

namespace App\Application\Pipeline;

use App\Application\Message\DeleteAudioMessage;
use App\Application\Message\EmbedCallMessage;
use App\Application\Provider\EmbeddingClient;
use App\Application\Retention\RetentionPolicyResolver;
use App\Domain\Call\CallStatus;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use App\Infrastructure\Doctrine\Repository\UtteranceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Final stage: embed the call's utterances (vectors stored in the pgvector
 * `embedding` column for tenant-scoped semantic search), then complete the call.
 * Once completed, the retention policy is evaluated: `delete_after_processing`
 * dispatches audio deletion immediately (spec §9).
 */
#[AsMessageHandler]
final class EmbedCallHandler
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly UtteranceRepository $utterances,
        private readonly EmbeddingClient $embedding,
        private readonly StepRunner $step,
        private readonly RetentionPolicyResolver $retention,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(EmbedCallMessage $message): void
    {
        $call = $this->calls->get(Uuid::fromString($message->callId));
        if ($call === null) {
            return;
        }

        $this->step->run($call, 'embed', 'start_embedding', 'complete', function () use ($call) {
            $utterances = array_values($this->utterances->findForCall($call));
            $texts = array_map(static fn ($u) => $u->text(), $utterances);

            if ($texts !== []) {
                $vectors = $this->embedding->embed($texts);
                foreach ($utterances as $i => $utterance) {
                    if (isset($vectors[$i])) {
                        $utterance->setEmbedding($vectors[$i]);
                    }
                }
            }
        });

        // Retention: delete audio now if the policy says so.
        if ($call->status() === CallStatus::Completed && $call->isAudioAvailable()
            && $this->retention->resolve($call->tenant())->deletesImmediately()) {
            $this->bus->dispatch(new DeleteAudioMessage((string) $call->id()));
        }
    }
}
