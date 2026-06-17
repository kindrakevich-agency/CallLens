<?php

declare(strict_types=1);

namespace App\Application\Provider;

use App\Domain\Call\Transcript;
use App\Domain\Scorecard\Scorecard;

/**
 * LLM scoring port (spec §10). The real impl uses structured JSON output at
 * temperature 0, grounds only on the agent's turns, and requires an evidence
 * quote per criterion that is validated against the transcript (M4).
 */
interface ScoringClient
{
    public function score(Transcript $transcript, ?Scorecard $scorecard): ScoringResult;
}
