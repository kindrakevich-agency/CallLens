<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Deepgram;

use App\Application\Provider\TranscriptionResult;
use App\Application\Provider\TranscriptSegment;
use App\Domain\Call\Channels;
use App\Domain\Call\Speaker;

/**
 * Turns a Deepgram `/v1/listen` response into a TranscriptionResult.
 *
 * - Dual channel (multichannel=true): each utterance carries a `channel` — by
 *   convention channel 0 is the agent, channel 1 the customer.
 * - Mono (diarize=true): segments are built from the **word-level** speaker
 *   labels and split exactly where the speaker changes. (Utterance-level speakers
 *   are unreliable at turn boundaries — an utterance split by a pause can straddle
 *   a speaker change — so we never use them for mono when words are available.)
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

        $segments = $channels === Channels::Dual
            ? $this->dualChannelSegments($results)
            : $this->monoSegments($results);

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
     * Dual channel: the channel IS the speaker, so utterances (or the per-channel
     * transcript) are authoritative.
     *
     * @param array<string,mixed> $results
     *
     * @return TranscriptSegment[]
     */
    private function dualChannelSegments(array $results): array
    {
        $segments = [];
        foreach (($results['utterances'] ?? []) as $u) {
            $text = trim((string) ($u['transcript'] ?? ''));
            if ($text === '') {
                continue;
            }
            $segments[] = new TranscriptSegment(
                ((int) ($u['channel'] ?? 0)) === 0 ? Speaker::Agent : Speaker::Customer,
                (int) round(((float) ($u['start'] ?? 0)) * 1000),
                (int) round(((float) ($u['end'] ?? 0)) * 1000),
                $text,
            );
        }

        return $segments !== [] ? $segments : $this->perChannelFallback($results);
    }

    /**
     * Mono: group consecutive WORDS by their diarized speaker so segment
     * boundaries align exactly with speaker changes. Falls back to utterances,
     * then to the raw transcript, if word-level data is absent.
     *
     * @param array<string,mixed> $results
     *
     * @return TranscriptSegment[]
     */
    private function monoSegments(array $results): array
    {
        $words = $results['channels'][0]['alternatives'][0]['words'] ?? [];

        if ($words !== []) {
            return $this->groupWordsBySpeaker($words);
        }

        // Fallbacks for responses without word-level data.
        $segments = [];
        $agentSpeaker = null;
        foreach (($results['utterances'] ?? []) as $u) {
            $text = trim((string) ($u['transcript'] ?? ''));
            if ($text === '') {
                continue;
            }
            $sp = (int) ($u['speaker'] ?? 0);
            $agentSpeaker ??= $sp;
            $segments[] = new TranscriptSegment(
                $sp === $agentSpeaker ? Speaker::Agent : Speaker::Customer,
                (int) round(((float) ($u['start'] ?? 0)) * 1000),
                (int) round(((float) ($u['end'] ?? 0)) * 1000),
                $text,
            );
        }

        return $segments !== [] ? $segments : $this->perChannelFallback($results);
    }

    /**
     * @param array<int,array<string,mixed>> $words
     *
     * @return TranscriptSegment[]
     */
    private function groupWordsBySpeaker(array $words): array
    {
        $segments = [];
        $agentSpeaker = null;

        $current = null; // ['speaker'=>int, 'start'=>float, 'end'=>float, 'text'=>string[]]
        foreach ($words as $w) {
            $sp = (int) ($w['speaker'] ?? 0);
            $agentSpeaker ??= $sp;
            $token = (string) ($w['punctuated_word'] ?? $w['word'] ?? '');
            if ($token === '') {
                continue;
            }

            if ($current === null || $current['speaker'] !== $sp) {
                if ($current !== null) {
                    $segments[] = $this->segmentFrom($current, $agentSpeaker);
                }
                $current = ['speaker' => $sp, 'start' => (float) ($w['start'] ?? 0), 'end' => (float) ($w['end'] ?? 0), 'text' => []];
            }
            $current['text'][] = $token;
            $current['end'] = (float) ($w['end'] ?? $current['end']);
        }
        if ($current !== null) {
            $segments[] = $this->segmentFrom($current, $agentSpeaker);
        }

        return $segments;
    }

    /**
     * @param array{speaker:int,start:float,end:float,text:string[]} $seg
     */
    private function segmentFrom(array $seg, int $agentSpeaker): TranscriptSegment
    {
        return new TranscriptSegment(
            $seg['speaker'] === $agentSpeaker ? Speaker::Agent : Speaker::Customer,
            (int) round($seg['start'] * 1000),
            (int) round($seg['end'] * 1000),
            trim(implode(' ', $seg['text'])),
        );
    }

    /**
     * @param array<string,mixed> $results
     *
     * @return TranscriptSegment[]
     */
    private function perChannelFallback(array $results): array
    {
        $segments = [];
        foreach (($results['channels'] ?? []) as $i => $channel) {
            $text = trim((string) ($channel['alternatives'][0]['transcript'] ?? ''));
            if ($text !== '') {
                $segments[] = new TranscriptSegment($i === 0 ? Speaker::Agent : Speaker::Customer, 0, 0, $text);
            }
        }

        return $segments;
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
