<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Webhook\WebhookEndpoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<WebhookEndpoint>
 */
final class WebhookEndpointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookEndpoint::class);
    }

    /**
     * Webhook auth runs before any tenant context, so this lookup is intentionally
     * NOT tenant-scoped — the endpoint id resolves the tenant.
     */
    public function findActive(Uuid $id): ?WebhookEndpoint
    {
        return $this->findOneBy(['id' => $id, 'isActive' => true]);
    }

    public function save(WebhookEndpoint $endpoint, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($endpoint);
        if ($flush) {
            $em->flush();
        }
    }
}
