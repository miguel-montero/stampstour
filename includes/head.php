<?php /* includes/head.php
 * Shared <head> boilerplate for the marketing pages.
 * Caller sets these before including:
 *   $page_title       (required)
 *   $page_description (required)
 *   $page_canonical   (required) - full https://stampstour.com/... URL
 *   $page_og          (optional) - array with keys: title, description, url, image
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

<!-- GOOGLE WEB FONT (self-hosted) -->
<link rel="preconnect" href="https://cdn.openwidget.com">
<link rel="preload" href="/fonts/fonts.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
<!-- COMMON CSS -->
<link rel="preload" href="/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bootstrap.min.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/style.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/style.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/vendors.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/bs-icon-font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"></noscript>
<!-- CUSTOM CSS -->
<link rel="preload" href="/css/custom.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/custom.css" rel="stylesheet"></noscript>
