# True Priority Separation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `fetchpriority="low"` preload pattern for deferred stylesheets with the `media="print"` onload-swap idiom, which reaches Blink's real `Low` priority tier instead of merely tying with the LCP image's `High` tier — resolving the ambiguous priority tie identified as the likely cause of a bimodal LCP/CLS split observed in PageSpeed Insights after yesterday's fix.

**Architecture:** Same 7 stylesheet-loading sites as yesterday's plan (6 shared stylesheets in `includes/head.php`, plus `timeline.css` on each of the 5 tour pages) — each `<link rel="preload" href="..." as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">` becomes `<link rel="stylesheet" href="..." media="print" onload="this.media='all';this.onload=null;">`. The `<noscript>` fallback tags, the `$critical_css_file` and `$lcp_preload_image` mechanisms, and everything else are untouched.

**Tech Stack:** Plain HTML/PHP, the `media="print"` onload-swap ("loadCSS") technique — a long-established, widely-supported pattern, no new dependencies.

## Global Constraints

- Only `includes/head.php` and the 5 tour pages' `timeline.css` lines change. No other files.
- The `<?php else: ?>` branch in `includes/head.php` (plain blocking `<link>` tags for pages without critical CSS) is completely unchanged.
- `<noscript>` fallback tags are completely unchanged on all 7 sites.
- The `$critical_css_file` and `$lcp_preload_image` blocks in `includes/head.php` are untouched by this plan.

---

### Task 1: Convert the 6 shared stylesheets to the `media="print"` swap pattern

**Files:**
- Modify: `includes/head.php:102-133` (the comment above the block, plus the 6 stylesheet `<link>` tags)

**Interfaces:**
- Consumes: nothing (first task).
- Produces: nothing new for later tasks — Task 2 is independent.

- [ ] **Step 1: Replace the comment and the 6 stylesheet tags**

Find (currently lines 102-133):

```html
<!-- Pages with matching critical CSS above already have everything needed
     for first paint inlined, so these stylesheets are preloaded with
     fetchpriority="low" instead of render-blocking <link rel="stylesheet">
     tags. In Chrome/Blink, fetchpriority="low" on a preloaded stylesheet only
     demotes it from the VeryHigh bucket (which render-blocking-style
     preloads get) down to High - it does NOT reach Blink's Low tier. Before
     this fix, these 7 stylesheets sat at VeryHigh while the LCP image (with
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
<link rel="preload" href="/css/vendors.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/bs-icon-font/bootstrap-icons.min.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"></noscript>
<!-- CUSTOM CSS -->
<link rel="preload" href="/css/custom.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/custom.css" rel="stylesheet"></noscript>
```

Replace with:

```html
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
```

The `<?php else: ?>` branch that follows (plain blocking `<link>` tags) is unchanged — leave it exactly as-is.

- [ ] **Step 2: Lint and verify**

```bash
php -l includes/head.php
grep -c 'media="print"' includes/head.php
grep -c 'fetchpriority="low"' includes/head.php
```

