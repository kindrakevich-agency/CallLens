<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Fake;

use App\Application\Provider\CriterionScoreResult;
use App\Application\Provider\ScoringClient;
use App\Application\Provider\ScoringResult;
use App\Domain\Call\Transcript;
use App\Domain\Scorecard\Scorecard;

/**
 * Deterministic LLM scoring for dev/tests. Scores are derived from a hash of the
 * criterion key so they're stable, and each evidence quote is taken verbatim from
 * the transcript so it satisfies the "evidence must appear in transcript" rule.
 */
final class FakeScoring implements ScoringClient
{
    /** @var array<string,string> default criteria when no scorecard is set */
    private const DEFAULT_CRITERIA = [
        'greeting' => 'Greeting & rapport',
        'needs_discovery' => 'Needs discovery',
        'objection_handling' => 'Objection handling',
        'next_step' => 'Securing a next step',
    ];

    public function score(Transcript $transcript, ?Scorecard $scorecard): ScoringResult
    {
        $evidence = $this->firstAgentQuote($transcript->fullText());

        $criteria = [];
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        $defs = $this->criteriaDefs($scorecard);
        foreach ($defs as [$key, $maxScore, $weight]) {
            $score = 1 + (crc32($key) % $maxScore); // 1..maxScore, stable
            $criteria[] = new CriterionScoreResult(
                criterionKey: $key,
                score: (float) $score,
                maxScore: $maxScore,
                evidenceQuote: $evidence,
                rationale: 'Deterministic fake score for development.',
            );
            $weightedSum += ($score / $maxScore) * $weight;
            $weightTotal += $weight;
        }

        $overall = $weightTotal > 0 ? round(($weightedSum / $weightTotal) * 100, 1) : 0.0;

        return new ScoringResult($overall, $criteria, 'fake-llm-v1');
    }

    /** @return list<array{0:string,1:int,2:float}> [key, maxScore, weight] */
    private function criteriaDefs(?Scorecard $scorecard): array
    {
        if ($scorecard !== null && !$scorecard->criteria()->isEmpty()) {
            $defs = [];
            foreach ($scorecard->criteria() as $c) {
                $defs[] = [$c->key(), $c->maxScore(), $c->weight()];
            }

            return $defs;
        }

        $defs = [];
        foreach (array_keys(self::DEFAULT_CRITERIA) as $key) {
            $defs[] = [$key, 5, 1.0];
        }

        return $defs;
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
