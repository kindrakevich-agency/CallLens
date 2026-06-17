<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use Symfony\Component\Uid\Uuid;

/**
 * Request-scoped holder for the current tenant. Populated from the authenticated
 * principal (or a webhook endpoint, from M2) and read by the Doctrine tenant filter
 * and any code that needs to stamp new records with the active tenant.
 */
final class TenantContext
{
    private ?Uuid $tenantId = null;

    public function setTenantId(?Uuid $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function tenantId(): ?Uuid
    {
        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }
}
