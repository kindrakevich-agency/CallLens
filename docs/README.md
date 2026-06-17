# CallLens Documentation

Living documentation for CallLens — maintained alongside the code (every feature
change updates the relevant doc in the same PR, per the spec's Definition of Done).

**Build status:** M0 (scaffolding), M1 (auth & tenancy) and M2 (ingestion +
pipeline skeleton) are implemented. M3–M11 are planned; docs mark unbuilt parts
as **Planned (Mx)**.

## Start here
- [Architecture](architecture.md) — system overview, services, request paths, exposure model.
- [Local development](local-development.md) — `make up`, service URLs/ports, tests, troubleshooting.
- [Configuration](configuration.md) — every environment variable + per-tenant settings.

## Domain & processing
- [Data model](data-model.md) — entities, ERD, enums, multi-tenancy.
- [Processing pipeline](processing-pipeline.md) — Workflow state machine, Messenger stages, retries.
- [Integrations](integrations.md) — STT/LLM/embedding/storage ports, fakes, provider selection.
- [Audio retention](audio-retention.md) — retention modes & deletion guarantees.

## Security & API
- [Authentication & security](authentication-security.md) — auth flows, tenancy, threat model, hardening.
- [Webhooks](webhooks.md) — the signed ingestion contract (HMAC, replay, idempotency).
- [API reference](api-reference.md) — endpoint catalog by exposure tier.

## Frontend, analytics, ops
- [Frontend](frontend.md) — Next.js structure, route areas, design tokens.
- [Reports & analytics](reports-analytics.md) — the Cube semantic layer (planned M7).
- [Deployment](deployment.md) — git-based deploy, migrations, rollback.
- [Operations & runbooks](operations-runbooks.md) — incident runbooks + health checks.

## Decisions
- [ADRs](adr/) — architecture decision records:
  - [0001 — API-first, no GPU](adr/0001-api-first-no-gpu.md)
  - [0002 — Nginx + PHP-FPM (not FrankenPHP)](adr/0002-nginx-php-fpm.md)
  - [0003 — JWT in an HttpOnly cookie](adr/0003-jwt-in-httponly-cookie.md)
  - [0004 — Doctrine tenant filter](adr/0004-doctrine-tenant-filter.md)
