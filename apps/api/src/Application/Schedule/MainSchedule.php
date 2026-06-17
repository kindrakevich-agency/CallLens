<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use App\Application\Message\AudioRetentionSweep;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The application's recurring schedule (consumed by the `scheduler` service via
 * `messenger:consume scheduler_default`).
 */
#[AsSchedule('default')]
final class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            // Daily audio-retention sweep (delete_after_days) at 03:00.
            ->add(RecurringMessage::cron('0 3 * * *', new AudioRetentionSweep()));
    }
}
