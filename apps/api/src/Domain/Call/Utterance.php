<?php

declare(strict_types=1);

namespace App\Domain\Call;

use App\Domain\Tenant\Tenant;
use App\Domain\Tenant\TenantOwned;
use App\Infrastructure\Doctrine\Repository\UtteranceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pgvector\Vector;
use Symfony\Component\Uid\Uuid;

/**
 * A diarized turn in a call. Carries tenant_id directly for fast tenant-scoped
 * semantic search. The `embedding` vector(1024) column is added in M5 (pgvector).
 */
#[ORM\Entity(repositoryClass: UtteranceRepository::class)]
#[ORM\Table(name: 'utterance')]
#[ORM\Index(name: 'idx_utterance_call', columns: ['call_id'])]
#[ORM\Index(name: 'idx_utterance_tenant', columns: ['tenant_id'])]
class Utterance implements TenantOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Call::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Call $call;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(enumType: Speaker::class)]
    private Speaker $speaker;

    #[ORM\Column(name: 'start_ms')]
    private int $startMs;

    #[ORM\Column(name: 'end_ms')]
    private int $endMs;

    #[ORM\Column(type: Types::TEXT)]
    private string $text;

    /** Multilingual embedding for tenant-scoped semantic search (HNSW, cosine). */
    #[ORM\Column(type: 'vector', length: 1024, nullable: true)]
    private ?Vector $embedding = null;

    #[ORM\Column(name: 'embedded_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $embeddedAt = null;

    public function __construct(Call $call, Speaker $speaker, int $startMs, int $endMs, string $text)
    {
        $this->id = Uuid::v7();
        $this->call = $call;
        $this->tenant = $call->tenant();
        $this->speaker = $speaker;
        $this->startMs = $startMs;
        $this->endMs = $endMs;
        $this->text = $text;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function call(): Call
    {
        return $this->call;
    }

    public function tenant(): Tenant
    {
        return $this->tenant;
    }

    public function speaker(): Speaker
    {
        return $this->speaker;
    }

    public function text(): string
    {
        return $this->text;
    }

    /** @param float[] $vector */
    public function setEmbedding(array $vector): void
    {
        $this->embedding = new Vector($vector);
        $this->embeddedAt = new \DateTimeImmutable();
    }

    public function embedding(): ?Vector
    {
        return $this->embedding;
    }

    public function isEmbedded(): bool
    {
        return $this->embedding !== null;
    }
}
