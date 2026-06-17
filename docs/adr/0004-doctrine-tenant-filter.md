# ADR-0004 — Doctrine SQL filter for tenant isolation

## Status

Accepted.

## Context

CallLens is a multi-tenant SaaS: every tenant-owned record carries `tenant_id`, and
a user must never read another tenant's data (README §6, §7.2, §11). Relying on each
query or repository method to remember to add a `tenant_id` predicate is error-prone
— a single forgotten clause leaks data across tenants.

The isolation must apply automatically to all reads, but it must **not** apply to the
authentication query that resolves the current user/tenant, otherwise resolution
would be circular.

## Decision

Apply tenant isolation with a **Doctrine ORM SQL filter** that auto-appends
`tenant_id = :current_tenant` to every query for a tenant-owned entity.

- `Domain\Tenant\TenantOwned` is a marker interface implemented by every
  tenant-scoped entity.
- `Infrastructure\Doctrine\Filter\TenantFilter` (a Doctrine `SQLFilter`) adds the
  constraint only for entities implementing `TenantOwned`, and emits no constraint
  when the `tenant_id` parameter is not yet set.
- `Infrastructure\Tenant\TenantFilterConfigurator` (a `kernel.request` subscriber)
  resolves the tenant from the authenticated principal, stores it in
  `TenantContext`, enables the filter, and sets the parameter. It is registered at
  priority **6** — below the firewall (priority 8) — so the user-provider /
  authentication query runs **before** the filter is active and is never scoped.

Storage keys are additionally namespaced per tenant
(`tenants/{tenantId}/calls/...`) as defense in depth (README §7.2). PostgreSQL
Row-Level Security is noted as an optional further layer but is **not** implemented.

## Consequences

- Tenant scoping is centralized and applied by default to all reads — developers
  cannot forget it per query.
- The deliberate priority ordering keeps authentication working: the auth query is
  unscoped, everything after it is scoped.
- The filter only covers entities implementing `TenantOwned`; native SQL / DBAL
  queries and Cube analytics bypass it and must enforce tenancy themselves.
- Defense in depth comes from namespaced object-storage keys; optional Postgres RLS
  remains **Planned** if a stronger guarantee is required (README §7.2, §16).
