# Authentication & Security

CallLens is a multi-tenant AI SaaS. Every request must resolve to exactly one
workspace (tenant) and one principal, and may never reach another tenant's data.
This document describes how sign-up, sign-in, token handling, authorization,
tenant isolation, webhook trust, rate limiting and auditing actually work, with
references to the implementing code. It tracks spec §11 (auth), §12.1 (exposure)
and §16 (security).

Authoritative sources for this document:

- `apps/api/config/packages/security.yaml`
- `apps/api/config/packages/lexik_jwt_authentication.yaml`
- `apps/api/config/packages/gesdinet_jwt_refresh_token.yaml`
- `apps/api/config/packages/rate_limiter.yaml`
- `apps/api/src/Security/*` (`GoogleAuthenticator`, `Voter/TenantVoter`, `WebhookSignatureVerifier`, `EventListener/LoginAuditListener`)
- `apps/api/src/Application/Auth/*` (`RegisterUserService`, `GoogleAuthenticationService`)
- `apps/api/src/Infrastructure/Tenant/*` and `apps/api/src/Infrastructure/Doctrine/Filter/TenantFilter.php`
- `apps/api/src/Api/Controller/*` (`AuthController`, `GoogleController`, `WebhookController`)

---

## 1. Sign-up & sign-in

Two credential types are supported, and a single account may carry both: a
password and a linked Google identity (find-or-create-and-link, see §1.3).

### 1.1 Email + password

**Register — `POST /auth/register`** (`AuthController::register`)

Public, rate-limited (see §6). Body:

```json
{ "email": "...", "password": "...", "name": "...", "workspace": "optional" }
```

Validation in the controller: a syntactically valid email, a password of at
least 8 characters, and a non-empty name. Failures return `422 Unprocessable
Entity`.

On success the request is delegated to `RegisterUserService::register`, which:

1. Lowercases/trims the email and rejects a duplicate with a `DomainException`
   (surfaced as `409 Conflict`).
2. Creates a **new `Tenant`** (workspace). The workspace name defaults to
   `"{name}'s workspace"` when not provided; the slug is made unique via
   `uniqueSlug` (`base`, `base-2`, `base-3`, …).
3. Creates the **first `User` as `Role::Owner`** and hashes the password
   (`UserPasswordHasherInterface::hashPassword`).
4. Persists tenant + user in one flush, then writes a `user.registered` audit
   entry.

The endpoint returns `201 Created` with the user payload (id, email, name,
role, `emailVerified`, and the tenant id/name/slug). It does **not** log the
user in; the client then calls `/auth/login`.

> Bootstrapping rule: the first user of a brand-new email always creates and
> owns a fresh workspace. There is no global "join existing tenant" path on
> registration. (Spec §11.)

**Login — `POST /auth/login`**

Handled entirely by the Symfony firewall's `json_login`, not by controller
code (`AuthController::login` only registers the route and throws if ever
executed). Config (`security.yaml`, `login` firewall):

- `username_path: email`, `password_path: password`
- success handler: `lexik_jwt_authentication.handler.authentication_success`
  (sets the `access_token` cookie; gesdinet sets the refresh cookie)
- failure handler: `lexik_jwt_authentication.handler.authentication_failure`
- `login_throttling`: 5 attempts / 15 minutes (see §6)

A successful login dispatches `LoginSuccessEvent`, which `LoginAuditListener`
records as `user.login` (see §7).

### 1.2 Google OAuth

**Start — `GET /auth/google`** (`GoogleController::connect`) redirects to Google
with scopes `openid email profile`.

**Callback — `GET /auth/google/check`** is matched by `GoogleAuthenticator`
(`supports()` keys on the `auth_google_check` route; the controller method body
never runs). The authenticator:

1. Exchanges the single-use authorization code for an access token
   (`fetchAccessToken`).
2. Fetches the Google profile and calls
   `GoogleAuthenticationService::findOrCreate($googleId, $email, $name)` to
   resolve the app user (see §1.3).
