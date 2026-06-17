<?php

declare(strict_types=1);

namespace App\Application\Message;

/** Score a transcribed call against its scorecard version. */
final readonly class ScoreCallMessage
{
    public function __construct(public string $callId)
    {
    }
}
