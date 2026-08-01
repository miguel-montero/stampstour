<?php
/* sitemap-generator.php
 * Serves the dynamic XML sitemap at the clean URL /sitemap.xml (see .htaccess).
 * Includes the site's static pages plus every published blog post, so new
 * posts show up automatically without anyone hand-editing a sitemap file.
 */
require __DIR__ . '/../db_config.php';

header('Content-Type: application/xml; charset=utf-8');

$staticPages = [
    ['loc' => '/'],
    ['loc' => '/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca'],
    ['loc' => '/maipo-valley-wine-tour-santiago'],
    ['loc' => '/portillo-inca-lagoon-andes-mountains-vineyard'],
    ['loc' => '/discover-santiago-city-tour'],
    ['loc' => '/cruise-transfer.php'],
    ['loc' => '/blog'],
    ['loc' => '/contact-us.php'],
    ['loc' => '/refunds-cancellations.php'],
    ['loc' => '/privacy.php'],
];

$posts = [];
if ($conn) {
    $result = $conn->query("
        SELECT slug, updated_at
        FROM blog_posts
        WHERE status = 'published' AND published_at <= NOW()
        ORDER BY published_at DESC
    ");
    if ($result) {
        $posts = $result->fetch_all(MYSQLI_ASSOC);
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
  <url>
    <loc><?= htmlspecialchars('https://stampstour.com' . $page['loc'], ENT_XML1, 'UTF-8') ?></loc>
  </url>
<?php endforeach; ?>
<?php foreach ($posts as $post): ?>
  <url>
    <loc><?= htmlspecialchars('https://stampstour.com/blog/' . rawurlencode($post['slug']), ENT_XML1, 'UTF-8') ?></loc>
    <lastmod><?= htmlspecialchars(date('c', strtotime($post['updated_at'])), ENT_XML1, 'UTF-8') ?></lastmod>
  </url>
<?php endforeach; ?>
</urlset>
