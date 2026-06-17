<?php

declare(strict_types=1);

namespace App\Application\Ingestion;

use App\Domain\Agent\Agent;
use App\Domain\Call\Call;
use App\Domain\Call\Channels;
use App\Domain\Tenant\Tenant;
use App\Infrastructure\Doctrine\Repository\AgentRepository;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use App\Infrastructure\Doctrine\Repository\ScorecardRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates a `Call(received)` from an ingest payload, deduplicating by
 * (tenant, external_id) so re-delivered webhooks/uploads are idempotent (spec §8).
 */
final class CallIngestionService
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly AgentRepository $agents,
        private readonly ScorecardRepository $scorecards,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{0: Call, 1: bool} the call and whether it was newly created
     */
    public function ingest(
        Tenant $tenant,
        string $externalId,
        string $source,
        ?string $agentExternalId = null,
        Channels $channels = Channels::Dual,
        string $language = 'auto',
        ?\DateTimeImmutable $startedAt = null,
        ?int $durationSec = null,
    ): array {
        $existing = $this->calls->findByTenantAndExternalId($tenant, $externalId);
        if ($existing !== null) {
            return [$existing, false];
        }

        $call = new Call($tenant, $externalId, $source);
        $call->setChannels($channels);
        $call->setLanguage($language);
        $call->setStartedAt($startedAt);
        $call->setDurationSec($durationSec);
        $call->setScorecardVersion($this->scorecards->findDefault($tenant));

        if ($agentExternalId !== null && $agentExternalId !== '') {
            $call->setAgent($this->resolveAgent($tenant, $agentExternalId));
        }

        $this->calls->save($call);
        $this->em->flush();

        return [$call, true];
    }

    private function resolveAgent(Tenant $tenant, string $externalId): Agent
    {
        $agent = $this->agents->findByExternalId($tenant, $externalId);
        if ($agent === null) {
            $agent = new Agent($tenant, $externalId, $externalId);
            $this->agents->save($agent);
        }

        return $agent;
    }
}
