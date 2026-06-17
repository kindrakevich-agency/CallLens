<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\Tenant\Tenant;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Security-relevant action log (spec §16): sign-ins, secret regeneration,
 * scorecard/retention changes, deletions.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'idx_audit_tenant_created', columns: ['tenant_id', 'created_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tenant $tenant;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $target;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $action,
        ?Tenant $tenant = null,
        ?User $user = null,
        ?string $target = null,
        ?string $ip = null,
        array $metadata = [],
    ) {
        $this->id = Uuid::v7();
        $this->action = $action;
        $this->tenant = $tenant;
        $this->user = $user;
        $this->target = $target;
        $this->ip = $ip;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function action(): string
    {
        return $this->action;
    }
}
