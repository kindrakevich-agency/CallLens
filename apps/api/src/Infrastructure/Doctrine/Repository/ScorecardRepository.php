<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Scorecard\Scorecard;
use App\Domain\Tenant\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Scorecard>
 */
final class ScorecardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scorecard::class);
    }

    public function findDefault(Tenant $tenant): ?Scorecard
    {
        return $this->findOneBy(['tenant' => $tenant, 'isDefault' => true], ['version' => 'DESC']);
    }

    public function save(Scorecard $scorecard, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($scorecard);
        if ($flush) {
            $em->flush();
        }
    }
}
