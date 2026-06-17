<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Retention\RetentionPolicy;
use App\Application\Retention\RetentionPolicyResolver;
use App\Domain\Tenant\Tenant;
use PHPUnit\Framework\TestCase;

final class RetentionPolicyResolverTest extends TestCase
{
    public function testFallsBackToEnvDefaults(): void
    {
        $resolver = new RetentionPolicyResolver('delete_after_days', 30);
        $policy = $resolver->resolve(new Tenant('Acme', 'acme'));

        self::assertSame('delete_after_days', $policy->mode);
        self::assertSame(30, $policy->days);
        self::assertTrue($policy->deletesAfterDays());
    }

    public function testTenantSettingsOverrideDefaults(): void
    {
        $tenant = new Tenant('Acme', 'acme');
        $tenant->setSettings(['audio_retention' => ['mode' => 'delete_after_processing', 'days' => 7]]);

        $policy = (new RetentionPolicyResolver('keep', 30))->resolve($tenant);

        self::assertSame('delete_after_processing', $policy->mode);
        self::assertTrue($policy->deletesImmediately());
        self::assertSame(7, $policy->days);
    }

    public function testInvalidModeFallsBackToKeep(): void
    {
        $tenant = new Tenant('Acme', 'acme');
        $tenant->setSettings(['audio_retention' => ['mode' => 'nonsense']]);

        self::assertSame(RetentionPolicy::MODE_KEEP, (new RetentionPolicyResolver('keep', 30))->resolve($tenant)->mode);
    }

    public function testCutoffSubtractsDays(): void
    {
        $policy = new RetentionPolicy('delete_after_days', 10);
        $now = new \DateTimeImmutable('2026-06-17');

        self::assertSame('2026-06-07', $policy->cutoff($now)->format('Y-m-d'));
    }
}
