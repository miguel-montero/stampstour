<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require __DIR__ . '/vendor/autoload.php';
$cfg = require __DIR__ . '/includes/mailer_config.php';
require __DIR__ . '/includes/Mailer.php';

$ok = send_booking_email('YOUR@TEST.EMAIL', 'You', 'SMTP test', '<b>SMTP works</b>');
echo $ok ? 'OK (mail sent)' : 'FAIL (check /storage/php_errors.log)';
