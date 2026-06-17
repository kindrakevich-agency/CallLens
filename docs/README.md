# CallLens Documentation

Living documentation for CallLens — maintained alongside the code (every feature
change updates the relevant doc in the same PR, per the spec's Definition of Done).

**Build status:** **M0–M10 implemented** — scaffolding, auth & tenancy, ingestion
& pipeline, Deepgram STT (M3), OpenAI scoring (M4), pgvector embeddings & semantic
search (M5), the cabinet (M6), Cube analytics (M7), audio retention (M8),
docs & API docs (M9), and security hardening (M10). **M11 (deploy & ops) is the
remaining milestone.** Docs mark any unbuilt parts as **Planned (Mx)**.

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
- [Reports & analytics](reports-analytics.md) — the Cube semantic layer + cabinet dashboard.
- [Deployment](deployment.md) — git-based deploy, migrations, rollback.
- [Operations & runbooks](operations-runbooks.md) — incident runbooks + health checks.

## Decisions
- [ADRs](adr/) — architecture decision records:
  - [0001 — API-first, no GPU](adr/0001-api-first-no-gpu.md)
  - [0002 — Nginx + PHP-FPM (not FrankenPHP)](adr/0002-nginx-php-fpm.md)
  - [0003 — JWT in an HttpOnly cookie](adr/0003-jwt-in-httponly-cookie.md)
  - [0004 — Doctrine tenant filter](adr/0004-doctrine-tenant-filter.md)
