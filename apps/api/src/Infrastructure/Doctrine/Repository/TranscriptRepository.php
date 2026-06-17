<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Call\Call;
use App\Domain\Call\Transcript;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transcript>
 */
final class TranscriptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transcript::class);
    }

    public function findForCall(Call $call): ?Transcript
    {
        return $this->findOneBy(['call' => $call]);
    }

    public function save(Transcript $transcript, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($transcript);
        if ($flush) {
            $em->flush();
        }
    }
}
