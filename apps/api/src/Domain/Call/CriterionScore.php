<?php

declare(strict_types=1);

namespace App\Domain\Call;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Per-criterion score with the evidence quote that justifies it. The quote must
 * appear verbatim in the transcript (validated at scoring time — spec §10).
 */
#[ORM\Entity]
#[ORM\Table(name: 'criterion_score')]
class CriterionScore
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: CallScore::class, inversedBy: 'criterionScores')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CallScore $callScore;

    #[ORM\Column(name: 'criterion_key', length: 60)]
    private string $criterionKey;

    #[ORM\Column]
    private float $score;

    #[ORM\Column(name: 'max_score')]
    private int $maxScore;

    #[ORM\Column(name: 'evidence_quote', type: Types::TEXT, nullable: true)]
    private ?string $evidenceQuote;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rationale;

    public function __construct(CallScore $callScore, string $criterionKey, float $score, int $maxScore, ?string $evidenceQuote, ?string $rationale)
    {
        $this->id = Uuid::v7();
        $this->callScore = $callScore;
        $this->criterionKey = $criterionKey;
        $this->score = $score;
        $this->maxScore = $maxScore;
        $this->evidenceQuote = $evidenceQuote;
        $this->rationale = $rationale;
        $callScore->addCriterionScore($this);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function criterionKey(): string
    {
        return $this->criterionKey;
    }

    public function score(): float
    {
        return $this->score;
    }

    public function maxScore(): int
    {
        return $this->maxScore;
    }

    public function evidenceQuote(): ?string
    {
        return $this->evidenceQuote;
    }

    public function rationale(): ?string
    {
        return $this->rationale;
    }
}
