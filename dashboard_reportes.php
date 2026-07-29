<?php
// Moved to /admin/dashboard.php as part of the admin-tools consolidation.
$target = '/admin/dashboard.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $target, true, 301);
exit;
