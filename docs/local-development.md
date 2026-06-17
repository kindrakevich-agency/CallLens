# Local Development

This guide covers running CallLens locally. The entire stack runs in Docker —
**no local PHP or Node installation is required**.

## Prerequisites

- **Docker** with **Docker Compose v2** (`docker compose`, not the legacy
  `docker-compose`).

That's the only prerequisite. PHP 8.5, Node 22, PostgreSQL, Redis, Cube, MinIO,
and Mailpit all run as containers. Every application command (Composer, Symfony
console, npm, PHPUnit, etc.) is executed **inside** a container via the
`Makefile`, so you never install language runtimes on your host.

## First-time setup

```bash
make init     # copies .env.example → .env (if missing) and builds all images
make up       # starts the full dev stack in the background
```

`make init` is idempotent: it only copies `.env.example` to `.env` when `.env`
does not already exist, then runs `docker compose build`.

`docker compose up` automatically loads `docker-compose.override.yml`, which adds
the local-dev conveniences: live code bind-mounts (`apps/api` and `apps/web`),
the Xdebug-enabled `dev` image targets, the `minio` + `mailpit` services, and
published ports for the data services.

After the stack is up, initialize the database:

```bash
make migrate  # run Doctrine migrations
make seed     # seed a demo tenant, users, scorecard, and sample calls
```

## Service URLs (dev)

The dev host ports are **8081** (API) and **3001** (web) — see Troubleshooting
for why these differ from the usual 8080/3000. They are driven by `API_PORT` and
`WEB_PORT` in `.env`.

| Service | URL | Notes |
|---|---|---|
| API (Nginx) | http://localhost:8081 | Symfony API; health at `/internal/health` |
| Web (Next.js) | http://localhost:3001 | Landing `/`, docs `/docs`, cabinet `/app` |
| Cube playground + API | http://localhost:4000 | Dev mode (`CUBEJS_DEV_MODE=true`) |
| MinIO console | http://localhost:9001 | Login `callens` / `callens-secret` |
| Mailpit (captured email) | http://localhost:8025 | Web UI for outgoing mail |

Other published dev ports (for debugging, on the host): MinIO S3 API `9000`,
PostgreSQL `5432`, Redis `6379`, Mailpit SMTP `1025`. In production none of the
data-service ports are published — only the API and web ports, bound to
loopback behind aaPanel-managed Nginx.

AI providers default to `fake` (`AI_STT_PROVIDER`, `AI_LLM_PROVIDER`,
`AI_EMBEDDINGS_PROVIDER`), so the whole processing pipeline runs deterministically
with **no paid API calls**. Set real provider keys in `.env` to switch.

## Make targets

All targets run their commands inside containers. Run `make help` to list them.

| Target | Description |
|---|---|
| `make help` | Show all available targets |
| `make init` | First-time setup: copy `.env` and build images |
| `make up` | Start the full dev stack |
| `make down` | Stop the stack |
| `make restart` | Restart the stack (`down` then `up`) |
| `make logs` | Tail logs (scope with `S=`, e.g. `make logs S=worker`) |
| `make ps` | Show service status |
| `make sh` | Open a bash shell in the `api` container |
| `make migrate` | Run Doctrine migrations |
| `make fixtures` | Load Doctrine fixtures |
| `make seed` | Seed a demo tenant, users, scorecard, and sample calls |
| `make test` | Run backend (PHPUnit) + frontend (npm) tests |
| `make test-db` | Create and migrate the test database |
| `make lint` | Run static analysis + linters (PHPStan, php-cs-fixer, ESLint) |
| `make deploy` | Run the production deploy script (`scripts/deploy.sh`) |

## Running tests

**Important:** the `api` container runs with `APP_ENV=dev` (set in
`docker-compose.override.yml`). Tests must run under `APP_ENV=test` so they use
the test database (`callens_test`) rather than the dev database. The `make`
targets handle this for you by passing `-e APP_ENV=test` to each command — do not
run the bare PHPUnit/console commands without it.

First time (or after new migrations), create and migrate the test database:

```bash
make test-db
```

This runs, with `APP_ENV=test`:

- `doctrine:database:create --if-not-exists` (creates `callens_test`)
- `doctrine:migrations:migrate --no-interaction`

Then run the suite:

```bash
make test
```

`make test` runs the backend tests with `docker compose exec -e APP_ENV=test api
php bin/phpunit`, then the frontend tests with `docker compose exec web npm test`.

## Troubleshooting

**Changed an env var but the app doesn't see it?**
`env_file` is read **only when the container starts** — editing `.env` does not
hot-reload into a running container. Recreate the affected container:

```bash
docker compose up -d api
```

(Do the same for `worker` / `scheduler` if they consume the changed variable.)

**Changed a message handler and the worker still runs the old code?**
The worker is a long-running process that loads handler code at boot. Restart it
so it picks up the change:

```bash
docker compose restart worker
```

**Ports 8081 / 3001 instead of 8080 / 3000?**
These were deliberately chosen because **8080 and 3000 were already taken** on
the typical dev machine. They are configurable via `API_PORT` and `WEB_PORT` in
`.env` if you need different values; the base compose files default to 8080/3000,
but `.env.example` ships with 8081/3001.

**Database not ready / connection refused on startup?**
The `db` service has a healthcheck and dependent services wait for
`service_healthy`. Give it a few seconds on first boot; check with
`make ps` and `make logs S=db`.
