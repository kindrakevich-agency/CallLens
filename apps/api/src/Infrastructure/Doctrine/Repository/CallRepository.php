<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Call\Call;
use App\Domain\Tenant\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Call>
 */
final class CallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Call::class);
    }

    /** Idempotency: ingestion deduplicates by (tenant, external_id). */
    public function findByTenantAndExternalId(Tenant $tenant, string $externalId): ?Call
    {
        return $this->findOneBy(['tenant' => $tenant, 'externalId' => $externalId]);
    }

    public function get(Uuid $id): ?Call
    {
        return $this->find($id);
    }

    public function save(Call $call, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($call);
        if ($flush) {
            $em->flush();
        }
    }
}
