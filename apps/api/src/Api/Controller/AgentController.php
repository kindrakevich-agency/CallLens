<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Domain\Agent\Agent;
use App\Infrastructure\Doctrine\Repository\AgentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Cabinet agents API (spec §13). Tenant-scoped by the Doctrine filter. */
final class AgentController
{
    public function __construct(private readonly AgentRepository $agents)
    {
    }

    #[Route('/api/v1/agents', name: 'agents_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = array_map(
            static fn (Agent $a) => [
                'id' => (string) $a->id(),
                'name' => $a->name(),
                'external_id' => $a->externalId(),
                'is_active' => $a->isActive(),
            ],
            $this->agents->findBy([], ['name' => 'ASC']),
        );

        return new JsonResponse(['items' => $items]);
    }
}
