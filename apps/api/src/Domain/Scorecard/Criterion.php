<?php

declare(strict_types=1);

namespace App\Domain\Scorecard;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One scored dimension of a scorecard, e.g. greeting, needs_discovery,
 * objection_handling, next_step. `guidance` is fed to the LLM scorer.
 */
#[ORM\Entity]
#[ORM\Table(name: 'criterion')]
#[ORM\UniqueConstraint(name: 'uniq_criterion_scorecard_key', columns: ['scorecard_id', 'criterion_key'])]
class Criterion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Scorecard::class, inversedBy: 'criteria')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Scorecard $scorecard;

    #[ORM\Column(name: 'criterion_key', length: 60)]
    private string $key;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column]
    private float $weight = 1.0;

    #[ORM\Column(name: 'max_score')]
    private int $maxScore = 5;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $guidance = null;

    public function __construct(Scorecard $scorecard, string $key, string $title, float $weight = 1.0, int $maxScore = 5, ?string $guidance = null)
    {
        $this->id = Uuid::v7();
        $this->scorecard = $scorecard;
        $this->key = $key;
        $this->title = $title;
        $this->weight = $weight;
        $this->maxScore = $maxScore;
        $this->guidance = $guidance;
        $scorecard->addCriterion($this);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function weight(): float
    {
        return $this->weight;
    }

    public function maxScore(): int
    {
        return $this->maxScore;
    }

    public function guidance(): ?string
    {
        return $this->guidance;
    }
}
