# Configuration

CallLens is configured through environment variables (`.env`, copied from
`.env.example`) plus per-tenant overrides stored in the database. Secrets must
never be committed; in production use the Symfony secrets vault.

> **Looking for where to get the API keys** (Deepgram, OpenAI, Google OAuth,
> Hetzner)? See **[credentials.md](./credentials.md)** — a step-by-step guide to
> registering for each service and which variable to set. This page is the
> reference for *what each variable is*.

`docker compose up` works with the shipped `.env.example` defaults out of the
box — the AI providers default to `fake`, so the full pipeline runs with no paid
calls.

## Environment variables

### App

| Variable | Default (`.env.example`) | Description |
|---|---|---|
| `APP_ENV` | `dev` | Symfony environment (`dev` / `prod` / `test`). The dev container overrides this to `dev`; tests require `test`. |
| `APP_SECRET` | `change_me_in_prod_use_a_32+_char_random_string` | Symfony app secret. Use a 32+ char random string in prod. |
| `APP_URL` | `http://localhost:8081` | Public base URL of the API (served by Nginx). |
| `WEB_URL` | `http://localhost:3001` | Public base URL of the Next.js app (landing / docs / cabinet). |
| `API_PORT` | `8081` | Host port the API Nginx publishes in dev. |
| `WEB_PORT` | `3001` | Host port the Next.js web app publishes in dev. |

### Database (PostgreSQL 17 + pgvector)

| Variable | Default | Description |
|---|---|---|
| `POSTGRES_DB` | `callens` | Database name created by the `db` container. |
| `POSTGRES_USER` | `callens` | Database user. |
| `POSTGRES_PASSWORD` | `callens` | Database password. |
| `DATABASE_URL` | `postgresql://callens:callens@db:5432/callens?serverVersion=17&charset=utf8` | DSN the Symfony app uses. Host `db` is the compose service name. |

### Redis

| Variable | Default | Description |
|---|---|---|
| `REDIS_URL` | `redis://redis:6379` | Redis connection for cache and the Messenger broker. |

### Messenger

Separate transports keep a slow STT provider from starving scoring/embeddings.

| Variable | Default | Description |
|---|---|---|
| `MESSENGER_TRANSPORT_DSN` | `redis://redis:6379/messages` | Default async transport (scoring, embeddings, etc.). |
| `MESSENGER_TRANSCRIBE_DSN` | `redis://redis:6379/transcribe` | Dedicated transcription transport. |
| `MESSENGER_FAILED_DSN` | `doctrine://default?queue_name=failed` | Dead-letter transport for permanently failed messages. |

### Object storage (S3)

MinIO in dev, Hetzner Object Storage in prod.

| Variable | Default | Description |
|---|---|---|
| `S3_ENDPOINT` | `http://minio:9000` | S3 endpoint. MinIO service in dev; Hetzner endpoint in prod. |
| `S3_REGION` | `us-east-1` | S3 region. |
| `S3_BUCKET` | `callens-audio` | Bucket that stores call audio (auto-created in dev by `minio-setup`). |
| `S3_KEY` | `callens` | S3 access key (also the MinIO root user in dev). |
| `S3_SECRET` | `callens-secret` | S3 secret key (also the MinIO root password in dev). |
| `S3_USE_PATH_STYLE` | `true` | Use path-style addressing (required by MinIO). |

### AI providers

`fake` is the **default** for all three and runs the entire pipeline
deterministically with **no paid API calls**. Switch by setting the provider
and the matching API key.

| Variable | Default | Description |
|---|---|---|
| `AI_STT_PROVIDER` | `fake` | Speech-to-text provider: `deepgram` \| `assemblyai` \| `gladia` \| `fake`. |
| `AI_LLM_PROVIDER` | `fake` | LLM scoring provider: `openai` \| `gemini` \| `anthropic` \| `fake`. |
| `AI_EMBEDDINGS_PROVIDER` | `fake` | Embeddings provider: `openai` \| `voyage` \| `fake`. |
| `EMBEDDING_DIM` | `1024` | Embedding vector dimension (must match the model and `Utterance.embedding`). |
| `DEEPGRAM_API_KEY` | _(commented out)_ | Deepgram API key, when `AI_STT_PROVIDER=deepgram`. |
| `OPENAI_API_KEY` | _(commented out)_ | OpenAI API key, for OpenAI LLM/embeddings. |
| `OPENAI_LLM_MODEL` | _(commented out)_ `gpt-4o-mini` | OpenAI LLM model for scoring. |
| `OPENAI_EMBEDDING_MODEL` | _(commented out)_ `text-embedding-3-large` | OpenAI embeddings model. |

### Auth / JWT

| Variable | Default | Description |
|---|---|---|
| `JWT_PASSPHRASE` | `change_me` | Passphrase for the JWT signing key. |
| `JWT_ACCESS_TOKEN_TTL` | `900` | Access token lifetime in seconds (15 min). |
| `JWT_REFRESH_TOKEN_TTL` | `2592000` | Refresh token lifetime in seconds (30 days). |
| `COOKIE_DOMAIN` | `localhost` | Domain for the auth cookie. |
| `COOKIE_SECURE` | `false` | Set `true` in prod (HTTPS-only cookies). |
| `GOOGLE_OAUTH_CLIENT_ID` | _(empty)_ | Google OAuth client ID for Google sign-in. |
| `GOOGLE_OAUTH_CLIENT_SECRET` | _(empty)_ | Google OAuth client secret. |

### Mail

Mailpit captures all outgoing mail in dev (web UI at http://localhost:8025).

| Variable | Default | Description |
|---|---|---|
| `MAILER_DSN` | `smtp://mailpit:1025` | Symfony Mailer DSN. Mailpit SMTP in dev; a transactional provider in prod. |
| `MAIL_FROM` | `no-reply@calllens.app` | Default "from" address for outgoing email. |

### Audio retention

Global defaults; per-tenant overrides live in `Tenant.settings` (see below).

| Variable | Default | Description |
|---|---|---|
| `AUDIO_RETENTION_MODE` | `keep` | Retention strategy: `keep` \| `delete_after_processing` \| `delete_after_days`. |
| `AUDIO_RETENTION_DAYS` | `30` | Days to keep audio when `mode=delete_after_days`. |

### Cube (analytics)

| Variable | Default | Description |
|---|---|---|
| `CUBEJS_API_SECRET` | `dev_cube_secret_change_me` | Secret for signing Cube API tokens. |
| `CUBEJS_DEV_MODE` | `true` | Enables the Cube dev playground (http://localhost:4000). `false` in prod. |

> Cube also receives its database connection from compose
> (`CUBEJS_DB_HOST=db`, `CUBEJS_DB_NAME`/`USER`/`PASS` derived from the
> `POSTGRES_*` variables) — these are wired in `docker-compose.yml`, not set
> directly in `.env`.

## Per-tenant settings

Some configuration is per-workspace rather than global. The `Tenant.settings`
column is a JSONB field that holds overrides resolved at runtime, falling back to
the global env defaults when a key is absent. It currently holds:

- **`audio_retention`** — per-tenant override of the retention policy
  (`mode` and `days`), overriding `AUDIO_RETENTION_MODE` / `AUDIO_RETENTION_DAYS`.
- **`locale`** — the tenant's default locale.
- **default scorecard** — the scorecard applied to new calls when none is
  specified.

Because these are stored in the database per tenant, changing them does not
require recreating containers or editing `.env` — unlike the environment
variables above.
