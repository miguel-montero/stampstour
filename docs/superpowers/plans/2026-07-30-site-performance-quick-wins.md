# Site Performance Quick Wins Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cut `stampstour.com`'s front-end page weight (currently ~2MB of oversized images on the homepage alone, more on tour pages) without any visual, markup-behavior, or template-structure changes beyond adding `loading="lazy"` / `width`/`height` attributes and deleting one dead stylesheet link.

**Architecture:** This is an asset-optimization + light-markup pass on a legacy hand-rolled PHP site (no build pipeline, no test framework, no bundler). All image work happens in place (same filenames/formats) via ImageMagick's `magick` CLI. All markup work is direct edits to the `.php`/`.html` files that reference those images. Nothing here is deployed automatically — see Task 8.

**Tech Stack:** PHP (no framework), static `.htaccess`-routed pages, ImageMagick (`magick`, confirmed installed with WebP read/write support) for image resize/recompress, `php -l` for syntax verification after markup edits.

## Global Constraints

- Every image file this plan touches must be backed up (byte-for-byte copy, original path preserved under `_archive/img-optimization-backup/`) **before** it is overwritten. `_archive/` is already gitignored in this repo.
- No image filename or format changes — every file is overwritten in place.
- No `loading="lazy"` on above-the-fold / LCP images (logos, homepage hero slider image).
- No changes to CSS/JS bundling, the Revolution Slider, or the per-tour gallery thumbnail *files* (`_medium.webp`) — only `loading="lazy"` is added to those `<img>` tags in this pass; resizing them is out of scope (flagged as follow-up in the spec).
- After every markup edit to a `.php` file, run `php -l <file>` and confirm `No syntax errors detected` before moving on.
- Working directory for all commands below: `/Users/miguelmontero/Documents/superpowers/STAMP` (the actual site root — do not confuse with its parent directory, which holds an unrelated stale copy of `.htaccess`).

---

### Task 1: Back up original images before touching any of them

**Files:**
- Create: `_archive/img-optimization-backup/img/logolargo.png`, `.../logolargo.webp`, `.../logo_sticky.png`, `.../logo_sticky.webp`, `.../Tours/portada.jpg`, `.../Tours/portada.webp`, `.../Tours/Andes/portada.jpg`, `.../Tours/Andes/portada.webp`, `.../Tours/Stgo/portada.jpg`, `.../Tours/Stgo/portada.webp`, `.../Tours/Valpo/portada.jpeg`, `.../Tours/Valpo/portada.webp`, `.../Tours/Valpo/portada.jpg`, `.../Tours/Cruise/portada.jpeg`, `.../Tours/Cruise/portada.webp`, `.../Tours/Cruise/cover.jpg`, `.../Tours/Cruise/cover.webp` (17 files total, mirroring `img/...` under `_archive/img-optimization-backup/img/...`)

**Interfaces:**
- Produces: a complete, untouched-original copy of every file Task 2 and Task 3 will overwrite. Later tasks assume this backup exists before they run.

- [ ] **Step 1: Create the backup directory tree and copy every source file**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
mkdir -p _archive/img-optimization-backup/img/Tours/Andes \
         _archive/img-optimization-backup/img/Tours/Stgo \
         _archive/img-optimization-backup/img/Tours/Valpo \
         _archive/img-optimization-backup/img/Tours/Cruise

for f in \
  img/logolargo.png img/logolargo.webp \
  img/logo_sticky.png img/logo_sticky.webp \
  img/Tours/portada.jpg img/Tours/portada.webp \
  img/Tours/Andes/portada.jpg img/Tours/Andes/portada.webp \
  img/Tours/Stgo/portada.jpg img/Tours/Stgo/portada.webp \
  img/Tours/Valpo/portada.jpeg img/Tours/Valpo/portada.webp img/Tours/Valpo/portada.jpg \
  img/Tours/Cruise/portada.jpeg img/Tours/Cruise/portada.webp \
  img/Tours/Cruise/cover.jpg img/Tours/Cruise/cover.webp \
