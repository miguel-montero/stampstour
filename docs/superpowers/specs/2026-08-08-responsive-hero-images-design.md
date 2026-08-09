# Responsive mobile hero images for homepage and tour pages

## Context

Following the stylesheet-priority fix (`docs/superpowers/specs/2026-08-08-stylesheet-priority-media-print-design.md`), a real production resource-timing waterfall on `contact-us.php` confirmed the CSS priority fix worked exactly as designed (deferred stylesheets reached Chrome's genuine `VeryLow` tier) — yet the LCP hero image still took ~5.2s to download. That's strong evidence the image's own size, not remaining priority contention, is now the dominant bottleneck.

Investigating the homepage and tour pages' hero images found the same pattern, at larger scale:

| Page | Current hero source | Size | Native resolution | Displayed at |
|---|---|---|---|---|
| Homepage | `img/Tours/portada.webp` | 265KB | 1883×1059 | full-bleed, `object-fit:cover` |
| Discover Santiago | `img/Tours/Stgo/big.jpg` | 320KB | 1400×1050 | 360px/470px-tall banner, full width, `object-fit:cover` |
| Maipo | `img/Tours/Maipo/big.jpg` | 63KB | 720×480 (already correctly sized) | same |
| Portillo/Andes | `img/Tours/Andes/big.jpg` | 353KB | 1920×1440 | same |
| Valparaíso | `img/Tours/Valpo/big.jpg` | 294KB | 1920×716 | same |
| Cruise Transfer | `img/Tours/Cruise/big.jpg` | 210KB | 2000×1269 | same |

Every hero except Maipo's is served at 2-4x the resolution it's ever displayed at, identically to every viewport — no mobile-specific variant exists for any of them.

A real, tested fix was verified during investigation: a properly-generated mobile-sized WebP variant for Portillo/Andes (native 1920×1440 → resized to 780×585, `cwebp -q 80`) came out to **57.6KB — a 6.1x reduction** from the current 353KB JPG. A similar variant for a content-page hero (`Stgo/big.webp`, 1400×1050 → 600×450) came out to **49.7KB, a 4x reduction**.

**Important finding, don't reuse blindly:** each of these tour images already has a `.webp` sibling file on disk, but they are NOT reliable drop-in replacements — most are badly encoded and larger than the current JPG (Andes: 828KB webp vs 353KB jpg; Valparaíso: 377KB vs 294KB). Only Maipo's and Discover-Santiago's existing webp files are genuinely smaller. New, properly-encoded mobile variants must be generated fresh for this plan, not assumed from what's already on disk.

Tooling confirmed available in this environment: ImageMagick (`magick`) for resizing, `cwebp` for encoding.

## Goals

- Cut the LCP-critical hero image's mobile download weight substantially (demonstrated: 4-6x smaller) on the homepage and all 5 tour-type pages (the 4 uniform tour pages + `cruise-transfer.php`), without changing how the page looks on any device — same photo, same crop, same visual design.
- Make `includes/head.php`'s `$lcp_preload_image` mechanism responsive, so the browser's preload scanner fetches the *correct* (small, mobile) variant on a mobile viewport instead of always preloading the desktop-sized file regardless of device.
- Keep desktop/tablet visuals and file sizes unchanged — this is a mobile-specific addition, not a wholesale image replacement.

## Non-goals

- The content-pages hero image (`img/Tours/Stgo/big.webp`, used by `contact-us.php`/`privacy.php`/`refunds-cancellations.php`/`blog.php`/`blog-post.php`/`gallery.php`) — already investigated with real numbers (49.7KB mobile variant tested) but explicitly scoped out of *this* plan at the user's direction ("homepage and tour pages"); a natural, near-identical follow-up using the same technique.
- Regenerating or fixing the existing (badly-encoded) `.webp` sibling files already on disk — this plan adds new, correctly-sized mobile variants alongside them; the existing files are left alone (some may still be referenced elsewhere, out of scope to audit here).
- Any further architecture changes discussed during brainstorming (inlining all CSS, replacing icon fonts with SVG, lazy-loading the PayPal footer badge, minimizing JS) — a larger, separate initiative, not part of this plan.
- Desktop/tablet image optimization — out of scope; only the mobile-specific gap is addressed here.

## Design

### 1. Generate mobile-sized WebP variants

For each of the 6 heroes, generate a new file at a mobile-appropriate width (~780px, providing headroom for ~2x device-pixel-ratio on a 390px viewport — matching the exact dimensions already tested and verified during investigation), preserving each source image's native aspect ratio, encoded via `cwebp -q 80` (the quality setting already verified to produce a clean, correctly-sized result in testing):

| File to create | Source | Target dimensions (preserves native aspect ratio) |
|---|---|---|
| `img/Tours/portada-mobile-hero.webp` | `img/Tours/portada.webp` (1883×1059) | 780×439 |
| `img/Tours/Stgo/big-mobile.webp` | `img/Tours/Stgo/big.jpg` (1400×1050) | 780×585 |
| `img/Tours/Maipo/big-mobile.webp` | `img/Tours/Maipo/big.jpg` (720×480) | skip — already smaller than the 780px target; see Step 1a |
| `img/Tours/Andes/big-mobile.webp` | `img/Tours/Andes/big.jpg` (1920×1440) | 780×585 |
| `img/Tours/Valpo/big-mobile.webp` | `img/Tours/Valpo/big.jpg` (1920×716) | 780×291 |
| `img/Tours/Cruise/big-mobile.webp` | `img/Tours/Cruise/big.jpg` (2000×1269) | 780×495 |

**Step 1a — Maipo is a special case:** its current file (720×480, 63KB) is already smaller than the 780px mobile target would be, so generating a "mobile" variant would make things larger, not smaller. Skip generating a new file for Maipo; instead, just add a WebP-encoded version at its *current* dimensions (`img/Tours/Maipo/big-optimized.webp`, 720×480, `cwebp -q 82` — WebP still saves meaningfully over JPG at equal dimensions per the earlier investigation's own numbers, 45KB webp vs 63KB jpg) and use that as the single source for all viewports on this one page (no separate mobile/desktop split needed here, since the current size is already mobile-appropriate).

**Naming convention rationale:** homepage's file is named `portada-mobile-hero.webp` (not `portada-mobile.webp`) because `img/Tours/portada.webp` already has an unrelated sibling naming pattern used elsewhere on the homepage for tour cards (`img/Tours/Valpo/portada-mobile.webp` etc. — a *different* image, the tour-card thumbnail, not the hero) — using a distinct name avoids any confusion with that pre-existing, unrelated file.

Exact generation commands (run once, commit the resulting files):

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
magick img/Tours/portada.webp -resize 780x439 /tmp/portada-mobile-hero.png
cwebp -q 80 /tmp/portada-mobile-hero.png -o img/Tours/portada-mobile-hero.webp

magick img/Tours/Stgo/big.jpg -resize 780x585 /tmp/stgo-big-mobile.png
cwebp -q 80 /tmp/stgo-big-mobile.png -o img/Tours/Stgo/big-mobile.webp

magick img/Tours/Maipo/big.jpg -resize 720x480 /tmp/maipo-big-optimized.png
cwebp -q 82 /tmp/maipo-big-optimized.png -o img/Tours/Maipo/big-optimized.webp

magick img/Tours/Andes/big.jpg -resize 780x585 /tmp/andes-big-mobile.png
cwebp -q 80 /tmp/andes-big-mobile.png -o img/Tours/Andes/big-mobile.webp

magick img/Tours/Valpo/big.jpg -resize 780x291 /tmp/valpo-big-mobile.png
cwebp -q 80 /tmp/valpo-big-mobile.png -o img/Tours/Valpo/big-mobile.webp

magick img/Tours/Cruise/big.jpg -resize 780x495 /tmp/cruise-big-mobile.png
cwebp -q 80 /tmp/cruise-big-mobile.png -o img/Tours/Cruise/big-mobile.webp
```

### 2. Convert each hero `<img>` to a responsive `<picture>`

**Homepage** (`index.php`), current:
```html
<img
    src="img/Tours/portada.webp"
    width="1883"
    height="1059"
    fetchpriority="high"
    alt="Colorful hillside houses in Valparaíso, Chile"
    class="hero-bg">
```
becomes:
```html
<picture>
    <source media="(max-width: 767px)" srcset="img/Tours/portada-mobile-hero.webp">
    <img
        src="img/Tours/portada.webp"
        width="1883"
        height="1059"
        fetchpriority="high"
        alt="Colorful hillside houses in Valparaíso, Chile"
        class="hero-bg">
</picture>
```

**Each tour page** (`discover-santiago-city-tour.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`, `cruise-transfer.php`), example (Portillo/Andes), current:
```html
<img src="img/Tours/Andes/big.jpg" width="1920" height="1440" fetchpriority="high" alt="Andes banner" class="tour-banner-bg">
```
becomes:
```html
<picture>
    <source media="(max-width: 767px)" srcset="img/Tours/Andes/big-mobile.webp">
    <img src="img/Tours/Andes/big.jpg" width="1920" height="1440" fetchpriority="high" alt="Andes banner" class="tour-banner-bg">
</picture>
```
(Adjust `width`/`height`/`alt` per page to match each page's existing values exactly — this plan does not change those, only wraps the existing `<img>` in a `<picture>` with a mobile `<source>` added.)

**Maipo** (`maipo-valley-wine-tour-santiago.php`) — special case per Step 1a, uses the single optimized WebP directly, no `<picture>`/media-query split needed:
```html
<img src="img/Tours/Maipo/big-optimized.webp" width="720" height="480" fetchpriority="high" alt="Maipo Valley banner" class="tour-banner-bg">
```

The `max-width: 767px` breakpoint matches this codebase's own established convention (the exact breakpoint already used throughout `css/style.css`/`includes/critical/*.css` for mobile-specific rules, e.g. `@media (max-width:767px)` blocks already present in the tour critical CSS).

### 3. Make `includes/head.php`'s `$lcp_preload_image` responsive

> **Amendment (post-implementation, commit `0a8c2358`):** the `imagesrcset`/`imagesizes` design described below shipped, but the final whole-branch review found it was a real bug: `imagesrcset`/`imagesizes` resource selection is DPR-aware, while the `<picture><source media="...">` element (Design section 2) it's meant to mirror is a pure media-query match with no DPR sensitivity. The `imagesizes` value here (`780px`, the mobile file's own width) never matched the image's actual rendered CSS width (`100vw`), so on any real phone with DPR >= 2 the two mechanisms disagreed — the preload fetched the desktop file while `<picture>` rendered the mobile one, downloading both. The shipped fix (`includes/head.php`, current code) replaces this with two separate `media`-gated `<link rel="preload">` tags, mirroring the `<picture>` breakpoint exactly instead of using width descriptors — not DPR-sensitive by construction, so preload and render always agree. **Do not reimplement the `imagesrcset`/`imagesizes` version below** — it's left in place only as a historical record of what shipped first and why it was wrong; see `includes/head.php`'s doc comment for the current, correct design.

Currently:
```php
<?php if (!empty($lcp_preload_image) && is_file(__DIR__ . '/../' . $lcp_preload_image)): ?>
<link rel="preload" as="image" href="/<?= htmlspecialchars($lcp_preload_image, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
<?php endif; ?>
```

New: extend the doc comment to document two additional, optional variables — `$lcp_preload_image_mobile` (mobile variant path) and `$lcp_preload_image_width` (the *actual pixel width* of the `$lcp_preload_image` file, required whenever `_mobile` is set, since the `imagesrcset` `w`-descriptor must match the real file width for the browser's preload-selection math to be correct — it is not a fixed constant across pages, as these hero files range from 1400px to 2000px wide). The mobile variants are always generated at a fixed 780px width (per Step 1), so that side of the descriptor is a constant; the desktop side is not. Use the standard `imagesrcset`/`imagesizes` preload attributes (the same mechanism `<picture>`/`<img srcset>` already use, applied to `<link rel="preload">`) so the browser's preload scanner picks the right file per viewport before it even starts parsing the body:

```php
<?php if (!empty($lcp_preload_image) && is_file(__DIR__ . '/../' . $lcp_preload_image)): ?>
<?php if (!empty($lcp_preload_image_mobile) && !empty($lcp_preload_image_width) && is_file(__DIR__ . '/../' . $lcp_preload_image_mobile)): ?>
<link rel="preload" as="image" href="/<?= htmlspecialchars($lcp_preload_image, ENT_QUOTES, 'UTF-8') ?>" imagesrcset="/<?= htmlspecialchars($lcp_preload_image_mobile, ENT_QUOTES, 'UTF-8') ?> 780w, /<?= htmlspecialchars($lcp_preload_image, ENT_QUOTES, 'UTF-8') ?> <?= (int) $lcp_preload_image_width ?>w" imagesizes="(max-width: 767px) 780px, 100vw" fetchpriority="high">
<?php else: ?>
<link rel="preload" as="image" href="/<?= htmlspecialchars($lcp_preload_image, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
<?php endif; ?>
<?php endif; ?>
```

Pages that don't set `$lcp_preload_image_mobile`/`$lcp_preload_image_width` (any page not touched by this plan) fall through to the existing, unchanged single-image preload — fully backward compatible, zero behavior change for every page not explicitly updated.

Each page sets all three variables before including `head.php` (desktop width per the Context table above: portada 1883, Stgo 1400, Andes 1920, Valpo 1920, Cruise 2000). Example (`index.php`):
```php
$lcp_preload_image = 'img/Tours/portada.webp';
$lcp_preload_image_mobile = 'img/Tours/portada-mobile-hero.webp';
$lcp_preload_image_width = 1883;
```
Example (`portillo-inca-lagoon-andes-mountains-vineyard.php`):
```php
$lcp_preload_image = 'img/Tours/Andes/big.jpg';
$lcp_preload_image_mobile = 'img/Tours/Andes/big-mobile.webp';
$lcp_preload_image_width = 1920;
```
Example (`discover-santiago-city-tour.php`):
```php
$lcp_preload_image = 'img/Tours/Stgo/big.jpg';
$lcp_preload_image_mobile = 'img/Tours/Stgo/big-mobile.webp';
$lcp_preload_image_width = 1400;
```
Example (`valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`):
```php
$lcp_preload_image = 'img/Tours/Valpo/big.jpg';
$lcp_preload_image_mobile = 'img/Tours/Valpo/big-mobile.webp';
$lcp_preload_image_width = 1920;
```
Example (`cruise-transfer.php`):
```php
$lcp_preload_image = 'img/Tours/Cruise/big.jpg';
$lcp_preload_image_mobile = 'img/Tours/Cruise/big-mobile.webp';
$lcp_preload_image_width = 2000;
```
Maipo keeps a single `$lcp_preload_image` (no `_mobile`/`_width`, per Step 1a):
```php
$lcp_preload_image = 'img/Tours/Maipo/big-optimized.webp';
```

## Testing

Consistent with this session's established, hard-won lessons:
- **Do not measure LCP timing on a local `php -S` server** — CDP network throttling doesn't meaningfully throttle localhost traffic; local testing here is for functional/visual correctness only (the right file loads on the right viewport, nothing renders broken, `width`/`height`/`alt` preserved).
- **Real measurement must be against production**, after deploy, and should use a controlled comparison (interleaved local A/B for a relative signal, plus a single labeled production reading) rather than a naive before/after taken minutes apart — host-load drift already produced one false "regression" reading earlier this session.
- Verify at the network level (not just visually) that a mobile-viewport Puppeteer session actually requests the `-mobile`/`-optimized` file, and a desktop-viewport session requests the original — this is the concrete proof the `<picture>`/preload logic is wired correctly, not just that something loads.
- Visually confirm the crop/framing looks the same on mobile after the swap (the resize preserves aspect ratio exactly, so this should be a non-issue, but verify directly rather than assume).

## Risks

- **7 new/changed image files plus 6 page edits plus one shared-code change is a moderate blast radius** — mitigated by keeping every page's existing desktop path, `width`/`height`, and `alt` text completely unchanged; only a `<picture>` wrapper and a new mobile `<source>` are added.
- **`imagesrcset`/`imagesizes` on `<link rel="preload">` has slightly newer/narrower browser support than plain `srcset` on `<img>`** (well-supported in Chrome/Edge, which is what this site's mobile traffic and Lighthouse/PSI testing predominantly use; older/other browsers simply ignore the attributes and fall back to the plain `href`, which still works correctly — no broken state possible, just a missed optimization on unsupported browsers).
- **Maipo's asymmetric treatment (no `_mobile` variant, just a re-encoded same-size WebP) is a deliberate exception**, not an oversight — flagged explicitly in the design so a future reader doesn't "fix" it into unnecessary inconsistency with the other 5 pages.
