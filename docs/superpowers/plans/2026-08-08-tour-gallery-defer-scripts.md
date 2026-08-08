# Tour Gallery Defer Scripts + Visibility Safety Net Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cut the real-world (throttled mobile) delay before the tour-page image gallery's JS attaches, and make the gallery immune to CLS regardless of how long that delay ends up being, without changing any other behavior on the 5 affected pages.

**Architecture:** `defer` every `<script src>` tag in the two script-loading blocks that serve these 5 pages, fix the 2 inline `<script>` blocks whose immediate `jQuery(...)` calls would otherwise break under deferred loading, and add a CSS-only `visibility:hidden`-until-ready safety net (with a 10s force-reveal fallback) on top of the already-shipped layout-space reservation.

**Tech Stack:** Plain PHP includes, vanilla JS, CSS. No build step.

## Global Constraints

- Scope is exactly 5 pages: the 4 pages using `includes/tour-scripts.php` (discover-santiago-city-tour.php, maipo-valley-wine-tour-santiago.php, portillo-inca-lagoon-andes-mountains-vineyard.php, valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php) and `cruise-transfer.php`. No other file/page changes.
- Every `<script src>` in scope gets `defer` added. `js/transfer.js` (`cruise-transfer.php`) already has `defer` — leave it as-is.
- Inline `<script>` blocks (no `src`) are never deferred by the browser regardless of the `defer` attribute on other tags — any inline block that references `jQuery`/`$` immediately must be wrapped in `document.addEventListener('DOMContentLoaded', function () { ... })` so it doesn't run until every deferred script above it has executed.
- `#Img_carousel` gets `visibility: hidden` by default (CSS) and is revealed via `.css('visibility', 'visible')` chained directly onto the `.sliderPro({...})` call — not a separate statement, so the reveal can never accidentally run before init completes.
- The CSS safety net (visibility + 10s force-reveal animation) is added to all 3 vendor CSS files that already carry the CLS-fix rules: `css/vendors-tour.css`, `css/vendors.css`, `css/vendors.unminified.css` — keep them byte-for-byte in sync with each other for this new block, the same way the existing CLS-fix rules already are.
- Do not modify `js/jquery.sliderPro.js`, any plugin vendor file, or any page's gallery HTML markup.
- Verification must use throttled conditions (CDP `Network.emulateNetworkConditions` ~1.6 Mbps down / 750 Kbps up / 150ms latency, `Emulation.setCPUThrottlingRate` rate 4, `page.setCacheEnabled(false)`) — fast/cached-condition testing already proved insufficient once on this exact gallery and must not be relied on again as the sole verification.

---

### Task 1: `includes/tour-scripts.php` — defer scripts + fix inline sticky-sidebar init

**Files:**
- Modify: `includes/tour-scripts.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new for other tasks — this file is a leaf include, consumed unchanged by the 4 tour pages.

- [ ] **Step 1: Replace the full script block**

Current content of `includes/tour-scripts.php` (verify it still matches before editing — if it doesn't, stop and report):

```php
<?php /* includes/tour-scripts.php
 * Shared trailing <script> block for the 4 plain tour pages
 * (valparaiso, maipo, portillo/andes, discover-santiago).
 * Caller sets $exp_name before including, e.g.:
 *   <?php $exp_name = 'Maipo'; include __DIR__ . '/includes/tour-scripts.php'; ?>
 */
?>
<!-- jQuery FIRST -->
<script src="js/jquery-3.7.1.min.js"></script>

<!-- Core bundle (Bootstrap) + tour-only extras (Parallax, Magnific Popup,
     daterangepicker + moment). See
     docs/superpowers/specs/2026-08-03-homepage-tour-bundle-split-design.md -->
<script src="js/vendors-core.min.js"></script>
<script src="js/vendors-tour.min.js"></script>

<!-- Site functions (ok after core+extras) -->
<script src="js/functions.js"></script>

<!-- Gallery Plugin -->
<link rel="stylesheet" href="css/slider-pro.min.css">
<script src="js/jquery.sliderPro.min.js"></script>

