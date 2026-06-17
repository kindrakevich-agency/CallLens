<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\AuthTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Single-use, expiring token backing email verification and password reset
 * (spec §11/§12.1). Only the SHA-256 hash of the token is stored, so a database
 * read never reveals a usable link. Consumed exactly once.
 */
#[ORM\Entity(repositoryClass: AuthTokenRepository::class)]
#[ORM\Table(name: 'auth_token')]
#[ORM\UniqueConstraint(name: 'uniq_auth_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_auth_token_user_type', columns: ['user_id', 'type'])]
class AuthToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(enumType: AuthTokenType::class)]
    private AuthTokenType $type;

    #[ORM\Column(name: 'token_hash', length: 64)]
    private string $tokenHash;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'used_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, AuthTokenType $type, string $tokenHash, \DateTimeImmutable $expiresAt)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->type = $type;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function user(): User
    {
        return $this->user;
    }

    public function type(): AuthTokenType
    {
        return $this->type;
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return $this->usedAt === null && $this->expiresAt > $now;
    }

    public function consume(): void
    {
        $this->usedAt ??= new \DateTimeImmutable();
    }
}
