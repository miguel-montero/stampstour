<?php
// One-time migration: travel agent portal schema (vendedores.ta_* columns and
// the ta_code_attempts table). Changes no data. Safe to run more than once.
//
// Visit this file once in a browser while logged into /login.php, confirm
// every line says [ok] or [skip], then DELETE THIS FILE from the server.
// Run it BEFORE deploying the travel agent portal code.

require __DIR__ . '/admin/_auth.php';
require __DIR__ . '/../db_config.php';
require __DIR__ . '/includes/migrations/travel_agents_2026_10_27.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Stamp's Tour migration -- " . date('Y-m-d H:i:s') . "\n\n";

try {
    foreach (migrate_travel_agents($conn) as $line) {
        echo $line . "\n";
    }
    echo "\nDone. Delete this file from the server.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo '[error] ' . $e->getMessage() . "\n";
}
