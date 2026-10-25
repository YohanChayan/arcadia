# Running Arcadia in Docker

One image, two targets (`Dockerfile`):

| Target | Used by | Code |
|---|---|---|
| `dev` | `docker-compose.yml`, on your machine | mounted — an edit is live |
| `prod` | the server's compose file | baked in, with the built client and SSR bundles |

Both start from `base`: PHP 8.5.12 (what production runs) with `pdo_mysql`, `intl`, `gd`
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
which `RefreshDatabase` would otherwise wipe.

## The MySQL accounts

The same three as production, created on the volume's first start (`docker/mysql/01-users.sh`):

| Account | May |
|---|---|
| `root` | everything (locally with a password; on the server, the socket only) |
| `arcadia_app` | everything on the `arcadia` database — the application's user |
| `agent_ro` | `SELECT` on every table except `users` |

MySQL cannot grant a whole database minus one table, so `agent_ro` is granted table by table:
**run `docker compose exec mysql agent-ro-grants` after every `migrate` that adds a table.**
The script is the local twin of the server's `/usr/local/sbin/agent-ro-grants`.

The scripts run only when the volume is new. To start the database over:
`docker compose down -v` (it deletes the data).

## Traps

- **PHP 8.5 has OPcache built in.** Listing `opcache` in `docker-php-ext-install` fails the
  build.
- **nginx re-resolves `app` every 10 seconds** (`docker/nginx/default.conf`). A name resolved
  once at startup keeps the address of a container that has since been recreated — a 502.
- **`node_modules` lives in a volume**, not your machine's folder: the native binaries of
  Rolldown, Lightning CSS and Tailwind are per platform, and a macOS install cannot load in Linux.
