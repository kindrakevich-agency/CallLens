<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider\Fake;

use App\Application\Provider\AudioRef;
use App\Application\Provider\SpeechToTextClient;
use App\Application\Provider\TranscriptionResult;
use App\Application\Provider\TranscriptSegment;
use App\Domain\Call\Speaker;

/**
 * Deterministic STT for dev/tests — returns a fixed diarized dialogue so the
 * whole pipeline runs end-to-end with no paid calls (spec §6, §10).
 */
final class FakeSpeechToText implements SpeechToTextClient
{
    private const TURNS = [
        [Speaker::Agent, "Hi, thanks for calling Acme, my name is Sam. How can I help you today?"],
        [Speaker::Customer, "Hi Sam, I'm looking at your pricing but I'm not sure it fits our budget."],
        [Speaker::Agent, "I understand. Can I ask what you're trying to achieve so I can recommend the right plan?"],
        [Speaker::Customer, "We need call analytics for a team of ten reps."],
        [Speaker::Agent, "Great — the Team plan covers that. Could we book a short demo for Thursday to walk through it?"],
        [Speaker::Customer, "Thursday works, let's do it."],
    ];

    public function transcribe(AudioRef $audio): TranscriptionResult
    {
        $segments = [];
        $cursor = 0;
        $full = [];
        foreach (self::TURNS as [$speaker, $text]) {
            $duration = 2000 + (\strlen($text) * 25);
            $segments[] = new TranscriptSegment($speaker, $cursor, $cursor + $duration, $text);
            $full[] = ($speaker === Speaker::Agent ? 'Agent: ' : 'Customer: ') . $text;
            $cursor += $duration;
        }

        $language = 'auto' === $audio->language ? 'en' : $audio->language;

        return new TranscriptionResult(
            language: $language,
            fullText: implode("\n", $full),
            segments: $segments,
            provider: 'fake',
            model: 'fake-stt-v1',
        );
    }
}
