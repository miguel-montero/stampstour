<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM blog_posts WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: blog.php?deleted=1');
exit;
