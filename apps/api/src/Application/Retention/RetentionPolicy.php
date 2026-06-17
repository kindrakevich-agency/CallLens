<?php

declare(strict_types=1);

namespace App\Application\Retention;

/**
 * Effective audio-retention policy for a tenant (spec §9).
 */
final readonly class RetentionPolicy
{
    public const MODE_KEEP = 'keep';
    public const MODE_AFTER_PROCESSING = 'delete_after_processing';
    public const MODE_AFTER_DAYS = 'delete_after_days';

    public const MODES = [self::MODE_KEEP, self::MODE_AFTER_PROCESSING, self::MODE_AFTER_DAYS];

    public function __construct(
        public string $mode,
        public int $days,
    ) {
    }

    public function deletesImmediately(): bool
    {
        return $this->mode === self::MODE_AFTER_PROCESSING;
    }

    public function deletesAfterDays(): bool
    {
        return $this->mode === self::MODE_AFTER_DAYS;
    }

    /** The cutoff: completed calls older than this are due for deletion. */
    public function cutoff(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify(sprintf('-%d days', $this->days));
    }
}
