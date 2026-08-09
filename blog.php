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
$critical_css_file = __DIR__ . '/includes/critical/content.css';
$vendor_css_variant = 'core';
$lcp_preload_image = 'img/Tours/Stgo/big.webp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<link href="/css/blog.css" rel="stylesheet"/>
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <?php include __DIR__ . '/includes/cookie-banner.php'; ?>

  <section id="hero_2">
    <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
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
          <div class="col-lg-9">
            <div class="box_style_1">
              <?php foreach ($posts as $i => $post): $href = '/blog/' . rawurlencode($post['slug']); ?>
                <div class="post">
                  <?php if (!empty($post['featured_image'])): ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
                      <img src="<?= htmlspecialchars('/' . ltrim($post['featured_image'], '/'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid" loading="lazy">
                    </a>
                  <?php endif; ?>
                  <div class="post_info clearfix">
                    <div class="post-left">
                      <ul>
                        <li><i class="icon-calendar-empty"></i> On <span><?= date('j M Y', strtotime($post['published_at'])) ?></span></li>
                      </ul>
                    </div>
                  </div>
                  <h2><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
                  <?php if (!empty($post['excerpt'])): ?>
                    <p><?= htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8') ?></p>
                  <?php endif; ?>
                  <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="btn_1" title="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">Read more</a>
                </div>
                <!-- end post -->
                <?php if ($i < count($posts) - 1): ?><hr><?php endif; ?>
              <?php endforeach; ?>
            </div>
            <!-- end box_style_1 -->
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <?php include __DIR__ . '/includes/content-scripts.php'; ?>
</body>
</html>
