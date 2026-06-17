<?php

declare(strict_types=1);

namespace App\Domain\Call;

use App\Infrastructure\Doctrine\Repository\TranscriptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** The full transcript of a call (one per call). */
#[ORM\Entity(repositoryClass: TranscriptRepository::class)]
#[ORM\Table(name: 'transcript')]
class Transcript
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Call::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Call $call;

    #[ORM\Column(length: 12)]
    private string $language;

    #[ORM\Column(name: 'full_text', type: Types::TEXT)]
    private string $fullText;

    #[ORM\Column(length: 40)]
    private string $provider;

    #[ORM\Column(length: 80)]
    private string $model;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Call $call, string $language, string $fullText, string $provider, string $model)
    {
        $this->id = Uuid::v7();
        $this->call = $call;
        $this->language = $language;
        $this->fullText = $fullText;
        $this->provider = $provider;
        $this->model = $model;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function call(): Call
    {
        return $this->call;
    }

    public function fullText(): string
    {
        return $this->fullText;
    }
}