3. On success, reuses lexik's success handler so the **same `access_token` (and
   gesdinet refresh) cookies** are set exactly as configured, then issues a
   `RedirectResponse` to `WEB_URL + /app` carrying those cookies. Failure
   returns `401` JSON.

### 1.3 Find-or-create-and-link

`GoogleAuthenticationService::findOrCreate` resolves a Google identity in this
order:

1. **Existing `google_id`** → return that user.
2. **Existing email** → link Google to that account (`linkGoogle`), mark the
   email verified (`markEmailVerified`), flush, and write a `user.google_linked`
   audit entry. This is how a password account and a Google identity become one
   account.
3. **Otherwise** → create a **new `Tenant` + Owner `User`**, link Google, mark
   the email verified (Google has already verified it), and write a
   `user.registered_google` audit entry.

So Google sign-in is also a bootstrap path: a brand-new Google user gets their
own workspace as Owner, mirroring `/auth/register`.

### 1.4 Session helpers

- **`GET /auth/me`** (`ROLE_USER`) returns the current user payload via
  `#[CurrentUser]`.
- **`POST /auth/logout`** is stateless: it clears the `access_token` cookie
  (path `/`) and the `refresh_token` cookie (path `/auth/refresh`). The rotating
  refresh token also self-expires, and reuse is rejected by `single_use`.

### 1.5 Email verification & password reset

Backed by single-use, expiring `auth_token` rows. Only the **SHA-256 hash** of a
token is persisted, so a database read never yields a usable link; each token is
consumed exactly once, and issuing a new one invalidates any outstanding token of
the same type for that user.

- **Email verification** — registration sends a confirmation email (24-hour TTL).
  `POST /auth/email/verify` (public, token-authenticated) marks the mailbox
  verified; `POST /auth/email/resend` (`ROLE_USER`) re-issues it. Google accounts
  are verified on link. The cabinet shows a banner until the email is confirmed.
- **Password reset** — `POST /auth/password/forgot` is **non-revealing** (always
  `204`, rate-limited per IP) so it can't probe for registered addresses; it mails
  a reset link (1-hour TTL). `POST /auth/password/reset` rotates the password hash
  and, since it proves mailbox control, also marks the email verified.

Mail is sent via Symfony Mailer (`MAILER_DSN` → Mailpit in dev; the null transport
under test).

---

## 2. Token mechanism

### 2.1 Access token (JWT) — cookie only

JWTs are issued by LexikJWTAuthenticationBundle and live **only** in a cookie
named `access_token` — never in a response body, never in `localStorage`
(`lexik_jwt_authentication.yaml`):

- Cookie flags: **`httpOnly: true`**, **`secure`** (from `COOKIE_SECURE`),
  **`samesite: lax`**, `path: /`, `domain` from `COOKIE_DOMAIN`.
- `lifetime` / `token_ttl`: short, from `JWT_ACCESS_TOKEN_TTL`.
- Token extraction: the `cookie` extractor (`name: access_token`) is enabled and
  the `authorization_header` extractor is **disabled** — the API will not accept
  a bearer header.
- `remove_token_from_body_when_cookies_used: true` — the raw token is never
  leaked in JSON.

The `main` firewall (`jwt: ~`) validates this cookie on every request.

### 2.2 Refresh token — rotating, single-use

Refresh is handled by GesdinetJWTRefreshTokenBundle
(`gesdinet_jwt_refresh_token.yaml`) via the dedicated `refresh` firewall
(`refresh_jwt`, `check_path: /auth/refresh`). `AuthController::refresh` only
registers the route.

- TTL from `JWT_REFRESH_TOKEN_TTL`; refresh token class
  `App\Domain\Auth\RefreshToken`.
- **`single_use: true`** — every successful refresh issues a brand-new token
  pair and invalidates the old refresh token. **Replaying a used refresh token
  is rejected** (reuse detection). `return_expiration: true`.
