<?php

declare(strict_types=1);

namespace App\Application\Message;

/** Delete a completed call's stored audio (retention — spec §9). */
final readonly class DeleteAudioMessage
{
    public function __construct(public string $callId)
    {
    }
}
