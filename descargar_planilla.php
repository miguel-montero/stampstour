<?php
// Moved to /admin/consolidate-day.php as part of the admin-tools consolidation.
$target = '/admin/consolidate-day.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $target, true, 301);
exit;
