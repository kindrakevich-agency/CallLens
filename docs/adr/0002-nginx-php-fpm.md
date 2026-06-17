# ADR-0002 — Nginx + PHP-FPM as the app server

## Status

Accepted.

## Context

The Symfony 7.4 / PHP 8.5 API needs an HTTP front. The main options were:

- **Nginx + PHP-FPM** — the long-standing, well-understood Symfony deployment.
- **FrankenPHP** — a modern Caddy-based PHP app server with worker mode.

Production TLS is terminated by an aaPanel-managed Nginx that reverse-proxies to
the app container (README §12.1, §17). The deployment must be boring, observable,
and identical in dev and prod.

## Decision

Serve the API with **Nginx + PHP-FPM only**. FrankenPHP is **explicitly rejected**
(README §4: "Nginx + PHP-FPM only. Do **not** use FrankenPHP.").

In compose ([`../../docker-compose.yml`](../../docker-compose.yml)):

- `nginx` (`nginx:1.27-alpine`) is the only API-side service that publishes a host
  port; it proxies to the PHP-FPM container using `docker/nginx/api.conf`.
- `api` is built from `docker/php/Dockerfile` (target `prod`) and runs PHP-FPM.
- The same PHP image (`docker/php/Dockerfile`) also runs the `worker` and
  `scheduler` Messenger consumers, so app and workers share one runtime.

## Consequences

- Mirrors the aaPanel-managed Nginx in production for dev/prod parity; one less
  moving part to reason about at the TLS edge.
- Mature, well-documented operational model (process management, slow-log, metrics)
  and broad community knowledge.
- PHP-FPM's process-per-request model forgoes FrankenPHP worker-mode warm-start
  performance — acceptable, since heavy work is offloaded to async workers and
  external AI APIs (see [ADR-0001](0001-api-first-no-gpu.md)).
- PHP-FPM's port is never published to the host; only `nginx` is reachable
  (README §12.1).
