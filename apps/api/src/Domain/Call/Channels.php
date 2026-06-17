<?php

declare(strict_types=1);

namespace App\Domain\Call;

/**
 * Audio channel layout. Dual-channel records the rep and customer on separate
 * channels (preferred — no diarization needed); mono needs provider diarization.
 */
enum Channels: string
{
    case Mono = 'mono';
    case Dual = 'dual';
}
