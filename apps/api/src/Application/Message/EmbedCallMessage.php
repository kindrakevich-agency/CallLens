<?php

declare(strict_types=1);

namespace App\Application\Message;

/** Embed a scored call's utterances, then finalize (→ completed). */
final readonly class EmbedCallMessage
{
    public function __construct(public string $callId)
    {
    }
}
