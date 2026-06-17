<?php

declare(strict_types=1);

namespace App\Api\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Internal health probe. In production this route lives under /internal/* and is
 * NOT exposed to the internet (network/firewall restricted) — see spec §12.1.
 */
final class HealthController
{
    #[Route('/internal/health', name: 'internal_health', methods: ['GET'])]
    public function __invoke(Connection $db): JsonResponse
    {
        $checks = [];

        try {
            $db->executeQuery('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'down';
        }

        $healthy = !\in_array('down', $checks, true);

        return new JsonResponse(
            [
                'status' => $healthy ? 'ok' : 'degraded',
                'service' => 'calllens-api',
                'checks' => $checks,
            ],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
