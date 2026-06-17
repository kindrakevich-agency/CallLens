<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Periodic sweep (Scheduler, daily) that finds completed calls whose audio is
 * older than their tenant's `delete_after_days` window and queues deletion (§9).
 */
final readonly class AudioRetentionSweep
{
}
