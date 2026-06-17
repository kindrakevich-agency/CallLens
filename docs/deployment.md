# Deployment (git-based)

CallLens deploys to a single **Hetzner AX-line dedicated server** (Ubuntu Server
24.04 LTS) running the app as Docker Compose, for parity with local development.
Deployment is **git-driven**.

> **Status:** the runtime artifacts referenced here partly exist:
> `docker-compose.prod.yml` is present and the `/internal/health` endpoint is
> **implemented**. The deploy automation (`scripts/deploy.sh` / `make deploy`)
> is **Planned (M11)** — the `make deploy` target exists but the script it calls
> (`./scripts/deploy.sh`) is not written yet.

## Topology

- **Public edge:** **aaPanel-managed Nginx** terminates TLS (Let's Encrypt) and
  reverse-proxies to the app container(s). Only **ports 80/443** are exposed to
  the internet.
- **Data services** (Postgres, Redis, Cube, object storage) are bound to the
  **internal Docker network only** — never publicly reachable (spec §12.1, §16).
- **Runtime:** `docker-compose.yml` + `docker-compose.prod.yml`. Prod services:
  `api`, `nginx`, `worker`, `scheduler`, `web`, `cube`, `db`, `redis`.
- **Health probe:** `GET /internal/health` (DB connectivity check) — returns
  `200 {"status":"ok"}` or `503 {"status":"degraded"}`. Lives under `/internal/*`
  and is firewall-restricted, not exposed publicly.

## Trigger (spec §18)

Either path is git-driven:

1. **Push to a bare prod remote** on the server with a `post-receive` hook that
   runs the deploy, **or**
2. **CI (GitHub Actions)** runs lint -> static analysis -> tests -> build images,
   then triggers an **SSH deploy** to the server.

## Deploy steps (Planned — `scripts/deploy.sh` / `make deploy`)

1. Fetch the new revision into a release directory.
2. Build/pull images:
   `docker compose -f docker-compose.yml -f docker-compose.prod.yml build` (or `pull`).
3. Run DB migrations: `php bin/console doctrine:migrations:migrate`
   (backward-compatible — see below).
4. Build frontend (`next build`) / update Cube.
5. **Rolling restart** of `api`, `worker`, `web`; **drain workers gracefully**
   so in-flight messages finish.
6. Health check `GET /internal/health`; on failure, **roll back** to the previous
   release.

## Migrations — expand-then-contract

Migrations must be **backward-compatible** (expand-then-contract): a deploy first
applies additive ("expand") schema changes that both old and new code tolerate,
so **old workers keep running during the rolling restart**. Destructive
("contract") changes are deferred to a later deploy once no old code references
the removed structures.

## Secrets

**Zero secrets in git.** The server reads configuration from the environment /
**Symfony Secrets vault**. See `docs/configuration.md` for the variable list.

## Rollback

The health check in step 6 gates the deploy. On failure, point the running stack
back at the previous release directory and restart `api`/`worker`/`web`. Because
migrations are expand-then-contract, the previous code revision remains
schema-compatible, so rollback does not require a down-migration.

## Backups

Nightly Postgres dumps + object-storage lifecycle; dumps stored to a separate
Hetzner Storage Box / bucket, with a periodic restore test (see
`docs/operations-runbooks.md`). **Backup/restore automation is Planned (M11).**

## Milestone summary

| Item | Status |
| --- | --- |
| `docker-compose.prod.yml`, prod service set | Implemented |
| `/internal/health` probe | Implemented |
| `make deploy` target | Implemented (calls a not-yet-written script) |
| `scripts/deploy.sh`, post-receive hook / GH Actions SSH deploy | Planned (M11) |
| Backup + restore automation | Planned (M11) |
