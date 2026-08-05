<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

$REPO_ROOT = dirname(__DIR__);
$GALLERY_IMG_DIR = $REPO_ROOT . '/img/Gallery';
// gallery-data.json is git-tracked (shared with the local Node pipeline).
// This page never touches it - it has no git credentials and no way to
// push, so writing here would just diverge from git and vanish on the next
// deploy. Uploads go into gallery-data-admin.json instead: gitignored,
// server-local only. gallery.php merges both when rendering.
$MANIFEST_PATH = $REPO_ROOT . '/gallery-pipeline/gallery-data.json';
$ADMIN_MANIFEST_PATH = $REPO_ROOT . '/gallery-pipeline/gallery-data-admin.json';

function stamp_gallery_slugify(string $title): string
{
    $slug = strtolower($title);
    $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u'];
    $slug = strtr($slug, $map);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

function stamp_gallery_read_manifest(string $path): array
{
    if (!file_exists($path)) return [];
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function stamp_gallery_write_manifest(string $path, array $entries): void
{
    file_put_contents($path, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

function stamp_gallery_unique_slug(array $allEntries, string $base): string
{
    $existing = array_column($allEntries, 'id');
    if (!in_array($base, $existing, true)) return $base;
    $n = 2;
    while (in_array("$base-$n", $existing, true)) $n++;
    return "$base-$n";
}

// Mirrors bulk-publish.js's convention on the local pipeline: parse
// WhatsApp's PHOTO-YYYY-MM-DD-HH-MM-SS export filename if present, else
// just clean up the raw filename. No manual title entry required.
function stamp_gallery_title_from_filename(string $filename): string
{
    $stem = preg_replace('/\.(jpe?g|png)$/i', '', $filename) ?? $filename;
    if (preg_match('/^PHOTO-(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})-(\d{2})(?:\s*\((\d+)\))?$/i', $stem, $m)) {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthName = $months[(int)$m[2] - 1] ?? $m[2];
        $dup = isset($m[7]) ? " ({$m[7]})" : '';
        return "Photo from {$monthName} " . (int)$m[3] . ", {$m[1]} {$m[4]}:{$m[5]}{$dup}";
    }
    $clean = trim(preg_replace('/[-_]+/', ' ', $stem) ?? $stem);
    return $clean !== '' ? $clean : 'Photo';
}

function stamp_gallery_date_from_filename(string $filename): string
{
    if (preg_match('/^PHOTO-(\d{4})-(\d{2})-(\d{2})-/i', $filename, $m)) {
        return "{$m[1]}-{$m[2]}-{$m[3]}";
    }
    return date('Y-m-d');
}

// Resize via GD (thumb ~500px wide, large ~1600px wide). Prefers WebP output
// when this GD build supports it, falls back to JPEG otherwise - production's
// exact GD/WebP support was unconfirmed when this was built, so it degrades
// gracefully instead of assuming.
function stamp_gallery_generate_variant(string $sourcePath, string $outDir, string $baseName, int $maxWidth): ?array
{
    $info = @getimagesize($sourcePath);
    if ($info === false) return null;
    [$width, $height, $type] = $info;

    $image = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
        IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
        default => null,
    };
    if (!$image) return null;

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = (int)round($height * ($maxWidth / $width));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }

    $useWebp = function_exists('imagewebp');
    $ext = $useWebp ? 'webp' : 'jpg';
    $outPath = "$outDir/{$baseName}.{$ext}";
    $ok = $useWebp ? imagewebp($image, $outPath, 80) : imagejpeg($image, $outPath, 82);
    imagedestroy($image);

    return $ok ? ['path' => $outPath, 'ext' => $ext] : null;
}

$gdMissing = !function_exists('imagecreatefromjpeg');
$results = [];
$uploadedCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['photos'])) {
    @mkdir($GALLERY_IMG_DIR, 0755, true);
    $adminManifest = stamp_gallery_read_manifest($ADMIN_MANIFEST_PATH);
    $allEntries = array_merge(stamp_gallery_read_manifest($MANIFEST_PATH), $adminManifest);

    $names = $_FILES['photos']['name'];
    $tmpPaths = $_FILES['photos']['tmp_name'];
    $errors = $_FILES['photos']['error'];
    $count = is_array($names) ? count($names) : 0;

    for ($i = 0; $i < $count; $i++) {
        $originalName = basename((string)$names[$i]);
        if ($originalName === '' || $errors[$i] !== UPLOAD_ERR_OK) {
            if ($originalName !== '' && $errors[$i] !== UPLOAD_ERR_OK) {
                $results[] = "✗ {$originalName}: upload error (code {$errors[$i]})";
            }
            continue;
        }
        if (!preg_match('/\.(jpe?g|png)$/i', $originalName)) {
            $results[] = "✗ {$originalName}: skipped, not a jpg/png";
            continue;
        }
        if ($gdMissing) {
            $results[] = "✗ {$originalName}: server can't resize images (GD not available) - not uploaded";
            continue;
        }

        $title = stamp_gallery_title_from_filename($originalName);
        $dateAdded = stamp_gallery_date_from_filename($originalName);
        $baseSlug = stamp_gallery_slugify($title) ?: 'photo';
        $id = stamp_gallery_unique_slug($allEntries, $baseSlug);

        $thumb = stamp_gallery_generate_variant($tmpPaths[$i], $GALLERY_IMG_DIR, "{$id}-thumb", 500);
        $large = stamp_gallery_generate_variant($tmpPaths[$i], $GALLERY_IMG_DIR, "{$id}-large", 1600);

        if ($thumb === null || $large === null) {
            $results[] = "✗ {$originalName}: could not process image (unsupported or corrupt file?)";
            continue;
        }

        $entry = [
            'id' => $id,
            'title' => $title,
            'tags' => [],
            'thumb' => "img/Gallery/{$id}-thumb.{$thumb['ext']}",
            'large' => "img/Gallery/{$id}-large.{$large['ext']}",
            'sourceFile' => $originalName,
            'dateAdded' => $dateAdded,
        ];
        $adminManifest[] = $entry;
        $allEntries[] = $entry;
        $uploadedCount++;
        $results[] = "✓ {$originalName} → {$id}";
    }

    stamp_gallery_write_manifest($ADMIN_MANIFEST_PATH, $adminManifest);
}

