<?php
// includes/cron_reconcile_getnet.php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$LOCK = sys_get_temp_dir() . '/cron_reconcile_getnet.lock';
$fh = fopen($LOCK, 'c');
if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) { exit; } // avoid overlap

require __DIR__ . '/../../db_config.php'; // defines $conn (mysqli)
require __DIR__ . '/reconcile_getnet.php';

$result = reconcile_getnet_pending($conn, 50);

error_log(sprintf(
    "cron_reconcile_getnet: checked=%d corrected=%d",
    $result['checked'],
    $result['corrected']
));