<!-- Sticky Sidebar -->
<script src="js/theia-sticky-sidebar.js"></script>
<script>
jQuery(function($){
  if ($.fn.theiaStickySidebar) {
    $('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
  }
});
</script>

<!-- Expose tour name BEFORE tours.js -->
<script>window.EXP_NAME = '<?php echo htmlspecialchars($exp_name, ENT_QUOTES, 'UTF-8'); ?>';</script>

<!-- Your custom code LAST -->
<script src="js/tours.js"></script>
```

Replace it entirely with:

```php
<?php /* includes/tour-scripts.php
 * Shared trailing <script> block for the 4 plain tour pages
 * (valparaiso, maipo, portillo/andes, discover-santiago).
 * Caller sets $exp_name before including, e.g.:
 *   <?php $exp_name = 'Maipo'; include __DIR__ . '/includes/tour-scripts.php'; ?>
 *
 * All scripts below are `defer` so they download in parallel instead of
 * one at a time, and don't block HTML parsing/painting - deferred
 * scripts still execute in this exact document order (guaranteed by
 * spec), so the jQuery -> plugin -> tours.js dependency chain stays
 * intact without any changes inside tours.js itself. The inline sticky-
 * sidebar init block below can't itself be deferred (inline scripts
 * always run immediately at their parse position, regardless of defer
 * on surrounding tags), so it's wrapped in a native DOMContentLoaded
 * listener instead - that event only fires after every deferred script
 * above has finished, so jQuery/$ and the plugin are guaranteed to exist
 * by the time this callback runs. See
 * docs/superpowers/specs/2026-08-08-tour-gallery-defer-scripts-design.md
 */
?>
<!-- jQuery FIRST -->
<script defer src="js/jquery-3.7.1.min.js"></script>

<!-- Core bundle (Bootstrap) + tour-only extras (Parallax, Magnific Popup,
     daterangepicker + moment). See
     docs/superpowers/specs/2026-08-03-homepage-tour-bundle-split-design.md -->
<script defer src="js/vendors-core.min.js"></script>
<script defer src="js/vendors-tour.min.js"></script>

<!-- Site functions (ok after core+extras) -->
<script defer src="js/functions.js"></script>

<!-- Gallery Plugin -->
<link rel="stylesheet" href="css/slider-pro.min.css">
<script defer src="js/jquery.sliderPro.min.js"></script>

<!-- Sticky Sidebar -->
<script defer src="js/theia-sticky-sidebar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  jQuery(function($){
    if ($.fn.theiaStickySidebar) {
      $('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
    }
  });
});
</script>

<!-- Expose tour name BEFORE tours.js -->
<script>window.EXP_NAME = '<?php echo htmlspecialchars($exp_name, ENT_QUOTES, 'UTF-8'); ?>';</script>

<!-- Your custom code LAST -->
<script defer src="js/tours.js"></script>
```

- [ ] **Step 2: Verify with `php -l`**

```bash
php -l includes/tour-scripts.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify every `<script src>` in the file has `defer`, and exactly one inline block was changed**

```bash
grep -c "defer" includes/tour-scripts.php
grep -n "<script" includes/tour-scripts.php
```

Expected: 7 lines contain `defer` (6 `<script defer src=...>` tags plus the doc-comment prose mentioning "defer" — if the count differs, check by reading the file directly rather than assuming). The `window.EXP_NAME` inline script and the sticky-sidebar inline script should both still be present, unchanged in their embedded logic (only the sticky-sidebar one gains the `DOMContentLoaded` wrapper).

- [ ] **Step 4: Commit**

```bash
git add includes/tour-scripts.php
git commit -m "perf: defer script loading in tour-scripts.php, fix inline sticky-sidebar init timing"
```

---

### Task 2: `cruise-transfer.php` — defer scripts + fix inline gallery/sticky-sidebar init + reveal chain

**Files:**
- Modify: `cruise-transfer.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by other tasks.

- [ ] **Step 1: Replace the script section**

Locate this exact block near the end of `cruise-transfer.php` (verify it still matches before editing — if it doesn't, stop and report):

```html
<!-- Scripts (jQuery, Bootstrap, plugins) -->
<script src="js/jquery-3.7.1.min.js"></script>
<script src="js/vendor/jquery-ui-autocomplete.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/jquery.sliderPro.min.js"></script>
<script src="js/theia-sticky-sidebar.js"></script>
<script src="js/common_scripts_min.js"></script>
<script src="js/functions.js"></script>

