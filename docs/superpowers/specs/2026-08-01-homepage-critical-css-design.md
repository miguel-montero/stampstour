# Homepage critical CSS: eliminate render-blocking stylesheets

## Context

A PageSpeed Insights audit run against production this session found mobile First Contentful Paint at 5.3s and Lighthouse performance 41/100, with `render-blocking-insight` estimating **2,850ms of recoverable FCP time** from stylesheets that block first paint:

| File | Blocking time | Loaded via |
|---|---|---|
| `css/bootstrap.min.css` | 2,045ms | `includes/head.php` (sitewide) |
| `css/style.css` | 1,888ms | `includes/head.php` (sitewide) |
| `css/vendors.css` | 1,731ms | `includes/head.php` (sitewide) |
| `css/custom.css` | 475ms | `includes/head.php` (sitewide) |
| `fonts/fonts.css` | 161ms | `includes/head.php` (sitewide) |

All five are plain `<link rel="stylesheet">` tags in the shared `includes/head.php`, so the browser must download and parse all of them before it can paint anything — on every page, since `head.php` is included sitewide.

A related bug found during investigation: `css/vendors.css` pulls in the icon-font stylesheet via a CSS `@import url("bs-icon-font/bootstrap-icons.min.css")`. `@import` is fetched only after the importing stylesheet itself starts parsing, serializing that request behind `vendors.css` instead of letting the browser fetch it in parallel with everything else — a well-known anti-pattern (`cwv-audit` skill's LCP guidance explicitly calls out "avoid `@import` in CSS").

This is the render-blocking-CSS work that was scoped separately from the Revolution Slider removal (`docs/superpowers/specs/2026-08-01-hero-revslider-replacement-design.md`), which is already shipped. This spec covers **the homepage only** — proving the mechanism on the highest-traffic page before rolling it out to the other ~9 page templates (tour pages, blog, contact, etc.) as separate follow-up pieces, since each template has different above-the-fold content and doing all of them at once is more surface area than is prudent for a first pass.

## Goals

- Eliminate render-blocking CSS on `index.php`'s critical rendering path: the fixed header/nav and the hero section (everything visible without scrolling) should be able to paint without waiting for any external stylesheet to download.
- Every visitor still gets fully correct styling — including JS-disabled visitors (rare, but must not see a permanently broken page).
- Fix the `vendors.css` `@import` so the icon-font stylesheet loads in parallel rather than serialized behind it.
- No visual regression versus the current fully-loaded appearance — the critical CSS must be generated from real rendering, not hand-guessed, to minimize the risk of a missing rule causing a flash when the full stylesheets swap in.

## Non-goals

- Any page other than `index.php`. `includes/head.php` is shared sitewide, so this must be implemented in a way that doesn't change behavior on other pages (see Design).
- Introducing a persistent build pipeline. Node/`critical` is used once, locally, to generate a static CSS string that gets committed as plain text — the deployed site remains pure PHP with no build step, matching its current git-pull deployment model.
- Bundling/minifying the non-critical stylesheets themselves, or reducing their byte size — this spec only changes *when* they load, not their contents. (Bootstrap/vendors.css trimming, if ever pursued, is a separate, much larger, higher-risk piece.)
- Automatic regeneration of the critical CSS when source files change. It's a static snapshot; see Maintenance below.

## Design

### 1. Generate critical CSS locally (one-time, not part of deployment)

Run the `critical` npm package (Puppeteer-based; determines exactly which CSS rules apply to the rendered above-the-fold content, not a hand-picked guess) against `index.php` served locally via `php -S`, at two viewports:
- Mobile: 390×844 (matches the viewport used throughout this session's hero verification work)
- Desktop: 1470×900 (matches the common-laptop width used to catch the hero height bug earlier this session)

`critical` merges the results across both viewports into one critical-CSS string covering both.

This is a local, throwaway dev-dependency install (`npm install --no-save critical` or `npx critical`, in a scratch directory, not added to the repo) — its only output that matters is the generated CSS text, which gets pasted into the PHP source in step 2.

### 2. Inline the critical CSS, homepage-only

`includes/head.php` is shared by every page, but the critical CSS is specific to the homepage's header+hero markup. `index.php` already sets page-specific variables (`$page_title`, `$page_description`, etc.) before including `head.php` — follow that existing pattern:

```php
// index.php, before the head.php include:
$critical_css = <<<CSS
/* ... generated output from step 1 ... */
CSS;
```

```php
// includes/head.php, where the stylesheet links currently are:
<?php if (!empty($critical_css)): ?>
<style><?= $critical_css ?></style>
<?php endif; ?>
```

Pages that don't set `$critical_css` (everything except `index.php`, for now) get no inline block and no change in behavior — this is what keeps the change homepage-only despite `head.php` being shared.

### 3. Load the five stylesheets non-blocking

Replace each of the five `<link rel="stylesheet">` tags in `includes/head.php` with the standard preload/onload-swap pattern:

```html
<link rel="preload" href="/fonts/fonts.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="/fonts/fonts.css"></noscript>

<link rel="preload" href="/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="/css/bootstrap.min.css"></noscript>

<link rel="preload" href="/css/style.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="/css/style.css"></noscript>

<link rel="preload" href="/css/vendors.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="/css/vendors.css"></noscript>

<link rel="preload" href="/css/custom.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="/css/custom.css"></noscript>
```

This applies to **every page** (it's the existing shared markup in `head.php`, not gated behind `$critical_css`) — the preload/onload-swap pattern is safe and beneficial everywhere, it's only the *inlined critical CSS content* that's homepage-specific. Other pages will simply load their full styling slightly asynchronously without an inlined critical block yet, which is strictly no worse than today's fully-blocking behavior (their content still ends up styled correctly, just not gated behind a synchronous download first).

`this.onload=null` prevents the handler from re-firing if the browser re-evaluates the swapped `rel="stylesheet"` link.

### 4. Fix the `vendors.css` `@import`

In `css/vendors.css`, remove the `@import url("bs-icon-font/bootstrap-icons.min.css");` line (currently the first rule in the file) and add its own preload/onload-swap `<link>` pair in `includes/head.php` alongside the other five, so it fetches in parallel instead of serialized behind `vendors.css`.

## Verification

1. Local `php -S` server, headless Chrome screenshots of `index.php` at the same width set used for the hero work (375, 576, 650, 768, 880, 992, 1100, 1200, 1470, 1920), **using the CDP/iframe measurement method** established during the hero final review — not raw `--window-size` at widths below 500px, which was found this session to silently clamp to a 500px viewport floor.
2. Confirm no visible flash: capture a screenshot immediately on load and one after full load; the above-the-fold region (header + hero) should look identical in both — that's the signal the critical CSS actually covers everything needed.
3. Confirm the site is still fully usable with JavaScript disabled (the `<noscript>` fallback path) — at least one screenshot with JS disabled in headless Chrome.
4. `php -l` on both modified PHP files, div/tag balance checks.
5. Confirm no other page's rendered output changed — spot-check one or two other pages (e.g. a tour page) to confirm they still load their stylesheets correctly via the new preload pattern, just without an inlined critical block.
6. Once deployed, re-run PageSpeed Insights (mobile + desktop) against production and compare FCP/LCP/Lighthouse score against the last recorded baseline (mobile: 41/100, FCP 5.3s, LCP 9.9s; desktop: 67/100, LCP 1.7s) — not a blocking step for this spec, but the actual measure of whether this worked.

## Risks

- **Critical CSS could miss a rule**, causing a brief flash when the full stylesheet swaps in. Mitigated by generating it from real rendering (not hand-picked) and by the before/after screenshot comparison in verification.
- **Static snapshot goes stale.** If the header or hero markup changes meaningfully later, the inlined critical CSS won't automatically reflect it. Mitigated by leaving a clear comment with the exact regeneration command at the `$critical_css` definition site.
- **This does not by itself fix mobile LCP down to "good."** The render-blocking-insight's 2,850ms estimate was for FCP specifically; LCP depends on FCP but also on when the hero image itself is ready, which this spec doesn't change. Expect a real FCP improvement and a correlated but likely smaller LCP improvement — not a guarantee of hitting the 2.5s "good" LCP threshold in one step.