$maxUploads = (int)ini_get('max_file_uploads');
$maxPost = (string)ini_get('post_max_size');
$liveCount = count(stamp_gallery_read_manifest($MANIFEST_PATH)) + count(stamp_gallery_read_manifest($ADMIN_MANIFEST_PATH));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Gallery Upload | Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('gallery'); ?>
<div class="container" style="padding: 24px 0; max-width: 800px;">
  <h2>Gallery Upload</h2>
  <p class="text-muted">Select a folder (or individual photos) and upload. No title, tags, or date needed - they're generated automatically from each file.</p>

  <?php if ($gdMissing): ?>
    <div class="alert alert-warning">This server's PHP doesn't have the GD image library, so photos can't be resized here. Contact your host to enable it, or use the local upload tool on your Mac instead.</div>
  <?php endif; ?>

  <?php if (!empty($results)): ?>
    <div class="alert <?= $uploadedCount > 0 ? 'alert-success' : 'alert-danger' ?>">
      <strong><?= $uploadedCount ?> photo(s) uploaded.</strong>
      <ul class="mb-0" style="max-height: 300px; overflow-y: auto;">
        <?php foreach ($results as $r): ?>
          <li><?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Photos</label>
      <input type="file" name="photos[]" id="photos-input" class="form-control" multiple accept="image/*" webkitdirectory>
      <small class="text-muted d-block mt-1">
        Picks an entire folder's photos at once (falls back to picking multiple individual files if your browser doesn't support folder selection).
        This server accepts at most <?= $maxUploads ?: '?' ?> files and <?= htmlspecialchars($maxPost, ENT_QUOTES, 'UTF-8') ?> total per upload -
        if you have more than that, just submit again for the rest.
      </small>
    </div>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>

  <p class="mt-3 text-muted"><?= $liveCount ?> photo(s) currently live in the gallery. <a href="/gallery.php" target="_blank">View gallery</a></p>
</div>
</body>
</html>
