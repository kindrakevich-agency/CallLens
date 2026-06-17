<?php

declare(strict_types=1);

namespace App\Application\Provider;

/** One criterion's score from the LLM, with the supporting evidence quote. */
final readonly class CriterionScoreResult
{
    public function __construct(
        public string $criterionKey,
        public float $score,
        public int $maxScore,
        public ?string $evidenceQuote,
        public ?string $rationale,
    ) {
    }
}
