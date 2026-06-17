# Webhook Contract — `POST /v1/webhooks/calls`

CallLens ingests calls via a **public, HMAC-signed** webhook. The endpoint is
reachable from the internet but accepts a request only if it carries a valid
signature for a known, active endpoint and a fresh timestamp. Ingestion is
**asynchronous**: a verified request returns `202 Accepted` in milliseconds, and
the audio is downloaded and processed in the background.

- **Method / path:** `POST /v1/webhooks/calls`
- **Content type:** `application/json`
- **Auth:** HMAC-SHA256 signature only (no bearer token / cookie)
- **Idempotent by:** `(tenant, call_id)`
- **Replay window:** 300 seconds

---

## Required headers

| Header | Value | Notes |
|---|---|---|
| `X-CallLens-Endpoint` | Endpoint **UUID** | Identifies the registered webhook endpoint. Must be a valid UUID and resolve to an **active** endpoint. The tenant and the endpoint signing secret are derived from it. |
| `X-CallLens-Signature` | `sha256=<hmac>` | Lowercase hex HMAC, prefixed with `sha256=`. See signing below. |
| `X-CallLens-Timestamp` | RFC 3339 / ISO 8601 timestamp, e.g. `2026-06-17T09:14:02Z` | Parsed with `strtotime`; must be within the replay window of the server clock. The timestamp is **bound into the signature**. |
| `Content-Type` | `application/json` | The body is parsed as JSON. |

---

## Signature computation

The signature covers **both the timestamp and the raw body**, so the timestamp
cannot be swapped for a forged-fresh one after the fact:

```
signed_payload = timestamp + "." + rawBody
signature      = "sha256=" + lowerhex( HMAC_SHA256( signed_payload, endpoint_signing_secret ) )
```

- `timestamp` is the **exact string** sent in `X-CallLens-Timestamp`.
- `rawBody` is the **exact bytes** of the request body — sign before any
  re-serialization, pretty-printing, or key reordering.
- `endpoint_signing_secret` is the per-endpoint secret shown in the cabinet
  (Settings → integrations/webhook), where it can also be regenerated.
- Verification uses a constant-time comparison (`hash_equals`).

> **Difference from README §23.** The README appendix example describes the
> signature as "HMAC-SHA256 of the raw body using the endpoint secret." The
> **actual** implementation signs `timestamp + "." + rawBody` (the timestamp is
> bound). This is a deliberate hardening improvement over the example: it
> prevents an attacker from reusing a captured signature with a refreshed
> timestamp to slip past the replay window. This document describes the real
> behavior — sign `timestamp + "." + rawBody`.

### Bash (openssl) example

```bash
#!/usr/bin/env bash
set -euo pipefail

ENDPOINT_ID="00000000-0000-0000-0000-000000000000"   # X-CallLens-Endpoint UUID
SECRET="whsec_your_endpoint_signing_secret"
URL="https://api.calllens.app/v1/webhooks/calls"

# Exact bytes you will send as the body. Keep this string verbatim.
BODY='{"call_id":"c_8421","recording_url":"https://.../rec.mp3","agent_id":"mgr_17","channels":"dual","language":"auto","started_at":"2026-06-17T09:14:02Z","duration_sec":312}'

TS="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

# signed_payload = timestamp + "." + rawBody
SIG="sha256=$(printf '%s' "${TS}.${BODY}" \
  | openssl dgst -sha256 -hmac "$SECRET" -binary \
  | xxd -p -c 256)"

curl -sS -X POST "$URL" \
  -H "Content-Type: application/json" \
  -H "X-CallLens-Endpoint: $ENDPOINT_ID" \
  -H "X-CallLens-Timestamp: $TS" \
  -H "X-CallLens-Signature: $SIG" \
  --data-raw "$BODY"
```

> Use `--data-raw` (not `--data`) so curl sends the body byte-for-byte and your
> signature matches.

### Pseudo-code