; do
  cp "$f" "_archive/img-optimization-backup/$f"
done
```

- [ ] **Step 2: Verify all 17 files were copied and are byte-identical to the originals**

```bash
count=0
for f in \
  img/logolargo.png img/logolargo.webp \
  img/logo_sticky.png img/logo_sticky.webp \
  img/Tours/portada.jpg img/Tours/portada.webp \
  img/Tours/Andes/portada.jpg img/Tours/Andes/portada.webp \
  img/Tours/Stgo/portada.jpg img/Tours/Stgo/portada.webp \
  img/Tours/Valpo/portada.jpeg img/Tours/Valpo/portada.webp img/Tours/Valpo/portada.jpg \
  img/Tours/Cruise/portada.jpeg img/Tours/Cruise/portada.webp \
  img/Tours/Cruise/cover.jpg img/Tours/Cruise/cover.webp \
; do
  cmp -s "$f" "_archive/img-optimization-backup/$f" && count=$((count+1)) || echo "MISMATCH: $f"
done
echo "$count / 17 verified identical"
```

Expected: `17 / 17 verified identical`, no `MISMATCH` lines.

- [ ] **Step 3: Commit**

Note: `_archive/` is gitignored, so `git status` should show no new files here. Nothing to commit for this task — the backup is local-only by design (per spec). Just confirm:

```bash
git status --short | grep img-optimization-backup
```

Expected: no output (confirms the backup directory is correctly ignored, not staged).

---

### Task 2: Resize and recompress the logo images

**Files:**
- Modify: `img/logolargo.png`, `img/logolargo.webp`, `img/logo_sticky.png`, `img/logo_sticky.webp`

**Interfaces:**
- Consumes: originals backed up in Task 1 (rollback source if anything goes wrong: `cp _archive/img-optimization-backup/img/logolargo.png img/logolargo.png` etc.)
- Produces: `img/logolargo.{png,webp}` at 320×114px, `img/logo_sticky.{png,webp}` at 320×74px — consumed visually by every page that renders the header (all pages) and by Task 6 (which adds matching `width` attributes elsewhere).

- [ ] **Step 1: Resize and recompress**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
magick img/logolargo.png    -resize 320x114 -strip -quality 90 img/logolargo.png
magick img/logolargo.webp   -resize 320x114 -quality 88          img/logolargo.webp
magick img/logo_sticky.png  -resize 320x74  -strip -quality 90 img/logo_sticky.png
magick img/logo_sticky.webp -resize 320x74  -quality 88          img/logo_sticky.webp
```

- [ ] **Step 2: Verify new dimensions and sizes**

```bash
identify -format "%f %wx%h %b\n" img/logolargo.png img/logolargo.webp img/logo_sticky.png img/logo_sticky.webp
```

Expected: `logolargo.png 320x114 <a few tens of KB>`, `logolargo.webp 320x114 <a few KB>`, `logo_sticky.png 320x74 <a few tens of KB>`, `logo_sticky.webp 320x74 <a few KB>` — all four dramatically smaller than the pre-resize sizes (385KB/138KB/915KB/76KB respectively).

- [ ] **Step 3: Visual sanity check**

Open `img/logolargo.png` and `img/logo_sticky.png` in Preview (or `open img/logolargo.png img/logo_sticky.png`) and confirm the logo text is still crisp and readable, not blurry or artifacted from over-compression.

- [ ] **Step 4: Commit**

```bash
git add img/logolargo.png img/logolargo.webp img/logo_sticky.png img/logo_sticky.webp
git commit -m "Resize/recompress logo images to match actual display size

logolargo.png/.webp and logo_sticky.png/.webp were shipped at their
original export resolution (up to 1799x417) despite rendering at
34-47px tall in the header. Resized to ~2x retina display size."
```

