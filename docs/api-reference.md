# CallLens HTTP API Reference

This catalogs the **current** HTTP endpoints of the CallLens API, grouped by
**exposure** (per README §12.1). Network/firewall + Nginx + auth enforce these
exposure boundaries; this document reflects the routes actually defined in the
controllers.

> **Conventions.** JSON request/response bodies unless noted. Error responses use
> the shape `{"error": "<message>"}`. Auth cookies: `access_token` and
> `refresh_token` (the latter scoped to `/auth/refresh`).

---

## Public

### `GET /internal/health`
- **Auth:** none (but **internal-only in production** — bound to the internal
  network / IP allow-list, not internet-exposed; see README §12.1).
- **Request:** none.
- **Response:** `200 OK` when healthy, `503 Service Unavailable` when degraded.

```json
{
  "status": "ok",
  "service": "calllens-api",
  "checks": { "database": "ok" }
}
```
`status` is `degraded` and HTTP `503` if any check (currently `database`) is `down`.

### `POST /auth/register`
- **Auth:** none. **Rate-limited** per client IP (`429 Too Many Requests` on excess).
- **Request:**

```json
{ "email": "boss@prodaj.com.ua", "password": "min-8-chars", "name": "Boss", "workspace": "Optional workspace name" }
```
`workspace` is optional.
- **Response:** `201 Created` with the user payload (see [User payload](#user-payload)).
- **Errors:** `422` (invalid email, password < 8 chars, or empty name), `409` (e.g. email already registered), `429` (rate limit).

### `POST /auth/login`
- **Auth:** none. Handled by the `json_login` firewall authenticator (the
  controller body is never executed).
- **Request:** JSON credentials (email + password).
- **Response:** on success, sets the `access_token` / `refresh_token` cookies.

### `POST /auth/refresh`
- **Auth:** valid refresh token (cookie, scoped to `/auth/refresh`). Handled by the
  `refresh_jwt` authenticator. Refresh tokens are single-use / rotating; reuse is rejected.
- **Response:** rotates and re-issues the auth cookies.

### `POST /auth/logout`
- **Auth:** none required (stateless).
- **Response:** `204 No Content`; clears the `access_token` (`/`) and
  `refresh_token` (`/auth/refresh`) cookies. The rotating refresh token also self-expires.

### `GET /auth/me`
- **Auth:** **required** (authenticated principal).
- **Response:** `200 OK` with the [User payload](#user-payload).

### `GET /auth/google`
- **Auth:** none. Starts the Google OAuth flow.
- **Response:** `302` redirect to Google (scopes: `openid`, `email`, `profile`).

### `GET /auth/google/check`
- **Auth:** none. OAuth callback, handled by `GoogleAuthenticator` (the
  controller body is never executed).
- **Response:** establishes the session / auth cookies on success.

---

## Webhook (public, HMAC-signed)

### `POST /v1/webhooks/calls`
- **Auth:** **HMAC-SHA256 signature only** — no bearer token / cookie. Public,
  rate-limited, replay-protected, idempotent by `(tenant, call_id)`.
- **Required headers:** `X-CallLens-Endpoint` (endpoint UUID),
  `X-CallLens-Signature` (`sha256=<hmac>`), `X-CallLens-Timestamp`,
  `Content-Type: application/json`.
- **Request body:** `call_id` (required), `recording_url`, `agent_id`,
  `channels` (`mono`|`dual`, default `dual`), `language` (default `auto`),
  `started_at`, `duration_sec`.
- **Response:** `202 Accepted` → `{"status":"accepted","call_id":"…","duplicate":false}`.
- **Errors:** `401` (unknown endpoint / stale timestamp / invalid signature),
  `422` (invalid JSON / missing `call_id`).

> **Full contract:** see [`webhooks.md`](./webhooks.md) — headers, signing
> (`timestamp + "." + rawBody`), replay window (300s), idempotency, payload
> schema, dual-channel recommendation, and worked signing examples.

---

## Cabinet / authenticated (`/api/v1/*`)

Authenticated **and tenant-scoped**. The tenant is taken from the authenticated
principal (never from the request body).

### `POST /api/v1/calls/upload`
- **Auth:** **required** (`CurrentUser`). Tenant-scoped to the principal's tenant.
- **Request:** `multipart/form-data`.

| Field | Type | Required | Default | Notes |
|---|---|---|---|---|
| `audio` | file | **Yes** | — | The recording. Missing → `422`. Stored synchronously in object storage. |
| `external_id` | string | No | `upload-<uuidv7>` | Dedup key with the tenant. |
| `channels` | `mono`\|`dual` | No | `dual` | Anything but `mono` → `dual`. Dual-channel recommended. |
| `agent_external_id` | string | No | none | Auto-creates the agent on first sight. |
| `language` | string | No | `auto` | |

- **Responses:**
  - `202 Accepted` (new call) → `{"status":"accepted","id":"<call-uuid>","external_id":"…"}`. Audio is stored synchronously, then transcription is dispatched.
  - `200 OK` (duplicate `(tenant, external_id)`) → `{"status":"duplicate","id":"<call-uuid>","external_id":"…"}`.
- **Errors:** `422` when the `audio` field is missing.

> Other cabinet CRUD resources (calls, agents, scorecards, search, reports,
> settings) are **planned** — see the note below.

---

## User payload

Returned by `POST /auth/register` and `GET /auth/me`:

```json
{
  "id": "<user-uuid>",
  "email": "boss@prodaj.com.ua",
  "name": "Boss",
  "role": "<role>",
  "emailVerified": false,
  "tenant": {
    "id": "<tenant-uuid>",
    "name": "Workspace",
    "slug": "workspace"
  }
}
```

---

## Planned: auto-generated API docs

OpenAPI 3.1 + ReDoc auto-generation (via **API Platform**) is **planned**, not yet
in place. Per README §12.2:

- API Platform will generate **OpenAPI 3.1** from resources/DTOs automatically,
  rendered with **ReDoc** (primary, internal/authenticated route, e.g.
  `/internal/docs`) and optionally **Scalar**.
- A trimmed, **public** subset (the webhook contract + public endpoints) will be
  published in the docs site (`/docs`), exported to `docs/api/openapi.json`, and
  kept in sync by CI (doc drift fails the build).

The cabinet CRUD resources that populate these auto-generated docs **arrive from
milestone M6**. Until then, this hand-written reference is the source of truth for
the live endpoints, and [`webhooks.md`](./webhooks.md) is the source of truth for
the webhook contract.
