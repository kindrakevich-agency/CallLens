# Reports & Analytics (Cube)

CallLens serves dashboards through **Cube** — a semantic layer over PostgreSQL.
Measures and dimensions are defined once and exposed via Cube's REST/JS API to
the cabinet, with **pre-aggregations** and caching so reports stay fast on
Postgres alone. No separate search/analytics engine is used for reporting.

> **Status: Planned (M7).** The Cube **service already runs in the stack** but
> the data model (`services/cube/`) and the cabinet dashboards that consume it
> are not built yet. See "What exists today" below.

## What exists today

The `cube` service is wired into the Compose stack (`docker-compose.yml`):

- Image `cubejs/cube:latest`, connected to Postgres (`CUBEJS_DB_TYPE: postgres`,
  host `db`), reachable on the **internal Docker network only** in prod.
- Config mounted from `./services/cube` -> `/cube/conf`.
- In **dev** (`docker-compose.override.yml`) `CUBEJS_DEV_MODE: "true"` and the
  **dev playground + API are exposed on `:4000`** for local exploration.
- In **prod** (`docker-compose.prod.yml`) dev mode is off and the port is not
  published; the cabinet reaches Cube over the internal network.

What is **not** built yet: the cube model files, pre-aggregations, and the
Next.js dashboard components.

## Planned semantic model (spec §14)

Postgres remains the **single source of truth**; Cube never owns data — it only
defines a query interface over existing tables.

- **Measures:** `avg_score`, `call_count`.
- **Dimensions:** `agent`, `criterion`, time dimension `week`.
- **Pre-aggregation:** `by_agent_week` — rolls up average score and call count
  per agent per week so the common dashboards never hit raw rows.

Dashboards consume Cube's REST/JS API from the Next.js cabinet (`/app`):

- Average score per agent.
- Score trend over time.
- Top objections.
- Score distribution.

## Scaling note

Cube + Postgres pre-aggregations are the reporting path. Elasticsearch/
OpenSearch is **only** introduced later if full-text relevance/faceting at scale
is needed — not for reporting, and not in the M7 scope.

## Configuration

Relevant env (see `docs/configuration.md`): `CUBEJS_API_SECRET` (`CUBE_API_SECRET`
in spec) and the shared Postgres connection. The Cube DB credentials mirror the
app's Postgres (`POSTGRES_DB` / `POSTGRES_USER` / `POSTGRES_PASSWORD`).

## Milestone summary

| Item | Status |
| --- | --- |
| `cube` service in Compose (dev playground `:4000`) | Implemented |
| Cube data model (`services/cube/`), measures/dimensions, `by_agent_week` | Planned (M7) |
| Cabinet analytics dashboards consuming Cube API | Planned (M7) |
