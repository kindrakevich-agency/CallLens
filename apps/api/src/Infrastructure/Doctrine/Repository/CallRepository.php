<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Call\Call;
use App\Domain\Call\CallScore;
use App\Domain\Call\CallStatus;
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

    /**
     * Tenant-scoped (via the active filter) paginated call list with the overall
     * score joined in.
     *
     * @return array{items: array<int, array{call: Call, overall: float|null}>, total: int}
     */
    public function paginate(?CallStatus $status, ?Uuid $agentId, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.createdAt', 'DESC');
        if ($status !== null) {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }
        if ($agentId !== null) {
            $qb->andWhere('IDENTITY(c.agent) = :agent')->setParameter('agent', $agentId, 'uuid');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(c.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $rows = $qb
            ->select('c AS call', 'cs.overallScore AS overall')
            ->leftJoin(CallScore::class, 'cs', 'WITH', 'cs.call = c')
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $items = array_map(
            static fn (array $r) => ['call' => $r['call'], 'overall' => $r['overall'] !== null ? (float) $r['overall'] : null],
            $rows,
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Completed calls that still have audio, oldest first. NOT tenant-scoped on
     * purpose — the retention sweep runs without a principal and spans tenants.
     *
     * @return Call[]
     */
    public function completedWithAudio(int $limit): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')->setParameter('status', CallStatus::Completed)
            ->andWhere('c.audioObjectKey IS NOT NULL')
            ->orderBy('c.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
