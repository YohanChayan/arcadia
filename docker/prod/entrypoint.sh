#!/bin/sh
# Every PHP container in compose.prod.yml starts here: arc-app and arc-queue.
#
# Each container has its own filesystem, so each caches config, routes, events and views for
# itself, from the environment compose gave it. A changed .env therefore needs the containers
# recreated (`up -d`), not restarted. Migrations are not run here: they are a deploy step.
set -e

php artisan optimize --no-interaction

exec "$@"