---

### Task 3: Resize and recompress the hero and tour-card images

**Files:**
- Modify: `img/Tours/portada.jpg`, `img/Tours/portada.webp` (homepage hero), `img/Tours/Andes/portada.jpg`, `img/Tours/Andes/portada.webp`, `img/Tours/Stgo/portada.jpg`, `img/Tours/Stgo/portada.webp`, `img/Tours/Valpo/portada.jpeg`, `img/Tours/Valpo/portada.webp`, `img/Tours/Valpo/portada.jpg` (the stray duplicate 404.html uses), `img/Tours/Cruise/portada.jpeg`, `img/Tours/Cruise/portada.webp`, `img/Tours/Cruise/cover.jpg`, `img/Tours/Cruise/cover.webp`
- Do NOT modify: `img/Tours/Maipo/portada.jpg` / `.webp` — already 720×480, smaller than its 800×533 display size; not oversized, leave as-is.

**Interfaces:**
- Consumes: originals backed up in Task 1.
- Produces: resized/recompressed images at their existing filenames, consumed visually by `index.php`, `404.html`, and the four tour detail pages.

- [ ] **Step 1: Resize (bounding box — only shrinks if larger, so already-small files like Andes/Stgo/Valpo just get recompressed) and recompress**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP

# Homepage hero — cap at 1920px wide
magick img/Tours/portada.jpg  -resize 1920x1920\> -strip -sampling-factor 4:2:0 -quality 78 img/Tours/portada.jpg
magick img/Tours/portada.webp -resize 1920x1920\> -quality 78                     img/Tours/portada.webp

# Tour cards — cap at 1600px wide (2x retina of the 800x533 display size)
for dir in Andes Stgo; do
  magick "img/Tours/$dir/portada.jpg"  -resize 1600x1600\> -strip -sampling-factor 4:2:0 -quality 78 "img/Tours/$dir/portada.jpg"
  magick "img/Tours/$dir/portada.webp" -resize 1600x1600\> -quality 78                     "img/Tours/$dir/portada.webp"
done

magick img/Tours/Valpo/portada.jpeg -resize 1600x1600\> -strip -sampling-factor 4:2:0 -quality 78 img/Tours/Valpo/portada.jpeg
magick img/Tours/Valpo/portada.webp -resize 1600x1600\> -quality 78                     img/Tours/Valpo/portada.webp
magick img/Tours/Valpo/portada.jpg  -resize 1600x1600\> -strip -sampling-factor 4:2:0 -quality 78 img/Tours/Valpo/portada.jpg

# Cruise — portada.* and cover.* are byte-identical duplicates; resize once, copy to the other name
magick img/Tours/Cruise/portada.jpeg -resize 1600x1600\> -strip -sampling-factor 4:2:0 -quality 78 img/Tours/Cruise/portada.jpeg
magick img/Tours/Cruise/portada.webp -resize 1600x1600\> -quality 78                     img/Tours/Cruise/portada.webp
cp img/Tours/Cruise/portada.jpeg img/Tours/Cruise/cover.jpg
cp img/Tours/Cruise/portada.webp img/Tours/Cruise/cover.webp
```

- [ ] **Step 2: Verify new dimensions and sizes**

```bash
identify -format "%f %wx%h %b\n" \
  img/Tours/portada.jpg img/Tours/portada.webp \
  img/Tours/Andes/portada.jpg img/Tours/Andes/portada.webp \
  img/Tours/Stgo/portada.jpg img/Tours/Stgo/portada.webp \
  img/Tours/Valpo/portada.jpeg img/Tours/Valpo/portada.webp img/Tours/Valpo/portada.jpg \
  img/Tours/Cruise/portada.jpeg img/Tours/Cruise/portada.webp \
  img/Tours/Cruise/cover.jpg img/Tours/Cruise/cover.webp
