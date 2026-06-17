<?php

declare(strict_types=1);

namespace App\Application\Provider;

use App\Domain\Call\Channels;

/** Everything the STT provider needs to fetch and transcribe a call's audio. */
final readonly class AudioRef
{
    public function __construct(
        public string $objectKey,
        public Channels $channels,
        public string $language = 'auto',
    ) {
    }
}
