# Split shared JS/CSS bundles into per-page-type variants (Phase 2)

## Context

Phase 1 (`docs/superpowers/specs/2026-08-03-remove-dead-vendor-code-design.md`) removed everything confirmed dead *everywhere* in the codebase. What's left in `js/common_scripts.js` (11,170 lines) and `css/vendors.unminified.css` (17,132 lines) is no longer dead — but it's not evenly needed either:

| Component | Needed by homepage? | Needed by the 4 uniform tour pages? |
|---|---|---|
| Bootstrap JS bundle (`js/common_scripts.js` lines 1–6,318) | Yes | Yes |
| WOW.js (lines 8,599–9,114) | Yes (9 `data-wow-*` elements) | No (zero `data-wow-*` anywhere on any of the 4) |
| Parallax + Magnific Popup (lines 6,319–8,598) | No (zero `.parallax-window`, `.magnific-gallery`, `.video`, `#access_link` matches) | Yes (gallery + video modal) |
| daterangepicker + moment.js (lines 9,115–11,170) | No | Yes (`.date-pick` booking widget) |
| Icon fonts (`css/vendors.unminified.css` lines 1–11,156) | Yes | Yes |
| Animate.css (lines 11,157–14,653) | Yes (powers WOW's `fadeInUp`/`zoomIn` classes) | No |
| Magnific Popup CSS + toggle-switch CSS + Slider Pro CSS + daterangepicker CSS (lines 14,654–17,132) | No | Yes |

Confirmed via direct `grep` against every relevant page's markup (see brainstorming transcript) — not assumed. This split is real and clean: homepage and the 4 tour pages (Maipo, Andes/Portillo, Valparaíso, Discover Santiago — the 4 that share `includes/tour-scripts.php`) have almost no overlap in what they need beyond the Bootstrap/icon-font core.

`cruise-transfer.php` needs the same tour-page plugins but has a different, non-uniform script-loading setup (its own duplicate Bootstrap JS include, an autocomplete widget) — explicitly out of scope for this pass, left exactly as-is.

**The hazard.** `js/functions.js` is a single shared script loaded on every page, and it unconditionally calls plugins from *all* of these categories: `new WOW().init()`, `$('.parallax-window').parallax(...)`, and three separate `.magnificPopup(...)` calls (video modal, image gallery, and a defunct `#access_link` sign-in modal — commented out of `includes/header.php` but the JS call is unconditional regardless). Today this is safe because every page loads every plugin. Once bundles split, homepage will lack Parallax/Magnific Popup and tour pages will lack WOW — an unguarded call to a missing plugin throws and halts everything later in `functions.js` (cart dropdown, hamburger menu, scroll-to-top, panel dropdowns), exactly the class of bug Phase 1 guarded against for Ion.RangeSlider/Owl Carousel.

## Goals

- Split `js/common_scripts.js`/`css/vendors.unminified.css` into a shared "core" plus two "extra" variants (home, tour), and regenerate minified production files for each.
- Guard every `functions.js` call site that depends on a plugin not present in every variant, so no page can throw and halt the rest of the script.
- Reduce homepage's combined JS+CSS payload by ~38% (422,007 → 262,270 bytes, verified via real `terser`/`clean-css-cli` output) and the 4 tour pages' by ~16% (422,007 → 355,188 bytes).
- Zero visible or functional change on any page.

## Non-goals

- `cruise-transfer.php`, `shopping.php`, `login.php`, `admin.php`, `return.php`, `success.php`, `blog.php`, `blog-post.php`, `contact-us.php`, `gallery.php`, `refunds-cancellations.php`, `privacy.php` — all keep loading the existing unified `js/common_scripts_min.js`/`css/vendors.css` completely unchanged. Nothing about their markup, includes, or behavior changes. The existing combined files are **not deleted** — they remain the default/fallback output for every page not explicitly opted into a variant.
- Splitting Bootstrap itself, or any further subdivision within the core bundle.
- Any further Phase 3 (e.g. per-tour-page differences, lazy-loading, HTTP/2 push) — out of scope.
- Fixing unrelated pre-existing issues surfaced along the way (e.g. `cruise-transfer.php`'s duplicate Bootstrap JS, the `css/slider-pro.min.css` 404) — noted, not fixed here.

## Design

### 1. New build outputs (source files unchanged)

`js/common_scripts.js` and `css/vendors.unminified.css` remain the single source of truth — no edits to their content in this phase (Phase 1 already trimmed them). Six new files are generated as additional minifier outputs, using the same `terser`/`clean-css-cli` scratch-tool approach as Phase 1:

| New file | Source lines | Verified minified size |
|---|---|---|
| `js/vendors-core.min.js` | `common_scripts.js` 1–6,318 | 80,558 B |
| `js/vendors-home.min.js` | `common_scripts.js` 8,599–9,114 (WOW) | 8,329 B |
| `js/vendors-tour.min.js` | `common_scripts.js` 6,319–8,598 + 9,115–11,170 (Parallax+Magnific Popup, then daterangepicker/moment) | 119,110 B |
| `css/vendors-core.css` | `vendors.unminified.css` 1–11,156 (icons) | 114,708 B |
| `css/vendors-home.css` | `vendors.unminified.css` 11,157–14,653 (Animate.css) | 58,675 B |
| `css/vendors-tour.css` | `vendors.unminified.css` 14,654–17,132 (Magnific Popup + switch + Slider Pro + daterangepicker CSS) | 40,812 B |

These were already generated and byte-verified during brainstorming (sum of parts equals the current combined file's byte count exactly, confirming no boundary overlap or gap).

### 2. `includes/head.php`: page-aware CSS loading

Add an optional caller variable, checked alongside the existing `$critical_css_file`/`$lcp_preload_image` pattern already established in this file:

```php
 *   $vendor_css_variant (optional) - 'home' or 'tour'. When set, loads
 *                        css/vendors-core.css + css/vendors-{variant}.css
 *                        instead of the full css/vendors.css. Unset (the
 *                        default) preserves today's behavior exactly, so
 *                        every page that doesn't opt in is untouched.
```

In both the critical-CSS branch and the non-critical-CSS branch (the `<?php if (...): ?> ... <?php else: ?>` block at lines 101–143), replace the single `css/vendors.css` `<link>`/preload with two, only when `$vendor_css_variant` is set:

```php
<?php if (!empty($vendor_css_variant) && in_array($vendor_css_variant, ['home', 'tour'], true)): ?>
<link rel="preload" href="/css/vendors-core.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/vendors-<?= $vendor_css_variant ?>.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-<?= $vendor_css_variant ?>.css" rel="stylesheet"></noscript>
<?php else: ?>
<link rel="preload" href="/css/vendors.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<?php endif; ?>
```

(Mirrored in the non-critical-CSS `<?php else: ?>` branch at lines 134-143 using plain `<link rel="stylesheet">` tags instead of preload+swap.) Same `fetchpriority="low"` treatment as the existing stylesheets, consistent with the established priority-tuning work already shipped.

`index.php` sets `$vendor_css_variant = 'home';` alongside its existing `$critical_css_file`/`$lcp_preload_image` assignments. Each of the 4 tour pages sets `$vendor_css_variant = 'tour';` the same way. No other page sets it, so no other page's output changes.

### 3. JS loading changes

`index.php` (lines 265-267):
```php
<script src="js/jquery-3.7.1.min.js"></script>
<script src="js/vendors-core.min.js"></script>
<script src="js/vendors-home.min.js"></script>
<script src="js/functions.js"></script>
```

`includes/tour-scripts.php` (lines 9-14), used by all 4 uniform tour pages:
```php
<!-- jQuery FIRST -->
<script src="js/jquery-3.7.1.min.js"></script>

<!-- Core bundle (Bootstrap) + tour-only extras (Parallax, Magnific Popup, daterangepicker/moment) -->
<script src="js/vendors-core.min.js"></script>
<script src="js/vendors-tour.min.js"></script>

<!-- Site functions (ok after core+extras) -->
<script src="js/functions.js"></script>
```

### 4. `js/functions.js`: guard every now-conditional plugin call

Five call sites, guarded the same way Phase 1 guarded Ion.RangeSlider/Owl Carousel:

```js
// line ~105
if (typeof WOW !== 'undefined') {
  new WOW().init();
}
```

```js
// inside the $(function () { ... }) block starting ~line 108
if ($.fn.parallax) {
  $('.parallax-window').parallax({zIndex:1});
}

if ($.fn.magnificPopup) {
  $('.video').magnificPopup({
    type: 'iframe',
    closeMarkup: '<button title="%title%" type="button" class="mfp-close" style="font-size:21px">&#215;</button>'
  });

  $('.magnific-gallery').each(function () {
    $(this).magnificPopup({ /* ...unchanged... */ });
  });
}
```

```js
// top-level, ~line 246 (outside the $(function(){...}) block)
if ($.fn.magnificPopup) {
  $('#access_link').magnificPopup({ /* ...unchanged... */ });
}
```

This is the load-bearing safety mechanism for the whole plan, same as Phase 1. Per the correction recorded in Phase 1's final review, the valid verification signal is **not** "some feature still visibly works" (that can pass even with a missed guard, if the feature happens to be bound upstream of the failure) — it's (a) zero `pageerror` events captured on every verified page, since an uncaught `TypeError` inside `functions.js`'s wrapping IIFE surfaces there regardless of bind order, plus (b) explicitly testing a feature bound *after* the last guard in file order (the `.panel-dropdown` handler and the final `data-background` hero-image swap are the last two blocks in the file).

## Verification

1. `node --check` on all three new JS files plus `functions.js` after guarding.
2. Local Puppeteer pass on homepage and all 4 tour pages (`php -S`): zero `pageerror` events; `.panel-dropdown` and the `data-background` hero swap (the file's last two blocks) both work correctly on every page — this is the real proof the guards are complete, not the `#toTop` visibility check (which sits upstream of the guarded blocks and would pass even if a guard were missing, per the Phase 1 review correction).
3. Visually confirm WOW's scroll animations still fire on homepage, and that Parallax/Magnific Popup gallery/video modal and the daterangepicker booking widget still work on a tour page (Maipo).
4. Byte-size sanity check: new files' sizes match the verified figures in the Design table above.
5. Confirm the 7+ untouched pages (blog.php, contact-us.php, shopping.php, etc.) are byte-identical in their rendered `<head>`/script output before and after this change — they must not reference any new file.
6. Deploy, purge Cloudflare cache, spot-check production homepage + Maipo + one untouched page (e.g. contact-us.php) for zero new console errors, same methodology as Phase 1's post-deploy check.

## Risks

- **Same class of risk as Phase 1, in both directions this time.** Phase 1 only had to guard against plugins missing on *every* page. This phase has WOW missing on tour pages AND Parallax/Magnific Popup missing on homepage — twice the guard surface, and a mistake here is just as silent and sitewide-shaped (though scoped to only 5 pages, not literally every page, since untouched pages keep the full bundle).
- **`includes/head.php` is shared by 12 pages.** The `$vendor_css_variant` branch must be strictly additive — verification step 5 exists specifically to catch any accidental change to the 7+ pages that don't set it.
- **Boundary-line fragility**, same as Phase 1: the exact line ranges were re-verified against the live file's current state (post-Phase-1) during brainstorming, not assumed from an earlier read — but should be re-confirmed once more at plan-writing time in case anything shifted.
