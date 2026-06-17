<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Application\Ingestion\CallIngestionService;
use App\Application\Message\IngestCallMessage;
use App\Domain\Call\Channels;
use App\Infrastructure\Doctrine\Repository\WebhookEndpointRepository;
use App\Security\WebhookSignatureVerifier;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Public, HMAC-signed call ingestion (spec §12.1, §23). Returns 202 immediately;
 * audio download + processing happen asynchronously. Replay-protected and
 * idempotent by (tenant, call_id).
 */
final class WebhookController
{
    public function __construct(
        private readonly WebhookEndpointRepository $endpoints,
        private readonly WebhookSignatureVerifier $verifier,
        private readonly CallIngestionService $ingestion,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/v1/webhooks/calls', name: 'webhook_calls', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $endpointId = (string) $request->headers->get('X-CallLens-Endpoint', '');
        $signature = (string) $request->headers->get('X-CallLens-Signature', '');
        $timestamp = (string) $request->headers->get('X-CallLens-Timestamp', '');
        $rawBody = $request->getContent();

        if (!Uuid::isValid($endpointId)) {
            return $this->deny('Unknown endpoint.');
        }
        $endpoint = $this->endpoints->findActive(Uuid::fromString($endpointId));
        if ($endpoint === null) {
            return $this->deny('Unknown endpoint.');
        }
        if (!$this->verifier->isFreshTimestamp($timestamp)) {
            return $this->deny('Stale or missing timestamp.');
        }
        if (!$this->verifier->verify($endpoint, $timestamp, $signature, $rawBody)) {
            return $this->deny('Invalid signature.');
        }

        try {
            $payload = json_decode($rawBody, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $callId = (string) ($payload['call_id'] ?? '');
        if (!\is_array($payload) || $callId === '') {
            return new JsonResponse(['error' => 'call_id is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        [$call, $created] = $this->ingestion->ingest(
            tenant: $endpoint->tenant(),
            externalId: $callId,
            source: $endpoint->sourceType(),
            agentExternalId: isset($payload['agent_id']) ? (string) $payload['agent_id'] : null,
            channels: ($payload['channels'] ?? 'dual') === 'mono' ? Channels::Mono : Channels::Dual,
            language: (string) ($payload['language'] ?? 'auto'),
            startedAt: $this->parseDate($payload['started_at'] ?? null),
            durationSec: isset($payload['duration_sec']) ? (int) $payload['duration_sec'] : null,
        );

        if ($created) {
            $this->bus->dispatch(new IngestCallMessage(
                (string) $call->id(),
                (string) ($payload['recording_url'] ?? ''),
            ));
        }

        return new JsonResponse(
            ['status' => 'accepted', 'call_id' => $callId, 'duplicate' => !$created],
            Response::HTTP_ACCEPTED,
        );
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function deny(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_UNAUTHORIZED);
    }
}
