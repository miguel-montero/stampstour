<?php
declare(strict_types=1);

require __DIR__ . '/lib/draft-parser.php';
require __DIR__ . '/../includes/blog-slug.php';

function stamp_insert_fail(string $message): void {
    fwrite(STDERR, "Error: $message\n");
    exit(1);
}

$path = $argv[1] ?? '';
if ($path === '') {
    stamp_insert_fail('Usage: php insert-blog-draft.php <path-to-draft.md>');
}
if (!is_file($path)) {
    stamp_insert_fail("Draft file not found: $path");
}

try {
    $parsed = stamp_parse_draft_file($path);
} catch (RuntimeException $e) {
    stamp_insert_fail($e->getMessage());
}

$fields = $parsed['fields'];
$bodyHtml = stamp_markdown_to_html($parsed['body']);

if (str_contains($bodyHtml, '[IMAGE:')) {
    stamp_insert_fail('Draft still contains unreplaced [IMAGE: ...] placeholder(s) in the body.');
}

$missingFields = stamp_missing_required_fields($fields, $bodyHtml);
if (!empty($missingFields)) {
    stamp_insert_fail('Missing required field(s): ' . implode(', ', $missingFields));
}

$siteRoot = dirname(__DIR__);
$imagePaths = stamp_extract_image_paths($fields['featured_image'] ?? '', $bodyHtml);
$missingImages = stamp_missing_image_paths($imagePaths, $siteRoot);
if (!empty($missingImages)) {
    stamp_insert_fail("Missing image file(s):\n  " . implode("\n  ", $missingImages));
}

$slugSource = ($fields['slug'] ?? '') !== '' ? $fields['slug'] : $fields['title'];
$slug = stamp_blog_slugify($slugSource);
if ($slug === '') {
    stamp_insert_fail('Slug could not be generated from the title or slug field.');
}

require __DIR__ . '/../../db_config.php';

$check = $conn->prepare('SELECT id FROM blog_posts WHERE slug = ?');
$check->bind_param('s', $slug);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    stamp_insert_fail("That slug is already used by another post: $slug");
}
$check->close();

$title = $fields['title'];
$excerpt = $fields['excerpt'];
$featuredImage = $fields['featured_image'] ?? '';
$metaTitle = $fields['meta_title'];
$metaDescription = $fields['meta_description'];
$status = 'draft';

$stmt = $conn->prepare("
    INSERT INTO blog_posts (slug, title, excerpt, content, featured_image, meta_title, meta_description, status, published_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)
");
$stmt->bind_param(
    'ssssssss',
    $slug, $title, $excerpt, $bodyHtml, $featuredImage, $metaTitle, $metaDescription, $status
);

try {
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    if ($conn->errno === 1062) {
        stamp_insert_fail("That slug is already used by another post: $slug");
    }
    stamp_insert_fail('Database error: ' . $e->getMessage());
}

$id = (int)$stmt->insert_id;
$stmt->close();

echo "Inserted draft post #$id ($slug)\n";
echo "Review at: admin/blog-edit.php?id=$id\n";