```

Expected: Cruise files drop from 2560×1440 to 1600×900; Andes/Stgo/Valpo keep their original dimensions (already under the 1600px cap) but shrink in byte size due to recompression; all files well under their pre-task sizes (Cruise in particular should drop from ~884KB/733KB to well under 200KB).

- [ ] **Step 3: Confirm the Cruise duplicate pairs are still identical after the copy**

```bash
cmp -s img/Tours/Cruise/portada.jpeg img/Tours/Cruise/cover.jpg && echo "jpg pair OK" || echo "MISMATCH"
cmp -s img/Tours/Cruise/portada.webp img/Tours/Cruise/cover.webp && echo "webp pair OK" || echo "MISMATCH"
```

Expected: `jpg pair OK` and `webp pair OK`.

- [ ] **Step 4: Visual sanity check**

```bash
open img/Tours/portada.jpg img/Tours/Andes/portada.jpg img/Tours/Stgo/portada.jpg img/Tours/Valpo/portada.jpeg img/Tours/Cruise/portada.jpeg
```

Confirm none look over-compressed (blocky/blurry) — quality 78 should be visually indistinguishable from the originals at normal viewing size.

- [ ] **Step 5: Commit**

```bash
git add img/Tours/portada.jpg img/Tours/portada.webp \
        img/Tours/Andes/portada.jpg img/Tours/Andes/portada.webp \
        img/Tours/Stgo/portada.jpg img/Tours/Stgo/portada.webp \
        img/Tours/Valpo/portada.jpeg img/Tours/Valpo/portada.webp img/Tours/Valpo/portada.jpg \
        img/Tours/Cruise/portada.jpeg img/Tours/Cruise/portada.webp \
        img/Tours/Cruise/cover.jpg img/Tours/Cruise/cover.webp
git commit -m "Resize/recompress hero and tour-card images

Cruise/portada.jpeg was 2560x1440 (884KB) for an ~800x533 display
size; other tour cards were reasonably sized but poorly compressed.
Maipo left untouched - already smaller than its display size."
```

---

### Task 4: Add `loading="lazy"` to per-tour gallery thumbnails

These `<img class="sp-thumbnail">` tags currently load eagerly with a plain `src` (unlike the lightbox's own `sp-image` tags, which already use a `blank.gif` JS-driven placeholder). Valparaiso alone renders 44 of these per page load. Adding `loading="lazy"` is a pure attribute addition — zero visual change, browser defers offscreen images automatically.

**Files:**
- Modify: `maipo-valley-wine-tour-santiago.php:105,107`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php:113,115`, `discover-santiago-city-tour.php:107,109`, `portillo-inca-lagoon-andes-mountains-vineyard.php:108,110`, `cruise-transfer.php:115,117`

**Interfaces:**
- None — this task only edits static HTML attributes inside existing PHP templates; no functions/signatures involved.

- [ ] **Step 1: Edit `maipo-valley-wine-tour-santiago.php`**

Line 105, change:
```php
        <img class="sp-thumbnail" src="img/Tours/Maipo/portada.webp" alt="Maipo thumbnail cover">
```
to:
```php
        <img class="sp-thumbnail" src="img/Tours/Maipo/portada.webp" alt="Maipo thumbnail cover" loading="lazy">
```

Line 107, change:
```php
         <img class="sp-thumbnail" src="img/Tours/Maipo/<?php echo $i; ?>_medium.webp" alt="Maipo thumbnail <?php echo $i; ?>">
```
to:
```php
         <img class="sp-thumbnail" src="img/Tours/Maipo/<?php echo $i; ?>_medium.webp" alt="Maipo thumbnail <?php echo $i; ?>" loading="lazy">
```

- [ ] **Step 2: Edit `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`**

Line 113, change:
```php
        <img class="sp-thumbnail" src="img/Tours/Valpo/portada.webp" alt="Valparaiso thumbnail cover">
```
to:
```php
        <img class="sp-thumbnail" src="img/Tours/Valpo/portada.webp" alt="Valparaiso thumbnail cover" loading="lazy">
```

