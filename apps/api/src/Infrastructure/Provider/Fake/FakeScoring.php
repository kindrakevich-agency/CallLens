<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Fake;

use App\Application\Provider\CriterionScoreResult;
use App\Application\Provider\ScoringClient;
use App\Application\Provider\ScoringResult;
use App\Domain\Call\Transcript;
use App\Domain\Scorecard\CriterionDefinition;
use App\Domain\Scorecard\Scorecard;

/**
 * Deterministic LLM scoring for dev/tests. Scores are derived from a hash of the
 * criterion key so they're stable, and each evidence quote is taken verbatim from
 * the transcript so it satisfies the "evidence must appear in transcript" rule.
 */
final class FakeScoring implements ScoringClient
{
    public function score(Transcript $transcript, ?Scorecard $scorecard): ScoringResult
    {
        $evidence = $this->firstAgentQuote($transcript->fullText());

        $criteria = [];
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach (CriterionDefinition::resolve($scorecard) as $def) {
            $score = 1 + (crc32($def->key) % $def->maxScore); // 1..maxScore, stable
            $criteria[] = new CriterionScoreResult(
                criterionKey: $def->key,
                score: (float) $score,
                maxScore: $def->maxScore,
                evidenceQuote: $evidence,
                rationale: 'Deterministic fake score for development.',
            );
            $weightedSum += ($score / $def->maxScore) * $def->weight;
            $weightTotal += $def->weight;
        }

        $overall = $weightTotal > 0 ? round(($weightedSum / $weightTotal) * 100, 1) : 0.0;

        return new ScoringResult($overall, $criteria, 'fake-llm-v1');
    }

    private function firstAgentQuote(string $fullText): ?string
    {
        foreach (explode("\n", $fullText) as $line) {
            if (str_starts_with($line, 'Agent: ')) {
                return substr($line, \strlen('Agent: '));
            }
        }

        return null;
    }
}
