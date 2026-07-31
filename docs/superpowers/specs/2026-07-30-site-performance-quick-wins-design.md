# Site Performance Quick Wins

## Problem

`stampstour.com` feels slow to load. A diagnostic pass on the live homepage found the bottleneck is front-end asset weight, not backend response time (TTFB is a healthy ~250ms):

- **Images are drastically oversized, in both formats actually served.** The homepage already ships `<picture>`/`<source type="webp">` markup, so modern browsers fetch the `.webp` sibling instead of the `.jpg`/`.png` fallback — but the `.webp` files were generated at full original resolution and are often barely smaller than the fallback:

  | File | Fallback | WebP (what browsers actually fetch) | Displayed at |
  |---|---|---|---|
  | `img/logolargo.png`/`.webp` | 1691×600, 385KB | 138KB | 47px tall |
  | `img/logo_sticky.png`/`.webp` | 1799×417, 915KB | 76KB | 34px tall |
  | `img/Tours/portada.jpg`/`.webp` (hero) | 1883×1059, 325KB | 294KB | full-width hero |
  | `img/Tours/Maipo/portada.jpg`/`.webp` | 720×480, 107KB | 92KB | ~800×533 card |
  | `img/Tours/Andes/portada.jpg`/`.webp` | 1400×1000, 302KB | 230KB | ~800×533 card |
  | `img/Tours/Stgo/portada.jpg`/`.webp` | 1440×959, 240KB | 266KB | ~800×533 card |
  | `img/Tours/Valpo/portada.jpeg`/`.webp` | 955×650, 116KB | 162KB | ~800×533 card |
  | `img/Tours/Cruise/portada.jpeg`/`.webp` | **2560×1440, 884KB** | 733KB | ~800×533 card |

  Real-world homepage weight from just these files (webp variants, as actually downloaded) is **~2MB**.

- **Per-tour photo gallery thumbnails are worse, and load eagerly (no lazy-loading at all).** Each tour page (`maipo-valley-wine-tour-santiago.php`, etc.) renders a gallery of `<img class="sp-thumbnail" src="img/Tours/<Tour>/<n>_medium.webp">` tags with a plain `src` (not lazy, not behind a JS placeholder like the lightbox's own `sp-image` tags, which already use a `blank.gif` placeholder pattern). These `_medium.webp` files range from ~50KB to **495KB each**, and there are 8–45 of them per tour folder. This is likely the single largest chunk of transferred bytes on any tour page, but resizing/recompressing all of them (dozens of files across 5 tour folders) is a larger undertaking than the rest of this pass — see Out of Scope.
- **`style-rtl.css` loads on every page** despite the site being English/LTR only — dead weight on every request.
- Some `<img>` tags are missing explicit `width`/`height`, risking layout shift.
- Separately, Cloudflare's "Browser Cache TTL" dashboard setting is capping cached-asset lifetime at 4 hours, overriding the origin's correct 1-year/1-month `.htaccess` expiry rules — but this is a Cloudflare dashboard change outside this repo, not part of this implementation (see Out of Scope).

## Scope

Whole site (all pages share the same header, logo, and CSS includes, so header/CSS fixes apply everywhere automatically). Quick-wins only: no visual/markup redesign, no new image formats introduced (WebP is already in production — this pass fixes the sizing of files that already exist, in both formats already being served), no JS/CSS bundling rework.

## Approach

Recompress and resize images **in place** — same filenames, same formats (both the fallback and the existing `.webp` sibling get resized) — so no template/HTML changes are required for the resize itself. (`loading="lazy"` and `width`/`height` attribute additions are the one place this pass does touch markup, since those don't exist yet in some spots.)

## Changes

### 1. Image backup (do this first, before touching any image)

Before overwriting any image file, copy the original into `_archive/img-optimization-backup/`, mirroring its original relative path (e.g. `img/logolargo.png` → `_archive/img-optimization-backup/img/logolargo.png`). `_archive/` is already gitignored in this repo (established as the "local dead-file safety net" pattern), so backups stay local and are never committed or deployed to the live server.

### 2. Resize + recompress images

For each file below, resize **both** the fallback (`.png`/`.jpg`/`.jpeg`) and its existing `.webp` sibling to the same target dimensions, keeping filenames and formats unchanged:

- `img/logolargo.{png,webp}` and `img/logo_sticky.{png,webp}`: resize to ~2x rendered display size (retina-sharp) — roughly 320×114 and 320×74.
- `img/Tours/portada.{jpg,webp}` (homepage hero): resize to a sane max width for a full-width hero (no need for the full 1883px source).
- `img/Tours/{Maipo,Andes,Stgo,Valpo,Cruise}/portada.{jpg/jpeg,webp}`: resize to a sane max width for their ~800×533 card display size and recompress as mozjpeg-quality JPEG / equivalent WebP quality.
- Target: this set drops from ~2MB (real webp weight) to a few hundred KB total.

### 3. Lazy-loading + layout-shift prevention

- Add `loading="lazy"` to the per-tour gallery `sp-thumbnail` `<img>` tags (`img/Tours/<Tour>/<n>_medium.webp`, in each tour page's PHP loop) — these currently load eagerly with a plain `src` and are the single biggest opportunity here short of resizing them (see Out of Scope for why resizing them isn't in this pass).
- Audit remaining pages (`login.php`, `404.html`, `admin.php`, `admin/preferentials.php`, `shopping.php`) for below-the-fold `<img>` tags missing `loading="lazy"`; add it where missing. (The homepage's tour cards already have `loading="lazy"` — no change needed there.)
- Do **not** add `loading="lazy"` to hero/LCP images (e.g. the homepage slider image, logos) — lazy-loading them would delay Largest Contentful Paint.
- Add explicit `width`/`height` attributes to any `<img>` currently missing them (e.g. `logolargo.png` usages lack `width` in several places while `logo_sticky.png` usages already have it), to prevent layout shift.

### 4. Remove dead CSS

- Remove the `style-rtl.css` `<link>` from the shared header, after confirming no page/feature toggles an RTL class or `dir="rtl"` attribute that depends on it.

## Out of Scope

- **Cloudflare Browser Cache TTL setting.** Currently capping cached assets at 4 hours regardless of origin headers. Fixing it requires Cloudflare dashboard access (Dashboard → stampstour.com → Caching → Configuration → Browser Cache TTL → set to "Respect Existing Headers"), which isn't available in this pass. The image/CSS weight reduction in this plan still speeds up every load (first-visit or repeat); only the "skip re-download entirely on repeat visits" benefit is deferred until someone changes that setting.
- AVIF image format and additional responsive `srcset` breakpoints.
- **Bulk resize/recompression of the per-tour gallery thumbnails** (`_medium.webp`, 8–45 files per tour folder, up to 495KB each). This is a large, mechanically different task (many files, need to check each one's actual displayed thumbnail size) — flagged as follow-up work. This pass only adds `loading="lazy"` to them, which is a meaningful win on its own since they currently load eagerly.
- CSS/JS bundling, minification pipeline, or critical-CSS extraction.
- Replacing or deferring the Revolution Slider homepage carousel.

## Testing

- Visual check: homepage and each tour page, logo and hero/card images render sharp (not blurry from over-compression) at both normal and retina display density.
- Confirm no layout shift introduced by the new image dimensions (compare before/after screenshots).
- Confirm site still renders correctly (no RTL-dependent styling broken) after removing `style-rtl.css`.
- Re-run the same `curl`-based size checks used in diagnosis to confirm image payload dropped from ~2MB+ to target sizes.
