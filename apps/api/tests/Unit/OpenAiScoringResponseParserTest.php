<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Scorecard\CriterionDefinition;
use App\Infrastructure\Provider\OpenAi\OpenAiScoringResponseParser;
use PHPUnit\Framework\TestCase;

final class OpenAiScoringResponseParserTest extends TestCase
{
    /** @return CriterionDefinition[] */
    private function defs(): array
    {
        return [
            new CriterionDefinition('greeting', 'Greeting', 5, 1.0),
            new CriterionDefinition('next_step', 'Next step', 10, 2.0),
        ];
    }

    public function testMapsClampsAndComputesWeightedOverall(): void
    {
        $content = [
            'criteria' => [
                ['key' => 'greeting', 'score' => 4, 'evidence_quote' => 'Hi there', 'rationale' => 'Warm open.'],
                // score above max should clamp to 10
                ['key' => 'next_step', 'score' => 99, 'evidence_quote' => '', 'rationale' => 'Booked a demo.'],
            ],
        ];

        $result = (new OpenAiScoringResponseParser())->parse($content, $this->defs(), 'gpt-4o-mini');

        self::assertSame('gpt-4o-mini', $result->model);
        self::assertCount(2, $result->criteria);

        self::assertSame('greeting', $result->criteria[0]->criterionKey);
        self::assertSame(4.0, $result->criteria[0]->score);
        self::assertSame('Hi there', $result->criteria[0]->evidenceQuote);

        self::assertSame(10.0, $result->criteria[1]->score, 'score clamped to maxScore');
        self::assertNull($result->criteria[1]->evidenceQuote, 'empty quote becomes null');

        // weighted: (4/5*1 + 10/10*2) / (1+2) = (0.8 + 2)/3 = 0.9333 → 93.3%
        self::assertEqualsWithDelta(93.3, $result->overallScore, 0.1);
    }

    public function testMissingCriterionScoresZero(): void
    {
        $content = ['criteria' => [['key' => 'greeting', 'score' => 5, 'evidence_quote' => 'x', 'rationale' => 'y']]];

        $result = (new OpenAiScoringResponseParser())->parse($content, $this->defs(), 'gpt-4o-mini');

        self::assertSame('next_step', $result->criteria[1]->criterionKey);
        self::assertSame(0.0, $result->criteria[1]->score, 'absent criterion defaults to 0');
    }
}
