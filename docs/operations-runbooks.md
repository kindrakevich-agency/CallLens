# Operations & Runbooks

Incident runbooks for CallLens (spec §19) plus the health checks that exist
today. Backup and monitoring **automation** is **Planned (M11)**; the manual
health checks below are **Implemented**.

## Health checks (Implemented)

Run these first to triage any incident:

- **Stack status:** `make` (see targets) / `docker compose ps` — confirm all
  prod services are up: `api`, `nginx`, `worker`, `scheduler`, `web`, `cube`,
  `db`, `redis`.
- **API + DB liveness:** `GET /internal/health` — returns
  `200 {"status":"ok","checks":{"database":"ok"}}` when healthy, or `503`
  `{"status":"degraded"}` if the DB `SELECT 1` fails. Internal-only route
  (firewall-restricted), implemented in `apps/api/src/Api/Controller/HealthController.php`.
- **Object storage round-trip:** `php bin/console app:storage:check` — does a
  put -> get -> exists -> delete against S3 (MinIO in dev, Hetzner Object Storage
  in prod) and fails loudly on any mismatch. Use this to confirm storage is
  reachable and writable. Source: `StorageCheckCommand.php`.

## Runbook: stuck queue

Processing runs on **Symfony Messenger** with separate transports (spec §8):
`async` (ingest/score/embed/finalize), `transcribe` (dedicated STT queue so a
slow provider can't starve scoring), and `failed` — the **dead-letter**
transport where permanent failures land. Config:
`apps/api/config/packages/messenger.yaml`.

Symptoms: calls stop advancing; transcripts/scores not appearing.

1. Check workers are running: `docker compose ps` (the `worker` service / its
   Supervisor-managed processes).
2. Inspect transport depth and the dead-letter queue:
   `php bin/console messenger:failed:show`.
3. Look at why messages failed (provider error, validation, etc.).
4. Retry recoverable failures: `php bin/console messenger:failed:retry`
   (interactive) or remove poison messages: `messenger:failed:remove <id>`.
5. If workers are wedged, restart them (`docker compose restart worker`); they
   drain gracefully. Async/transcribe transports auto-retry (3 retries with
   exponential backoff) before dead-lettering, so transient blips self-heal.

## Runbook: provider outage (STT / LLM / embeddings)

External paid APIs (the **amber** dependencies) can degrade or go down.

1. Confirm it's the provider, not us: check API health, error messages in worker
   logs, and `/internal/health` (DB still ok rules out internal causes).
2. Affected messages retry automatically then dead-letter; they are **not**
   lost — they wait in `failed`.
3. Once the provider recovers, drain the backlog with
   `php bin/console messenger:failed:retry`.
4. If the outage is prolonged, consider switching the configured provider
   (`AI_STT_PROVIDER` / `AI_LLM_PROVIDER` / `AI_EMBEDDINGS_PROVIDER`) per
   `docs/configuration.md`, or pausing the affected worker so the queue holds
   work without churning retries.

## Runbook: restore from backup

> **Backup/restore automation is Planned (M11).** The intended design (spec §17):
> nightly Postgres dumps + object-storage lifecycle, stored to a separate Hetzner
> Storage Box / bucket, with a periodic restore test.

Restore procedure (target shape):

1. Stop the app services that write data (`api`, `worker`, `scheduler`) to halt
   new writes.
2. Restore the most recent good Postgres dump into `db` (drop/recreate or restore
   into a fresh volume; pgvector extension must be present).
3. Object audio in S3 is restored from the bucket / lifecycle copy as needed;
   verify with `php bin/console app:storage:check`.
4. Run `php bin/console doctrine:migrations:migrate` to bring the restored schema
   to the current revision.
5. Bring services back up and verify `GET /internal/health` returns `ok`.
6. Spot-check a recent call end-to-end (transcript, score, audio playback).

## Monitoring (Planned — M11)

Real-time server analytics (CPU/RAM/disk/traffic) via **aaPanel**, alerting, and
automated backup verification are Planned (M11). Until then, use the manual
health checks above.

## Status summary

| Item | Status |
| --- | --- |
| `docker compose ps` / `make` status | Implemented |
| `GET /internal/health` | Implemented |
| `app:storage:check` round-trip | Implemented |
| Messenger dead-letter (`failed`) + retry tooling | Implemented |
| Backup/restore automation | Planned (M11) |
| Monitoring/alerting (aaPanel analytics) | Planned (M11) |
