<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Fetch a call's audio from its recording URL and store it, then start the
 * pipeline. Used by the webhook path so the endpoint can return 202 immediately
 * without downloading audio inline.
 */
final readonly class IngestCallMessage
{
    public function __construct(
        public string $callId,
        public string $recordingUrl,
    ) {
    }
}
