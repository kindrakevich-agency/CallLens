<?php

declare(strict_types=1);

namespace App\Domain\Call;

/** Who is speaking in an utterance. Scoring only grounds on the agent's turns. */
enum Speaker: string
{
    case Agent = 'agent';
    case Customer = 'customer';
}
