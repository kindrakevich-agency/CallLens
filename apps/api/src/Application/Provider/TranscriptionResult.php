<?php

declare(strict_types=1);

namespace App\Application\Provider;

/** STT output: full text + diarized segments + the provider/model that produced it. */
final readonly class TranscriptionResult
{
    /** @param TranscriptSegment[] $segments */
    public function __construct(
        public string $language,
        public string $fullText,
        public array $segments,
        public string $provider,
        public string $model,
    ) {
    }
}