<!-- Initialize gallery slider -->
<script>
  $('#Img_carousel').sliderPro({
    width: 960,
    height: 500,
    fade: true,
    arrows: true,
    buttons: false,
    fullScreen: false,
    smallSize: 500,
    startSlide: 0,
    mediumSize: 1000,
    largeSize: 3000,
    thumbnailArrows: true,
    autoplay: false
  });
</script>

<!-- Activate sticky sidebar -->
<script>
  jQuery('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
</script>
```

Replace it with:

```html
<!-- Scripts (jQuery, Bootstrap, plugins) -->
<!-- All deferred: download in parallel instead of one at a time, execute
     in this exact document order once HTML parsing finishes. See
     docs/superpowers/specs/2026-08-08-tour-gallery-defer-scripts-design.md -->
<script defer src="js/jquery-3.7.1.min.js"></script>
<script defer src="js/vendor/jquery-ui-autocomplete.js"></script>
<script defer src="js/bootstrap.bundle.min.js"></script>
<script defer src="js/jquery.sliderPro.min.js"></script>
<script defer src="js/theia-sticky-sidebar.js"></script>
<script defer src="js/common_scripts_min.js"></script>
<script defer src="js/functions.js"></script>

<!-- Initialize gallery slider + sticky sidebar. Wrapped in DOMContentLoaded
     because inline scripts (no src) can't themselves be deferred - they'd
     otherwise run before the deferred jQuery/plugin scripts above have
     executed. DOMContentLoaded only fires after every deferred script has
     finished, so jQuery/$/the plugins are guaranteed ready here. The
     .css('visibility','visible') reveal is the CSS safety-net pairing -
     see css/vendors-tour.css's #Img_carousel visibility:hidden rule. -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  $('#Img_carousel').sliderPro({
    width: 960,
    height: 500,
    fade: true,
    arrows: true,
    buttons: false,
    fullScreen: false,
    smallSize: 500,
    startSlide: 0,
    mediumSize: 1000,
    largeSize: 3000,
    thumbnailArrows: true,
    autoplay: false
  }).css('visibility', 'visible');

  jQuery('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
});
</script>
```

Leave the rest of the file (the `<script>const prices = ...;</script>` block, the already-deferred `<script defer src="js/transfer.js"></script>`, and the trailing booking-box-move IIFE) completely unchanged.

- [ ] **Step 2: Verify with `php -l`**

```bash
php -l cruise-transfer.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify the reveal chain and DOMContentLoaded wrapper are present**

```bash
grep -n "visibility.*visible\|DOMContentLoaded\|defer src" cruise-transfer.php
```

Expected: 7 `defer src` lines, one `DOMContentLoaded` line, one `.css('visibility', 'visible')` line.

- [ ] **Step 4: Commit**

```bash
git add cruise-transfer.php
git commit -m "perf: defer script loading in cruise-transfer.php, fix inline init timing, add gallery reveal"
```

---

### Task 3: `js/tours.js` — add the visibility reveal chain

**Files:**
- Modify: `js/tours.js`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by other tasks. This is the gallery-init counterpart for the 4 pages using `includes/tour-scripts.php` (Task 1) — `cruise-transfer.php` has its own separate init, already handled in Task 2.

**Context:** `tours.js` is loaded with `defer` as of Task 1. Its top-level `$('#Img_carousel').sliderPro({...})` call does NOT need a `DOMContentLoaded` wrapper — `tours.js` is an external deferred script, and deferred external scripts already execute in guaranteed document order after their dependencies (jQuery, `vendors-tour.min.js`, `jquery.sliderPro.min.js` are all deferred and appear earlier in `includes/tour-scripts.php`). Only the visibility-reveal chain needs adding here.

- [ ] **Step 1: Add the reveal chain**

The first statement in `js/tours.js` is currently:

