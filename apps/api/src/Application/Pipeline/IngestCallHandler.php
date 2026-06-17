<?php

declare(strict_types=1);

namespace App\Application\Pipeline;

use App\Application\Message\IngestCallMessage;
use App\Application\Message\TranscribeCallMessage;
use App\Application\Provider\ObjectStorage;
use App\Domain\Call\ProcessingEvent;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use App\Infrastructure\Doctrine\Repository\ProcessingEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Webhook path: download the recording, store it, then start the pipeline. Kept
 * out of the HTTP request so the webhook can return 202 in milliseconds (spec §8).
 */
#[AsMessageHandler]
final class IngestCallHandler
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly ObjectStorage $storage,
        private readonly ProcessingEventRepository $events,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(IngestCallMessage $message): void
    {
        $call = $this->calls->get(Uuid::fromString($message->callId));
        if ($call === null) {
            return;
        }

        // Idempotent: audio already stored → just (re)start the pipeline.
        if (!$call->isAudioAvailable()) {
            $event = new ProcessingEvent($call, 'ingest', ProcessingEvent::STATUS_STARTED);
            $this->events->save($event);

            try {
                $audio = $this->httpClient->request('GET', $message->recordingUrl)->getContent();
                $key = sprintf('tenants/%s/calls/%s/audio.%s', $call->tenant()->id(), $call->id(), $this->extensionFor($message->recordingUrl));
                $this->storage->put($key, $audio);
                $call->setAudioObjectKey($key);
                $this->em->flush();
                $event->finish(ProcessingEvent::STATUS_SUCCEEDED);
                $this->events->save($event);
            } catch (\Throwable $e) {
                $event->finish(ProcessingEvent::STATUS_FAILED, $e->getMessage());
                $this->events->save($event);

                throw $e;
            }
        }

        $this->bus->dispatch(new TranscribeCallMessage($message->callId));
    }

    private function extensionFor(string $url): string
    {
        $path = (string) parse_url($url, \PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        return preg_match('/^(mp3|wav|ogg|m4a|flac)$/', $ext) ? $ext : 'mp3';
    }
}
