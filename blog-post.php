<?php
require __DIR__ . '/../db_config.php';

$slug = $_GET['slug'] ?? '';
$post = null;

if ($conn && $slug !== '') {
    $stmt = $conn->prepare("
        SELECT title, excerpt, content, featured_image, meta_title, meta_description, published_at
        FROM blog_posts
        WHERE slug = ? AND status = 'published' AND published_at <= NOW()
        LIMIT 1
    ");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$post) {
    http_response_code(404);
}

$page_canonical = 'https://stampstour.com/blog/' . rawurlencode($slug);

if ($post) {
    $page_title       = ($post['meta_title'] ?: $post['title']) . ' | Stamps Tour';
    $page_description = $post['meta_description'] ?: $post['excerpt'];
    $page_og = [
        'title'       => $post['title'],
        'description' => $post['meta_description'] ?: $post['excerpt'],
        'url'         => $page_canonical,
    ];
    if (!empty($post['featured_image'])) {
        $page_og['image'] = 'https://stampstour.com/' . ltrim($post['featured_image'], '/');
    }
} else {
    $page_title       = 'Post Not Found | Stamps Tour';
    $page_description = 'This blog post could not be found.';
}
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

  <main>
    <div class="container margin_60">
      <?php if (!$post): ?>
        <h1>Post not found</h1>
        <p>Sorry, we couldn't find that blog post. <a href="/blog">Back to the blog</a>.</p>
      <?php else: ?>
        <div class="row">
          <div class="col-lg-8">
            <h1><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted"><?= date('F j, Y', strtotime($post['published_at'])) ?></p>
            <?php if (!empty($post['featured_image'])): ?>
              <img src="<?= htmlspecialchars($post['featured_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid margin_30">
            <?php endif; ?>
            <div class="blog_post_content">
              <?= $post['content'] ?>
            </div>
            <hr>
            <p><a href="/blog" class="btn_1">&larr; Back to the blog</a></p>
          </div>
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