```js
   $('#Img_carousel').sliderPro({
     width: 960,
     height: 500,
     fade: true,
     arrows: true,
     buttons: false,
     fullScreen: false,
     smallSize: 500,
     startSlide: 0,
     mediumSize: 1000,
     largeSize: 3000,
     thumbnailArrows: true,
     autoplay: false
   });
```

Change the closing `});` to `}).css('visibility', 'visible');` — i.e. the statement becomes:

```js
   $('#Img_carousel').sliderPro({
     width: 960,
     height: 500,
     fade: true,
     arrows: true,
     buttons: false,
     fullScreen: false,
     smallSize: 500,
     startSlide: 0,
     mediumSize: 1000,
     largeSize: 3000,
     thumbnailArrows: true,
     autoplay: false
   }).css('visibility', 'visible');
```

No other line in this statement changes. The rest of `js/tours.js` (date/time picker setup, booking header button logic) is untouched.

- [ ] **Step 2: Verify with Node's syntax checker**

```bash
node --check js/tours.js
```

Expected: no output (exit code 0 means valid syntax).

- [ ] **Step 3: Commit**

```bash
git add js/tours.js
git commit -m "perf: reveal tour gallery only after Slider Pro init completes"
```

---

### Task 4: Visibility safety-net CSS (3 vendor CSS files)

**Files:**
- Modify: `css/vendors-tour.css`
- Modify: `css/vendors.css`
- Modify: `css/vendors.unminified.css`

**Interfaces:**
- Consumes: nothing new.
- Produces: the `#Img_carousel { visibility: hidden; ... }` rule that Tasks 2 and 3's `.css('visibility', 'visible')` calls rely on to have something to override.

**Context:** All 3 files already carry the CLS-fix rules from the earlier plan (`#Img_carousel .sp-slides { aspect-ratio: 960 / 500; }` and `#Img_carousel .sp-thumbnails { min-height: 180px; max-height: 180px; overflow: hidden; white-space: nowrap; }`). This task adds one more rule alongside them in each file, following the same 3-file-sync pattern already established.

- [ ] **Step 1: Append to `css/vendors-tour.css`**

Verify the file currently ends with (read the last ~20 lines to confirm before editing):

```css
#Img_carousel .sp-thumbnails {
  min-height: 180px;
  max-height: 180px;
  overflow: hidden;
  white-space: nowrap;
}
```

Append immediately after that closing `}` (no blank line needed, but one is fine):

```css

/* Visibility safety net (2026-08-08): hide the whole gallery until
   Slider Pro's JS finishes initializing and explicitly reveals it (see
   js/tours.js and cruise-transfer.php's inline init - both chain
   .css('visibility','visible') onto the .sliderPro() call). Elements
   with visibility:hidden occupy their normal layout space but are never
   painted, so the Layout Instability API records zero shift for
   whatever the raw pre-JS markup does internally while hidden, however
   unstable. Combined with the aspect-ratio/min-height/max-height
   reservations above (which keep the *page's* layout stable throughout,
   pre- and post-reveal), this makes the gallery immune to CLS
   regardless of how long script loading ends up taking. The animation
   is a pure-CSS fallback: if JS never runs at all (blocked/failed
   script), force-reveal after 10s rather than staying invisible
   forever - accepts a possible late shift in that rare failure case
   over a gallery that never appears. See
   docs/superpowers/specs/2026-08-08-tour-gallery-defer-scripts-design.md */
#Img_carousel {
  visibility: hidden;
  animation: sp-force-reveal 0s 10s forwards;
}
@keyframes sp-force-reveal {
  to { visibility: visible; }
}
```

- [ ] **Step 2: Append the identical block to `css/vendors.css`**

Verify `css/vendors.css` ends with the same `#Img_carousel .sp-thumbnails {...}` block (it should be byte-identical to `vendors-tour.css`'s, per the earlier plan's sync requirement), then append the exact same new block from Step 1.

- [ ] **Step 3: Insert into `css/vendors.unminified.css`**

This file is unminified/readable source, not a trailing-append target — the existing CLS-fix block lives mid-file, inside the Slider Pro CSS section (around line 16,721-16,731, immediately after `.slider-pro img.sp-layer { border: none; }` and before `/* 11. Bootstrap Date range picker */`). Verify the current content matches:

