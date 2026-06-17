<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\OpenAi;

use App\Application\Provider\ScoringClient;
use App\Application\Provider\ScoringResult;
use App\Domain\Call\Transcript;
use App\Domain\Scorecard\CriterionDefinition;
use App\Domain\Scorecard\Scorecard;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI LLM scoring (spec §10, M4). Uses Chat Completions with structured JSON
 * output at temperature 0; the evidence-quote validation is applied by the
 * EvidenceValidatingScoringClient decorator that wraps this.
 */
final class OpenAiScoring implements ScoringClient
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ScoringPromptBuilder $prompt,
        private readonly OpenAiScoringResponseParser $parser,
        #[Autowire('%env(OPENAI_API_KEY)%')] private readonly string $apiKey,
        #[Autowire('%env(OPENAI_LLM_MODEL)%')] private readonly string $model,
    ) {
    }

    public function score(Transcript $transcript, ?Scorecard $scorecard): ScoringResult
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $criteria = CriterionDefinition::resolve($scorecard);

        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $this->model,
                'temperature' => 0,
                'messages' => $this->prompt->messages($transcript->fullText(), $criteria),
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $this->prompt->schema(),
                ],
            ],
            'timeout' => 120,
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf(
                'OpenAI returned %d: %s',
                $response->getStatusCode(),
                substr($response->getContent(false), 0, 500),
            ));
        }

        $data = $response->toArray();
        $content = $data['choices'][0]['message']['content'] ?? '{}';

        try {
            $decoded = json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('OpenAI returned non-JSON scoring content: ' . $e->getMessage());
        }

        return $this->parser->parse(\is_array($decoded) ? $decoded : [], $criteria, $this->model);
    }
}
