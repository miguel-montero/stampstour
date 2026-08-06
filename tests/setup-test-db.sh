#!/usr/bin/env bash
# tests/setup-test-db.sh
# Idempotent: creates the test database if missing, (re)imports the schema.
# Safe to re-run any time to reset to a clean structure.
set -euo pipefail

DB_NAME="stampst1_stamptour_test"
DB_USER="stampst1_user"
DB_PASS="D4t"
DB_HOST="localhost"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARSET=utf8mb4;"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCRIPT_DIR/schema.sql"

echo "Test database '$DB_NAME' ready."