Line 115, change:
```php
         <img class="sp-thumbnail" src="img/Tours/Valpo/<?php echo $i; ?>_medium.webp" alt="Valparaiso thumbnail <?php echo $i; ?>">
```
to:
```php
         <img class="sp-thumbnail" src="img/Tours/Valpo/<?php echo $i; ?>_medium.webp" alt="Valparaiso thumbnail <?php echo $i; ?>" loading="lazy">
```

- [ ] **Step 3: Edit `discover-santiago-city-tour.php`**

Line 107, change:
```php
        <img class="sp-thumbnail" src="img/Tours/Stgo/portada.webp" alt="Stgo thumbnail cover">
```
to:
```php
        <img class="sp-thumbnail" src="img/Tours/Stgo/portada.webp" alt="Stgo thumbnail cover" loading="lazy">
```

Line 109, change:
```php
         <img class="sp-thumbnail" src="img/Tours/Stgo/<?php echo $i; ?>_medium.webp" alt="Stgo thumbnail <?php echo $i; ?>">
```
to:
```php
         <img class="sp-thumbnail" src="img/Tours/Stgo/<?php echo $i; ?>_medium.webp" alt="Stgo thumbnail <?php echo $i; ?>" loading="lazy">
```

- [ ] **Step 4: Edit `portillo-inca-lagoon-andes-mountains-vineyard.php`**

Line 108, change:
```php
        <img class="sp-thumbnail" src="img/Tours/Andes/portada.webp" alt="Andes thumbnail cover">
```
to:
```php
        <img class="sp-thumbnail" src="img/Tours/Andes/portada.webp" alt="Andes thumbnail cover" loading="lazy">
```

Line 110, change:
```php
         <img class="sp-thumbnail" src="img/Tours/Andes/<?php echo $i; ?>_medium.webp" alt="Andes thumbnail <?php echo $i; ?>">
```
to:
```php
         <img class="sp-thumbnail" src="img/Tours/Andes/<?php echo $i; ?>_medium.webp" alt="Andes thumbnail <?php echo $i; ?>" loading="lazy">
```

- [ ] **Step 5: Edit `cruise-transfer.php`**

Line 115, change:
```php
            <img class="sp-thumbnail" src="img/Tours/Cruise/cover.webp" alt="Cover thumbnail">
```
to:
```php
            <img class="sp-thumbnail" src="img/Tours/Cruise/cover.webp" alt="Cover thumbnail" loading="lazy">
```

Line 117, change:
```php
              <img class="sp-thumbnail" src="img/Tours/Cruise/<?= $i ?>_medium.webp" alt="Thumbnail <?= $i ?>">
```
to:
```php
              <img class="sp-thumbnail" src="img/Tours/Cruise/<?= $i ?>_medium.webp" alt="Thumbnail <?= $i ?>" loading="lazy">
```

- [ ] **Step 6: Lint all five files**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l maipo-valley-wine-tour-santiago.php
php -l valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php
php -l discover-santiago-city-tour.php
php -l portillo-inca-lagoon-andes-mountains-vineyard.php
php -l cruise-transfer.php
```

Expected: `No syntax errors detected in ...` for all five.

- [ ] **Step 7: Verify the attribute landed everywhere intended**

```bash
grep -c 'sp-thumbnail.*loading="lazy"' maipo-valley-wine-tour-santiago.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php discover-santiago-city-tour.php portillo-inca-lagoon-andes-mountains-vineyard.php cruise-transfer.php
```

Expected: `2` for every file (cover line + loop line).

- [ ] **Step 8: Commit**

```bash
git add maipo-valley-wine-tour-santiago.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php discover-santiago-city-tour.php portillo-inca-lagoon-andes-mountains-vineyard.php cruise-transfer.php
git commit -m "Add loading=lazy to per-tour gallery thumbnails

