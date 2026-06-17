<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\OpenAi;

use App\Domain\Scorecard\CriterionDefinition;

/**
 * Builds the chat messages and the strict JSON schema for LLM scoring (spec §10):
 * score ONLY the agent's turns, require a verbatim evidence quote per criterion,
 * and constrain the output shape so it parses deterministically.
 */
final class ScoringPromptBuilder
{
    /**
     * @param CriterionDefinition[] $criteria
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function messages(string $transcriptText, array $criteria): array
    {
        $system = <<<'TXT'
            You are a strict, objective sales-call QA reviewer.
            Score ONLY the sales agent's performance — the lines prefixed "Agent:". Never score the customer.
            For each criterion return:
              - score: a number from 0 to the criterion's max (higher is better),
              - evidence_quote: a VERBATIM substring copied exactly from the transcript that justifies the score (empty string if there is no supporting evidence — do not invent quotes),
              - rationale: one concise sentence.
            Ground every judgement in the transcript. Do not reward things the agent did not actually say.
            TXT;

        $criteriaList = [];
        foreach ($criteria as $c) {
            $criteriaList[] = sprintf(
                '- key: %s | title: %s | max score: %d%s',
                $c->key,
                $c->title,
                $c->maxScore,
                $c->guidance ? ' | guidance: ' . $c->guidance : '',
            );
        }

        $user = sprintf(
            "Scorecard criteria:\n%s\n\nTranscript:\n%s",
            implode("\n", $criteriaList),
            $transcriptText,
        );

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * @return array<string,mixed> a JSON-schema for OpenAI structured outputs
     */
    public function schema(): array
    {
        return [
            'name' => 'call_scoring',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['criteria'],
                'properties' => [
                    'criteria' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['key', 'score', 'evidence_quote', 'rationale'],
                            'properties' => [
                                'key' => ['type' => 'string'],
                                'score' => ['type' => 'number'],
                                'evidence_quote' => ['type' => 'string'],
                                'rationale' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
