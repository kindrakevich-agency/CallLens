<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Application\Provider\CriterionScoreResult;
use App\Application\Provider\ScoringClient;
use App\Application\Provider\ScoringResult;
use App\Domain\Call\Transcript;
use App\Domain\Scorecard\Scorecard;

/**
 * Decorates any ScoringClient and drops evidence quotes that don't actually
 * appear in the transcript (spec §10), so fabricated quotes are never persisted.
 * The score and rationale are kept; only the unverifiable quote is nulled.
 */
final class EvidenceValidatingScoringClient implements ScoringClient
{
    public function __construct(
        private readonly ScoringClient $inner,
        private readonly EvidenceValidator $validator,
    ) {
    }

    public function score(Transcript $transcript, ?Scorecard $scorecard): ScoringResult
    {
        $result = $this->inner->score($transcript, $scorecard);
        $transcriptText = $transcript->fullText();

        $validated = array_map(
            function (CriterionScoreResult $c) use ($transcriptText): CriterionScoreResult {
                if ($this->validator->appearsIn($c->evidenceQuote, $transcriptText)) {
                    return $c;
                }

                return new CriterionScoreResult(
                    criterionKey: $c->criterionKey,
                    score: $c->score,
                    maxScore: $c->maxScore,
                    evidenceQuote: null, // fabricated — dropped
                    rationale: $c->rationale,
                );
            },
            $result->criteria,
        );

        return new ScoringResult($result->overallScore, $validated, $result->model);
    }
}
