<?php

declare(strict_types=1);

namespace App\Domain\Call;

use App\Domain\Scorecard\Scorecard;
use App\Infrastructure\Doctrine\Repository\CallScoreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** The overall LLM score for a call against a scorecard version. */
#[ORM\Entity(repositoryClass: CallScoreRepository::class)]
#[ORM\Table(name: 'call_score')]
class CallScore
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Call::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Call $call;

    #[ORM\ManyToOne(targetEntity: Scorecard::class)]
    #[ORM\JoinColumn(name: 'scorecard_version_id', nullable: true, onDelete: 'SET NULL')]
    private ?Scorecard $scorecardVersion;

    #[ORM\Column(name: 'overall_score')]
    private float $overallScore;

    #[ORM\Column(length: 80)]
    private string $model;

    /** @var Collection<int, CriterionScore> */
    #[ORM\OneToMany(mappedBy: 'callScore', targetEntity: CriterionScore::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $criterionScores;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Call $call, ?Scorecard $scorecardVersion, float $overallScore, string $model)
    {
        $this->id = Uuid::v7();
        $this->call = $call;
        $this->scorecardVersion = $scorecardVersion;
        $this->overallScore = $overallScore;
        $this->model = $model;
        $this->criterionScores = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function overallScore(): float
    {
        return $this->overallScore;
    }

    public function addCriterionScore(CriterionScore $score): void
    {
        if (!$this->criterionScores->contains($score)) {
            $this->criterionScores->add($score);
        }
    }

    /** @return Collection<int, CriterionScore> */
    public function criterionScores(): Collection
    {
        return $this->criterionScores;
    }
}
