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

    /** Clear the default flag on all of a tenant's scorecards (before setting a new one). */
    public function clearDefaults(Tenant $tenant): void
    {
        foreach ($this->findBy(['tenant' => $tenant, 'isDefault' => true]) as $s) {
            $s->setDefault(false);
        }
    }

    public function countForTenant(Tenant $tenant): int
    {
        return (int) $this->count(['tenant' => $tenant]);
    }

    public function save(Scorecard $scorecard, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($scorecard);
        if ($flush) {
            $em->flush();
        }
    }

    public function remove(Scorecard $scorecard, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->remove($scorecard);
        if ($flush) {
            $em->flush();
        }
    }
}
