<?php

declare(strict_types=1);

namespace App\Application\Provider;

/** LLM scoring output: an overall score plus per-criterion results. */
final readonly class ScoringResult
{
    /** @param CriterionScoreResult[] $criteria */
    public function __construct(
        public float $overallScore,
        public array $criteria,
        public string $model,
    ) {
    }
}
