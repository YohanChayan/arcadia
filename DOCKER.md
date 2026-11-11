# Running Arcadia in Docker

One image, two targets (`Dockerfile`):

| Target | Used by | Code |
|---|---|---|
| `dev` | `docker-compose.yml`, on your machine | mounted — an edit is live |
| `prod` | `compose.prod.yml`, on the server: `arc-app`, `arc-queue` | baked in, with the built client and SSR bundles |
| `web` | `compose.prod.yml`, on the server: nginx | `public/` only, copied from `prod` |
| `ssr` | `compose.prod.yml`, on the server: `arc-ssr` | the SSR bundle and its runtime `node_modules` |

Both start from `base`: PHP 8.5.12 (what production runs) with `pdo_mysql`, `pdo_pgsql`, `intl`, `gd`
(JPEG, WebP, FreeType), `exif`, `zip`, `bcmath`, `pcntl`, production's upload limits, Composer and
Node 22. Node is in the image because Wayfinder's Vite plugin runs `php artisan`, and the SSR
server needs it at runtime.

## The local stack

| Service | What it does | On your machine |
|---|---|---|
| `app` | PHP-FPM | — |
| `nginx` | serves `public/`, hands PHP to `app` | http://localhost:8083 |
| `queue` | `queue:listen --queue=arcadia` — every mail is a queued job | — |
| `node` | Vite and builds, idle until you exec into it | Vite on 5174 |
| `mysql` | MySQL 8.0, as production | 3311 |
| `pgvector` | Postgres 18 with pgvector — the vector store, nothing else | 5433 |
| `ssr` | the server render of guests' pages — profile `ssr` | — |
| `mailpit` | catches mail — profile `mail` | http://localhost:8026 |

The ports are offset from yhn-dev-side's stack (8082, 3310, 5173), so both run at once.

The PHP containers take their database, host and mail settings from `docker-compose.yml`
(`x-app-env`), not from `.env` — a real environment variable wins over `.env`. So `.env` only
needs an `APP_KEY`, and stays usable outside Docker.

## First run

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec mysql agent-ro-grants
docker compose exec node npm ci
docker compose exec node npm run build:ssr
docker compose --profile ssr up -d
```

Then http://localhost:8083. For hot reload instead of a build: `docker compose exec node npm run dev`.

## What the seed gives you

`migrate --seed` creates the owner (`UserSeeder`, yhndevside@gmail.com) plus the roles, Google,
the settings, the starter phrases and prompts. Locally (`APP_ENV=local`) it also adds fake data (`DemoSeeder`):

| | |
|---|---|
| Guides | Spanish: one published and open for enrollment, one published with two readers, one draft. English: one published and open |
| Readers | `ana@example.com` and `bob@example.com`, password `password` |
| Requests | two in the editor's inbox, and one access request |

Start over with `docker compose exec app php artisan migrate:fresh --seed`, then
`docker compose exec mysql agent-ro-grants` — the tables are new, and so must be their grants.

Tests run on in-memory SQLite wherever you run them — `php artisan test` on your machine or
`docker compose exec app php artisan test`. `phpunit.xml` forces that over the stack's MySQL,
which `RefreshDatabase` would otherwise wipe. A test that needs the vector store uses its own
database, `arcadia_vectors_testing`, never the index (`phpunit.xml`).

## The vector store

Guides live in MySQL. The knowledge index — what the AI may know about them — lives in Postgres
with pgvector, a second connection named `vectors` (`config/database.php`, `VECTOR_DB_*`). It is
derived data, rebuilt from MySQL at any time, so it is never backed up.

On the volume's first start, `docker/pgvector/01-databases.sql` creates `arcadia_vectors_testing`
beside the index and enables pgvector in both. Its tables have their own migrations, never run
by a plain `migrate`:

```bash
docker compose exec app php artisan migrate --database=vectors --path=database/migrations/vectors
docker compose exec app php artisan knowledge:rebuild      # needs GEMINI_API_KEY
```

A release, or a change to what a guide promises, queues that guide's reindexing on the
`knowledge` queue, which the worker takes after mail. Outside Docker, `.env`'s `VECTOR_DB_*` point at
127.0.0.1:5433. To start it over: `docker compose rm -sf pgvector && docker volume rm
arcadia_arcadia_pgvector_data`.

## The MySQL accounts

The same three as production, created on the volume's first start (`docker/mysql/01-users.sh`):

| Account | May |
|---|---|
| `root` | everything (locally with a password; on the server, the socket only) |
| `arcadia_app` | everything on the `arcadia` database — the application's user |
| `agent_ro` | `SELECT` on exactly what `database/agent-access.json` allows: every `read` table, and only the `allow` columns of a `columns` table |

`agent-ro-grants` grants what the manifest allows and revokes everything else, so a table the
manifest does not classify stays unreadable, and a missing or malformed manifest revokes it all.
The suite fails until every migrated table is classified (`AgentAccessManifestTest`).
**Run `docker compose exec -T mysql agent-ro-grants < database/agent-access.json` after every
`migrate` that adds a table, and after editing the manifest.** The script is the local twin of
the server's `/usr/local/sbin/agent-ro-grants` (yhn-infra), which reads each project's checkout
every 15 minutes, so on the server a new table becomes readable once its classification is deployed.

The scripts run only when the volume is new. To start the database over:
`docker compose down -v` (it deletes the data).

## Production

`compose.prod.yml` runs Arcadia on a host that already provides the shared platform: **one
Caddy** (TLS, on the external `edge` network) and **one MySQL** (on the external `db` network),
each project in its own stack under `/srv/apps/<project>`. It has the same shape as
yhn-dev-side's, which runs beside it.

```
  Caddy (platform) ──edge──► arcadia-web (nginx: public/, /build cached a year)
                                  │ fastcgi
                                  ▼
                              arc-app (PHP-FPM) · arc-queue ──db──► platform MySQL
                                  │ http :13714
                                  ▼
                              arc-ssr (node bootstrap/ssr/ssr.js)

                              arc-app · arc-queue ──internal──► arc-vectors (Postgres + pgvector)
