<?php

declare(strict_types=1);

namespace App\Application\Provider;

/**
 * Speech-to-text port (spec §10). Default impl Deepgram (M3); a deterministic
 * fake runs in dev/tests. Dual-channel audio is transcribed per channel; mono
 * requests provider diarization.
 */
interface SpeechToTextClient
{
    public function transcribe(AudioRef $audio): TranscriptionResult;
}
