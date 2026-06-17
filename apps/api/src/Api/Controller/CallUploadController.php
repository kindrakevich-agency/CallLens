<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Application\Ingestion\CallIngestionService;
use App\Application\Message\TranscribeCallMessage;
use App\Application\Provider\ObjectStorage;
use App\Domain\Call\Channels;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * Manual call upload (authenticated cabinet path, spec §2.1). Stores the audio
 * synchronously and starts the pipeline. Tenant comes from the principal.
 */
final class CallUploadController
{
    public function __construct(
        private readonly CallIngestionService $ingestion,
        private readonly ObjectStorage $storage,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/api/v1/calls/upload', name: 'call_upload', methods: ['POST'])]
    public function __invoke(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $file = $request->files->get('audio');
        if ($file === null) {
            return new JsonResponse(['error' => 'An audio file is required (field "audio").'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $externalId = (string) ($request->request->get('external_id') ?: 'upload-' . Uuid::v7());
        $channels = $request->request->get('channels') === 'mono' ? Channels::Mono : Channels::Dual;

        [$call, $created] = $this->ingestion->ingest(
            tenant: $user->tenant(),
            externalId: $externalId,
            source: 'upload',
            agentExternalId: (string) ($request->request->get('agent_external_id') ?? '') ?: null,
            channels: $channels,
            language: (string) ($request->request->get('language') ?? 'auto'),
        );

        if (!$created) {
            return new JsonResponse(
                ['status' => 'duplicate', 'id' => (string) $call->id(), 'external_id' => $externalId],
                Response::HTTP_OK,
            );
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'mp3');
        $key = sprintf('tenants/%s/calls/%s/audio.%s', $user->tenant()->id(), $call->id(), $ext);
        $this->storage->put($key, (string) file_get_contents($file->getPathname()), (string) $file->getMimeType());
        $call->setAudioObjectKey($key);
        $this->em->flush();

        $this->bus->dispatch(new TranscribeCallMessage((string) $call->id()));

        return new JsonResponse(
            ['status' => 'accepted', 'id' => (string) $call->id(), 'external_id' => $externalId],
            Response::HTTP_ACCEPTED,
        );
    }
}
