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
 *   $vendor_css_variant (optional) - 'home', 'tour', or 'core'. 'home'/'tour'
 *                        load css/vendors-core.css + css/vendors-{variant}.css
 *                        instead of the full css/vendors.css (see
 *                        docs/superpowers/specs/2026-08-03-homepage-tour-bundle-split-design.md).
 *                        'core' loads css/vendors-core.css alone, no second
 *                        file - for pages that use icon classes (fontello/
 *                        icon_set_1, all that's in vendors-core.css) but
 *                        nothing from Magnific Popup, the switch toggle,
 *                        Slider Pro, daterangepicker, or WOW/Animate.css (see
 *                        docs/superpowers/plans/2026-08-08-content-pages-vendors-core-only.md
 *                        for how this was verified page-by-page). Unset (the
 *                        default) preserves today's behavior exactly, so
 *                        every page that doesn't opt in is untouched.
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
     any fonts/css/img url() paths it produces). includes/critical/tour.css
     additionally carries a small hand-added #Img_carousel block (marked
     HAND-ADDED in that file) that is NOT part of any `npx critical`
     extraction - if you ever regenerate tour.css, copy that block back in
     from the marked section, or the tour-gallery CLS fix silently breaks
     again. -->
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
     for first paint inlined, so these stylesheets are preloaded with
     fetchpriority="low" instead of render-blocking <link rel="stylesheet">
     tags. In Chrome/Blink, fetchpriority="low" on a preloaded stylesheet only
     demotes it from the VeryHigh bucket (which render-blocking-style
     preloads get) down to High - it does NOT reach Blink's Low tier. Before
     this fix, these stylesheets (6 on pages using the full vendors.css, or
     7 on pages using the split vendors-core + vendors-home/tour variants)
     sat at VeryHigh while the LCP image (with
     fetchpriority="high") sat at High, so the image was strictly outranked
     by every stylesheet - a real priority inversion, not mere "same tier"
     contention. This fix brings the stylesheets down to High, tying them
     with the image's own explicit preload below (also fetchpriority="high"),
     which removes the inversion so the image gets a fair share of bandwidth
     instead of being starved below the sheets entirely. It does not achieve
     true separation (image outright beating the sheets); that would require
     a different technique, e.g. the media="print" onload-swap idiom, which
     does reach Blink's Low tier, if ever pursued as a follow-up. Measured via
     CDP against actual Chrome priority buckets. See
     docs/superpowers/specs/2026-08-02-tour-pages-lcp-priority-fix-design.md. -->
<link rel="preload" href="/fonts/fonts.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
<!-- COMMON CSS -->
<link rel="preload" href="/css/bootstrap.min.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bootstrap.min.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/style.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/style.css" rel="stylesheet"></noscript>
<?php if (!empty($vendor_css_variant) && in_array($vendor_css_variant, ['home', 'tour'], true)): ?>
<link rel="preload" href="/css/vendors-core.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/vendors-<?= $vendor_css_variant ?>.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-<?= $vendor_css_variant ?>.css" rel="stylesheet"></noscript>
<?php elseif (!empty($vendor_css_variant) && $vendor_css_variant === 'core'): ?>
<link rel="preload" href="/css/vendors-core.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<?php else: ?>
<link rel="preload" href="/css/vendors.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<?php endif; ?>
<link rel="preload" href="/css/bs-icon-font/bootstrap-icons.min.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"></noscript>
<!-- CUSTOM CSS -->
<link rel="preload" href="/css/custom.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/custom.css" rel="stylesheet"></noscript>
<?php else: ?>
<link href="/fonts/fonts.css" rel="stylesheet"/>
<!-- COMMON CSS -->
<link href="/css/bootstrap.min.css" rel="stylesheet"/>
<link href="/css/style.css" rel="stylesheet"/>
<?php if (!empty($vendor_css_variant) && in_array($vendor_css_variant, ['home', 'tour'], true)): ?>
<link href="/css/vendors-core.css" rel="stylesheet"/>
<link href="/css/vendors-<?= $vendor_css_variant ?>.css" rel="stylesheet"/>
<?php elseif (!empty($vendor_css_variant) && $vendor_css_variant === 'core'): ?>
<link href="/css/vendors-core.css" rel="stylesheet"/>
<?php else: ?>
<link href="/css/vendors.css" rel="stylesheet"/>
<?php endif; ?>
<link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"/>
<!-- CUSTOM CSS -->
<link href="/css/custom.css" rel="stylesheet"/>
<?php endif; ?>