Expected: `No syntax errors detected`. First grep returns `6` (the 6 shared stylesheet tags — note the code comment also mentions the technique by name in prose, so don't be alarmed if a broader search of the file shows more; this specific grep pattern only matches the actual attribute). Second grep returns `0` (no `fetchpriority="low"` remains in this file).

- [ ] **Step 3: Commit**

```bash
git add includes/head.php
git commit -m "Switch deferred stylesheets to media=print swap for true priority separation"
```

---

### Task 2: Convert `timeline.css` to the same pattern on all 5 tour pages

**Files:**
- Modify: `portillo-inca-lagoon-andes-mountains-vineyard.php:12`
- Modify: `maipo-valley-wine-tour-santiago.php:12`
- Modify: `discover-santiago-city-tour.php:12`
- Modify: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php:13`
- Modify: `cruise-transfer.php:42`

**Interfaces:**
- Consumes: nothing from Task 1 — independent change.
- Produces: nothing further — Task 3 verifies both.

- [ ] **Step 1: Replace `timeline.css`'s preload tag on all 5 tour pages**

Each of the 5 tour pages has this exact line (find/replace identically on all 5):

Find:
```html
  <link rel="preload" href="css/timeline.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
```

Replace with:
```html
  <link rel="stylesheet" href="css/timeline.css" media="print" onload="this.media='all';this.onload=null;">
```

The line immediately after (the `<noscript>` fallback) is unchanged on all 5.

- [ ] **Step 2: Lint and verify**

```bash
php -l portillo-inca-lagoon-andes-mountains-vineyard.php
php -l maipo-valley-wine-tour-santiago.php
php -l discover-santiago-city-tour.php
php -l valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php
php -l cruise-transfer.php
grep -c 'media="print"' portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
```

Expected: all `php -l` calls report `No syntax errors detected`; each grep count is `1`.

- [ ] **Step 3: Commit**

```bash
git add portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
git commit -m "Switch timeline.css to media=print swap for true priority separation"
```

---

### Task 3: Verify priority separation and no regression

**Files:**
- None modified — this task only verifies. If a check fails, revisit Tasks 1-2's changes in place, then re-verify.

**Interfaces:**
- Consumes: the working `includes/head.php` and 5 tour pages from Tasks 1-2.
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

- [ ] **Step 2: Confirm the priority separation directly via CDP**

Using Puppeteer with a CDP session, load `http://localhost:8899/maipo-valley-wine-tour-santiago.php` and capture `Network.requestWillBeSent` events, reading the `initialPriority` field for: the LCP image request (`img/Tours/Maipo/big.jpg`) and at least 2 of the deferred stylesheet requests (e.g. `vendors.css`, `bootstrap.min.css`). This is the same methodology the final reviewer of yesterday's plan used to diagnose the `High`/`High` tie.

Expected: the LCP image's `initialPriority` is `High` (unchanged from yesterday — its own preload is untouched by this plan). The deferred stylesheets' `initialPriority` should now be `Low` — strictly below the image's `High`, not tied with it. If any deferred stylesheet still reports `High` or higher, that's a real problem to investigate before proceeding (the `media="print"` technique not taking effect as expected).

- [ ] **Step 3: Confirm no regression in the throttled-network download-time improvement**

Using the same throttled-network methodology from yesterday's plan (CDP `Emulation.setDeviceMetricsOverride` 412×823 mobile, `Network.emulateNetworkConditions` ~1.6Mbps down/750Kbps up/150ms latency, `Emulation.setCPUThrottlingRate` rate 4, fresh incognito context, `waitUntil: 'load'`), measure the LCP image's `responseEnd` for both Maipo (`img/Tours/Maipo/big.jpg`) and the homepage (`img/Tours/portada.webp`).

Expected: results at least as good as yesterday's post-fix numbers (Maipo ~2.5s, homepage ~8.6s under this profile) — ideally better, now that the image isn't sharing a priority tier with the stylesheets. Record the exact numbers in the task report regardless of outcome.

- [ ] **Step 4: Visual regression check under throttling**

Using the same throttled CDP session, load Maipo, wait for the `load` event, then scroll to and screenshot the photo-gallery and itinerary sections (same check as yesterday's Task 3). Confirm both are fully styled with no unstyled flash — `media="print"` swap has the same "invisible until swap" visual behavior as the previous approach, so this should be unaffected, but confirm directly.

- [ ] **Step 5: Lint and tag-balance check**

```bash
php -l includes/head.php
for f in portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php; do
  php -l "$f"
  echo "$f: <div>=$(grep -c '<div' "$f") </div>=$(grep -c '</div>' "$f")"
done
```

Expected: no syntax errors; `<div>`/`</div>` counts match on each tour page (unchanged from before this plan).

- [ ] **Step 6: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 7: If any check failed, fix and re-verify**

Repeat Steps 1-6 after any fix. Do not proceed to Task 4 until every check in Steps 2-5 passes.

- [ ] **Step 8: Commit (only if Step 7 required a fix)**

```bash
git add includes/head.php portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
git commit -m "Fix issue found during priority-separation verification"
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

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to pull on the cPanel server (via Git Version Control's Pull or Deploy), and per the caching issue discovered earlier this session, likely also needs to purge the Cloudflare cache — including the HTML pages themselves, since the critical CSS is inlined into them. Note: the separate Cloudflare "Browser Cache TTL" issue discovered today (static assets capped at a 4-hour `Cache-Control: max-age` regardless of origin config) is unrelated to this specific deploy step and doesn't block it — that's a distinct, already-flagged follow-up pending HostGator support.

- [ ] **Step 3: Once deployed and cache-purged, re-run PageSpeed Insights (mobile) on Maipo at least 4 times**

This matches the run count that first revealed the bimodal LCP/CLS pattern. Check whether the numbers now cluster around a single outcome instead of splitting evenly between ~2.6s and ~6.5s LCP. Also run it at least once on the homepage. If the PSI API's daily quota is exhausted, use the headless-browser method against pagespeed.web.dev established earlier this session. If the bimodal split persists despite this fix, that's a real, informative result worth reporting back — it would mean the split has a different root cause than the priority tie, not that this plan's change was wrong (see the design spec's Risks section).
