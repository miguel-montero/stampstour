# Fix stylesheet/image priority tie via media="print" onload-swap

## Context

An earlier plan (`docs/superpowers/specs/2026-08-02-tour-pages-lcp-priority-fix-design.md`) found and partially fixed a real LCP regression: the site's shared, non-critical stylesheets were loading as `<link rel="preload" as="style">`, which Chrome/Blink schedules at the same high-priority tier as an image with `fetchpriority="high"`. That plan added `fetchpriority="low"` to the preloads, but its own investigation (documented in the fix's shipped code comment, `includes/head.php:120-139`) found this only demotes the request from Blink's `VeryHigh` bucket down to `High` — it does **not** reach the actual `Low` tier. So today, the LCP-critical hero image and 6 deferred stylesheets are all tied at `High`, splitting bandwidth roughly evenly under a throttled connection instead of the image getting the share it needs. That earlier plan's own write-up named the correct follow-up: the `media="print"` onload-swap idiom, which does reach Blink's genuine `Low` tier.

This was re-confirmed this session via a direct resource-timing waterfall on `contact-us.php` under throttled mobile conditions: the LCP image (`img/Tours/Stgo/big.webp`, 198KB) started downloading at 686ms but didn't finish until 6,350ms — a 5.7s download for a file that should take about 1s alone on the throttle profile used — because it was sharing bandwidth with 6-7 other `High`-priority resources (`bootstrap.min.css`, `style.css`, the vendor CSS bundle, `bootstrap-icons.min.css`, `fonts.css`, plus a Cloudflare analytics beacon) that all started at the same instant.

A separate, smaller, related issue was found in the same investigation: `css/timeline.css` is loaded three different ways across the site — the priority-tied preload pattern (5 tour pages, `cruise-transfer.php`), or a **fully render-blocking** plain `<link rel="stylesheet">` with no preload at all (`contact-us.php`, `refunds-cancellations.php`, `privacy.php`). The latter is worse than the priority-tie problem this spec otherwise addresses, and gets fixed the same way, in the same pass.

This session already shipped two other real, measured LCP/TTI improvements on top of the same underlying investigation:
- `docs/superpowers/plans/2026-08-08-content-pages-remove-common-scripts.md` — removed a 208KB unused JS bundle, confirmed (via controlled A/B testing) to genuinely help but only modestly (~2-3%, ~150-260ms) — LCP is still ~5.5s on `contact-us.php`, confirming a bigger contributor remains.
- `docs/superpowers/plans/2026-08-08-tour-gallery-defer-scripts.md` — deferred tour-page script loading, fixed a related but separate CLS mechanism.

This spec's fix targets the largest remaining known contributor.

## Goals

- Let the LCP-critical image genuinely win the bandwidth contest against deferred stylesheets, by moving those stylesheets into Blink's real `Low` priority tier instead of the `High` tier they're stuck at today.
- Apply the fix once, in the shared `includes/head.php` preload block, so every page routing through it benefits consistently (homepage, all 5 tour pages, `cruise-transfer.php`, all 6 content pages).
- Fix the `timeline.css` inconsistency in the same pass: convert both its priority-tied-preload usage (5 tour pages + `cruise-transfer.php`) and its fully-render-blocking usage (`contact-us.php`, `refunds-cancellations.php`, `privacy.php`) to the same non-blocking, genuinely-low-priority pattern.

## Non-goals

- `shopping.php` and the checkout flow — doesn't use `includes/head.php` at all (confirmed in the 2026-08-02 spec's own non-goals), untouched here.
- The font-swap CLS mechanism (Montserrat webfont causing ~1-2px text shifts when it loads) — a separate, already-identified issue, not fixed by this change. `fonts.css` (the stylesheet declaring `@font-face` rules) IS included in this fix's scope, but changing when the *stylesheet* loads doesn't change the font-swap *mechanism* itself — that's a distinct problem for a future plan (metric-matched fallback fonts).
- Reducing the byte size of any of these stylesheets — this spec only changes loading priority, not content.
- The homepage's own separate, not-yet-addressed script-defer gap (index.php's scripts are still render-blocking, unlike the now-fixed tour/content pages) — unrelated mechanism, separate future work.

## Design

### 1. Convert the shared `includes/head.php` preload block

