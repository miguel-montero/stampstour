<?php
declare(strict_types=1);

require __DIR__ . '/lib/blog-draft-insert.php';

$path = $argv[1] ?? '';
if ($path === '') {
    fwrite(STDERR, "Error: Usage: php insert-blog-draft.php <path-to-draft.md>\n");
    exit(1);
}

require __DIR__ . '/../../db_config.php';

$result = stamp_insert_blog_draft($path, dirname(__DIR__), $conn);

if (!$result['success']) {
    fwrite(STDERR, "Error: {$result['error']}\n");
    exit(1);
}

echo "Inserted draft post #{$result['id']} ({$result['slug']})\n";
echo "Review at: admin/blog-edit.php?id={$result['id']}\n";
