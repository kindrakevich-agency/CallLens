<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\User\User;
use App\Domain\Webhook\WebhookEndpoint;
use App\Infrastructure\Doctrine\Repository\WebhookEndpointRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * Cabinet settings (spec §13): webhook ingest endpoints (with signing secret) and
 * the audio-retention policy stored in Tenant.settings.
 */
final class SettingsController
{
    private const RETENTION_MODES = ['keep', 'delete_after_processing', 'delete_after_days'];

    public function __construct(
        private readonly WebhookEndpointRepository $endpoints,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(APP_URL)%')] private readonly string $appUrl,
    ) {
    }

    #[Route('/api/v1/settings/webhooks', name: 'settings_webhooks_list', methods: ['GET'])]
    public function listWebhooks(): JsonResponse
    {
        $items = array_map(fn (WebhookEndpoint $e) => $this->webhookPayload($e), $this->endpoints->findBy([]));

        return new JsonResponse(['url' => rtrim($this->appUrl, '/') . '/v1/webhooks/calls', 'items' => $items]);
    }

    #[Route('/api/v1/settings/webhooks', name: 'settings_webhooks_create', methods: ['POST'])]
    public function createWebhook(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sourceType = \is_array($data) ? (string) ($data['source_type'] ?? 'generic') : 'generic';

        $endpoint = new WebhookEndpoint($user->tenant(), $sourceType ?: 'generic');
        $this->endpoints->save($endpoint, true);

        return new JsonResponse($this->webhookPayload($endpoint), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/settings/webhooks/{id}/rotate', name: 'settings_webhooks_rotate', methods: ['POST'])]
    public function rotateSecret(string $id): JsonResponse
    {
        $endpoint = Uuid::isValid($id) ? $this->endpoints->find(Uuid::fromString($id)) : null;
        if ($endpoint === null) {
            return new JsonResponse(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $endpoint->regenerateSecret();
        $this->em->flush();

        return new JsonResponse($this->webhookPayload($endpoint));
    }

    #[Route('/api/v1/settings/retention', name: 'settings_retention_get', methods: ['GET'])]
    public function getRetention(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse($this->retention($user));
    }

    #[Route('/api/v1/settings/retention', name: 'settings_retention_put', methods: ['PUT'])]
    public function setRetention(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $mode = \is_array($data) ? (string) ($data['mode'] ?? '') : '';
        if (!\in_array($mode, self::RETENTION_MODES, true)) {
            return new JsonResponse(['error' => 'Invalid retention mode.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $settings = $user->tenant()->settings();
        $settings['audio_retention'] = [
            'mode' => $mode,
            'days' => max(1, (int) ($data['days'] ?? 30)),
        ];
        $user->tenant()->setSettings($settings);
        $this->em->flush();

        return new JsonResponse($this->retention($user));
    }

    private function retention(User $user): array
    {
        $r = $user->tenant()->settings()['audio_retention'] ?? [];

        return [
            'mode' => $r['mode'] ?? 'keep',
            'days' => $r['days'] ?? 30,
            'modes' => self::RETENTION_MODES,
        ];
    }

    private function webhookPayload(WebhookEndpoint $e): array
    {
        return [
            'id' => (string) $e->id(),
            'source_type' => $e->sourceType(),
            'is_active' => $e->isActive(),
            'signing_secret' => $e->signingSecret(),
        ];
    }
}
