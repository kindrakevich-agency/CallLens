<?php

declare(strict_types=1);

namespace App\Application\Schedule;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The application's recurring schedule (consumed by the `scheduler` service via
 * `messenger:consume scheduler_default`). Housekeeping tasks are registered here;
 * the daily AudioRetentionSweep is added in M8.
 */
#[AsSchedule('default')]
final class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            // ->add(RecurringMessage::cron('0 3 * * *', new AudioRetentionSweep()))  // M8
        ;
    }
}