```

All three images come from the one `Dockerfile`, by target. The code, `vendor/` and both bundles
are baked in; nothing is bind-mounted. Only `storage/` is a volume, shared by the PHP containers
and mounted read-only into nginx for `public/storage`.

What to know about it:

- **No `ports:` anywhere.** Docker bypasses the host firewall, so a published port is public.
  Caddy reaches `arcadia-web` by container name over `edge`; no other service joins `edge`.
- **Service names are prefixed** (`arc-app`, …). Every stack on the host shares `edge` and `db`,
  and a service name is a DNS name on every network it joins — two stacks with an `app` would
  answer for each other.
- **The database host is `platform-mysql-1`**, the container's name, not the `mysql` alias,
  which another stack could also claim.
- **Configuration is `.env` through `env_file`**, copied from `docker/prod/env.example`. Compose
  reads it, not Laravel's dotenv: write values literally, never `${OTHER}`. It is read when a
  container is **created**, so after editing it run `up -d`, not `restart`.
- **Each PHP container caches for itself.** `docker/prod/entrypoint.sh` runs `php artisan
  optimize` on every start, because app and queue have separate filesystems.
- **All PHP containers run as `www-data`**, so `docker compose exec` does too. Migrations are a
  deploy step, never run on start.
- **The vector store is Arcadia's own** (`arc-vectors`, on `internal` only), not the platform's,
  which is MySQL. Compose creates its database and user from `.env`'s `VECTOR_DB_*` on the
  volume's first start, and refuses to start without `VECTOR_DB_PASSWORD`. The index is rebuilt
  from MySQL, so a lost volume costs a rebuild, not data.
- **Cache, sessions and queue are the database**: the PHP containers share them without Redis.
- **No scheduler**: Arcadia schedules nothing. Add an `arc-scheduler` like the studio's
  `yhn-scheduler` the day `routes/console.php` schedules something.
- **Behind the proxy** the app trusts `X-Forwarded-*` from private addresses only
  (`bootstrap/app.php`). Without that, every link it builds would be `http://`, and every
  visitor would share Caddy's address for rate limiting.
- Images are built **on the server**. The Vite build is the heaviest step; on a small host,
  build while traffic is low.

### First deploy

The database and its user are created once in the platform MySQL, as root:

```sql
CREATE DATABASE arcadia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'arcadia'@'%' IDENTIFIED BY '…';
GRANT ALL PRIVILEGES ON arcadia.* TO 'arcadia'@'%';
```

Then, in a checkout of this repository at `/srv/apps/arcadia`:

```bash
cp docker/prod/env.example .env && chmod 600 .env        # fill it in; APP_KEY below

docker compose -f compose.prod.yml build
docker compose -f compose.prod.yml run --rm --no-deps --entrypoint php arc-app artisan key:generate --show
#   paste the key into .env as APP_KEY

docker compose -f compose.prod.yml up -d
docker compose -f compose.prod.yml exec arc-app php artisan migrate --force --seed
docker compose -f compose.prod.yml exec arc-app php artisan migrate --force --database=vectors --path=database/migrations/vectors
docker compose -f compose.prod.yml exec arc-app php artisan knowledge:rebuild
```

**`UserSeeder` creates the owner with a password committed to this repository.** Change it
before the host is reachable.

The Caddy site file is `docker/prod/caddy/<host>.caddy`: it goes into the platform's
`caddy/sites/` once DNS points at the host, followed by a Caddy reload.

### Updating

Bring the checkout to the new commit, then:

```bash
docker compose -f compose.prod.yml build
docker compose -f compose.prod.yml up -d
docker compose -f compose.prod.yml exec arc-app php artisan migrate --force
docker compose -f compose.prod.yml exec arc-app php artisan migrate --force --database=vectors --path=database/migrations/vectors
```

`up -d` recreates every container whose image changed, which re-runs `optimize` in each.

## Traps

- **PHP 8.5 has OPcache built in.** Listing `opcache` in `docker-php-ext-install` fails the
  build.
- **nginx re-resolves `app` every 10 seconds** (`docker/nginx/default.conf`). A name resolved
  once at startup keeps the address of a container that has since been recreated — a 502.
- **The SSR bundle is not self-contained.** It imports `vue`, `@inertiajs/vue3` and the other
  runtime dependencies from `node_modules`, so `ssr` carries them, pruned of dev dependencies.
  Moving a package the SSR imports into `devDependencies` breaks the server render in production
  only.
- **Locally, the production stack and this one share the project name `arcadia`** (this one
  takes the folder's name). `compose.prod.yml` is for the server; run it here only from another
  directory, or the two stacks list as one.
- **`node_modules` lives in a volume**, not your machine's folder: the native binaries of
  Rolldown, Lightning CSS and Tailwind are per platform, and a macOS install cannot load in Linux.