These sp-thumbnail images (8-45 per tour page) were loading eagerly
with a plain src, unlike the lightbox's own sp-image tags which
already defer via a blank.gif placeholder. Valparaiso alone renders
44 of these on page load."
```

---

### Task 5: Add `loading="lazy"` and `width`/`height` to 404.html suggestion images

`404.html`'s "tour you are looking for is below" section links to 4 plain `<img>` tags with no `loading`, `width`, or `height` attributes at all.

**Files:**
- Modify: `404.html:116,121,126,131`

**Interfaces:**
- None.

- [ ] **Step 1: Edit each of the four suggestion images**

Line 116, change:
```html
					<p><a href="/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php"><img src="/img/Tours/Valpo/portada.jpg" alt="Pic" class="img-fluid"></a></p>
```
to:
```html
					<p><a href="/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php"><img src="/img/Tours/Valpo/portada.jpg" alt="Pic" class="img-fluid" width="1000" height="667" loading="lazy"></a></p>
```

Line 121, change:
```html
					<p><a href="/maipo-valley-wine-tour-santiago.php"><img src="/img/Tours/Maipo/portada.jpg" alt="Pic" class="img-fluid"></a></p>
```
to:
```html
					<p><a href="/maipo-valley-wine-tour-santiago.php"><img src="/img/Tours/Maipo/portada.jpg" alt="Pic" class="img-fluid" width="720" height="480" loading="lazy"></a></p>
```

Line 126, change:
```html
					<p><a href="/portillo-inca-lagoon-andes-mountains-vineyard.php"><img src="/img/Tours/Andes/portada.jpg" alt="Pic" class="img-fluid"></a></p>
```
to:
```html
					<p><a href="/portillo-inca-lagoon-andes-mountains-vineyard.php"><img src="/img/Tours/Andes/portada.jpg" alt="Pic" class="img-fluid" width="1400" height="1000" loading="lazy"></a></p>
```

Line 131, change:
```html
					<p><a href="/discover-santiago-city-tour.php"><img src="/img/Tours/Stgo/portada.jpg" alt="Pic" class="img-fluid"></a></p>
```
to:
```html
					<p><a href="/discover-santiago-city-tour.php"><img src="/img/Tours/Stgo/portada.jpg" alt="Pic" class="img-fluid" width="1440" height="959" loading="lazy"></a></p>
```

Note: `width`/`height` here are each image's actual current pixel dimensions (Andes/Stgo unchanged by Task 3 since already under the resize cap; Maipo untouched by Task 3; Valpo's stray `.jpg` unchanged by Task 3 since 1000×667 is under the 1600px cap) — confirm with `identify` if unsure before committing:

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
identify -format "%f %wx%h\n" img/Tours/Valpo/portada.jpg img/Tours/Maipo/portada.jpg img/Tours/Andes/portada.jpg img/Tours/Stgo/portada.jpg
```

- [ ] **Step 2: Verify the edits landed**

```bash
grep -n 'loading="lazy"' 404.html
```

