# Content-page hero image: fix LCP discovery delay

## Context

A live Lighthouse audit of `gallery.php` (mobile, simulated throttling) scored 57/100 performance, with LCP at 11.7s. The LCP element is the page's hero banner (`section#hero_2`), and Lighthouse's `lcp-breakdown-insight` attributes **1,597ms of "element render delay"** to it — the single largest chunk of the LCP timeline, larger than time-to-first-byte, resource load delay, and resource load duration combined.

The root cause: `#hero_2`'s background image is applied via JavaScript, not markup. Every affected page renders:
```html
<section id="hero_2" class="background-image" data-background="url(/img/Tours/Stgo/big.jpg)">
```
and `js/functions.js:402-403` does:
```js
$('.background-image').each(function(){
  $(this).css('background-image', $(this).attr('data-background'));
});
```
The browser's preload scanner can't discover this image from the initial HTML — it only becomes fetchable after CSS parses, jQuery loads and executes, and this handler runs. That sequencing, not the image's file size, is the dominant cost.

This exact problem was already solved elsewhere in this codebase for the tour pages (`discover-santiago-city-tour.php` and siblings), via a real `<img fetchpriority="high">` instead of a CSS background:
```html
<section class="tour-banner">
  <img src="img/Tours/Stgo/big.jpg" width="1400" height="1050" fetchpriority="high" alt="Santiago banner" class="tour-banner-bg">
```
with:
```css
.tour-banner { position: relative; overflow: hidden; height: 360px; /* 470px ≥768px */ }
.tour-banner-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center center; }
```
That work never touched the content pages, which use a different (older) hero mechanism: `#hero_2` + `.opacity-mask` (dark overlay) + `.intro_title h1` (centered white heading) — visually distinct from `.tour-banner` (which shows badges/pricing over the image, not a simple centered title), so the fix here reuses the *technique* (real `<img fetchpriority="high">` replacing a JS-driven background), not the tour pages' specific CSS classes.

The exact same image (`img/Tours/Stgo/big.jpg`, 1400×1050, 320KB) is reused as generic hero art across 7 public pages, via identical markup:

| Page | Confirmed identical `#hero_2` structure |
|---|---|
| `gallery.php`, `return.php`, `refunds-cancellations.php`, `shopping.php`, `contact-us.php`, `privacy.php`, `blog.php` | Yes — only the `<h1>` text and `.opacity-mask`'s alpha value (0.4 or 0.45) differ |

`admin/_hero.php` uses the identical mechanism but is excluded from this fix (see Non-goals).

An unused WebP (`img/Tours/Stgo/big.webp`, 252KB, same 1400×1050) already exists alongside the JPG but is referenced nowhere — it was never quality-tuned for this purpose.

## Goals

- Every one of the 7 pages' hero image becomes discoverable from the initial HTML (a real `<img fetchpriority="high" width="…" height="…">`), eliminating the JS-dependent discovery delay that's currently the largest single contributor to LCP on these pages.
- Explicit `width`/`height` attributes on the new `<img>` so the browser reserves layout space immediately — no cumulative layout shift versus the current behavior (which also had a `background-color: #ddd` placeholder, so CLS was already effectively zero; this preserves that, doesn't regress it).
- Serve the hero image as a properly quality-tuned WebP instead of the current 320KB JPEG, as a secondary (smaller, safe) improvement bundled in alongside the discovery fix.
- Zero visual change: same crop, same image, same dark overlay, same centered white heading — only the loading mechanism and file format change.

## Non-goals

- **`admin/_hero.php` is out of scope.** It uses the identical `.background-image`/`data-background` mechanism, but it's behind login, not performance-critical, and not what the Lighthouse audit measured. Leaving it means `js/functions.js`'s `.background-image` JS handler (lines 402-403) must stay in the codebase — it's still needed for that page, and also for `404.html`, which uses the identical mechanism as the site's error page (wired up via `.htaccess`) and is likewise untouched by this change. This is intentional, not leftover debt from this change.
- **No responsive `srcset`/multiple sizes.** The tour pages' already-accepted precedent (`tour-banner-bg`) also ships one flat image with no responsive variants — matching that keeps this change consistent with the rest of the site rather than introducing a new pattern here first.
- **No change to the image's dimensions or crop.** This was considered and deliberately rejected: `#hero_2` renders as a very wide, short box (470px tall at ≥768px, versus the source's near-square 1400×1050 / 4:3 ratio). Working through `object-fit: cover`'s scaling behavior, on a sufficiently wide desktop viewport the *width* — not the height — becomes the constraining dimension, meaning the current 1400px-wide source is already close to the minimum needed to avoid upscaling on wide screens, even though most of its 1050px height is cropped away and never visible. Narrowing the source to "fix" the wasted height would make wide-desktop rendering *worse* (more upscaling, blurrier) for a marginal extra byte saving. Not worth that trade — this fix only changes format/compression, not pixel dimensions.
- **Not cleaning up `#hero_2`'s now-unused `background-color`/`background-size`/`background-position`/`background-repeat` CSS declarations.** They become inert (no `background-image` is ever set via JS on the fixed pages anymore) but harmless — touching a CSS rule shared across many pages for a pure tidiness win isn't worth the risk here.
- **Not touching the tour pages** (`discover-santiago-city-tour.php` and siblings) — they already went through this exact fix in prior work.
- **Not the icon-font weight problem** (fontello/icon_set_1/bootstrap-icons, ~480KB combined, mostly unused glyphs) surfaced by the same audit — that's a separate, unrelated subsystem and gets its own spec.

