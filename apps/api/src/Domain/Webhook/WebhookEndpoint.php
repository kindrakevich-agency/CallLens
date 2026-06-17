<?php

declare(strict_types=1);

namespace App\Domain\Webhook;

use App\Domain\Tenant\Tenant;
use App\Domain\Tenant\TenantOwned;
use App\Infrastructure\Doctrine\Repository\WebhookEndpointRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * An ingest source for a tenant. The signing secret verifies HMAC-signed webhook
 * payloads (spec §16, §23). A tenant may have several (one per telephony source).
 */
#[ORM\Entity(repositoryClass: WebhookEndpointRepository::class)]
#[ORM\Table(name: 'webhook_endpoint')]
#[ORM\Index(name: 'idx_webhook_tenant', columns: ['tenant_id'])]
class WebhookEndpoint implements TenantOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(name: 'signing_secret', length: 80)]
    private string $signingSecret;

    /** Free-form source label, e.g. twilio | binotel | ringostat | bitrix24 | generic. */
    #[ORM\Column(name: 'source_type', length: 40)]
    private string $sourceType;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, string $sourceType, ?string $signingSecret = null)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->sourceType = $sourceType;
        $this->signingSecret = $signingSecret ?? bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function tenant(): Tenant
    {
        return $this->tenant;
    }

    public function signingSecret(): string
    {
        return $this->signingSecret;
    }

    public function regenerateSecret(): void
    {
        $this->signingSecret = bin2hex(random_bytes(32));
    }

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
