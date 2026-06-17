<?php

declare(strict_types=1);

namespace App\Domain\Tenant;

/**
 * Marker for entities scoped to a workspace. The Doctrine tenant filter
 * auto-applies `tenant_id = :current_tenant` to every TenantOwned entity (spec §7.2),
 * so business code never has to remember to filter by tenant.
 */
interface TenantOwned
{
    public function tenant(): Tenant;
}
