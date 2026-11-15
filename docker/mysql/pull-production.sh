#!/usr/bin/env bash
# Replaces the local `arcadia` database with a copy of production's.
#
#   ARCADIA_PROD_SSH=<admin ssh alias> docker/mysql/pull-production.sh [--yes] [--keep]
#
# Nothing is installed or left on the server: one SSH session runs mysqldump inside the platform
# MySQL with the app's own credentials (read from arc-app's environment, never printed) and
# streams the dump back gzipped. It needs the admin user, which runs Docker; `agent-ro` cannot
# read every table, so it cannot make a dump.
#
# Locally, from the repository root with the stack up: the database is saved to
# storage/app/db-dumps/arcadia-local-<date>.sql.gz (the five newest kept), then dropped and
# recreated, the dump imported as root, sessions, cache and queued jobs emptied (production's are of no use
# here), migrations newer than production run, and agent_ro's grants re-applied.
#
#   --yes          do not ask before replacing the local database
#   --keep         keep the dump in storage/app/db-dumps/ (gitignored); without it, it is deleted
#   --from <file>  import a dump kept earlier instead of fetching one; no SSH, the file is kept
set -euo pipefail

host="${ARCADIA_PROD_SSH:-yhndev}"
assume_yes=false
keep=false
from=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes) assume_yes=true ;;
        --keep) keep=true ;;
        --from) from="${2:?--from needs a file}"; shift ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
    shift
done

if [[ -n "$from" ]]; then
    from="$(cd "$(dirname "$from")" >/dev/null && pwd)/$(basename "$from")"
    [[ -f "$from" ]] || { echo "No such file: $from" >&2; exit 2; }
elif [[ -z "$host" ]]; then
    echo "Set ARCADIA_PROD_SSH to the server's admin SSH alias (from ~/.ssh/config)." >&2
    exit 2
fi

cd "$(dirname "$0")/../.." >/dev/null

if ! docker compose ps --status running --services | grep -qx mysql; then
    echo "The local stack is not running: docker compose up -d" >&2
    exit 1
fi

if [[ "$assume_yes" != true ]]; then
    read -r -p "This replaces the local 'arcadia' database with production's. Continue? [y/N] " answer
    [[ "$answer" =~ ^[yY]$ ]] || { echo "Nothing changed."; exit 0; }
fi

dump_dir="storage/app/db-dumps"
mkdir -p "$dump_dir"
dump="$dump_dir/arcadia-production-$(date +%Y%m%d-%H%M%S).sql.gz"
if [[ -n "$from" ]]; then
    dump="$from"
    keep=true
fi

# A failed run keeps the dump: after the drop it is the only copy left to import again.
cleanup() {
    local status=$?
    if [[ $status -ne 0 && -s "$dump" ]]; then
        echo "Dump kept at $dump (import it with --from)." >&2
    elif [[ "$keep" != true ]]; then
        rm -f "$dump"
    fi
}
trap cleanup EXIT

if [[ -z "$from" ]]; then
echo "→ Dumping production over SSH ($host)…"
ssh "$host" 'bash -s' > "$dump" <<'REMOTE'
set -euo pipefail
# Anything printed here would land in the dump: cd says nothing.
cd /srv/apps/arcadia >/dev/null
user="$(docker compose -f compose.prod.yml exec -T arc-app printenv DB_USERNAME)"
pass="$(docker compose -f compose.prod.yml exec -T arc-app printenv DB_PASSWORD)"
db="$(docker compose -f compose.prod.yml exec -T arc-app printenv DB_DATABASE)"
# Passed by name from the environment, so the password never appears in the host's process list.
MYSQL_PWD="$pass" docker exec -e MYSQL_PWD platform-mysql-1 \
    mysqldump -u"$user" --single-transaction --quick --no-tablespaces --routines --triggers \
        --set-gtid-purged=OFF --default-character-set=utf8mb4 "$db" \
    | gzip
REMOTE
fi

# An empty or cut-off stream would otherwise drop the local data for nothing.
if ! gzip -t "$dump" 2>/dev/null || ! gzip -dc "$dump" | tail -n 1 | grep -q 'Dump completed'; then
    echo "The dump is empty or incomplete; the local database was not touched." >&2
    keep=true
    exit 1
fi
echo "   $(du -h "$dump" | cut -f1) compressed."

# Root inside the local container; the password from its own environment, so no warning on stderr.
mysql_root() {
    docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --default-character-set=utf8mb4 "$@"' -- "$@"
}

# The local database is never dropped unsaved: it goes to a file first, checked like the dump,
# and only the five newest of those are kept. Restore one with --from.
has_local="$(mysql_root -N -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'arcadia'")"
if [[ "$has_local" == 1 ]]; then
    backup="$dump_dir/arcadia-local-$(date +%Y%m%d-%H%M%S).sql.gz"
    echo "→ Backing up the local database to $backup…"
    docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --quick --no-tablespaces --routines --triggers --set-gtid-purged=OFF --default-character-set=utf8mb4 arcadia' \
        | gzip > "$backup"
    if ! gzip -dc "$backup" | tail -n 1 | grep -q 'Dump completed'; then
        echo "The local backup is incomplete; nothing was replaced." >&2
        exit 1
    fi
    ls -1t "$dump_dir"/arcadia-local-*.sql.gz | tail -n +6 | while read -r old; do rm -f "$old"; done
fi

echo "→ Recreating the local database…"
mysql_root -e "DROP DATABASE IF EXISTS arcadia; CREATE DATABASE arcadia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "→ Importing…"
gzip -dc "$dump" | mysql_root arcadia

echo "→ Emptying sessions, cache and queued jobs…"
existing="$(mysql_root -N arcadia -e "SELECT table_name FROM information_schema.tables WHERE table_schema = 'arcadia' AND table_name IN ('sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs')")"
for table in $existing; do
    mysql_root arcadia -e "TRUNCATE TABLE \`$table\`;"
done

echo "→ Running migrations newer than production…"
docker compose exec -T app php artisan migrate --force

echo "→ Re-applying agent_ro's grants…"
docker compose exec -T mysql agent-ro-grants < database/agent-access.json

docker compose exec -T app php artisan optimize:clear >/dev/null

if [[ -n "$from" ]]; then
    echo "✓ Local database replaced from $dump."
else
    echo "✓ Local database is a copy of production."
fi
if [[ "$keep" == true ]]; then
    echo "  Dump kept at $dump"
fi
if [[ -n "${backup:-}" ]]; then
    echo "  Your previous local database: $backup (restore it with --from)"
fi
