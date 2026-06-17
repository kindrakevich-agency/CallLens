<?php

declare(strict_types=1);

namespace App\Application\Message;

/** Transcribe (+ diarize) a stored call. Routed to the dedicated `transcribe` transport. */
final readonly class TranscribeCallMessage
{
    public function __construct(public string $callId)
    {
    }
}
