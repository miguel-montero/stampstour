<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';

$posts = [];
if ($conn) {
    $result = $conn->query("SELECT id, slug, title, status, published_at, updated_at FROM blog_posts ORDER BY updated_at DESC");
    if ($result) {
        $posts = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Blog Posts | Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('blog'); ?>
<div class="container" style="padding: 24px 0;">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <h2>Blog Posts</h2>
    <a href="blog-edit.php" class="btn btn-primary">+ New Post</a>
  </div>

  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success" style="margin-top:16px;">Post deleted.</div>
  <?php endif; ?>

  <table class="table table-striped" style="margin-top:16px;">
    <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Published</th><th>Updated</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><code><?= htmlspecialchars($p['slug'], ENT_QUOTES, 'UTF-8') ?></code></td>
        <td><span class="badge <?= $p['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
        <td><?= $p['published_at'] ? htmlspecialchars($p['published_at'], ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
        <td><?= htmlspecialchars($p['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <a href="blog-edit.php?id=<?= (int)$p['id'] ?>">Edit</a>
          &nbsp;|&nbsp;
          <?php if ($p['status'] === 'published'): ?>
            <a href="/blog/<?= htmlspecialchars(rawurlencode($p['slug']), ENT_QUOTES, 'UTF-8') ?>" target="_blank">View</a>
            &nbsp;|&nbsp;
          <?php endif; ?>
          <form method="POST" action="blog-delete.php" style="display:inline" onsubmit="return confirm('Delete this post? This cannot be undone.');">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="btn btn-link p-0" style="color:#c00;">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($posts)): ?>
      <tr><td colspan="6">No posts yet &mdash; click "+ New Post" to write the first one.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