- The refresh token also lives in an **`http_only`, `secure`, `same_site: lax`**
  cookie, but is **scoped to `path: /auth/refresh`**, so it is never transmitted
  with ordinary API requests — only to the refresh endpoint.

### 2.3 Password hashing — argon2id via libsodium

`security.yaml` configures the hasher for `App\Domain\User\User` with
**`algorithm: sodium`**, which hashes with **argon2id**.

> This is set **explicitly**, not `auto`. The `auto` algorithm resolved to
> bcrypt in this environment; `sodium` pins argon2id as the spec requires
> (§11). The `when@test` block uses `sodium` with reduced `time_cost: 3` /
> `memory_cost: 10` to keep the test suite fast.

---

## 3. Authorization

### 3.1 Roles & hierarchy

Four workspace roles map to Symfony roles, with `role_hierarchy`
(`security.yaml`):

```
ROLE_OWNER   → ROLE_ADMIN
ROLE_ADMIN   → ROLE_MANAGER
ROLE_MANAGER → ROLE_VIEWER
ROLE_VIEWER  → ROLE_USER
```

So an Owner implies Admin → Manager → Viewer → User; "manager or above" is
expressed as `ROLE_MANAGER`.

| Workspace role | Symfony role | Implies |
|---|---|---|
| owner   | `ROLE_OWNER`   | admin, manager, viewer, user |
| admin   | `ROLE_ADMIN`   | manager, viewer, user |
| manager | `ROLE_MANAGER` | viewer, user |
| viewer  | `ROLE_VIEWER`  | user |

### 3.2 TenantVoter

`App\Security\Voter\TenantVoter` is defense-in-depth on top of the Doctrine
tenant filter, and votes on `TenantOwned` subjects for `VIEW`, `EDIT`,
`DELETE`:

- **Hard tenant boundary first:** if the subject's tenant id does not equal the
  user's tenant id, the vote is denied — regardless of role.
- Within the same tenant:
  - `VIEW` → granted to any authenticated user of that tenant.
  - `EDIT` / `DELETE` → require **`ROLE_MANAGER`** (manager and above).

This means a viewer can read but not mutate, and no role can touch another
tenant's record even if the filter were somehow bypassed.

---

## 4. Tenant isolation

### 4.1 Doctrine tenant filter

`App\Infrastructure\Doctrine\Filter\TenantFilter` (a Doctrine `SQLFilter`)
appends `alias.tenant_id = :tenant_id` to every query against any entity
implementing `TenantOwned`. If the `tenant_id` parameter is not set, it adds no
constraint (so the filter is inert until configured).

The filter is enabled **per request** by
`App\Infrastructure\Tenant\TenantFilterConfigurator`, an event subscriber on
`KernelEvents::REQUEST`:

- It runs at **priority 6**. The **firewall runs at priority 8**, i.e. earlier.
  This ordering is deliberate: the user-provider / authentication query (loading
  the user by email, validating the JWT) executes **before** the tenant filter
  is enabled, so the auth query is **unscoped** — otherwise there would be a
  chicken-and-egg problem (you can't scope to a tenant before you know who the
  user is). (Spec §7.2.)
- Only on the main request: it reads the authenticated `User` from `Security`,
  derives `tenant()->id()`, stores it in the request-scoped `TenantContext`, and
  enables the `tenant` filter with that id.

`TenantContext` is the request-scoped holder of the active tenant id, read by
the filter and by any code that stamps new records with the current tenant.

Net effect: after the firewall authenticates a request, **every** subsequent
ORM read of tenant-owned data is automatically constrained to the principal's
workspace.

### 4.2 Object-storage key namespacing

Audio is stored under tenant-namespaced keys, so isolation extends to object
storage and not just the database. Keys follow:

```
tenants/{tenantId}/calls/{callId}/audio.{ext}
```

