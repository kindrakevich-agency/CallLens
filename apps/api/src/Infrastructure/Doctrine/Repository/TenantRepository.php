<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Tenant\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tenant>
 */
final class TenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function save(Tenant $tenant, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($tenant);
        if ($flush) {
            $em->flush();
        }
    }
}
