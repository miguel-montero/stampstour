<?php
require __DIR__ . '/../db_config.php';

$slug = $_GET['slug'] ?? '';

// Preview mode: only bypasses the published-only filter when the requester
// also has a valid admin session (same session key admin/_auth.php checks) -
// an unauthenticated visitor appending ?preview=1 just gets normal
// published-only behavior, so this can never leak an unpublished post.
$isPreviewRequest = isset($_GET['preview']);
$isAdmin = false;
if ($isPreviewRequest) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $isAdmin = !empty($_SESSION['username']);
}
$isPreview = $isPreviewRequest && $isAdmin;

$post = null;

if ($conn && $slug !== '') {
    $whereStatus = $isPreview ? '' : "AND status = 'published' AND published_at <= NOW()";
    $stmt = $conn->prepare("
        SELECT id, title, excerpt, content, featured_image, meta_title, meta_description, published_at, status
        FROM blog_posts
        WHERE slug = ? $whereStatus
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

$isUnpublishedPreview = $post && $isPreview && $post['status'] !== 'published';

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
<?php if ($isUnpublishedPreview): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
</head>
<body>
  <?php $header_style = 'plain'; include __DIR__ . '/includes/header.php'; ?>
  <?php include __DIR__ . '/includes/cookie-banner.php'; ?>

  <?php if ($isUnpublishedPreview): ?>
    <div style="background:#fff3cd; color:#664d03; text-align:center; padding:10px; font-weight:bold;">
      PREVIEW &mdash; this post is <?= htmlspecialchars($post['status'], ENT_QUOTES, 'UTF-8') ?> and not visible to the public yet.
      <a href="/admin/blog-edit.php?id=<?= (int)$post['id'] ?>">Back to editor</a>
    </div>
  <?php endif; ?>

  <main>
    <div class="container margin_60 blog_post_wrap">
      <?php if (!$post): ?>
        <h1>Post not found</h1>
        <p>Sorry, we couldn't find that blog post. <a href="/blog">Back to the blog</a>.</p>
      <?php else: ?>
        <div class="row">
          <div class="col-lg-9">
            <div class="box_style_1">
              <div class="post nopadding">
                <?php if (!empty($post['featured_image'])): ?>
                  <img src="<?= htmlspecialchars('/' . ltrim($post['featured_image'], '/'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid">
                <?php endif; ?>
                <div class="post_info clearfix">
                  <div class="post-left">
                    <ul>
                      <li><i class="icon-calendar-empty"></i> On <span><?= $post['published_at'] ? date('j M Y', strtotime($post['published_at'])) : 'Not yet published' ?></span></li>
                    </ul>
                  </div>
                </div>
                <h1><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="blog_post_content">
                  <?= $post['content'] ?>
                </div>
              </div>
              <!-- end post -->
            </div>
            <!-- end box_style_1 -->
            <p class="margin_30"><a href="/blog" class="btn_1">&larr; Back to the blog</a></p>
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
