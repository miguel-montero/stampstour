<?php
require __DIR__ . '/../db_config.php';

$posts = [];
if ($conn) {
    $result = $conn->query("
        SELECT slug, title, excerpt, featured_image, published_at
        FROM blog_posts
        WHERE status = 'published' AND published_at <= NOW()
        ORDER BY published_at DESC
    ");
    if ($result) {
        $posts = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$page_title       = 'Blog | Stamps Tour';
$page_description = 'Travel tips, guides, and stories about touring Santiago, Valparaíso, Maipo Valley, and the Andes with Stamps Tour.';
$page_canonical   = 'https://stampstour.com/blog';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <?php include __DIR__ . '/includes/cookie-banner.php'; ?>

  <section id="hero_2" class="background-image" data-background="url(img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
      <div class="intro_title">
        <h1>Blog</h1>
      </div>
    </div>
  </section>

  <main>
    <div class="container margin_60">
      <?php if (empty($posts)): ?>
        <p>No posts published yet &mdash; check back soon!</p>
      <?php else: ?>
        <div class="row">
          <?php foreach ($posts as $post): $href = '/blog/' . rawurlencode($post['slug']); ?>
            <div class="col-md-4 margin_30">
              <div class="blog_post_card">
                <?php if (!empty($post['featured_image'])): ?>
                  <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= htmlspecialchars($post['featured_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid" loading="lazy">
                  </a>
                <?php endif; ?>
                <h3><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></h3>
                <p class="text-muted small"><?= date('F j, Y', strtotime($post['published_at'])) ?></p>
                <?php if (!empty($post['excerpt'])): ?>
                  <p><?= htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="btn_1">Read more</a>
              </div>
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
</body>
</html>
