<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Call\Call;
use App\Domain\Call\CallScore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CallScore>
 */
final class CallScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CallScore::class);
    }

    public function findForCall(Call $call): ?CallScore
    {
        return $this->findOneBy(['call' => $call]);
    }

    public function save(CallScore $score, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($score);
        if ($flush) {
            $em->flush();
        }
    }
}
