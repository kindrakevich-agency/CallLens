# ADR-0003 — JWT in an HttpOnly cookie

## Status

Accepted.

## Context

The cabinet is a Next.js SPA talking to a stateless Symfony API. It needs a session
mechanism that resists XSS token theft and CSRF, supports both email/password and
Google OAuth sign-in, and scales without server-side session storage (README §11).

Storing a JWT in `localStorage` exposes it to any XSS on the page. A bearer token in
JS-readable storage is the common anti-pattern this decision avoids.

## Decision

Use a stateless **JWT stored in an HttpOnly, Secure, SameSite=Lax cookie** — never
in `localStorage`. A short-lived access token is paired with a rotating refresh
token, with refresh-token reuse detection.

Implementation (bundles registered in `apps/api/config/bundles.php`):

- `lexik/jwt-authentication-bundle` issues and validates the access JWT.
- `gesdinet/jwt-refresh-token-bundle` provides rotating refresh tokens.
- `knpu/oauth2-client-bundle` handles Google sign-in via
  `Security/GoogleAuthenticator`; on first login the user (and tenant if none) is
  created/linked.
- Passwords are hashed with **argon2id**.
- Sign-ins are recorded by `Security/EventListener/LoginAuditListener` into the
  audit log.

Cookie-based state-changing requests are CSRF-protected and CORS is locked to the
app origin; auth endpoints are rate-limited (README §11, §16).

## Consequences

- The token is unreachable from JavaScript, removing the primary XSS token-theft
  vector; `SameSite=Lax` plus CSRF protection mitigates cross-site abuse.
- The API stays stateless and horizontally scalable — no server-side session store.
- Refresh rotation with reuse detection limits the blast radius of a leaked refresh
  token.
- Cookie auth requires explicit CSRF handling on mutating requests and a strict CORS
  allow-list; bearer-token clients are not the supported path for the cabinet.
- TOTP 2FA is **Planned** (README §11) and out of scope here.
