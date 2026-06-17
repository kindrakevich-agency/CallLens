<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Call\ProcessingEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessingEvent>
 */
final class ProcessingEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessingEvent::class);
    }

    public function save(ProcessingEvent $event, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($event);
        if ($flush) {
            $em->flush();
        }
    }
}
