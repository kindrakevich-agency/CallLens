<?php

declare(strict_types=1);

namespace App\Domain\Call;

use App\Domain\Agent\Agent;
use App\Domain\Scorecard\Scorecard;
use App\Domain\Tenant\Tenant;
use App\Domain\Tenant\TenantOwned;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A single ingested call. `status` is driven by the Symfony Workflow (spec §8);
 * ingestion deduplicates by (tenant, external_id). Audio is referenced by an
 * object-storage key and may be deleted post-processing per retention policy.
 */
#[ORM\Entity(repositoryClass: CallRepository::class)]
#[ORM\Table(name: 'call')]
#[ORM\UniqueConstraint(name: 'uniq_call_tenant_external', columns: ['tenant_id', 'external_id'])]
#[ORM\Index(name: 'idx_call_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_call_started', columns: ['started_at'])]
class Call implements TenantOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(name: 'external_id', length: 190)]
    private string $externalId;

    #[ORM\ManyToOne(targetEntity: Agent::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Agent $agent = null;

    #[ORM\Column(length: 40)]
    private string $source;

    #[ORM\Column(name: 'audio_object_key', length: 255, nullable: true)]
    private ?string $audioObjectKey = null;

    #[ORM\Column(name: 'audio_deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $audioDeletedAt = null;

    #[ORM\Column(enumType: Channels::class)]
    private Channels $channels = Channels::Dual;

    #[ORM\Column(length: 12)]
    private string $language = 'auto';

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'duration_sec', nullable: true)]
    private ?int $durationSec = null;

    #[ORM\Column(enumType: CallStatus::class)]
    private CallStatus $status = CallStatus::Received;

    #[ORM\ManyToOne(targetEntity: Scorecard::class)]
    #[ORM\JoinColumn(name: 'scorecard_version_id', nullable: true, onDelete: 'SET NULL')]
    private ?Scorecard $scorecardVersion = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, string $externalId, string $source)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->externalId = $externalId;
        $this->source = $source;
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

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function status(): CallStatus
    {
        return $this->status;
    }

    /** Workflow marking-store getter (returns the place name). */
    public function getStatus(): string
    {
        return $this->status->value;
    }

    /** Workflow marking-store setter (place name + transition context). */
    public function setStatus(string $status, array $context = []): void
    {
        $this->status = CallStatus::from($status);
    }

    public function agent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(?Agent $agent): void
    {
        $this->agent = $agent;
    }

    public function audioObjectKey(): ?string
    {
        return $this->audioObjectKey;
    }

    public function setAudioObjectKey(?string $key): void
    {
        $this->audioObjectKey = $key;
    }

    public function markAudioDeleted(): void
    {
        $this->audioDeletedAt = new \DateTimeImmutable();
        $this->audioObjectKey = null;
    }

    public function isAudioAvailable(): bool
    {
        return $this->audioObjectKey !== null;
    }

    public function channels(): Channels
    {
        return $this->channels;
    }

    public function setChannels(Channels $channels): void
    {
        $this->channels = $channels;
    }

    public function language(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function setDurationSec(?int $durationSec): void
    {
        $this->durationSec = $durationSec;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function scorecardVersion(): ?Scorecard
    {
        return $this->scorecardVersion;
    }

    public function setScorecardVersion(?Scorecard $scorecard): void
    {
        $this->scorecardVersion = $scorecard;
    }
}
