# Site Performance Quick Wins

## Problem

`stampstour.com` feels slow to load. A diagnostic pass on the live homepage found the bottleneck is front-end asset weight, not backend response time (TTFB is a healthy ~250ms):

- **Images are drastically oversized.** `logolargo.png` is 1691×600px (385KB) but displayed at 47px tall. `logo_sticky.png` is 1799×417px (**915KB**) displayed at 34px tall. Together the two logo files are ~1.27MB — more than all four tour photos combined. The four tour "portada" images (`img/Tours/{Maipo,Andes,Stgo,Valpo}/portada.*`) are similarly oversized relative to their card display size, totaling ~760KB.
- **No lazy-loading** on any `<img>`, so every image on a page loads immediately regardless of viewport position.
- **`style-rtl.css` loads on every page** despite the site being English/LTR only — dead weight on every request.
- Some `<img>` tags are missing explicit `width`/`height`, risking layout shift.
- Separately, Cloudflare's "Browser Cache TTL" dashboard setting is capping cached-asset lifetime at 4 hours, overriding the origin's correct 1-year/1-month `.htaccess` expiry rules — but this is a Cloudflare dashboard change outside this repo, not part of this implementation (see Out of Scope).

## Scope

Whole site (all pages share the same header, logo, and CSS includes, so header/CSS fixes apply everywhere automatically). Quick-wins only: no visual/markup redesign, no image format changes (stay JPEG/PNG), no JS/CSS bundling rework.

## Approach

Recompress and resize images **in place** — same filenames, same formats — so no template/HTML changes are required anywhere on the site. (Converting to WebP with `<picture>` fallbacks would save more bytes but requires markup changes on every page; deferred to a future round.)

## Changes

### 1. Image backup (do this first, before touching any image)

Before overwriting any image file, copy the original into `_archive/img-optimization-backup/`, mirroring its original relative path (e.g. `img/logolargo.png` → `_archive/img-optimization-backup/img/logolargo.png`). `_archive/` is already gitignored in this repo (established as the "local dead-file safety net" pattern), so backups stay local and are never committed or deployed to the live server.

### 2. Resize + recompress images

- `img/logolargo.png` and `img/logo_sticky.png`: resize to ~2x their rendered display size (retina-sharp) — roughly 320×114 and 320×74 — and re-export as PNG. Target: low tens of KB combined, down from ~1.27MB.
- `img/Tours/Maipo/portada.jpg`, `img/Tours/Andes/portada.jpg`, `img/Tours/Stgo/portada.jpg`, `img/Tours/Valpo/portada.jpeg`: resize to a sane max width for their card display size and recompress as mozjpeg-quality JPEG. Target: well under 150KB combined, down from ~760KB.
- Same filenames and formats throughout — no HTML changes needed.

### 3. Lazy-loading + layout-shift prevention

- Add `loading="lazy"` to below-the-fold `<img>` tags site-wide.
- Do **not** add `loading="lazy"` to the hero/LCP image (the first image the homepage paints) — lazy-loading it would delay Largest Contentful Paint.
- Add explicit `width`/`height` attributes to any `<img>` currently missing them, to prevent layout shift.

### 4. Remove dead CSS

- Remove the `style-rtl.css` `<link>` from the shared header, after confirming no page/feature toggles an RTL class or `dir="rtl"` attribute that depends on it.

## Out of Scope

- **Cloudflare Browser Cache TTL setting.** Currently capping cached assets at 4 hours regardless of origin headers. Fixing it requires Cloudflare dashboard access (Dashboard → stampstour.com → Caching → Configuration → Browser Cache TTL → set to "Respect Existing Headers"), which isn't available in this pass. The image/CSS weight reduction in this plan still speeds up every load (first-visit or repeat); only the "skip re-download entirely on repeat visits" benefit is deferred until someone changes that setting.
- WebP/AVIF image formats and `<picture>`/`srcset` responsive images.
- CSS/JS bundling, minification pipeline, or critical-CSS extraction.
- Replacing or deferring the Revolution Slider homepage carousel.

## Testing

- Visual check: homepage and each tour page, logo and hero/card images render sharp (not blurry from over-compression) at both normal and retina display density.
- Confirm no layout shift introduced by the new image dimensions (compare before/after screenshots).
- Confirm site still renders correctly (no RTL-dependent styling broken) after removing `style-rtl.css`.
- Re-run the same `curl`-based size checks used in diagnosis to confirm image payload dropped from ~2MB+ to target sizes.
