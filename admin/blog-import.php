<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';
require __DIR__ . '/../scripts/lib/blog-draft-insert.php';

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['draft']['tmp_name']) || $_FILES['draft']['error'] !== UPLOAD_ERR_OK) {
        $result = ['success' => false, 'error' => 'No file uploaded, or the upload failed. Please choose a .md draft file and try again.'];
    } elseif ($_FILES['draft']['size'] > 500_000) {
        $result = ['success' => false, 'error' => 'That file is larger than expected for a blog draft (500KB max). Please check it\'s the right file.'];
    } else {
        $result = stamp_insert_blog_draft($_FILES['draft']['tmp_name'], dirname(__DIR__), $conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Import Blog Draft | Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('blog'); ?>
<div class="container" style="padding: 24px 0; max-width: 700px;">
  <h2>Import Blog Draft</h2>
  <p class="text-muted">
    Upload a draft <code>.md</code> file produced by the <code>seo-post-writer</code> skill
    (frontmatter + body, image placeholders already replaced with real photos you've uploaded
    to this server). It runs the same checks as the command-line importer and inserts the post
    as a <strong>draft</strong> &mdash; nothing is published automatically.
  </p>

  <?php if ($result && $result['success']): ?>
    <div class="alert alert-success">
      Inserted draft post #<?= (int)$result['id'] ?> (<code><?= htmlspecialchars($result['slug'], ENT_QUOTES, 'UTF-8') ?></code>).
      <a href="blog-edit.php?id=<?= (int)$result['id'] ?>">Review and publish &rarr;</a>
    </div>
  <?php elseif ($result && !$result['success']): ?>
    <div class="alert alert-danger" style="white-space: pre-wrap;"><?= htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Draft file</label>
      <input type="file" name="draft" class="form-control" accept=".md,.markdown,text/markdown,text/plain" required>
    </div>
    <button type="submit" class="btn btn-primary">Import as draft</button>
    &nbsp;
    <a href="blog.php">Back to list</a>
  </form>
</div>
</body>
</html>
