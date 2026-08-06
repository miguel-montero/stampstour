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

// PHPUnit loads this bootstrap file from within a method scope, so a plain
// top-level $conn here does not land in PHP's actual global scope (where
// `global $conn;` in test classes looks for it). Register it explicitly.
$GLOBALS['conn'] = $conn;

require_once __DIR__ . '/../includes/reconcile_getnet.php';
