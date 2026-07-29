<?php
// Moved to /admin/consolidate-month.php as part of the admin-tools consolidation.
$target = '/admin/consolidate-month.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $target, true, 301);
exit;
