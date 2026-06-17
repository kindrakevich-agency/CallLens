<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\OpenAi;

use App\Application\Provider\CriterionScoreResult;
use App\Application\Provider\ScoringResult;
use App\Domain\Scorecard\CriterionDefinition;

/**
 * Turns the model's JSON scoring output into a ScoringResult. Iterates the
 * SCORECARD criteria (not the model's list) so every criterion is always scored,
 * clamps each score to [0, maxScore], and computes the overall as a weighted %.
 *
 * Pure (no I/O) → unit-testable against fixtures.
 */
final class OpenAiScoringResponseParser
{
    /**
     * @param array<string,mixed>     $content decoded model JSON {criteria: [...]}
     * @param CriterionDefinition[]    $criteria
     */
    public function parse(array $content, array $criteria, string $model): ScoringResult
    {
        $byKey = [];
        foreach (($content['criteria'] ?? []) as $item) {
            if (isset($item['key'])) {
                $byKey[(string) $item['key']] = $item;
            }
        }

        $results = [];
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($criteria as $def) {
            $item = $byKey[$def->key] ?? null;
            $score = $this->clamp((float) ($item['score'] ?? 0), $def->maxScore);
            $quote = trim((string) ($item['evidence_quote'] ?? ''));

            $results[] = new CriterionScoreResult(
                criterionKey: $def->key,
                score: $score,
                maxScore: $def->maxScore,
                evidenceQuote: $quote !== '' ? $quote : null,
                rationale: isset($item['rationale']) ? (string) $item['rationale'] : null,
            );

            $weightedSum += ($score / $def->maxScore) * $def->weight;
            $weightTotal += $def->weight;
        }

        $overall = $weightTotal > 0 ? round(($weightedSum / $weightTotal) * 100, 1) : 0.0;

        return new ScoringResult($overall, $results, $model);
    }

    private function clamp(float $score, int $max): float
    {
        return max(0.0, min($score, (float) $max));
    }
}
