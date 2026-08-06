<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

// Same two-manifest merge as gallery.php (gallery-data.json is git-tracked,
// gallery-data-admin.json is server-local from admin/gallery-upload.php) -
// this just serves it as JSON for the blog editor's photo picker to fetch.
$photos = [];
foreach (['gallery-data.json', 'gallery-data-admin.json'] as $manifestFile) {
    $manifestPath = dirname(__DIR__) . '/gallery-pipeline/' . $manifestFile;
    if (file_exists($manifestPath)) {
        $decoded = json_decode((string)file_get_contents($manifestPath), true);
        if (is_array($decoded)) {
            $photos = array_merge($photos, $decoded);
        }
    }
}
usort($photos, function ($a, $b) {
    return strcmp($b['dateAdded'] ?? '', $a['dateAdded'] ?? '');
});

header('Content-Type: application/json');
echo json_encode(array_map(function ($p) {
    return [
        'id' => $p['id'] ?? '',
        'title' => $p['title'] ?? '',
        'tags' => $p['tags'] ?? [],
        'thumb' => isset($p['thumb']) ? '/' . ltrim($p['thumb'], '/') : '',
        'large' => isset($p['large']) ? '/' . ltrim($p['large'], '/') : '',
    ];
}, $photos));