```css
.slider-pro img.sp-layer {
	border: none;
}

/* CLS fix (2026-08-07, follow-up 2026-08-08): reserve space for the
   Slider Pro gallery before its JS runs and computes real dimensions.
   See css/vendors-tour.css and css/vendors.css for the full rationale
   comment (kept in sync with this source file) - this same block must
   exist in this source file so a future regeneration via clean-css-cli
   doesn't silently drop it. */
#Img_carousel .sp-slides {
	aspect-ratio: 960 / 500;
}
#Img_carousel .sp-thumbnails {
	min-height: 180px;
	max-height: 180px;
	overflow: hidden;
	white-space: nowrap;
}
```

Append immediately after that closing `}` (using tabs for indentation, matching this file's existing style, not the 2-space style used in the generated files):

```css

/* Visibility safety net (2026-08-08): see css/vendors-tour.css and
   css/vendors.css for the full rationale comment (kept in sync with
   this source file). */
#Img_carousel {
	visibility: hidden;
	animation: sp-force-reveal 0s 10s forwards;
}
@keyframes sp-force-reveal {
	to { visibility: visible; }
}
```

Do NOT run any `cleancss`/build step to regenerate `css/vendors-tour.css` or `css/vendors.css` from this file — all 3 files are edited independently by hand, exactly as the earlier CLS-fix plan did.

- [ ] **Step 4: Verify all 3 files contain the new rule, and the generated files match each other exactly**

```bash
tail -c 700 css/vendors-tour.css
tail -c 700 css/vendors.css
diff <(tail -c 700 css/vendors-tour.css) <(tail -c 700 css/vendors.css) && echo "IDENTICAL TAILS"
grep -n "sp-force-reveal" css/vendors.unminified.css
```

Expected: the two generated files' tails are byte-identical, and `vendors.unminified.css` contains 2 matches for `sp-force-reveal` (the animation name and the `@keyframes` declaration).

- [ ] **Step 5: Commit**

```bash
git add css/vendors-tour.css css/vendors.css css/vendors.unminified.css
git commit -m "perf: hide tour gallery until Slider Pro init completes, CSS-only fallback reveal"
```

---

### Task 5: Local throttled verification

**Files:**
- None created or modified — verification only.

**Interfaces:**
- Consumes: Tasks 1-4's changes.
- Produces: functional-regression and CLS evidence for the task reviewer and final review.

**Context:** The earlier CLS-fix plan's local verification used fast/cached conditions and missed the real-world gap that motivated this plan — this task must use throttled conditions throughout, not as an afterthought. Reuse the Puppeteer install at `/Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen/node_modules`.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP && php -S localhost:8080 -t .
```

Leave running for the rest of this task.

- [ ] **Step 2: Functional regression check on all 5 pages**

Using Puppeteer (mobile viewport 390x844, throttled network ~1.6 Mbps down / 750 Kbps up / 150ms latency via `Network.emulateNetworkConditions`, CPU throttle rate 4 via `Emulation.setCPUThrottlingRate`, `page.setCacheEnabled(false)`), for each of the 5 pages (`cruise-transfer.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`, `maipo-valley-wine-tour-santiago.php`, `discover-santiago-city-tour.php`) served from `http://localhost:8080/...`, verify:

1. No JS console errors (`page.on('console', ...)` and `page.on('pageerror', ...)` — both should be empty/clean).
2. The gallery becomes visible within a reasonable time (`getComputedStyle('#Img_carousel').visibility === 'visible'` becomes true — confirms the JS-driven reveal fired, not just the 10s fallback).
3. Clicking a `.sp-thumbnail` element changes the selected slide (same check pattern as the earlier plan's Task 2 — compare slide image `src` and `sp-selected-thumbnail` index before/after click).
4. The sticky sidebar activates (`$('#sidebar').data('theiaStickySidebar')` is truthy, or equivalent DOM/class check for whichever marker the plugin sets).
5. On the 4 pages using `tour-scripts.php`: the date/time picker still initializes (`.date-pick` element has the `daterangepicker` plugin attached — check via `$.fn.daterangepicker` data or the picker's generated DOM).
6. On `cruise-transfer.php` specifically: confirm `js/transfer.js`'s booking/itinerary logic still runs (spot-check whatever DOM state it's responsible for — read `js/transfer.js` briefly to identify a concrete, checkable side effect if not already obvious).

- [ ] **Step 3: CLS measurement under the same throttled conditions**

Reuse/adapt the CLS-measurement script pattern from the earlier plan (`PerformanceObserver({type:'layout-shift'})`, same viewport) against the same 5 local URLs, under the same throttled conditions as Step 2 (not fast/cached conditions this time). Record CLS total and top shift sources per page.

- [ ] **Step 4: Confirm results**

All 5 pages should show CLS meaningfully improved from the pre-this-plan throttled baseline (cruise-transfer was 0.2040-0.2822 "Needs Improvement/Poor" after the CSS-only follow-up fix, before this plan). The gallery-related shift sources (`img.sp-thumbnail`, `div.sp-thumbnails`, `div.sp-slides`, `#Img_carousel`) should no longer appear as dominant contributors, since the visibility safety net should suppress them entirely regardless of timing. If any page still shows a significant gallery-attributed shift, do not proceed to Task 6 — investigate first (check the computed `visibility` value's timing, confirm the CSS actually loaded/applied, check for a typo in the reveal chain).

- [ ] **Step 5: Stop the local PHP server**

---

### Task 6: Deploy and confirm production

**Files:**
- None — deployment and verification only.

- [ ] **Step 1: Push the commits**

```bash
git push
```

(If the push is rejected due to upstream changes, `git fetch origin`, confirm no overlap with the files this plan touches, `git merge origin/main --no-edit`, then push again — same pattern used throughout this project's prior plans.)

- [ ] **Step 2: Ask the user to pull on the server**

Tell the user: "Pushed. Please pull this on production, then let me know once it's live so I can re-verify."

Wait for confirmation before continuing.

- [ ] **Step 3: Re-run the throttled functional + CLS checks from Task 5 against the live production URLs**

Same methodology, same 5 URLs, now against `https://stampstour.com/...` with a cache-busting query param (the earlier plan's final review found stale CDN caching gave a false-positive "fixed" reading once already — use `?cachebust=<timestamp>` and/or confirm `cf-cache-status`/`last-modified` response headers reflect the new deploy before trusting the measurement).

- [ ] **Step 4: Report the before/after comparison to the user**

Compare against the pre-this-plan throttled baseline (cruise-transfer 0.2040-0.2822, portillo/valparaiso/maipo/discover-santiago in the same "Needs Improvement" range) and confirm the improvement holds on live production, not just locally.

## Verification

1. `php -l` passes on both modified PHP files (Task 1, Task 2).
2. `node --check` passes on `js/tours.js` (Task 3).
3. All 3 CSS files contain the new visibility rule, with the two generated files byte-identical to each other for the new block (Task 4).
4. Local throttled functional regression check passes on all 5 pages: no console errors, gallery reveals correctly, thumbnail click works, sticky sidebar activates, date pickers work, cruise-transfer's booking logic works (Task 5).
5. Local throttled CLS measurement shows meaningful improvement over the pre-this-plan baseline on all 5 pages, gallery no longer a dominant shift source (Task 5).
6. Production re-verification (post-deploy, cache-busted) confirms the same result on live URLs (Task 6).

## Risks

- **`defer` is a real behavioral change to script execution timing across the whole page**, not just the gallery — Task 5's functional regression checklist exists specifically because a working CLS fix that silently breaks the sticky sidebar or date picker would be a worse outcome than the original problem. Do not skip or shortcut Task 5's checks.
- **The visibility safety net could mask a broken timing fix** — since hidden content generates no CLS regardless of what it does internally, a regression in the `defer`/`DOMContentLoaded` wiring that made the gallery take even longer to become interactive would still show a good CLS number. Task 5 Step 2's check #2 (confirm the JS-driven reveal actually fires, not just relying on the 10s CSS fallback) exists specifically to catch this.
- **Stale CDN caching produced a false-positive "confirmed fixed" reading once already** on this exact gallery (documented in the prior plan's final review) — Task 6 Step 3's cache-busting/header-check requirement exists specifically to not repeat that mistake.
