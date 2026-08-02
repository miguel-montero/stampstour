# Tour Pages LCP Priority-Contention Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix a confirmed LCP regression (Maipo mobile: 4.4s → 6.5s) caused by the tour-pages critical-CSS work converting 6 shared stylesheets plus `timeline.css` to high-priority preloads, which now dilute the LCP image's fair share of bandwidth under throttled connections. Fix: lower the deferred stylesheets' fetch priority (they're no longer render-blocking-critical now that critical CSS covers first paint), and explicitly preload each page's LCP image as reinforcement.

**Architecture:** Two independent changes to `includes/head.php`'s existing `$critical_css_file`-gated preload block (shared by the homepage and all 5 tour pages) plus 5 tour pages' individual `timeline.css` preload tags: (1) add `fetchpriority="low"` to every preloaded stylesheet `<link>`, (2) add a new `$lcp_preload_image`-gated block that emits an explicit `<link rel="preload" as="image" fetchpriority="high">` for the page's LCP image, following the exact same per-page-variable pattern already established by `$critical_css_file`.

**Tech Stack:** Plain PHP includes, vanilla HTML attributes, no build step. `fetchpriority` is a standard HTML attribute understood by Chromium-based browsers (what Lighthouse/PSI tests against and the majority of mobile traffic uses); unsupported browsers simply ignore it with no functional impact.

## Global Constraints