built in `IngestCallHandler` (webhook-ingested audio) and `CallUploadController`
(manual upload). The `ObjectStorage` port (Hetzner Object Storage in prod, MinIO
in dev) documents that keys are tenant-namespaced; downloads for the cabinet
player use short-lived presigned URLs.

---

## 5. Webhook trust

Call ingestion (`POST /v1/webhooks/calls`, `WebhookController`) is **public but
HMAC-verified**, replay-protected and idempotent (spec §16, §23).

`App\Security\WebhookSignatureVerifier`:

- **Signature:** HMAC-SHA256 over **`timestamp + "." + rawBody`** using the
  endpoint's `signingSecret()`, formatted as `sha256=<hex>`. Binding the
  timestamp into the signed payload prevents replay with a forged-fresh
  timestamp. Comparison uses `hash_equals` (constant-time).
- **Replay window:** `isFreshTimestamp` rejects timestamps outside ±**300
  seconds** (`replayWindowSeconds = 300`).
- A static `sign()` helper exists for clients/tests to produce a matching
  signature.

`WebhookController::__invoke` enforces, in order:

1. `X-CallLens-Endpoint` must be a valid UUID resolving to an **active**
   endpoint (else `401`).
2. `X-CallLens-Timestamp` must be fresh (else `401` "Stale or missing
   timestamp").
3. `X-CallLens-Signature` must match (else `401` "Invalid signature").
4. The body must be valid JSON with a non-empty `call_id` (else `422`).

**Idempotency** is by `(tenant, call_id)`: `CallIngestionService::ingest`
returns whether the call was newly created. A re-delivered webhook returns
`202 Accepted` with `"duplicate": true` and does **not** re-dispatch the
ingestion pipeline. Processing is asynchronous (only a new call dispatches
`IngestCallMessage`).

---

## 6. Rate limiting

- **Login throttling** (`security.yaml`, `login` firewall): `login_throttling`
  with **`max_attempts: 5`** over **`interval: 15 minutes`** (per IP +
  per-username backoff).
- **Registration limiter** (`rate_limiter.yaml`, `registration`): a
  **sliding-window** limiter, **limit 5 / 15 minutes**, keyed by **client IP**.
  `AuthController::register` consumes a token and returns **`429 Too Many
  Requests`** when the budget is exhausted.

---

## 7. Audit log

Security-relevant events are written to `AuditLog` (action, tenant, user,
target, ip):

| Action | Where | When |
|---|---|---|
| `user.registered` | `RegisterUserService` | email+password sign-up |
| `user.login` | `LoginAuditListener` (on `LoginSuccessEvent`) | any successful login |
| `user.registered_google` | `GoogleAuthenticationService` | first-time Google sign-up (new workspace) |
| `user.google_linked` | `GoogleAuthenticationService` | Google linked to an existing email account |

`LoginAuditListener` captures the client IP from the current request. (Spec §16
also lists secret regeneration, scorecard/retention changes and deletions as
auditable; those are produced elsewhere in the app.)

---

## 8. Exposure rules (spec §12.1)

Endpoints are grouped by exposure; Nginx, the host firewall and the firewall
config together enforce this. `security.yaml` `access_control`:

```
^/internal/health$              → PUBLIC_ACCESS
^/auth/(login|refresh|register|google)  → PUBLIC_ACCESS
^/v1/webhooks/                  → PUBLIC_ACCESS   (HMAC-verified in controller)
^/auth/me$                      → ROLE_USER
^/api                           → ROLE_USER
```

| Group | Examples | Exposure |
|---|---|---|
| **Public auth** | `POST /auth/register`, `/auth/login`, `/auth/refresh`, `/auth/google`, `/auth/google/check`, `/auth/logout` | Public, rate-limited. |
| **Webhook ingest** | `POST /v1/webhooks/calls` | Public **but HMAC-signed only**, replay-protected, idempotent. |
| **Authenticated cabinet API** | `/auth/me`, `/api/v1/*` (calls, agents, scorecards, search, reports, settings) | Authenticated (`ROLE_USER`) **and** tenant-scoped. |
| **Internal / ops** | `/internal/*` (detailed health/metrics, queue admin, OpenAPI/ReDoc UI). `/internal/health` is the only public probe. | **Not internet-exposed** — internal network / IP allow-list / separate non-public port; auth-gated. |

Hardening (spec §12.1/§16): Postgres, Redis, Cube, MinIO and PHP-FPM ports are
never published to the host's public interface; the firewall exposes only
80/443 (and restricted, key-only SSH). Security headers (HSTS, CSP,
X-Content-Type-Options, Referrer-Policy) and strict DTO validation apply.

---

## 9. Planned / not-yet-implemented

The following are specified (§11/§15/§16) and designed-for, but are **not** in
the current authentication code. They are listed here so the threat model is
explicit about what is and isn't enforced today.

- **CSRF protection** for cookie-based, state-changing requests. Because auth
  uses cookies, mutating endpoints need CSRF defenses (the `SameSite=Lax`
  cookies mitigate but do not fully replace this).
- **CORS allow-list** locked to the app origin only.
- **2FA** (optional TOTP).
- **Secrets vault in production.** Real `APP_SECRET`, `JWT_*`,
  `GOOGLE_OAUTH_CLIENT_ID/SECRET`, cookie domain/flags and storage credentials
  must come from the environment / Symfony Secrets vault — **never committed**.
  A documented rotation procedure is part of the spec.

---

## Hardening & security checklist (M10)

Implemented:

- **Network exposure** — only Nginx (API) and the web app publish ports; Postgres,
  Redis, Cube, MinIO and PHP-FPM are reachable on the Docker network only
  (`docker-compose.yml`). In prod only 80/443 are open and data-service ports bind
  to loopback (`docker-compose.prod.yml`).
- **Security headers** — `SecurityHeadersListener` adds `X-Content-Type-Options`,
  `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`,
  `Cross-Origin-Opener-Policy`, a strict CSP on API payloads
  (`default-src 'none'`), and HSTS over HTTPS. The Next app sets the same headers
  plus a page CSP via `next.config.ts`. The ReDoc page ships its own CSP.
- **CSRF** — JWT in a SameSite=Lax cookie (primary defense) **plus**
  `CsrfOriginListener`, which rejects unsafe (POST/PUT/PATCH/DELETE) *cookie-
  authenticated* requests whose `Origin`/`Referer` is not allow-listed.
- **CORS** — credentialed cross-origin limited to the SPA origin
  (`CORS_ALLOW_ORIGIN`) on `^/(api|auth|v1)/` only.
- **Authn/Z** — argon2id (sodium), JWT-in-cookie, rotating single-use refresh,
  voters, Doctrine tenant filter; auth endpoints are rate-limited.
- **Webhook trust** — HMAC-SHA256 over `timestamp + "." + rawBody`, 300s replay
  window, idempotency by `call_id`.
- **Secrets** — never committed; injected via env / Symfony secrets vault (a
  leaked dev `APP_SECRET` was purged from history and remediated).
- **Supply chain** — pinned `package-lock.json` + `composer.lock`; CI
  (`.github/workflows/ci.yml`) runs `composer audit` and `npm audit` on every
  push/PR alongside tests, typecheck, lint and build. Both audits are currently
  **clean** (a transitive `postcss` advisory was resolved via an npm override).

Planned (refinements / later milestones):

- Nonce-based strict CSP for the web app (drop `unsafe-inline`/`unsafe-eval`).
- `trusted_proxies` for correct HTTPS detection behind aaPanel (M11 deploy).
- Comprehensive DTO validation (Symfony Validator) on every write endpoint.
- Postgres Row-Level Security as defense in depth; SSH key-only + fail2ban,
  TLS via Let's Encrypt, encrypted volumes (M11 infra).
- A formal pen-test pass and dependency review (Dependabot).