## Design

### New CSS rule

One addition to `css/custom.css`, placed near the existing `.tour-banner-bg` rule it's modeled on:
```css
.hero-bg-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 1;
}
```
`#hero_2` already has `position: relative` (untouched, existing rule), so the absolutely-positioned image fills it correctly. `z-index: 1` keeps it beneath `.opacity-mask` (existing rule, `z-index: 2`), preserving the current dark-overlay-over-image stacking exactly as today.

### Markup change (repeated identically across all 7 pages)

Find (each page's exact heading text and opacity value differ, everything else is identical):
```html
<section id="hero_2" class="background-image" data-background="url(/img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```
Replace with:
```html
<section id="hero_2">
    <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```
- `class="background-image"` and `data-background="..."` are removed — this page no longer participates in the JS-driven mechanism.
- `alt=""` (not omitted) — this is decorative background art; each page's own `<h1>` already conveys the actual content, so an empty alt is the correct accessibility choice (not a missing/forgotten one).
- `width="1400" height="1050"` match the real new asset's pixel dimensions exactly (see below) — required for the browser to reserve correct layout space before the image loads.
- Some pages currently reference the image without a leading slash (`url(img/Tours/Stgo/big.jpg)`) and some with one (`url(/img/Tours/Stgo/big.jpg)`); since all 7 pages live at the repo root, both resolve identically today. The new `<img src>` uses a leading slash consistently on all 7 pages, matching the majority existing convention and removing the inconsistency.

### Image regeneration

Regenerate `img/Tours/Stgo/big.webp` (overwriting the existing unused one) from the current `img/Tours/Stgo/big.jpg` source, same 1400×1050 dimensions, WebP quality 75. Measured directly against the real source file: quality 75 produces a 193KB file (versus the current 320KB JPEG — a real ~40% reduction), and was chosen over quality 70 (182KB, marginally smaller but a visible step down in a side-by-side comparison) as the better size/quality balance. `big.jpg` itself is left in place, untouched — nothing else on the site references it apart from the tour pages, which are out of scope here.

## Verification

1. `php -l` on all 7 modified pages.
2. Local `php -S` server, visual check of all 7 pages: hero image displays correctly, same crop/framing as before, dark overlay and centered heading unchanged, no layout shift when the image loads (throttle network in dev tools to confirm the reserved space holds before the image arrives).
3. Confirm `js/functions.js`'s `.background-image` handler still works correctly for its remaining consumers, `admin/_hero.php` and `404.html` — log in to the local admin panel and check its hero section still renders, and load the 404 error page and check the same.
4. Re-run the same Lighthouse mobile audit against the live `gallery.php` used to diagnose this issue, once deployed. Compare LCP, the `lcp-breakdown-insight`'s "element render delay" figure specifically, and overall performance score against the documented baseline (57/100, LCP 11.7s, element render delay 1,597ms).
5. Spot-check at least one other of the 7 pages (not just gallery.php) with Lighthouse or a manual network-panel check, to confirm the fix generalizes rather than only having been verified on the one page the original audit targeted.

## Risks

- **Seven files get the same edit — a copy-paste-across-files change is exactly where one file quietly ends up different from the rest.** Mitigated by Verification step 5 explicitly requiring a second page to be checked, not just gallery.php.
- **The `object-fit: cover` upscaling trade-off (see Non-goals) is a pre-existing condition, not introduced by this change** — but it's worth the site owner knowing it exists: on very wide, high-DPI desktop monitors, this hero image was and remains modestly upscaled by the browser. This spec doesn't fix that; it's flagged here so it isn't mistaken for a new regression during Verification.
- **Quality 75 is a judgment call**, chosen from three real measured candidates (70/75/80). If it reads as noticeably softer once live, bumping to 80 (230KB, still well under the original 320KB JPEG) is a one-line regeneration with no other changes needed.
