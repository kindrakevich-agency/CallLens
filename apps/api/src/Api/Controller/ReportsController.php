<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\User\User;
use App\Infrastructure\Analytics\CubeClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Cabinet analytics (spec §14). Reads measures from the Cube semantic layer,
 * tenant-scoped via the signed security context. Each panel degrades to an empty
 * series if Cube is unavailable, so the dashboard never hard-fails.
 */
final class ReportsController
{
    public function __construct(private readonly CubeClient $cube)
    {
    }

    #[Route('/api/v1/reports', name: 'reports_overview', methods: ['GET'])]
    public function overview(#[CurrentUser] User $user): JsonResponse
    {
        $tenantId = (string) $user->tenant()->id();

        return new JsonResponse([
            'avg_score_per_agent' => $this->query($tenantId, [
                'measures' => ['call_scores.avg_overall', 'call_scores.count'],
                'dimensions' => ['agents.name'],
                'order' => ['call_scores.avg_overall' => 'desc'],
            ], fn ($r) => [
                'agent' => $r['agents.name'] ?? 'Unassigned',
                'avg' => $this->num($r['call_scores.avg_overall'] ?? null),
                'calls' => (int) ($r['call_scores.count'] ?? 0),
            ]),

            'calls_per_week' => $this->query($tenantId, [
                'measures' => ['calls.count'],
                'timeDimensions' => [['dimension' => 'calls.created_at', 'granularity' => 'week']],
                'order' => ['calls.created_at' => 'asc'],
            ], fn ($r) => [
                'week' => substr((string) ($r['calls.created_at.week'] ?? $r['calls.created_at'] ?? ''), 0, 10),
                'count' => (int) ($r['calls.count'] ?? 0),
            ]),

            'avg_score_per_week' => $this->query($tenantId, [
                'measures' => ['call_scores.avg_overall'],
                'timeDimensions' => [['dimension' => 'call_scores.created_at', 'granularity' => 'week']],
                'order' => ['call_scores.created_at' => 'asc'],
            ], fn ($r) => [
                'week' => substr((string) ($r['call_scores.created_at.week'] ?? $r['call_scores.created_at'] ?? ''), 0, 10),
                'avg' => $this->num($r['call_scores.avg_overall'] ?? null),
            ]),

            'status_breakdown' => $this->query($tenantId, [
                'measures' => ['calls.count'],
                'dimensions' => ['calls.status'],
            ], fn ($r) => [
                'status' => $r['calls.status'] ?? 'unknown',
                'count' => (int) ($r['calls.count'] ?? 0),
            ]),
        ]);
    }

    /**
     * @param array<string,mixed>                 $query
     * @param callable(array<string,mixed>): array $map
     *
     * @return array<int,array<string,mixed>>
     */
    private function query(string $tenantId, array $query, callable $map): array
    {
        try {
            return array_map($map, $this->cube->load($query, $tenantId));
        } catch (\Throwable) {
            return [];
        }
    }

    private function num(mixed $v): ?float
    {
        return $v === null ? null : round((float) $v, 1);
    }
}
