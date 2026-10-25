#!/bin/bash
# Runs once, the first time the MySQL volume is created (docker-entrypoint-initdb.d).
#
# The same three accounts as production:
#   root         the default admin (locally with a password; on the server, socket only)
#   arcadia_app  the application's own user: everything, on the `arcadia` database only
#   agent_ro     read-only, for agents — SELECT on every table except `users`, granted
#                table by table by `agent-ro-grants` once the tables exist
set -euo pipefail

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'%' IDENTIFIED WITH caching_sha2_password BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${DB_USERNAME}'@'%';

CREATE USER IF NOT EXISTS 'agent_ro'@'%' IDENTIFIED WITH caching_sha2_password BY '${AGENT_RO_PASSWORD}';

FLUSH PRIVILEGES;
SQL
