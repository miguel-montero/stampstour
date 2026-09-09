<?php
require __DIR__ . '/../db_config.php';

/**
 * Pull question/answer pairs out of the "Frequently Asked Questions" section of
 * a rendered post body, for FAQPage structured data. Finds the first <h2> whose
 * text contains "frequently asked questions", then treats each following <h3> as
 * a question and the text of the <p>/<li> elements after it (up to the next
 * <h3>/<h2>) as the answer. Returns [] when there is no recognisable FAQ block.
 *
 * @return array<int, array{q: string, a: string}>
 */
function stamp_extract_faq_from_html(string $html): array {
    if (trim($html) === '' || stripos($html, 'frequently asked questions') === false) {
        return [];
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $wrapper = $dom->getElementsByTagName('div')->item(0);
    if (!$wrapper) {
        return [];
    }

    $faq = [];
    $current = null;
    $inFaq = false;
    foreach (iterator_to_array($wrapper->childNodes) as $node) {
        if (!($node instanceof DOMElement)) {
            continue;
        }
        $tag = strtolower($node->tagName);
        if ($tag === 'h2') {
            if ($inFaq) {
                break; // FAQ section ends at the next H2
            }
            $inFaq = stripos($node->textContent, 'frequently asked questions') !== false;
            continue;
        }
        if (!$inFaq) {
            continue;
        }
        if ($tag === 'h3') {
            if ($current && $current['a'] !== '') {
                $faq[] = $current;
            }
            $current = ['q' => trim(preg_replace('/\s+/', ' ', $node->textContent)), 'a' => ''];
        } elseif ($current !== null && in_array($tag, ['p', 'ul', 'ol'], true)) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($text !== '') {
                $current['a'] = $current['a'] === '' ? $text : $current['a'] . ' ' . $text;
            }
        }
    }
    if ($current && $current['a'] !== '') {
        $faq[] = $current;
    }

    return $faq;
}

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
$vendor_css_variant = 'core';

if ($post) {
    $page_title       = ($post['meta_title'] ?: $post['title']) . ' | Stamp\'s Tour';
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
    $page_title       = 'Post Not Found | Stamp\'s Tour';
    $page_description = 'This blog post could not be found.';
}

// Content transforms + structured data for real (published) post views.
$contentHtml  = $post['content'] ?? '';
$jsonLdBlocks = [];

if ($post && !$isUnpublishedPreview) {
    // Defer offscreen images. The featured image (rendered separately below)
    // is left eager as the likely LCP element; every in-content <img> without
    // its own loading attribute becomes lazy.
    $contentHtml = preg_replace(
        '/<img (?![^>]*\bloading=)/i',
        '<img loading="lazy" decoding="async" ',
        $contentHtml
    );

    $imageUrl = !empty($post['featured_image'])
        ? 'https://stampstour.com/' . ltrim($post['featured_image'], '/')
        : null;
    $publishedIso = $post['published_at'] ? date('c', strtotime($post['published_at'])) : null;

    $blogPosting = [
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'headline'         => $post['title'],
        'description'      => $post['meta_description'] ?: $post['excerpt'],
        'url'              => $page_canonical,
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $page_canonical],
        'inLanguage'       => 'en',
        'author'           => ['@type' => 'Organization', 'name' => "Stamp's Tour", 'url' => 'https://stampstour.com/'],
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => "Stamp's Tour",
            'logo'  => ['@type' => 'ImageObject', 'url' => 'https://stampstour.com/img/logolargo.png'],
        ],
    ];
    if ($imageUrl) {
        $blogPosting['image'] = $imageUrl;
    }
    if ($publishedIso) {
        $blogPosting['datePublished'] = $publishedIso;
        $blogPosting['dateModified']  = $publishedIso;
    }
    $jsonLdBlocks[] = $blogPosting;

    $jsonLdBlocks[] = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://stampstour.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => 'https://stampstour.com/blog'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => $page_canonical],
        ],
    ];

    $faq = stamp_extract_faq_from_html($post['content']);
    if (count($faq) >= 2) {
        $jsonLdBlocks[] = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(static function (array $qa): array {
                return [
                    '@type'          => 'Question',
                    'name'           => $qa['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa['a']],
                ];
            }, $faq),
        ];
    }
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
<?php foreach ($jsonLdBlocks as $ldBlock): ?>
<script type="application/ld+json"><?= json_encode($ldBlock, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endforeach; ?>
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
                  <?= $contentHtml ?>
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

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <?php include __DIR__ . '/includes/content-scripts.php'; ?>
</body>
</html>
