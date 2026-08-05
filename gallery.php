<?php
// Two manifest sources: gallery-data.json is git-tracked (populated by the
// local Node pipeline and pulled onto this server); gallery-data-admin.json
// is server-local only, gitignored, populated by admin/gallery-upload.php.
// Kept separate deliberately - the admin panel has no git credentials, so
// merging into the git-tracked file here would make its content diverge
// from what's actually committed and get lost on the next deploy.
$photos = [];
foreach (['gallery-data.json', 'gallery-data-admin.json'] as $manifestFile) {
    $manifestPath = __DIR__ . '/gallery-pipeline/' . $manifestFile;
    if (file_exists($manifestPath)) {
        $decoded = json_decode(file_get_contents($manifestPath), true);
        if (is_array($decoded)) {
            $photos = array_merge($photos, $decoded);
        }
    }
}
usort($photos, function ($a, $b) {
    return strcmp($b['dateAdded'] ?? '', $a['dateAdded'] ?? '');
});

$allTags = [];
foreach ($photos as $photo) {
    foreach ($photo['tags'] ?? [] as $tag) {
        $allTags[$tag] = true;
    }
}
$allTags = array_keys($allTags);
sort($allTags);

$page_title       = 'Gallery | Stamps Tour';
$page_description = 'Candid photos from Stamps Tour guides across our Santiago, Valparaíso, Maipo Valley, Andes, and cruise transfer tours.';
$page_canonical   = 'https://stampstour.com/gallery.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<link href="/css/lightbox2.css" rel="stylesheet"/>
<link href="/css/gallery.css" rel="stylesheet"/>
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <?php include __DIR__ . '/includes/cookie-banner.php'; ?>

  <section id="hero_2" class="background-image" data-background="url(/img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
      <div class="intro_title">
        <h1>Gallery</h1>
      </div>
    </div>
  </section>

  <main>
    <div class="container margin_60">
      <?php if (empty($photos)): ?>
        <p>No photos yet &mdash; check back soon!</p>
      <?php else: ?>
        <div class="gallery-filters">
          <button type="button" class="gallery-filter-pill active" data-tag="">All</button>
          <?php foreach ($allTags as $tag): ?>
            <button type="button" class="gallery-filter-pill" data-tag="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></button>
          <?php endforeach; ?>
        </div>

        <div class="gallery-grid">
          <?php foreach ($photos as $photo):
            $uploadDateFormatted = '';
            if (!empty($photo['dateAdded'])) {
                $ts = strtotime($photo['dateAdded']);
                if ($ts !== false) $uploadDateFormatted = date('M j, Y', $ts);
            }
            $lightboxCaption = trim(($photo['title'] ?? '') . ($uploadDateFormatted !== '' ? ' — Upload date: ' . $uploadDateFormatted : ''));
          ?>
            <div class="gallery-item" data-tags="<?= htmlspecialchars(implode('|', $photo['tags'] ?? []), ENT_QUOTES, 'UTF-8') ?>">
              <a href="/<?= htmlspecialchars($photo['large'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 data-lightbox="gallery"
                 data-title="<?= htmlspecialchars($lightboxCaption, ENT_QUOTES, 'UTF-8') ?>"
                 class="gallery-item-link">
                <img src="/<?= htmlspecialchars($photo['thumb'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($photo['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     loading="lazy">
              </a>
              <?php if ($uploadDateFormatted !== ''): ?>
                <p class="gallery-item-date">Upload date: <?= htmlspecialchars($uploadDateFormatted, ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <footer class="revealed">
    <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>

  <?php include __DIR__ . '/includes/content-scripts.php'; ?>
  <script src="/js/lightbox2.js"></script>
  <script src="/js/gallery.js"></script>
</body>
</html>
