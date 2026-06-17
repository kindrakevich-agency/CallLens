<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Agent\Agent;
use App\Domain\Tenant\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agent>
 */
final class AgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agent::class);
    }

    public function findByExternalId(Tenant $tenant, string $externalId): ?Agent
    {
        return $this->findOneBy(['tenant' => $tenant, 'externalId' => $externalId]);
    }

    public function save(Agent $agent, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($agent);
        if ($flush) {
            $em->flush();
        }
    }
}
