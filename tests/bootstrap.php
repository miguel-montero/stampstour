<?php
// tests/bootstrap.php
// PHPUnit bootstrap: makes the test DB connection and the code under test
// available to every test class.
declare(strict_types=1);

$testDbConfig = __DIR__ . '/test_db_config.php';
if (!file_exists($testDbConfig)) {
    fwrite(STDERR, "Missing tests/test_db_config.php - copy tests/test_db_config.php.example and run tests/setup-test-db.sh first.\n");
    exit(1);
}
require $testDbConfig; // defines $conn (mysqli), connected to the TEST database

// Align the MySQL session clock with PHP's clock. php.ini pins PHP to
// date.timezone=UTC, but the DB server's NOW()/CURRENT_TIMESTAMP follow the
// OS timezone, which can differ (e.g. this host is America/Santiago). Tests
// that compare PHP-computed timestamps (e.g. `received_at`) against
// MySQL's NOW() - INTERVAL ... need both clocks to agree, so pin this
// session to UTC too. Doesn't affect production (db_config.php is separate
// and untouched).
$conn->query("SET time_zone = '+00:00'");

// Safety check: refuse to run if $conn isn't pointed at a database whose
// name ends in "_test". Every test's setUp()/tearDown() runs an
// unconditional DELETE FROM reservas, so a misconfigured
// tests/test_db_config.php (e.g. a typo pointing at the real local dev
// database) would otherwise silently wipe real data on the next test run.
$dbNameRow = $conn->query('SELECT DATABASE()')->fetch_row();
$currentDbName = $dbNameRow[0] ?? '';
if (!str_ends_with($currentDbName, '_test')) {
    fwrite(STDERR, "Refusing to run: tests are connected to '$currentDbName', which doesn't look like a test database (expected a name ending in '_test'). Check tests/test_db_config.php.\n");
    exit(1);
}

// PHPUnit loads this bootstrap file from within a method scope, so a plain
// top-level $conn here does not land in PHP's actual global scope (where
// `global $conn;` in test classes looks for it). Register it explicitly.
$GLOBALS['conn'] = $conn;

require_once __DIR__ . '/../includes/reconcile_getnet.php';
require_once __DIR__ . '/../includes/reprocess_paypal_events.php';
require_once __DIR__ . '/../includes/hotel_resolver.php';
