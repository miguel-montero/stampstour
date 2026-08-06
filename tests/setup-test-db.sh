#!/usr/bin/env bash
# tests/setup-test-db.sh
# Idempotent: creates the test database if missing, (re)imports the schema.
# Safe to re-run any time to reset to a clean structure.
#
# Reads connection details from tests/test_db_config.php (gitignored, real
# local credentials) rather than hardcoding them here - this script is
# committed to git, that file is not. Run this only after copying
# tests/test_db_config.php.example to tests/test_db_config.php and filling
# in your local MySQL user/password.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/test_db_config.php"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "Missing $CONFIG_FILE - copy tests/test_db_config.php.example to tests/test_db_config.php and fill in your local MySQL credentials first." >&2
    exit 1
fi

# Extract $host/$user/$password/$dbname from the gitignored config without
# ever printing or hardcoding the password in this (committed) script.
eval "$(php -r '
    require $argv[1];
    printf("DB_HOST=%s\nDB_USER=%s\nDB_PASS=%s\nDB_NAME=%s\n",
        escapeshellarg($host), escapeshellarg($user), escapeshellarg($password), escapeshellarg($dbname));
' "$CONFIG_FILE")"

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARSET=utf8mb4;"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCRIPT_DIR/schema.sql"

echo "Test database '$DB_NAME' ready."
