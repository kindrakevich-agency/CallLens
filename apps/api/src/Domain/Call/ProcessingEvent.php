<?php

declare(strict_types=1);

namespace App\Domain\Call;

use App\Infrastructure\Doctrine\Repository\ProcessingEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Observability for each pipeline step + its retries (spec §8). One row per
 * attempt of each step (transcribe/score/embed/...), recording success or error.
 */
#[ORM\Entity(repositoryClass: ProcessingEventRepository::class)]
#[ORM\Table(name: 'processing_event')]
#[ORM\Index(name: 'idx_processing_call', columns: ['call_id'])]
class ProcessingEvent
{
    public const STATUS_STARTED = 'started';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Call::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Call $call;

    #[ORM\Column(length: 40)]
    private string $step;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column]
    private int $attempt = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'finished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(Call $call, string $step, string $status, int $attempt = 1)
    {
        $this->id = Uuid::v7();
        $this->call = $call;
        $this->step = $step;
        $this->status = $status;
        $this->attempt = $attempt;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function finish(string $status, ?string $error = null): void
    {
        $this->status = $status;
        $this->error = $error;
        $this->finishedAt = new \DateTimeImmutable();
    }
}
