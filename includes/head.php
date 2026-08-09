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
 *   $lcp_preload_image_mobile (optional) - root-relative path to a mobile-sized
 *                        (780px wide) variant of $lcp_preload_image. When set
 *                        together with $lcp_preload_image and the mobile file
 *                        exists, TWO preload links are emitted, each gated by
 *                        a media attribute mirroring the site's
 *                        (max-width: 767px) mobile breakpoint used by the
 *                        corresponding <picture> markup, so only the resource
 *                        matching the visitor's actual viewport is fetched.
 *                        This deliberately avoids imagesrcset/imagesizes,
 *                        whose DPR-aware selection algorithm does not agree
 *                        with a plain media-query-based <picture> element and
 *                        was found to cause double-downloads on real phones
 *                        (DPR >= 2).
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
<?php if (!empty($lcp_preload_image_mobile) && is_file(__DIR__ . '/../' . $lcp_preload_image_mobile)): ?>
<link rel="preload" as="image" href="/<?= htmlspecialchars($lcp_preload_image_mobile, ENT_QUOTES, 'UTF-8') ?>" media="(max-width: 767px)" fetchpriority="high">
<link rel="preload" as="image" href="/<?= htmlspecialchars($lcp_preload_image, ENT_QUOTES, 'UTF-8') ?>" media="(min-width: 768px)" fetchpriority="high">
<?php else: ?>
<link rel="preload" as="image" href="/<?= htmlspecialchars($lcp_preload_image, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
<?php endif; ?>
<?php endif; ?>

<!-- GOOGLE WEB FONT (self-hosted) -->
<link rel="preconnect" href="https://cdn.openwidget.com">
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<!-- Pages with matching critical CSS above already have everything needed
     for first paint inlined, so these stylesheets load via the
     media="print" onload-swap idiom instead of render-blocking
     <link rel="stylesheet"> tags. An earlier fix (2026-08-02) tried
     rel="preload" + fetchpriority="low", but that only demotes Chrome/
     Blink's scheduling from the VeryHigh bucket down to High - it never
     reaches the real Low tier, so these stylesheets stayed tied with the
     LCP image's own fetchpriority="high" preload below, splitting
     bandwidth between them under a throttled connection instead of the
     image getting the share it needs. media="print" does reach Blink's
     genuine Low tier (the browser doesn't need it for the current
     screen-rendering context), so the image can now win outright. Once
     the file loads, onload flips media to 'all' and the browser applies
     it immediately, same as before. See
     docs/superpowers/specs/2026-08-08-stylesheet-priority-media-print-design.md
     and docs/superpowers/specs/2026-08-02-tour-pages-lcp-priority-fix-design.md
     for the full history. -->
<link rel="stylesheet" href="/fonts/fonts.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
<!-- COMMON CSS -->
<link rel="stylesheet" href="/css/bootstrap.min.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/bootstrap.min.css" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/css/style.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/style.css" rel="stylesheet"></noscript>
<?php if (!empty($vendor_css_variant) && in_array($vendor_css_variant, ['home', 'tour'], true)): ?>
<link rel="stylesheet" href="/css/vendors-core.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/css/vendors-<?= $vendor_css_variant ?>.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/vendors-<?= $vendor_css_variant ?>.css" rel="stylesheet"></noscript>
<?php elseif (!empty($vendor_css_variant) && $vendor_css_variant === 'core'): ?>
<link rel="stylesheet" href="/css/vendors-core.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<?php else: ?>
<link rel="stylesheet" href="/css/vendors.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<?php endif; ?>
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
