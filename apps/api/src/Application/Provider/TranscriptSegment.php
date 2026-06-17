<?php

declare(strict_types=1);

namespace App\Application\Provider;

use App\Domain\Call\Speaker;

/** One diarized segment returned by the STT provider. */
final readonly class TranscriptSegment
{
    public function __construct(
        public Speaker $speaker,
        public int $startMs,
        public int $endMs,
        public string $text,
    ) {
    }
}
