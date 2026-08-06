#!/usr/bin/env bash
# tests/setup-test-env.sh
# Downloads a pinned PHPUnit .phar into tests/tools/ (gitignored). No
# composer involvement - keeps the test framework itself out of the
# repo's own committed vendor/, which ships to production on every
# git pull deploy.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLS_DIR="$SCRIPT_DIR/tools"
PHPUNIT_VERSION="11.4.4"
PHPUNIT_URL="https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar"

mkdir -p "$TOOLS_DIR"
curl -sSL -o "$TOOLS_DIR/phpunit.phar" "$PHPUNIT_URL"
chmod +x "$TOOLS_DIR/phpunit.phar"
php "$TOOLS_DIR/phpunit.phar" --version