Expected: 4 matching lines (116, 121, 126, 131 — line numbers may shift slightly if the file was reformatted, that's fine).

- [ ] **Step 3: Commit**

```bash
git add 404.html
git commit -m "Add width/height and loading=lazy to 404 page suggestion images"
```

---

### Task 6: Add missing `width` attributes to logo `<img>` tags

Several logo `<img>` tags across the site have `height` but no `width` (or, in one case, neither), which now that the source files are 320×114 / 320×74 (Task 2) risks layout shift on first paint. Add explicit `width` matching the display aspect ratio.

**Files:**
- Modify: `login.php:108,111`, `admin.php:117,120`, `404.html:64,65`, `admin/preferentials.php:69`, `shopping.php:222-224`

**Interfaces:**
- None.

- [ ] **Step 1: Edit `login.php`**

Line 108, change:
```php
        <img alt="City tours" class="logo_normal" height="47" src="img/logolargo.png"/>
```
to:
```php
        <img alt="City tours" class="logo_normal" height="47" width="132" src="img/logolargo.png"/>
```

Line 111, change:
```php
        <img alt="City tours" class="logo_sticky" height="34" src="img/logo_sticky.png"/>
```
to:
```php
        <img alt="City tours" class="logo_sticky" height="34" width="147" src="img/logo_sticky.png"/>
```

(Lines 123 and 189 already have `width="160"` — leave those two unchanged.)

- [ ] **Step 2: Edit `admin.php`**

Line 117, change:
```php
        <img alt="City tours" class="logo_normal" height="47" src="img/logolargo.png"/>
```
to:
```php
        <img alt="City tours" class="logo_normal" height="47" width="132" src="img/logolargo.png"/>
```

Line 120, change:
```php
        <img alt="City tours" class="logo_sticky" height="34" src="img/logo_sticky.png"/>
```
to:
```php
        <img alt="City tours" class="logo_sticky" height="34" width="147" src="img/logo_sticky.png"/>
```

(Line 132 already has `width="160"` — leave unchanged.)

- [ ] **Step 3: Edit `404.html`**

Line 64, change:
```html
       <a href="/"><img alt="City tours" class="logo_normal" height="47" src="/img/logolargo.png"/></a>
```
to:
```html
       <a href="/"><img alt="City tours" class="logo_normal" height="47" width="132" src="/img/logolargo.png"/></a>
```

Line 65, change:
```html
       <a href="/"><img alt="City tours" class="logo_sticky" height="34" src="/img/logo_sticky.png"/></a>
```
to:
```html
       <a href="/"><img alt="City tours" class="logo_sticky" height="34" width="147" src="/img/logo_sticky.png"/></a>
```

(Line 74 already has `width="160"` — leave unchanged.)

- [ ] **Step 4: Edit `admin/preferentials.php`**

Line 69, change:
```php
      <img src="/img/logolargo.png" alt="Stamp’s Tour Logo"/>
```
to:
```php
      <img src="/img/logolargo.png" alt="Stamp’s Tour Logo" width="320" height="114"/>
```

(No existing `height` to preserve here, so use the image's actual full dimensions rather than a display-derived guess, since this page doesn't constrain the logo's rendered size via a known CSS rule.)

- [ ] **Step 5: Edit `shopping.php`**

Lines 222-224, change:
```php
  <img src="img/logolargo.png" alt="StampTour Logo"
       class="d-block d-md-none ms-2"
       style="height:28px;">
```
to:
```php
  <img src="img/logolargo.png" alt="StampTour Logo"
       class="d-block d-md-none ms-2"
       style="height:28px; width:79px;">
```

- [ ] **Step 6: Lint the three `.php` files**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l login.php
php -l admin.php
php -l admin/preferentials.php
php -l shopping.php
```

Expected: `No syntax errors detected in ...` for all four.

- [ ] **Step 7: Visual check**

```bash
open login.php admin.php 404.html
```
(Or load each in a local PHP server / browser if available — confirm the header logo still renders at its normal size and position, not stretched or shifted.)

- [ ] **Step 8: Commit**

```bash
git add login.php admin.php 404.html admin/preferentials.php shopping.php
git commit -m "Add missing width attributes to logo img tags

Prevents layout shift now that the underlying logo files are 320x114
/ 320x74 (down from up to 1799x417) after Task 2's resize."
```

---

### Task 7: Remove the dead `style-rtl.css` include

The site is English/LTR only. A repo-wide search (`grep -rn "dir=\"rtl\"\|dir='rtl'\|isRTL\|is_rtl"`) confirmed nothing anywhere toggles an RTL class or `dir="rtl"` attribute, so this stylesheet is pure dead weight loaded on every page via the shared header include.

**Files:**
- Modify: `includes/head.php:79`

**Interfaces:**
- None.

- [ ] **Step 1: Remove the line**

In `includes/head.php`, delete line 79:
```php
<link href="css/style-rtl.css" rel="stylesheet"/>
```

- [ ] **Step 2: Lint**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l includes/head.php
```

Expected: `No syntax errors detected in includes/head.php`.

- [ ] **Step 3: Verify no page still references it**

```bash
grep -rn "style-rtl" --include="*.php" --include="*.html" . | grep -v vendor
```

Expected: no output.

- [ ] **Step 4: Visual check**

Load the homepage and one tour page locally (or via a quick PHP built-in server: `php -S localhost:8000` from the STAMP directory, then visit `http://localhost:8000/`) and confirm layout/styling looks unchanged — `style-rtl.css` should have been a no-op for an LTR page, so there should be zero visible difference.

- [ ] **Step 5: Commit**

```bash
git add includes/head.php
git commit -m "Remove dead style-rtl.css include

Site is English/LTR only; confirmed no page/feature toggles an RTL
class or dir=rtl attribute anywhere in the codebase."
```

---

### Task 8: Final verification and deploy reminder

This site has no CI/CD — changes only go live once manually pulled to the HostGator cPanel server (as established during earlier debugging of the `.htaccess` redirect issue: git push → manual "Pull or Deploy" in cPanel's Git Version Control, with the deployed `.htaccess` living directly in `public_html`, not a subdirectory or `public_html`'s parent).

**Files:** None (verification only).

- [ ] **Step 1: Confirm total local repo image weight dropped as expected**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
du -ch img/logolargo.png img/logolargo.webp img/logo_sticky.png img/logo_sticky.webp \
       img/Tours/portada.jpg img/Tours/portada.webp \
       img/Tours/Andes/portada.jpg img/Tours/Andes/portada.webp \
       img/Tours/Stgo/portada.jpg img/Tours/Stgo/portada.webp \
       img/Tours/Valpo/portada.jpeg img/Tours/Valpo/portada.webp img/Tours/Valpo/portada.jpg \
       img/Tours/Cruise/portada.jpeg img/Tours/Cruise/portada.webp img/Tours/Cruise/cover.jpg img/Tours/Cruise/cover.webp \
  | tail -1
```

Expected: total well under 500KB (down from the ~3.3MB combined fallback + ~2MB combined webp measured during diagnosis).

- [ ] **Step 2: Run `git log --oneline` to confirm all 6 commits from Tasks 2-7 are present**

```bash
git log --oneline -7
```

Expected: 6 commits from this plan (Tasks 2,3,4,5,6,7) plus the two earlier spec commits below them.

- [ ] **Step 3: Tell the user this is ready to deploy**

This step has no shell command — it's a handoff. Once all commits above are made, tell the user (Miguel) the changes are committed locally and ready for his usual deploy flow: `git push`, then pull/deploy via HostGator cPanel's Git Version Control, same as the `.htaccess` fix earlier in this project. Remind him the Cloudflare Browser Cache TTL setting (spec's "Out of Scope" item) is still an open follow-up outside this repo.

- [ ] **Step 4: After Miguel confirms deploy, re-run the live size checks**

```bash
for f in "img/logolargo.webp" "img/logo_sticky.webp" "img/Tours/portada.webp" "img/Tours/Andes/portada.webp" "img/Tours/Stgo/portada.webp" "img/Tours/Valpo/portada.webp" "img/Tours/Cruise/portada.webp"; do
  size=$(curl -sI "https://stampstour.com/$f" | grep -i "^content-length" | tr -d '\r')
  echo "$f -> $size"
done
curl -sI "https://stampstour.com/" | grep -i "cache-control\|cf-cache-status"
```

Expected: each `content-length` now matches the new small local file sizes (confirms the deploy actually landed, same lesson learned from the earlier `.htaccess` incident where a local fix didn't reach the live server).
