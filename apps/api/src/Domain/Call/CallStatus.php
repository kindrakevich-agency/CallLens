<?php

declare(strict_types=1);

namespace App\Domain\Call;

/**
 * Call lifecycle states, driven by the Symfony Workflow (spec §8):
 * received → transcribing → transcribed → scoring → scored → embedding → completed,
 * with `failed` reachable from any step.
 */
enum CallStatus: string
{
    case Received = 'received';
    case Transcribing = 'transcribing';
    case Transcribed = 'transcribed';
    case Scoring = 'scoring';
    case Scored = 'scored';
    case Embedding = 'embedding';
    case Completed = 'completed';
    case Failed = 'failed';
}
