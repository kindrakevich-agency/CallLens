<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Deepgram;

use App\Application\Provider\TranscriptionResult;
use App\Application\Provider\TranscriptSegment;
use App\Domain\Call\Channels;
use App\Domain\Call\Speaker;

/**
 * Turns a Deepgram `/v1/listen?utterances=true` response into a TranscriptionResult.
 *
 * - Dual channel (multichannel=true): each utterance carries a `channel` — by
 *   convention channel 0 is the agent, channel 1 the customer.
 * - Mono (diarize=true): each utterance carries a `speaker` — the first speaker
 *   seen is treated as the agent.
 *
 * Pure (no I/O) so it can be unit-tested against fixtures.
 */
final class DeepgramResponseParser
{
    /**
     * @param array<string,mixed> $json decoded Deepgram response
     */
    public function parse(array $json, Channels $channels, string $requestedLanguage, string $model): TranscriptionResult
    {
        $results = $json['results'] ?? [];
        $utterances = $results['utterances'] ?? [];

        $segments = [];
        $speakerForCustomerKey = null; // first non-agent speaker id (mono diarization)

        foreach ($utterances as $u) {
            $text = trim((string) ($u['transcript'] ?? ''));
            if ($text === '') {
                continue;
            }

            $speaker = $this->resolveSpeaker($u, $channels, $speakerForCustomerKey);
            $segments[] = new TranscriptSegment(
                $speaker,
                (int) round(((float) ($u['start'] ?? 0)) * 1000),
                (int) round(((float) ($u['end'] ?? 0)) * 1000),
                $text,
            );
        }

        // Fallback: no utterances → one segment per channel transcript.
        if ($segments === []) {
            foreach (($results['channels'] ?? []) as $i => $channel) {
                $text = trim((string) ($channel['alternatives'][0]['transcript'] ?? ''));
                if ($text !== '') {
                    $segments[] = new TranscriptSegment($i === 0 ? Speaker::Agent : Speaker::Customer, 0, 0, $text);
                }
            }
        }

        $fullText = implode("\n", array_map(
            static fn (TranscriptSegment $s) => ($s->speaker === Speaker::Agent ? 'Agent: ' : 'Customer: ') . $s->text,
            $segments,
        ));

        return new TranscriptionResult(
            language: $this->resolveLanguage($results, $requestedLanguage),
            fullText: $fullText,
            segments: $segments,
            provider: 'deepgram',
            model: $model,
        );
    }

    /**
     * @param array<string,mixed> $utterance
     */
    private function resolveSpeaker(array $utterance, Channels $channels, ?int &$customerKey): Speaker
    {
        if ($channels === Channels::Dual) {
            return ((int) ($utterance['channel'] ?? 0)) === 0 ? Speaker::Agent : Speaker::Customer;
        }

        $speakerId = (int) ($utterance['speaker'] ?? 0);
        // First distinct speaker is the agent; everyone else is the customer.
        if ($customerKey === null && $speakerId !== 0) {
            $customerKey = $speakerId;
        }

        return $speakerId === 0 ? Speaker::Agent : Speaker::Customer;
    }

    /**
     * @param array<string,mixed> $results
     */
    private function resolveLanguage(array $results, string $requestedLanguage): string
    {
        if ($requestedLanguage !== 'auto' && $requestedLanguage !== '') {
            return $requestedLanguage;
        }

        $detected = $results['channels'][0]['detected_language'] ?? null;

        return \is_string($detected) && $detected !== '' ? $detected : 'en';
    }
}
