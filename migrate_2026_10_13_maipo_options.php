<?php
// One-time migration: Maipo tour options (Classic / Classic + Lunch / Premium).
// Repurposes experiencias 'Maipo' as Premium ($139) and adds MAIPO_CLASSIC
// ($110) and MAIPO_CLASSIC_LUNCH ($129). Safe to run more than once.
//
// Visit this file once in a browser while logged into /login.php, confirm
// every line says [ok] or [skip], then DELETE THIS FILE from the server.
// Run it BEFORE deploying the new maipo-valley-wine-tour-santiago.php.

require __DIR__ . '/admin/_auth.php';
require __DIR__ . '/../db_config.php';
require __DIR__ . '/includes/migrations/maipo_options_2026_10_13.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Stamp's Tour migration -- " . date('Y-m-d H:i:s') . "\n\n";

try {
    foreach (migrate_maipo_options($conn) as $line) {
        echo $line . "\n";
    }
    echo "\nDone. Delete this file from the server.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo '[error] ' . $e->getMessage() . "\n";
}
