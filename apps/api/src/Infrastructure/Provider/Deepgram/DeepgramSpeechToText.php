<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Deepgram;

use App\Application\Provider\AudioRef;
use App\Application\Provider\ObjectStorage;
use App\Application\Provider\SpeechToTextClient;
use App\Application\Provider\TranscriptionResult;
use App\Domain\Call\Channels;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Deepgram speech-to-text (spec §10, M3). Reads the call's audio from object
 * storage and posts it to /v1/listen. Dual-channel audio is transcribed with
 * `multichannel=true` (rep/customer on separate channels); mono uses provider
 * diarization. `utterances=true` gives turn-level segments.
 */
final class DeepgramSpeechToText implements SpeechToTextClient
{
    private const ENDPOINT = 'https://api.deepgram.com/v1/listen';

    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly HttpClientInterface $httpClient,
        private readonly DeepgramResponseParser $parser,
        #[Autowire('%env(DEEPGRAM_API_KEY)%')] private readonly string $apiKey,
        #[Autowire('%env(DEEPGRAM_MODEL)%')] private readonly string $model,
    ) {
    }

    public function transcribe(AudioRef $audio): TranscriptionResult
    {
        if ($audio->objectKey === '') {
            throw new \RuntimeException('Cannot transcribe: no audio object key.');
        }
        if ($this->apiKey === '') {
            throw new \RuntimeException('DEEPGRAM_API_KEY is not configured.');
        }

        $bytes = $this->storage->get($audio->objectKey);

        $response = $this->httpClient->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Token ' . $this->apiKey,
                'Content-Type' => 'application/octet-stream',
            ],
            'query' => $this->queryFor($audio),
            'body' => $bytes,
            'timeout' => 300,
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf(
                'Deepgram returned %d: %s',
                $response->getStatusCode(),
                substr($response->getContent(false), 0, 500),
            ));
        }

        return $this->parser->parse(
            $response->toArray(),
            $audio->channels,
            $audio->language,
            $this->model,
        );
    }

    /**
     * @return array<string,string>
     */
    private function queryFor(AudioRef $audio): array
    {
        $query = [
            'model' => $this->model,
            'smart_format' => 'true',
            'punctuate' => 'true',
            'utterances' => 'true',
        ];

        if ($audio->channels === Channels::Dual) {
            $query['multichannel'] = 'true';
        } else {
            $query['diarize'] = 'true';
        }

        if ($audio->language === 'auto' || $audio->language === '') {
            $query['detect_language'] = 'true';
        } else {
            $query['language'] = $audio->language;
        }

        return $query;
    }
}
