<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Filter;

use App\Domain\Tenant\TenantOwned;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Adds `tenant_id = :current_tenant` to every query for a TenantOwned entity.
 * Enabled per-request by TenantFilterConfigurator once the principal is known —
 * it is NOT active during the authentication query itself (spec §7.2).
 */
final class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->reflClass?->implementsInterface(TenantOwned::class)) {
            return '';
        }

        try {
            $tenantId = $this->getParameter('tenant_id');
        } catch (\InvalidArgumentException) {
            return ''; // parameter not set yet → no constraint
        }

        return sprintf('%s.tenant_id = %s', $targetTableAlias, $tenantId);
    }
}
