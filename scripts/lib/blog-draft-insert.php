<?php
declare(strict_types=1);

require __DIR__ . '/draft-parser.php';
require __DIR__ . '/../../includes/blog-slug.php';

/**
 * Validates and inserts a draft file into blog_posts as status='draft'.
 * Shared by the CLI script (scripts/insert-blog-draft.php) and the admin
 * web importer (admin/blog-import.php) so both paths run identical checks.
 *
 * @return array{success: bool, id?: int, slug?: string, error?: string}
 */
function stamp_insert_blog_draft(string $draftPath, string $siteRoot, mysqli $conn): array {
    if (!is_file($draftPath)) {
        return ['success' => false, 'error' => "Draft file not found: $draftPath"];
    }

    try {
        $parsed = stamp_parse_draft_file($draftPath);
    } catch (RuntimeException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    $fields = $parsed['fields'];
    $bodyHtml = stamp_markdown_to_html($parsed['body']);

    if (str_contains($bodyHtml, '[IMAGE:')) {
        return ['success' => false, 'error' => 'Draft still contains unreplaced [IMAGE: ...] placeholder(s) in the body.'];
    }

    $missingFields = stamp_missing_required_fields($fields, $bodyHtml);
    if (!empty($missingFields)) {
        return ['success' => false, 'error' => 'Missing required field(s): ' . implode(', ', $missingFields)];
    }

    $imagePaths = stamp_extract_image_paths($fields['featured_image'] ?? '', $bodyHtml);
    $missingImages = stamp_missing_image_paths($imagePaths, $siteRoot);
    if (!empty($missingImages)) {
        return ['success' => false, 'error' => "Missing image file(s):\n  " . implode("\n  ", $missingImages)];
    }

    $slugSource = ($fields['slug'] ?? '') !== '' ? $fields['slug'] : $fields['title'];
    $slug = stamp_blog_slugify($slugSource);
    if ($slug === '') {
        return ['success' => false, 'error' => 'Slug could not be generated from the title or slug field.'];
    }

    $check = $conn->prepare('SELECT id FROM blog_posts WHERE slug = ?');
    $check->bind_param('s', $slug);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        return ['success' => false, 'error' => "That slug is already used by another post: $slug"];
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
            return ['success' => false, 'error' => "That slug is already used by another post: $slug"];
        }
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }

    $id = (int)$stmt->insert_id;
    $stmt->close();

    return ['success' => true, 'id' => $id, 'slug' => $slug];
}
