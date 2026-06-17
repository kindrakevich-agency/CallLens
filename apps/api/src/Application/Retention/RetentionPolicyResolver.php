<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Domain\Tenant\Tenant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves a tenant's effective retention policy: per-tenant
 * `settings.audio_retention` overrides the global env defaults (spec §9).
 */
final class RetentionPolicyResolver
{
    public function __construct(
        #[Autowire('%env(AUDIO_RETENTION_MODE)%')] private readonly string $defaultMode,
        #[Autowire('%env(int:AUDIO_RETENTION_DAYS)%')] private readonly int $defaultDays,
    ) {
    }

    public function resolve(Tenant $tenant): RetentionPolicy
    {
        $settings = $tenant->settings()['audio_retention'] ?? [];

        $mode = (string) ($settings['mode'] ?? $this->defaultMode);
        if (!\in_array($mode, RetentionPolicy::MODES, true)) {
            $mode = RetentionPolicy::MODE_KEEP;
        }

        $days = max(1, (int) ($settings['days'] ?? $this->defaultDays));

        return new RetentionPolicy($mode, $days);
    }
}