Current (verified current as of this session — `includes/head.php:119-163`):

```php
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<!-- [existing rationale comment about fetchpriority=low capping at High] -->
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
... (plain blocking fallback for pages without critical CSS - unchanged, out of scope)
<?php endif; ?>
```

New: replace every `<link rel="preload" href="X" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">` with `<link rel="stylesheet" href="X" media="print" onload="this.media='all';this.onload=null;">`. The `<noscript>` fallback lines are unchanged — they already provide the correct behavior for JS-disabled visitors regardless of which technique the JS-enabled path uses. The conditional structure (the `home`/`tour`/`core`/default branches for the vendor bundle) is unchanged — only the tag syntax inside each branch changes. The existing rationale comment gets replaced with one explaining the new mechanism and linking this spec.

Result for one stylesheet (repeat for all 6: `fonts.css`, `bootstrap.min.css`, `style.css`, the vendor bundle in all 3 branches, `bootstrap-icons.min.css`, `custom.css`):

```html
<link rel="stylesheet" href="/fonts/fonts.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
```

**Why this works:** a `<link rel="stylesheet" media="print">` is fetched by the browser (so it's still discovered early, same as a preload) but isn't needed for the current screen rendering context, so Chrome schedules it at genuinely low priority — unlike a preload, which is inherently a "you'll need this soon" signal that keeps it in a higher tier regardless of the `fetchpriority` hint. Once the file loads, `onload` flips `media` to `'all'`, and the browser applies it immediately, same as today.

### 2. Fix `timeline.css` — two source patterns, same target pattern

**5 tour pages + `cruise-transfer.php`** — currently the same priority-tied preload pattern as the head.php block, one line each:

```html
<link rel="preload" href="css/timeline.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
```

becomes:

```html
<link rel="stylesheet" href="css/timeline.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
```

Applies identically to: `discover-santiago-city-tour.php`, `maipo-valley-wine-tour-santiago.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`, `cruise-transfer.php`.

**`contact-us.php`, `refunds-cancellations.php`, `privacy.php`** — currently plain, fully render-blocking, no preload, no noscript fallback needed (already blocking = already works with JS disabled):

```html
<link href="css/timeline.css" rel="stylesheet"/>
```

becomes (adding a `<noscript>` fallback, since this pattern needs one where the plain blocking version didn't):

```html
<link rel="stylesheet" href="css/timeline.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
```

## Testing

Both prior LCP-related plans this session already established: (1) local `php -S` throttled testing does not reliably reproduce real network-timing effects (CDP throttling doesn't meaningfully throttle localhost traffic — confirmed directly this session), and (2) a naive before/after comparison across two points in time can be misleading if host load differs (also confirmed this session, resolved via controlled interleaved A/B testing). Both lessons apply directly here:

- **Primary verification must be against real production**, not local server timing.
- **Any before/after LCP comparison must control for host-load drift** — either measure immediately before/after deploy in close succession, or use an interleaved A/B approach (temporarily reverting `includes/head.php` and re-measuring, matching the technique already used and proven this session) if a clean before/after isn't otherwise achievable.
- Functional/visual verification (nothing renders unstyled, no console errors) can and should use local `php -S` testing — that part doesn't depend on realistic network timing.

## Risks

- **If `onload` never fires (network failure, blocked resource), the stylesheet never applies.** This is not a new risk — the current `rel="preload"` + `onload` swap-to-`rel="stylesheet"` pattern already has this exact same failure mode today, already in production. Switching techniques doesn't change this risk's presence or severity, just the mechanism.
- **A visitor who prints the page mid-load could see incorrect print styling** — a `media="print"` stylesheet trick means during the brief window before `onload` fires, an actual print action would use whatever CSS is currently active for print media, which may not be what a real print stylesheet would show. Extremely low practical risk for a tour-booking site (printing mid-page-load is rare), and this is the standard, widely-accepted tradeoff of this well-established technique (used broadly in production across the web).
- **Site-wide blast radius** — this touches the shared code path used by nearly every public page. Mitigated by: the technique itself only changes *when* styles apply (timing), not *what* styles apply (content) or the `<noscript>` fallback (unchanged), and verification explicitly includes a visual check across page types (homepage, a tour page, a content page) before considering this done.