- Only `includes/head.php`, `index.php`, and the 5 tour pages (`portillo-inca-lagoon-andes-mountains-vineyard.php`, `maipo-valley-wine-tour-santiago.php`, `discover-santiago-city-tour.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`, `cruise-transfer.php`) change. `shopping.php` is untouched (doesn't use `includes/head.php`).
- The existing `onload="this.onload=null;this.rel='stylesheet'"` swap mechanism and `<noscript>` fallbacks are untouched — `fetchpriority` only affects fetch scheduling, nothing else.
- `$lcp_preload_image` values (root-relative paths, no leading slash needed — `head.php` adds it) must exactly match each page's existing LCP `<img>` `src` attribute:
  - `index.php`: `img/Tours/portada.webp`
  - Andes: `img/Tours/Andes/big.jpg`
  - Maipo: `img/Tours/Maipo/big.jpg`
  - Santiago: `img/Tours/Stgo/big.jpg`
  - Valparaíso: `img/Tours/Valpo/big.jpg`
  - Cruise: `img/Tours/Cruise/big.jpg`

---

### Task 1: Lower priority on deferred stylesheets (the real fix)

**Files:**
- Modify: `includes/head.php:87-106` (the `$critical_css_file`-gated preload block, plus its stale comment)
- Modify: `portillo-inca-lagoon-andes-mountains-vineyard.php:11`
- Modify: `maipo-valley-wine-tour-santiago.php:11`
- Modify: `discover-santiago-city-tour.php:11`
- Modify: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php:12`
- Modify: `cruise-transfer.php:41`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: nothing new for later tasks — Task 2 is independent.

- [ ] **Step 1: Update `includes/head.php`'s preload block**

Find (currently lines 87-106):

```html
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<!-- Homepage has matching critical CSS above, so it's safe to defer these -
     see the comment on the critical-CSS block. Other pages (below) don't
     have their own critical CSS yet, so they keep blocking stylesheets to
     avoid a flash of unstyled content - see docs/superpowers/plans/2026-08-01-homepage-critical-css.md
     final review for why this couldn't safely be sitewide. -->
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
<?php else: ?>
```

Replace with:

```html
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<!-- Pages with matching critical CSS above already have everything needed
     for first paint inlined, so these can safely load at low fetch priority
     - freeing bandwidth for the page's LCP image, which otherwise competes
     for the same high-priority tier as preloaded stylesheets under a
     throttled connection. See
     docs/superpowers/specs/2026-08-02-tour-pages-lcp-priority-fix-design.md. -->
<link rel="preload" href="/fonts/fonts.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
<!-- COMMON CSS -->
<link rel="preload" href="/css/bootstrap.min.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bootstrap.min.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/style.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/style.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/vendors.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/bs-icon-font/bootstrap-icons.min.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"></noscript>
<!-- CUSTOM CSS -->
<link rel="preload" href="/css/custom.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/custom.css" rel="stylesheet"></noscript>
<?php else: ?>
```

The `<?php else: ?>` branch (plain blocking `<link>` tags for pages without critical CSS) is unchanged — leave it exactly as-is.

- [ ] **Step 2: Add `fetchpriority="low"` to `timeline.css`'s preload on all 5 tour pages**

Each of the 5 tour pages has this exact line (find/replace identically on all 5):

Find:
```html
  <link rel="preload" href="css/timeline.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
```

Replace with:
```html
  <link rel="preload" href="css/timeline.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
```

Apply to: `portillo-inca-lagoon-andes-mountains-vineyard.php`, `maipo-valley-wine-tour-santiago.php`, `discover-santiago-city-tour.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`, `cruise-transfer.php`. The line immediately after (the `<noscript>` fallback) is unchanged on all 5.

- [ ] **Step 3: Lint and verify**

```bash
php -l includes/head.php
php -l portillo-inca-lagoon-andes-mountains-vineyard.php
php -l maipo-valley-wine-tour-santiago.php
php -l discover-santiago-city-tour.php
php -l valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php
php -l cruise-transfer.php
grep -c 'fetchpriority="low"' includes/head.php
grep -c 'timeline.css.*fetchpriority="low"' portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
```

Expected: all `php -l` calls report `No syntax errors detected`. `includes/head.php` grep returns `6` (one per stylesheet). Each tour page's grep returns `1`.

- [ ] **Step 4: Commit**

```bash
git add includes/head.php portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
git commit -m "Lower fetch priority on deferred stylesheets to reduce LCP contention"
```

---

### Task 2: Explicitly preload each page's LCP image

**Files:**
- Modify: `includes/head.php` (new block, placed before the stylesheet preload block from Task 1, after the critical-CSS `<style>` block)
- Modify: `index.php:1-11`
- Modify: `portillo-inca-lagoon-andes-mountains-vineyard.php:1-5`
- Modify: `maipo-valley-wine-tour-santiago.php:1-5`
- Modify: `discover-santiago-city-tour.php:1-5`
- Modify: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php:1-5`
- Modify: `cruise-transfer.php:24-33`

**Interfaces:**
- Consumes: nothing from Task 1 — independent change.
- Produces: the `$lcp_preload_image` variable convention, mirroring the existing `$critical_css_file` pattern — any page wanting an explicit LCP-image preload sets this PHP variable (a root-relative path, no leading slash) before including `includes/head.php`.

- [ ] **Step 1: Add the preload block to `includes/head.php`**

Find the closing `<?php endif; ?>` of the critical-CSS `<style>` block (currently lines 81-83):

```php
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<style><?= file_get_contents($critical_css_file) ?></style>
<?php endif; ?>
```

Add immediately after it (before the `<!-- GOOGLE WEB FONT -->` comment that follows):

```php
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<style><?= file_get_contents($critical_css_file) ?></style>
<?php endif; ?>

<?php if (!empty($lcp_preload_image)): ?>
<link rel="preload" as="image" href="/<?= htmlspecialchars($lcp_preload_image, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
<?php endif; ?>
```

- [ ] **Step 2: Set `$lcp_preload_image` on `index.php`**

Find (currently lines 5-11, ending at the `$critical_css_file` line added by an earlier plan):

```php
$page_og = [
  'title'       => 'Stampstour - Discover Chile',
  'description' => 'Daily tours to Valparaíso, Maipo Wine Valley, Andes & Santiago. Book your curated experience with Stampstour!',
  'url'         => 'https://stampstour.com/',
  'image'       => 'https://stampstour.com/img/Tours/portada.jpg',
];
$critical_css_file = __DIR__ . '/includes/critical/home.css';
?>
```

Replace with:

```php
$page_og = [
  'title'       => 'Stampstour - Discover Chile',
  'description' => 'Daily tours to Valparaíso, Maipo Wine Valley, Andes & Santiago. Book your curated experience with Stampstour!',
  'url'         => 'https://stampstour.com/',
  'image'       => 'https://stampstour.com/img/Tours/portada.jpg',
];
$critical_css_file = __DIR__ . '/includes/critical/home.css';
$lcp_preload_image = 'img/Tours/portada.webp';
?>
```

- [ ] **Step 3: Set `$lcp_preload_image` on the 4 straightforward tour pages**

For each of `portillo-inca-lagoon-andes-mountains-vineyard.php`, `maipo-valley-wine-tour-santiago.php`, `discover-santiago-city-tour.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`: find the `$critical_css_file` line (added by the prior tour-pages-critical-css plan) and add the new variable immediately after it, before the closing `?>`. Example for Andes:

Find:
```php
$page_canonical   = 'https://stampstour.com/portillo-inca-lagoon-andes-mountains-vineyard';
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
?>
```

Replace with:
```php
$page_canonical   = 'https://stampstour.com/portillo-inca-lagoon-andes-mountains-vineyard';
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
$lcp_preload_image = 'img/Tours/Andes/big.jpg';
?>
```

Apply the same pattern to the other 3, using the exact values from Global Constraints:
- Maipo: `$lcp_preload_image = 'img/Tours/Maipo/big.jpg';`
- Santiago: `$lcp_preload_image = 'img/Tours/Stgo/big.jpg';`
- Valparaíso: `$lcp_preload_image = 'img/Tours/Valpo/big.jpg';`

- [ ] **Step 4: Set `$lcp_preload_image` on `cruise-transfer.php`**

This page has database-fetching PHP logic before its page-metadata block (same structure noted in the prior critical-CSS plan), so the metadata block is later in the file (around line 24-33). Find:

```php
$page_og = [
  'title'       => 'Cruise Transfer ↔ Santiago with Valparaiso Tour & Casablanca Wine Tasting | Stamps Tour',
  'description' => $page_description,
  'url'         => $page_canonical,
  'image'       => 'https://stampstour.com/img/Tours/Cruise/big.jpg',
];
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
?>
```

Replace with:

```php
$page_og = [
  'title'       => 'Cruise Transfer ↔ Santiago with Valparaiso Tour & Casablanca Wine Tasting | Stamps Tour',
  'description' => $page_description,
  'url'         => $page_canonical,
  'image'       => 'https://stampstour.com/img/Tours/Cruise/big.jpg',
];
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
$lcp_preload_image = 'img/Tours/Cruise/big.jpg';
?>
```

Do NOT add this to the earlier `<?php require __DIR__ . '/../db_config.php'; ... ?>` block at the top of the file — it must go in the second PHP block, immediately after `$critical_css_file`.

- [ ] **Step 5: Lint and verify**

```bash
php -l includes/head.php
php -l index.php
php -l portillo-inca-lagoon-andes-mountains-vineyard.php
php -l maipo-valley-wine-tour-santiago.php
php -l discover-santiago-city-tour.php
php -l valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php
php -l cruise-transfer.php
grep -c "lcp_preload_image" index.php portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
```

Expected: all `php -l` calls report `No syntax errors detected`. Each of the 6 grep counts (one per file listed) is `1` — the single `$lcp_preload_image = ...;` assignment line in that page.

- [ ] **Step 6: Commit**

```bash
git add includes/head.php index.php portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
git commit -m "Explicitly preload each page's LCP image"
```

---

### Task 3: Verify the fix under throttled network conditions

**Files:**
- None modified — this task only verifies. If a check fails, revisit Tasks 1-2's changes in place, then re-verify.

**Interfaces:**
- Consumes: the working `includes/head.php` and 6 pages from Tasks 1-2.
- Produces: verification evidence only.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/index.php
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/maipo-valley-wine-tour-santiago.php
```

Expected: `200` for both.

- [ ] **Step 2: Confirm markup is correct via `curl`**

```bash
curl -s http://localhost:8899/index.php | grep -o '<link rel="preload" as="image"[^>]*>'
curl -s http://localhost:8899/maipo-valley-wine-tour-santiago.php | grep -o '<link rel="preload" as="image"[^>]*>'
curl -s http://localhost:8899/maipo-valley-wine-tour-santiago.php | grep -c 'fetchpriority="low"'
```

Expected: the image preload line appears once on each page, with the correct `href` (`/img/Tours/portada.webp` for homepage, `/img/Tours/Maipo/big.jpg` for Maipo). The Maipo `fetchpriority="low"` count should be `7` (6 shared stylesheets + timeline.css).

- [ ] **Step 3: Re-run the throttled-network LCP-timing measurement**

Using Puppeteer with a fresh incognito browser context, CDP `Emulation.setDeviceMetricsOverride` for a 412×823 mobile viewport, `Network.emulateNetworkConditions` (downloadThroughput ≈ 1.6 Mbps / 204800 bytes/sec, uploadThroughput ≈ 750 Kbps / 96000 bytes/sec, latency 150ms), and `Emulation.setCPUThrottlingRate` at 4x — the same profile used during the investigation that diagnosed this regression. Navigate to `http://localhost:8899/maipo-valley-wine-tour-santiago.php` with `waitUntil: 'load'`, then read `performance.getEntriesByType('resource')` and find the entry for `img/Tours/Maipo/big.jpg`.

Expected: `responseEnd` for the banner image should be well under the ~6.6s figure measured before this fix — ideally back in the ~2.6-3.5s range that matched the pre-critical-CSS baseline (some increase over the original ~2.6s is acceptable since the image preload is new overhead too, but it should not still be in the 6s+ range). Record the exact number in the task report regardless of outcome — if it's not fully back to baseline, that's useful information, not necessarily a failure requiring more fixes (see the design spec's Risks section on the JS bundle also competing for bandwidth, which this plan doesn't address).

- [ ] **Step 4: Repeat Step 3 for the homepage**

Same methodology, `http://localhost:8899/index.php`, checking the `img/Tours/portada.webp` resource entry's `responseEnd`. Record the result — the design spec explicitly frames the homepage's improvement as a hypothesis to test, not a guarantee.

- [ ] **Step 5: Visual regression check under throttling**

Using the same throttled CDP session, load Maipo, wait for the `load` event, then scroll to the photo-gallery and itinerary sections and screenshot. Confirm both are fully styled (no unstyled flash) — the deferred stylesheets should still have finished loading well before a user could realistically scroll there, even at lower fetch priority.

- [ ] **Step 6: Lint and tag-balance check**

```bash
php -l includes/head.php
php -l index.php
for f in portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php; do
  php -l "$f"
  echo "$f: <div>=$(grep -c '<div' "$f") </div>=$(grep -c '</div>' "$f")"
done
```

Expected: no syntax errors; `<div>`/`</div>` counts match on each tour page (Tasks 1-2 only touch `<head>`-region markup and PHP variable assignments, so this should be unaffected, but confirm).

- [ ] **Step 7: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 8: If any check failed, fix and re-verify**

Repeat Steps 1-7 after any fix. Do not proceed to Task 4 until every check in Steps 2-6 passes (Step 3/4's exact numbers are recorded regardless, per that step's own guidance on partial improvement being acceptable).

- [ ] **Step 9: Commit (only if Step 8 required a fix)**

```bash
git add includes/head.php index.php portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
git commit -m "Fix issue found during LCP priority-fix verification"
```

If no fix was needed, skip this step.

---

### Task 4: Deploy and confirm production

**Files:**
- None modified — this task pushes already-committed changes and confirms the live site.

**Interfaces:**
- Consumes: the commits from Tasks 1-3.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push
```

- [ ] **Step 2: Remind the user to deploy**

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to pull on the cPanel server (via Git Version Control's Pull or Deploy), and per the caching issue discovered earlier this session, likely also needs to purge the Cloudflare cache — including the HTML pages themselves, since the critical CSS is inlined into them.

- [ ] **Step 3: Once deployed and cache-purged, re-run PageSpeed Insights (mobile) on Maipo and the homepage**

Compare LCP specifically against the regressed numbers (Maipo 6.5s, homepage 8.0s) and the pre-regression baseline (Maipo 4.4s). If the PSI API's daily quota is exhausted, use the headless-browser method against pagespeed.web.dev established earlier this session, or defer to a later session — not a blocking step for this plan.
