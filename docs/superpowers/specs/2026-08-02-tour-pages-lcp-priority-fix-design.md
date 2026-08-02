# Fix LCP regression from stylesheet/image priority contention on tour pages

## Context

After the tour-pages critical-CSS plan shipped, a PageSpeed Insights mobile check on Maipo showed a real, reproducible regression: LCP went from 4.4s (baseline, after the earlier responsive-images work) to 6.5s, while FCP and CLS both improved substantially (FCP 3.3s→1.7s, CLS 0.127→0.024). This was root-caused via direct investigation (systematic-debugging), not assumed:

1. PSI's LCP breakdown panel initially appeared to show a different LCP element (a lazy-loaded gallery thumbnail) than the pre-plan baseline (the eagerly-loaded `tour-banner-bg` image). Direct measurement in a real browser, using the actual `PerformanceObserver({type: 'largest-contentful-paint'})` API against the live production page under throttled network conditions (1.6Mbps down, 150ms latency, 4x CPU throttling — approximating Lighthouse's mobile profile), showed this was not the real mechanism: the banner image (`img/Tours/Maipo/big.jpg`, unchanged by any of this session's work) remains the actual final LCP element, but its own network request now takes until ~6.6s to complete — closely matching PSI's reported 6.5s.
2. Comparing the same throttled-network measurement against the pre-plan code (checked out via `git archive` at the commit immediately before the critical-CSS plan started, served locally) showed the identical image file completing in ~2.6-2.8s under the same throttling — confirming this is a real regression, not measurement noise or Lantern-simulation artifact.
3. The mechanism: before this plan, the site's shared stylesheets (`fonts.css`, `bootstrap.min.css`, `style.css`, `vendors.css`, `bootstrap-icons.min.css`, `custom.css`) loaded as plain blocking `<link rel="stylesheet">` tags on tour pages. The critical-CSS plan converted them to `<link rel="preload" as="style">` (matching the already-shipped homepage pattern) and added a 7th preloaded stylesheet (`timeline.css`). Chrome's fetch scheduler treats `rel="preload"` resources as high priority — the same tier as the banner image's `fetchpriority="high"` `<img>` tag. Going from a couple of high-priority requests to 7+ dilutes each one's fair share of bandwidth under a throttled connection, delaying the LCP image's completion even though neither its priority attribute nor its file changed.
4. This is a known category of tradeoff for the render-blocking-CSS-elimination technique (the original homepage critical-CSS spec's Risks section flagged that LCP might not improve, or might improve less than FCP) — but here it went further, into an actual regression, specifically because of how many resources now compete at the same priority tier.
5. `includes/head.php`'s stylesheet-preload block is shared between the homepage and the 5 tour pages (both set `$critical_css_file`), so this same mechanism plausibly also affects the homepage's own hero image — its LCP (last measured at 8.0s, down from 10.0s after the responsive-images fix, but never fully root-caused beyond that one known contributor) may partly reflect this same stylesheet-priority contention, compounding with the already-addressed tour-card-image bandwidth contention.

## Goals

- Restore the LCP-critical banner/hero image's effective download priority relative to the now-larger set of preloaded stylesheets, without reintroducing render-blocking CSS or losing the FCP/CLS gains the critical-CSS work already delivered.
- Apply the fix once, in shared code, so it benefits the homepage and all 5 tour pages consistently (matching how the underlying preload mechanism is already shared).

## Non-goals

- Rewriting or removing the critical-CSS mechanism itself. It's working as designed for FCP/CLS; this spec only rebalances resource priority among what it already defers.
- Reducing the byte size of `vendors.css`, `bootstrap.min.css`, or `js/common_scripts_min.js` (the latter is a large, 115-319KB JS bundle that also competes for bandwidth — a real contributor, but a separate, much larger effort out of scope here).
- Fully re-solving the homepage's LCP number. The homepage has a separate, already-addressed contributor (tour-card images racing the hero) and this spec's fix is a plausible additional improvement there, not a guarantee of hitting a specific target.
- Touching `shopping.php` — it doesn't use `includes/head.php` and wasn't part of the critical-CSS work that caused this regression.

## Design

### 1. Lower priority on stylesheets the critical CSS already makes non-urgent

In `includes/head.php`'s existing `$critical_css_file`-gated preload block (lines 87-106), add `fetchpriority="low"` to each of the 6 `<link rel="preload">` tags:

```html
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<!-- Pages with matching critical CSS above already have everything needed
     for first paint inlined, so these can safely load at low fetch priority
     - freeing bandwidth for the page's LCP image, which competes for the
     same high-priority tier as preloaded stylesheets under a throttled
     connection. See docs/superpowers/specs/2026-08-02-tour-pages-lcp-priority-fix-design.md. -->
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
... (unchanged blocking fallback for pages without critical CSS)
<?php endif; ?>
```

This also replaces the stale comment (currently says "Homepage has matching critical CSS above... Other pages (below) don't have their own critical CSS yet" — no longer accurate since the 5 tour pages now share this same path) with an accurate one.

Same treatment for each tour page's own `timeline.css` preload (currently `<link rel="preload" href="css/timeline.css" as="style" onload="...">`, added identically to all 5 pages by the prior plan): add `fetchpriority="low"`.

`fetchpriority` is a standard, widely-supported attribute (Chrome, Edge, and other Chromium browsers — which is what Lighthouse/PSI tests against and what the large majority of mobile traffic uses) that only affects fetch scheduling, not the existing `onload`-swap behavior — the `<noscript>` fallback and the `rel` swap logic are untouched.

### 2. Explicitly preload the LCP image (belt-and-suspenders)

Each of the 5 tour pages and the homepage already has `fetchpriority="high"` on its LCP `<img>` tag, and PSI's own `lcp-discovery-insight` audit confirms this is already sufficient for early discovery (`requestDiscoverable: true`, `priorityHinted: true`). An explicit `<link rel="preload" as="image">` is unlikely to move the needle much on its own, since discovery isn't the bottleneck — but it's a standard, low-cost addition recommended by Core Web Vitals guidance, so it's included as a secondary reinforcement alongside the real fix (Part 1).

Add a new PHP variable, `$lcp_preload_image`, following the same established pattern as `$critical_css_file`: each page sets it before including `head.php`, and `head.php` emits a preload tag when it's present.

`index.php`:
```php
$lcp_preload_image = 'img/Tours/portada.webp';
```

Each tour page (example, Andes):
```php
$lcp_preload_image = 'img/Tours/Andes/big.jpg';
```
(Maipo: `img/Tours/Maipo/big.jpg`; Santiago: `img/Tours/Stgo/big.jpg`; Valparaíso: `img/Tours/Valpo/big.jpg`; Cruise: `img/Tours/Cruise/big.jpg`.)

`includes/head.php`, new block (placed near the top of the preload section, before the stylesheet preloads, so the browser's preload scanner discovers it as early as possible):
```php
<?php if (!empty($lcp_preload_image)): ?>
<link rel="preload" as="image" href="/<?= htmlspecialchars($lcp_preload_image, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
<?php endif; ?>
```

This is independent of `$critical_css_file` — it doesn't require critical CSS to be present, though in practice all 6 pages that will set it also have critical CSS today.

## Verification

1. `php -l` on all modified files (`includes/head.php`, `index.php`, and the 5 tour pages).
2. Confirm via `curl`/grep that every preload `<link>` in the affected block now has `fetchpriority="low"`, and that the new image-preload tag appears exactly once per page that sets `$lcp_preload_image`, on a local `php -S` server.
3. **The real test**: repeat the throttled-network measurement methodology from the investigation (CDP `Network.emulateNetworkConditions` at ~1.6Mbps/150ms latency + 4x CPU throttling, `PerformanceObserver` for `largest-contentful-paint`) against the local server, for at least Maipo and the homepage. Compare the LCP image's `responseEnd` timing against both the current (regressed, ~6.6s) and pre-plan (~2.6-2.8s) baselines already captured during the investigation. Success is the image's completion time moving back toward the pre-plan range, not just "some improvement."
4. Visual regression check: confirm no flash of unstyled content appears below the fold on a slow connection — lowering the deferred stylesheets' priority means they finish loading a bit later in absolute terms, even though this doesn't affect the already-covered above-the-fold critical CSS content. Spot-check by throttling and scrolling to the gallery/itinerary sections shortly after load.
5. Once deployed and cache-purged (same caveat as before — critical CSS is inlined into cached HTML, so an HTML-including purge is needed), re-run PageSpeed Insights (mobile) on Maipo and the homepage, comparing LCP specifically against the regressed numbers (6.5s Maipo, 8.0s homepage) and the pre-regression baselines (4.4s Maipo, prior to this specific change).

## Risks

- **`fetchpriority="low"` could delay a deferred stylesheet enough to be visible if a real user scrolls unusually fast on a very slow connection.** Judged low-risk: these stylesheets are already loading asynchronously post-first-paint by design, and lowering priority shifts them later only relative to other requests, not to an absolute delay — verification step 4 explicitly checks for this.
- **This doesn't address the JS bundle (`js/common_scripts_min.js`, 115-319KB) also competing for bandwidth.** A real contributor visible in the investigation's network traces, but a much larger, separate effort (bundle splitting/trimming) — flagged as a follow-up, not fixed here.
- **The homepage's LCP improvement from this fix is a hypothesis, not confirmed** the way the tour-pages regression's root cause is. Verification step 5 tests it directly; if the homepage doesn't improve, that's still a valid, informative result (its hero already has an independently-confirmed contributor — tour-card images — that this fix doesn't touch).
