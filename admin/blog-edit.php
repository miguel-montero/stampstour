<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';
require __DIR__ . '/../includes/blog-slug.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$post = ['slug' => '', 'title' => '', 'excerpt' => '', 'content' => '', 'featured_image' => '', 'meta_title' => '', 'meta_description' => '', 'status' => 'draft', 'published_at' => ''];
$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post['title']            = trim($_POST['title'] ?? '');
    $rawSlug                  = trim($_POST['slug'] ?? '');
    $post['slug']             = stamp_blog_slugify($rawSlug !== '' ? $rawSlug : $post['title']);
    $post['excerpt']          = trim($_POST['excerpt'] ?? '');
    $post['content']          = $_POST['content'] ?? '';
    $post['featured_image']   = trim($_POST['featured_image'] ?? '');
    $post['meta_title']       = trim($_POST['meta_title'] ?? '');
    $post['meta_description'] = trim($_POST['meta_description'] ?? '');
    $post['status']           = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $publishedInput           = trim($_POST['published_at'] ?? '');

    if ($post['title'] === '') $errors[] = 'Title is required.';
    if ($post['content'] === '') $errors[] = 'Content is required.';
    if ($post['slug'] === '') $errors[] = 'Slug could not be generated from the title &mdash; please enter a title or a slug.';

    // Note: when defaulting to "publish now", we deliberately let MySQL's own
    // NOW() supply the timestamp (via SQL, not PHP's date()) instead of computing
    // it in PHP — the web server's PHP timezone and the DB server's timezone are
    // configured independently and are not guaranteed to agree, and every public
    // query compares published_at against MySQL's NOW(), so MySQL must also be
    // the one to set it when the exact moment isn't user-specified.
    $useNowLiteral = false;
    if ($publishedInput !== '') {
        $ts = strtotime($publishedInput);
        $publishedAt = $ts ? date('Y-m-d H:i:s', $ts) : null;
    } elseif ($post['status'] === 'published') {
        $useNowLiteral = true;
        $publishedAt = null; // filled in from DB after save
    } else {
        $publishedAt = null;
    }
    $post['published_at'] = $publishedAt ?? '';

    if (empty($errors) && $conn) {
        $publishedAtSql = $useNowLiteral ? 'NOW()' : '?';
        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE blog_posts
                SET slug=?, title=?, excerpt=?, content=?, featured_image=?, meta_title=?, meta_description=?, status=?, published_at=$publishedAtSql
                WHERE id=?
            ");
            if ($useNowLiteral) {
                $stmt->bind_param(
                    'ssssssssi',
                    $post['slug'], $post['title'], $post['excerpt'], $post['content'], $post['featured_image'],
                    $post['meta_title'], $post['meta_description'], $post['status'], $id
                );
            } else {
                $stmt->bind_param(
                    'sssssssssi',
                    $post['slug'], $post['title'], $post['excerpt'], $post['content'], $post['featured_image'],
                    $post['meta_title'], $post['meta_description'], $post['status'], $publishedAt, $id
                );
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO blog_posts (slug, title, excerpt, content, featured_image, meta_title, meta_description, status, published_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, $publishedAtSql)
            ");
            if ($useNowLiteral) {
                $stmt->bind_param(
                    'ssssssss',
                    $post['slug'], $post['title'], $post['excerpt'], $post['content'], $post['featured_image'],
                    $post['meta_title'], $post['meta_description'], $post['status']
                );
            } else {
                $stmt->bind_param(
                    'sssssssss',
                    $post['slug'], $post['title'], $post['excerpt'], $post['content'], $post['featured_image'],
                    $post['meta_title'], $post['meta_description'], $post['status'], $publishedAt
                );
            }
        }
        if ($stmt->execute()) {
            if ($id === 0) $id = (int)$stmt->insert_id;
            $saved = true;
            if ($useNowLiteral) {
                $refresh = $conn->prepare("SELECT published_at FROM blog_posts WHERE id=?");
                $refresh->bind_param('i', $id);
                $refresh->execute();
                $refresh->bind_result($refreshedPublishedAt);
                if ($refresh->fetch()) {
                    $post['published_at'] = $refreshedPublishedAt;
                }
                $refresh->close();
            }
        } elseif ($conn->errno === 1062) {
            $errors[] = 'That slug is already used by another post &mdash; please choose a different one.';
        } else {
            $errors[] = 'Database error: ' . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
        }
        $stmt->close();
    }
} elseif ($id > 0 && $conn) {
    $stmt = $conn->prepare("SELECT id, slug, title, excerpt, content, featured_image, meta_title, meta_description, status, published_at FROM blog_posts WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) $post = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $id ? 'Edit' : 'New' ?> Blog Post | Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('blog'); ?>
<div class="container" style="padding: 24px 0; max-width: 800px;">
  <h2><?= $id ? 'Edit' : 'New' ?> Blog Post</h2>

  <?php if ($saved): ?>
    <div class="alert alert-success">
      Saved. <a href="blog-edit.php?id=<?= (int)$id ?>">Continue editing</a>
      &middot; <a href="blog.php">Back to list</a>
      <?php if ($post['status'] === 'published'): ?>
        &middot; <a href="/blog/<?= htmlspecialchars(rawurlencode($post['slug']), ENT_QUOTES, 'UTF-8') ?>" target="_blank">View live</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= $e /* pre-escaped or safe static string above */ ?></div>
  <?php endforeach; ?>

  <form method="POST">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="mb-3">
      <label class="form-label">Title</label>
      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Slug <small class="text-muted">(URL: /blog/your-slug &mdash; leave blank to auto-generate from title)</small></label>
      <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Excerpt <small class="text-muted">(shown on the blog listing page; also used as the meta description if no SEO override is set below)</small></label>
      <textarea name="excerpt" class="form-control" rows="2"><?= htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Content</label>
      <div id="content-editor" style="background: #fff;"><?= $post['content'] ?></div>
      <textarea name="content" id="content-input" style="display: none;"></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Featured image path <small class="text-muted">(e.g. img/blog/my-post-cover.jpg &mdash; upload the file via your host first, or pick one from the gallery)</small></label>
      <div style="display:flex; gap:8px;">
        <input type="text" name="featured_image" id="featured-image-input" class="form-control" value="<?= htmlspecialchars($post['featured_image'], ENT_QUOTES, 'UTF-8') ?>">
        <button type="button" class="btn btn-outline-secondary" style="white-space:nowrap;" onclick="stampOpenGalleryPicker('featured')">Choose from Gallery</button>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">SEO title override <small class="text-muted">(optional)</small></label>
        <input type="text" name="meta_title" class="form-control" value="<?= htmlspecialchars($post['meta_title'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">SEO description override <small class="text-muted">(optional)</small></label>
        <input type="text" name="meta_description" class="form-control" value="<?= htmlspecialchars($post['meta_description'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-control">
          <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
          <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Published</option>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Published date <small class="text-muted">(leave blank = right now, when publishing)</small></label>
        <input type="datetime-local" name="published_at" class="form-control" value="<?= $post['published_at'] ? htmlspecialchars(str_replace(' ', 'T', substr($post['published_at'], 0, 16)), ENT_QUOTES, 'UTF-8') : '' ?>">
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Save</button>
    <a href="blog.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>

<!-- Gallery photo picker modal - shared by the "Choose from Gallery" button
     (sets the featured_image field) and Quill's image toolbar button
     (inserts an <img> at the cursor). Backed by admin/gallery-picker-data.php,
     which merges the same two manifests gallery.php renders publicly. -->
<div id="gallery-picker-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050;">
  <div style="background:#fff; max-width:900px; margin:40px auto; max-height:85vh; display:flex; flex-direction:column; border-radius:6px; overflow:hidden;">
    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #ddd;">
      <strong>Choose a photo from the gallery</strong>
      <button type="button" class="btn-close" onclick="stampCloseGalleryPicker()"></button>
    </div>
    <div style="padding:10px 16px; border-bottom:1px solid #eee; display:flex; gap:8px;">
      <select id="gallery-picker-tag-filter" class="form-select form-select-sm" style="max-width:250px;">
        <option value="">All tags</option>
      </select>
      <input type="text" id="gallery-picker-search" class="form-control form-control-sm" placeholder="Search by title/date&hellip;" style="max-width:250px;">
    </div>
    <div id="gallery-picker-grid" style="padding:16px; overflow-y:auto; display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:10px;">
      <p class="text-muted">Loading&hellip;</p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
  var quill = new Quill('#content-editor', {
    theme: 'snow',
    modules: {
      toolbar: {
        container: [
          [{ header: [2, 3, false] }],
          ['bold', 'italic', 'underline', 'link'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote', 'image'],
          ['clean']
        ],
        handlers: {
          // Default Quill image handler just prompts for a raw URL - point
          // it at the same gallery picker instead so post content can
          // reference real gallery photos without typing/remembering paths.
          image: function () { stampOpenGalleryPicker('content'); }
        }
      }
    }
  });

  document.querySelector('form').addEventListener('submit', function () {
    document.getElementById('content-input').value = quill.root.innerHTML;
  });

  var stampGalleryPhotos = null;
  var stampGalleryPickerTarget = null; // 'featured' or 'content'

  function stampOpenGalleryPicker(target) {
    stampGalleryPickerTarget = target;
    document.getElementById('gallery-picker-backdrop').style.display = 'block';
    if (stampGalleryPhotos === null) {
      fetch('gallery-picker-data.php')
        .then(function (r) { return r.json(); })
        .then(function (photos) {
          stampGalleryPhotos = photos;
          stampRenderGalleryTagFilter(photos);
          stampRenderGalleryGrid(photos);
        })
        .catch(function () {
          document.getElementById('gallery-picker-grid').innerHTML =
            '<p class="text-danger">Could not load the gallery. Try again later.</p>';
        });
    }
  }

  function stampCloseGalleryPicker() {
    document.getElementById('gallery-picker-backdrop').style.display = 'none';
  }

  function stampRenderGalleryTagFilter(photos) {
    var tags = {};
    photos.forEach(function (p) { (p.tags || []).forEach(function (t) { tags[t] = true; }); });
    var select = document.getElementById('gallery-picker-tag-filter');
    Object.keys(tags).sort().forEach(function (tag) {
      var opt = document.createElement('option');
      opt.value = tag;
      opt.textContent = tag;
      select.appendChild(opt);
    });
    select.onchange = stampApplyGalleryFilters;
    document.getElementById('gallery-picker-search').oninput = stampApplyGalleryFilters;
  }

  function stampApplyGalleryFilters() {
    var tag = document.getElementById('gallery-picker-tag-filter').value;
    var search = document.getElementById('gallery-picker-search').value.trim().toLowerCase();
    var filtered = stampGalleryPhotos.filter(function (p) {
      if (tag && (p.tags || []).indexOf(tag) === -1) return false;
      if (search && (p.title || '').toLowerCase().indexOf(search) === -1) return false;
      return true;
    });
    stampRenderGalleryGrid(filtered);
  }

  function stampRenderGalleryGrid(photos) {
    var grid = document.getElementById('gallery-picker-grid');
    if (!photos.length) {
      grid.innerHTML = '<p class="text-muted">No photos match that tag.</p>';
      return;
    }
    grid.innerHTML = '';
    photos.forEach(function (p) {
      var el = document.createElement('div');
      el.style.cursor = 'pointer';
      el.title = p.title;
      var img = document.createElement('img');
      img.src = p.thumb;
      img.alt = '';
      img.loading = 'lazy';
      img.style.cssText = 'width:100%; height:100px; object-fit:cover; border-radius:4px;';
      el.appendChild(img);
      el.addEventListener('click', function () { stampSelectGalleryPhoto(p); });
      grid.appendChild(el);
    });
  }

  function stampSelectGalleryPhoto(photo) {
    if (stampGalleryPickerTarget === 'featured') {
      document.getElementById('featured-image-input').value = photo.large;
    } else if (stampGalleryPickerTarget === 'content') {
      var range = quill.getSelection(true);
      quill.insertEmbed(range ? range.index : quill.getLength(), 'image', photo.large, 'user');
    }
    stampCloseGalleryPicker();
  }
</script>
</body>
</html>
