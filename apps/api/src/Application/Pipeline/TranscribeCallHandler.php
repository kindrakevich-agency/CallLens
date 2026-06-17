<?php

declare(strict_types=1);

namespace App\Application\Pipeline;

use App\Application\Message\ScoreCallMessage;
use App\Application\Message\TranscribeCallMessage;
use App\Application\Provider\AudioRef;
use App\Application\Provider\SpeechToTextClient;
use App\Domain\Call\Transcript;
use App\Domain\Call\Utterance;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use App\Infrastructure\Doctrine\Repository\TranscriptRepository;
use App\Infrastructure\Doctrine\Repository\UtteranceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class TranscribeCallHandler
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly TranscriptRepository $transcripts,
        private readonly UtteranceRepository $utterances,
        private readonly SpeechToTextClient $stt,
        private readonly StepRunner $step,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(TranscribeCallMessage $message): void
    {
        $call = $this->calls->get(Uuid::fromString($message->callId));
        if ($call === null) {
            return;
        }

        // Idempotent: if already transcribed, just ensure the next stage is queued.
        if ($this->transcripts->findForCall($call) === null) {
            $this->step->run($call, 'transcribe', 'start_transcription', 'complete_transcription', function () use ($call) {
                $result = $this->stt->transcribe(new AudioRef(
                    $call->audioObjectKey() ?? '',
                    $call->channels(),
                    $call->language(),
                ));

                $this->transcripts->save(new Transcript(
                    $call,
                    $result->language,
                    $result->fullText,
                    $result->provider,
                    $result->model,
                ));
                foreach ($result->segments as $segment) {
                    $this->utterances->save(new Utterance(
                        $call,
                        $segment->speaker,
                        $segment->startMs,
                        $segment->endMs,
                        $segment->text,
                    ));
                }
            });
        }

        $this->bus->dispatch(new ScoreCallMessage($message->callId));
    }
}
