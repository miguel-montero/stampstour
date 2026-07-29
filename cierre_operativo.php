<?php
// Moved to /admin/closing.php as part of the admin-tools consolidation.
$target = '/admin/closing.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $target, true, 301);
exit;
