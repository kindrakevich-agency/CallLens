<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Auth\AuthToken;
use App\Domain\Auth\AuthTokenType;
use App\Domain\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuthToken>
 */
final class AuthTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthToken::class);
    }

    public function findByHash(string $hash, AuthTokenType $type): ?AuthToken
    {
        return $this->findOneBy(['tokenHash' => $hash, 'type' => $type]);
    }

    /** Invalidate any outstanding tokens of a type for a user before issuing a new one. */
    public function invalidateOutstanding(User $user, AuthTokenType $type): void
    {
        foreach ($this->findBy(['user' => $user, 'type' => $type, 'usedAt' => null]) as $token) {
            $token->consume();
        }
    }

    public function save(AuthToken $token, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($token);
        if ($flush) {
            $em->flush();
        }
    }
}
