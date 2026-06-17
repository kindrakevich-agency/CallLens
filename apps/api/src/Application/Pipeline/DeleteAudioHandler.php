<?php

declare(strict_types=1);

namespace App\Application\Pipeline;

use App\Application\Message\DeleteAudioMessage;
use App\Application\Provider\ObjectStorage;
use App\Domain\Audit\AuditLog;
use App\Domain\Call\CallStatus;
use App\Domain\Call\ProcessingEvent;
use App\Infrastructure\Doctrine\Repository\AuditLogRepository;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use App\Infrastructure\Doctrine\Repository\ProcessingEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Deletes a completed call's audio object and records it (spec §9). Idempotent
 * and tolerant of an already-missing object. Never deletes audio for a call that
 * has not reached `completed`.
 */
#[AsMessageHandler]
final class DeleteAudioHandler
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly ObjectStorage $storage,
        private readonly ProcessingEventRepository $events,
        private readonly AuditLogRepository $auditLogs,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(DeleteAudioMessage $message): void
    {
        $call = $this->calls->get(Uuid::fromString($message->callId));
        if ($call === null || !$call->isAudioAvailable()) {
            return; // already deleted or gone
        }
        if ($call->status() !== CallStatus::Completed) {
            return; // invariant: never delete before the call is completed
        }

        $event = new ProcessingEvent($call, 'delete_audio', ProcessingEvent::STATUS_STARTED);
        $this->events->save($event);

        $key = (string) $call->audioObjectKey();
        $this->storage->delete($key); // idempotent
        $call->markAudioDeleted();
        $this->em->flush();

        $event->finish(ProcessingEvent::STATUS_SUCCEEDED);
        $this->events->save($event);

        $this->auditLogs->save(new AuditLog(
            action: 'audio.deleted',
            tenant: $call->tenant(),
            target: (string) $call->id(),
            metadata: ['object_key' => $key],
        ));
    }
}