```text
ts        = now_utc_iso8601()              # e.g. "2026-06-17T09:14:02Z"
rawBody   = serialize(payload)             # the exact bytes you will send
mac       = hmac_sha256(secret, ts + "." + rawBody)
signature = "sha256=" + to_lower_hex(mac)

POST /v1/webhooks/calls
  X-CallLens-Endpoint:  <endpoint-uuid>
  X-CallLens-Timestamp: ts
  X-CallLens-Signature: signature
  Content-Type:         application/json
  body = rawBody
```

---

## Replay protection

- The timestamp must be within **300 seconds** of server time (`abs(now - ts) <= 300`).
- A missing or unparseable timestamp is rejected.
- Because the timestamp is part of the signed payload, a captured request cannot
  be replayed with a refreshed timestamp without invalidating the signature.

## Idempotency

Calls are deduplicated by **`(tenant, call_id)`** (the tenant comes from the
endpoint; `call_id` is the request's `external_id`). Re-delivering the same
`call_id` is safe: the existing call is returned, no new processing is enqueued,
and the response carries `"duplicate": true`. Background download/processing is
dispatched **only on first creation**.

---

## Request payload schema

```json
{
  "call_id": "c_8421",
  "recording_url": "https://.../rec.mp3",
  "agent_id": "mgr_17",
  "channels": "dual",
  "language": "auto",
  "started_at": "2026-06-17T09:14:02Z",
  "duration_sec": 312
}
```

| Field | Type | Required | Default | Notes |
|---|---|---|---|---|
| `call_id` | string | **Yes** | — | Your external call identifier. Empty/missing → `422`. Dedup key together with the tenant. |
| `recording_url` | string (URL) | Recommended | `""` | Where CallLens downloads the audio asynchronously. Used only when the call is newly created. |
| `agent_id` | string | No | none | External agent id; an agent is auto-created on first sight. |
| `channels` | `"mono"` \| `"dual"` | No | `"dual"` | Any value other than `"mono"` is treated as `"dual"`. **Dual-channel is recommended** — separating agent and customer audio yields much better diarization and scoring accuracy. |
| `language` | string | No | `"auto"` | BCP-47-ish code or `"auto"` for auto-detection. |
| `started_at` | string (RFC 3339) | No | none | Call start time. Unparseable values are stored as `null`. |
| `duration_sec` | integer | No | none | Call duration in seconds. |

---

## Responses

### `202 Accepted` — verified and queued

```json
{
  "status": "accepted",
  "call_id": "c_8421",
  "duplicate": false
}
```

`duplicate` is `true` when the `(tenant, call_id)` pair already existed; in that
case no new processing is enqueued.

### `401 Unauthorized` — rejected at the signature gate

Returned as `{"error": "<reason>"}` for any of:

| Reason | Trigger |
|---|---|
| `Unknown endpoint.` | `X-CallLens-Endpoint` is not a valid UUID, or it does not resolve to an **active** endpoint. |
| `Stale or missing timestamp.` | `X-CallLens-Timestamp` is missing, unparseable, or outside the 300s window. |
| `Invalid signature.` | `X-CallLens-Signature` does not match the expected `sha256=<hmac>` over `timestamp + "." + rawBody`. |

### `422 Unprocessable Entity` — passed the gate, bad payload

Returned as `{"error": "<reason>"}`:

| Reason | Trigger |
|---|---|
| `Invalid JSON.` | Body is not valid JSON. |
| `call_id is required.` | Parsed body has an empty/missing `call_id`. |

---

## Quick checklist for integrators

1. Register a webhook endpoint in the cabinet; copy the **endpoint UUID** and the
   **signing secret**.
2. For each call, build the JSON body and a fresh UTC timestamp.
3. Sign `timestamp + "." + rawBody` with HMAC-SHA256; prefix with `sha256=`.
4. Send the three `X-CallLens-*` headers plus `Content-Type: application/json`.
5. Prefer **dual-channel** recordings and provide a reachable `recording_url`.
6. Treat `202` with `"duplicate": true` as success (idempotent re-delivery).
