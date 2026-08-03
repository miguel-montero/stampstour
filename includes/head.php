<?php /* includes/head.php
 * Shared <head> boilerplate for the marketing pages.
 * Caller sets these before including:
 *   $page_title        (required)
 *   $page_description  (required)
 *   $page_canonical    (required) - full https://stampstour.com/... URL
 *   $page_og           (optional) - array with keys: title, description, url, image
 *   $critical_css_file (optional) - path to a file of inlined critical CSS for this
 *                        page (e.g. includes/critical/home.css). When set and the
 *                        file exists, it's inlined in a <style> block and the main
 *                        stylesheets below load via a non-blocking preload+swap
 *                        instead of render-blocking <link rel="stylesheet">.
 *   $lcp_preload_image (optional) - root-relative image path (no leading slash,
 *                        e.g. 'img/Tours/Maipo/big.jpg') for this page's LCP image.
 *                        When set and the file exists, it's preloaded with
 *                        fetchpriority="high" so it isn't starved by the deferred
 *                        stylesheet preloads above.
 */
?>
<!-- Google Consent Mode v2: default-deny until the visitor chooses via the
     cookie banner (includes/cookie-banner.php). Must run before gtag.js. -->
<script>
 window.dataLayer = window.dataLayer || [];
 function gtag(){dataLayer.push(arguments);}

 gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'analytics_storage': 'denied',
  'wait_for_update': 500
 });

 (function () {
  try {
   if (localStorage.getItem('stamp_cookie_consent') === 'granted') {
    gtag('consent', 'update', {
     'ad_storage': 'granted',
     'ad_user_data': 'granted',
     'ad_personalization': 'granted',
     'analytics_storage': 'granted'
    });
   }
  } catch (e) { /* localStorage unavailable: default-deny stands */ }
 })();
</script>
<!-- Google tag (gtag.js) -->
<link rel="preconnect" href="https://www.googletagmanager.com">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-GWM59ECSLZ"></script>
<script>
 gtag('js', new Date());
 gtag('config', 'G-GWM59ECSLZ');
</script>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1" name="viewport"/>
<meta content="Miguel" name="author"/>
<title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="canonical" href="<?php echo htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!empty($page_og)): ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo htmlspecialchars($page_og['title'] ?? $page_title, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($page_og['description'] ?? $page_description, ENT_QUOTES, 'UTF-8'); ?>">
<meta property="og:url" content="<?php echo htmlspecialchars($page_og['url'] ?? $page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!empty($page_og['image'])): ?>
<meta property="og:image" content="<?php echo htmlspecialchars($page_og['image'], ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($page_og['title'] ?? $page_title, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($page_og['description'] ?? $page_description, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!empty($page_og['image'])): ?>
<meta name="twitter:image" content="<?php echo htmlspecialchars($page_og['image'], ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
<?php endif; ?>
<!-- Favicons-->
<link href="/img/favicon.ico" rel="shortcut icon" type="image/x-icon"/>
<link href="/img/apple-touch-icon-57x57-precomposed.png" rel="apple-touch-icon" type="image/x-icon"/>
<link href="/img/apple-touch-icon-72x72-precomposed.png" rel="apple-touch-icon" sizes="72x72" type="image/x-icon"/>
<link href="/img/apple-touch-icon-114x114-precomposed.png" rel="apple-touch-icon" sizes="114x114" type="image/x-icon"/>
<link href="/img/apple-touch-icon-144x144-precomposed.png" rel="apple-touch-icon" sizes="144x144" type="image/x-icon"/>
<!-- Homepage-only inlined critical CSS (covers the fixed header/nav + hero -
     everything visible without scrolling). Generated once via the `critical`
     npm package against a local rendering of index.php at 390x844 and
     1470x900 viewports - it is a static snapshot, not auto-regenerated. If
     the header or hero markup changes meaningfully, regenerate with:
       npx critical <homepage-url> --dimensions 390x844 --dimensions 1470x900
     and replace includes/critical/home.css with the output (root-absolute
     any fonts/css/img url() paths it produces). -->
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<style><?= file_get_contents($critical_css_file) ?></style>
<?php endif; ?>

<?php if (!empty($lcp_preload_image) && is_file(__DIR__ . '/../' . $lcp_preload_image)): ?>
<link rel="preload" as="image" href="/<?= htmlspecialchars($lcp_preload_image, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
<?php endif; ?>

<!-- GOOGLE WEB FONT (self-hosted) -->
<link rel="preconnect" href="https://cdn.openwidget.com">
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<!-- Pages with matching critical CSS above already have everything needed
     for first paint inlined, so these stylesheets can genuinely wait. They
     load via the media="print" onload-swap idiom ("loadCSS"): the browser
     downloads them immediately but doesn't apply them (media="print" doesn't
     match on-screen rendering), then onload swaps media to "all" once ready.
     Unlike the previous fetchpriority="low" preload approach (which only
     demoted these from Blink's VeryHigh bucket to High - still tied with the
     LCP image's own fetchpriority="high" preload above), media="print"
     reaches Blink's actual Low priority tier, so these now rank genuinely
     below the LCP image instead of tying with it. That tie was the suspected
     cause of a bimodal LCP/CLS split observed in PageSpeed Insights after
     the fetchpriority="low" fix shipped - see
     docs/superpowers/specs/2026-08-03-lcp-priority-true-separation-design.md. -->
<link rel="stylesheet" href="/fonts/fonts.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
<!-- COMMON CSS -->
<link rel="stylesheet" href="/css/bootstrap.min.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/bootstrap.min.css" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/css/style.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/style.css" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/css/vendors.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/css/bs-icon-font/bootstrap-icons.min.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"></noscript>
<!-- CUSTOM CSS -->
<link rel="stylesheet" href="/css/custom.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/custom.css" rel="stylesheet"></noscript>
<?php else: ?>
<link href="/fonts/fonts.css" rel="stylesheet"/>
<!-- COMMON CSS -->
<link href="/css/bootstrap.min.css" rel="stylesheet"/>
<link href="/css/style.css" rel="stylesheet"/>
<link href="/css/vendors.css" rel="stylesheet"/>
<link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"/>
<!-- CUSTOM CSS -->
<link href="/css/custom.css" rel="stylesheet"/>
<?php endif; ?>
