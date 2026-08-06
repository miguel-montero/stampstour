<?php
// includes/cron_reprocess_paypal.php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$LOCK = sys_get_temp_dir() . '/cron_reprocess_paypal.lock';
$fh = fopen($LOCK, 'c');
if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) { exit; } // avoid overlap

require __DIR__ . '/../../db_config.php'; // defines $conn (mysqli)
require __DIR__ . '/reprocess_paypal_events.php';

$paypalConfig = require __DIR__ . '/../../paypal_config.php';

$result = reprocess_paypal_stuck_events($conn, $paypalConfig, 50);

error_log(sprintf(
    "cron_reprocess_paypal: checked=%d reprocessed=%d failed=%d",
    $result['checked'],
    $result['reprocessed'],
    $result['failed']
));
