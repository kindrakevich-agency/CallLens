<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Call\Call;
use App\Domain\Call\Utterance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function save(Utterance $utterance, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($utterance);
        if ($flush) {
            $em->flush();
        }
    }
}
