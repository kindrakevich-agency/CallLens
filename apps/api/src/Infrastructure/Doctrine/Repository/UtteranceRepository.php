<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Call\Call;
use App\Domain\Call\Utterance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pgvector\Vector;

/**
 * @extends ServiceEntityRepository<Utterance>
 */
final class UtteranceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utterance::class);
    }

    /** @return Utterance[] */
    public function findForCall(Call $call): array
    {
        return $this->findBy(['call' => $call], ['startMs' => 'ASC']);
    }

    /**
     * Tenant-scoped approximate-nearest-neighbour search over utterance embeddings
     * (HNSW, cosine). The active Doctrine tenant filter auto-restricts to the
     * caller's workspace, so this never crosses tenants (spec §7.3).
     *
     * @param float[] $queryVector
     *
     * @return array<int, array{utterance: Utterance, distance: float}>
     */
    public function semanticSearch(array $queryVector, int $limit): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u AS utterance', 'cosine_distance(u.embedding, :vec) AS distance')
            ->where('u.embedding IS NOT NULL')
            ->setParameter('vec', new Vector($queryVector), 'vector')
            ->orderBy('distance', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row) => ['utterance' => $row['utterance'], 'distance' => (float) $row['distance']],
            $rows,
        );
    }

    public function save(Utterance $utterance, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($utterance);
        if ($flush) {
            $em->flush();
        }
    }
}
