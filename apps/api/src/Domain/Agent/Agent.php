<?php

declare(strict_types=1);

namespace App\Domain\Agent;

use App\Domain\Tenant\Tenant;
use App\Domain\Tenant\TenantOwned;
use App\Infrastructure\Doctrine\Repository\AgentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A sales rep being evaluated. `externalId` maps the customer's CRM/telephony id
 * to our record so webhook payloads can reference reps by their own ids.
 */
#[ORM\Entity(repositoryClass: AgentRepository::class)]
#[ORM\Table(name: 'agent')]
#[ORM\UniqueConstraint(name: 'uniq_agent_tenant_external', columns: ['tenant_id', 'external_id'])]
class Agent implements TenantOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(name: 'external_id', length: 120, nullable: true)]
    private ?string $externalId;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    public function __construct(Tenant $tenant, string $name, ?string $externalId = null)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->name = $name;
        $this->externalId = $externalId;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function tenant(): Tenant
    {
        return $this->tenant;
    }

    public function externalId(): ?string
    {
        return $this->externalId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
