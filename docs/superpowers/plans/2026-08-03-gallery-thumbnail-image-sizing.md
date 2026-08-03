# Gallery Thumbnail Image Sizing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Point each tour page's photo-gallery thumbnail strip (`.sp-thumbnail` images) at a dedicated, correctly-sized thumbnail file instead of the same oversized file used for the full slide view, cutting gallery thumbnail weight by ~90% with no visible quality change.

**Architecture:** 127 new WebP files (already generated and visually verified — see Global Constraints) sit alongside the existing `_medium.webp`/cover files, one per gallery image across all 5 tours. Each tour page's `.sp-thumbnail` `<img>` tags get their `src` changed to reference the new `_thumb`/`portada_thumb`/`cover_thumb` files. The `.sp-slide`/`.sp-image` elements (full-size slide view, driven by `data-*` attributes) are completely untouched — they keep using the existing `_medium.webp` files, which are already reasonably matched to their real (viewport-scaling) display size.

**Tech Stack:** Plain HTML/PHP, no build step. Thumbnail files were generated with ImageMagick (`-resize x180 -quality 80`, preserving each image's native aspect ratio at a fixed 180px height) from the existing `_medium.webp`/cover files, and visually spot-checked (one sample per tour, converted to PNG and inspected) before this plan was written — that step is already done; this plan only wires up the markup.

## Global Constraints

- Exact new files, already generated, verified, and present in the working tree — 127 total:
  - `img/Tours/Andes/{1-39}_thumb.webp` + `img/Tours/Andes/portada_thumb.webp` (40 files)
  - `img/Tours/Maipo/{1-9}_thumb.webp` + `img/Tours/Maipo/portada_thumb.webp` (10 files)
  - `img/Tours/Stgo/{1-8}_thumb.webp` + `img/Tours/Stgo/portada_thumb.webp` (9 files)
  - `img/Tours/Valpo/{1-45}_thumb.webp` + `img/Tours/Valpo/portada_thumb.webp` (46 files)
  - `img/Tours/Cruise/{0-20}_thumb.webp` + `img/Tours/Cruise/cover_thumb.webp` (22 files) — note Cruise's numbered files are 0-indexed (`0_thumb.webp` through `20_thumb.webp`), unlike the other 4 tours which are 1-indexed; its cover file is named `cover_thumb.webp`, not `portada_thumb.webp`.
  - All generated via: `convert <source> -resize x180 -quality 80 <dest>` (height fixed at 180px, width follows each image's native aspect ratio — measured real display need is 117×78, so 180px height comfortably covers 2x-retina with margin).
  - Confirmed aggregate byte reduction (existing `_medium`/cover files vs. new `_thumb`/`portada_thumb`/`cover_thumb` files, same image sets): Andes 9,976 KB → 560 KB, Maipo 1,096 KB → 172 KB, Stgo 960 KB → 116 KB, Valpo 4,368 KB → 708 KB, Cruise 2,340 KB → 344 KB. Total: 18,740 KB → 1,900 KB (~90% reduction).
- Only the `.sp-thumbnail` `<img>` tags' `src` attribute changes, on each of the 5 tour pages' two thumbnail-related lines (the standalone "cover" thumbnail line, and the `<?php for (...) ?>` loop's thumbnail line). Nothing else on those pages changes — not the `.sp-slide`/`.sp-image` elements, not the `data-*` attributes, not the loop bounds, not any other markup.
- Do not touch the existing `_medium.webp`/`_medium.jpg`/`portada.webp`/`cover.webp` files — they remain exactly as they are, still used by the slide view and any other existing references (e.g. lightbox `href` targets, `data-src`).
- Andes' pre-existing thumbnail-loop/slide-loop count mismatch (39 thumbnails, only 38 slides — confirmed real, unrelated bug) is explicitly out of scope. This plan does not change either loop's bounds on any tour page.

---

### Task 1: Wire up the new thumbnail files on all 5 tour pages

**Files:**
- Modify: `portillo-inca-lagoon-andes-mountains-vineyard.php:110,112`
- Modify: `maipo-valley-wine-tour-santiago.php:109,111`
- Modify: `discover-santiago-city-tour.php:109,111`
- Modify: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php:115,117`
- Modify: `cruise-transfer.php:119,121`

**Interfaces:**
- Consumes: the 127 already-generated thumbnail files listed in Global Constraints.
- Produces: nothing new for later tasks — Task 2 verifies this exact change.

- [ ] **Step 1: Andes — cover thumbnail (line 110)**

Find:
```html
        <img class="sp-thumbnail" src="img/Tours/Andes/portada.webp" alt="Andes thumbnail cover" loading="lazy">
```

Replace with:
```html
        <img class="sp-thumbnail" src="img/Tours/Andes/portada_thumb.webp" alt="Andes thumbnail cover" loading="lazy">
```

- [ ] **Step 2: Andes — numbered thumbnails loop (line 112)**

Find:
```html
         <img class="sp-thumbnail" src="img/Tours/Andes/<?php echo $i; ?>_medium.webp" alt="Andes thumbnail <?php echo $i; ?>" loading="lazy">
```

Replace with:
```html
         <img class="sp-thumbnail" src="img/Tours/Andes/<?php echo $i; ?>_thumb.webp" alt="Andes thumbnail <?php echo $i; ?>" loading="lazy">
```

- [ ] **Step 3: Maipo — cover thumbnail (line 109)**

Find:
```html
        <img class="sp-thumbnail" src="img/Tours/Maipo/portada.webp" alt="Maipo thumbnail cover" loading="lazy">
```

Replace with:
```html
        <img class="sp-thumbnail" src="img/Tours/Maipo/portada_thumb.webp" alt="Maipo thumbnail cover" loading="lazy">
```

- [ ] **Step 4: Maipo — numbered thumbnails loop (line 111)**

Find:
```html
         <img class="sp-thumbnail" src="img/Tours/Maipo/<?php echo $i; ?>_medium.webp" alt="Maipo thumbnail <?php echo $i; ?>" loading="lazy">
```

Replace with:
```html
         <img class="sp-thumbnail" src="img/Tours/Maipo/<?php echo $i; ?>_thumb.webp" alt="Maipo thumbnail <?php echo $i; ?>" loading="lazy">
```

- [ ] **Step 5: Santiago — cover thumbnail (line 109)**

Find:
```html
        <img class="sp-thumbnail" src="img/Tours/Stgo/portada.webp" alt="Stgo thumbnail cover" loading="lazy">
```

Replace with:
```html
        <img class="sp-thumbnail" src="img/Tours/Stgo/portada_thumb.webp" alt="Stgo thumbnail cover" loading="lazy">
```

- [ ] **Step 6: Santiago — numbered thumbnails loop (line 111)**

Find:
```html
         <img class="sp-thumbnail" src="img/Tours/Stgo/<?php echo $i; ?>_medium.webp" alt="Stgo thumbnail <?php echo $i; ?>" loading="lazy">
```

Replace with:
```html
         <img class="sp-thumbnail" src="img/Tours/Stgo/<?php echo $i; ?>_thumb.webp" alt="Stgo thumbnail <?php echo $i; ?>" loading="lazy">
```

- [ ] **Step 7: Valparaíso — cover thumbnail (line 115)**

Find:
```html
        <img class="sp-thumbnail" src="img/Tours/Valpo/portada.webp" alt="Valparaiso thumbnail cover" loading="lazy">
```

Replace with:
```html
        <img class="sp-thumbnail" src="img/Tours/Valpo/portada_thumb.webp" alt="Valparaiso thumbnail cover" loading="lazy">
```

- [ ] **Step 8: Valparaíso — numbered thumbnails loop (line 117)**

Find:
```html
         <img class="sp-thumbnail" src="img/Tours/Valpo/<?php echo $i; ?>_medium.webp" alt="Valparaiso thumbnail <?php echo $i; ?>" loading="lazy">
```

Replace with:
```html
         <img class="sp-thumbnail" src="img/Tours/Valpo/<?php echo $i; ?>_thumb.webp" alt="Valparaiso thumbnail <?php echo $i; ?>" loading="lazy">
```

- [ ] **Step 9: Cruise — cover thumbnail (line 119)**

Find:
```html
            <img class="sp-thumbnail" src="img/Tours/Cruise/cover.webp" alt="Cover thumbnail" loading="lazy">
```

Replace with:
```html
            <img class="sp-thumbnail" src="img/Tours/Cruise/cover_thumb.webp" alt="Cover thumbnail" loading="lazy">
```

- [ ] **Step 10: Cruise — numbered thumbnails loop (line 121)**

Find:
```html
              <img class="sp-thumbnail" src="img/Tours/Cruise/<?= $i ?>_medium.webp" alt="Thumbnail <?= $i ?>" loading="lazy">
```

Replace with:
```html
              <img class="sp-thumbnail" src="img/Tours/Cruise/<?= $i ?>_thumb.webp" alt="Thumbnail <?= $i ?>" loading="lazy">
```

- [ ] **Step 11: Lint and verify no `_medium.webp`/`portada.webp`/`cover.webp` references remain on any `.sp-thumbnail` line**

```bash
php -l portillo-inca-lagoon-andes-mountains-vineyard.php
php -l maipo-valley-wine-tour-santiago.php
php -l discover-santiago-city-tour.php
php -l valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php
php -l cruise-transfer.php
grep "sp-thumbnail" portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
```

Expected: all `php -l` calls report `No syntax errors detected`. Every printed `sp-thumbnail` line should reference a `_thumb.webp` file — none should still say `_medium.webp`, or reference `portada.webp`/`cover.webp` directly (those should now say `portada_thumb.webp`/`cover_thumb.webp`).

- [ ] **Step 12: Confirm no other lines on these 5 pages changed**

```bash
git diff --stat
```

Expected: exactly 5 files listed, each with exactly 2 lines changed (2 insertions, 2 deletions per file — the cover thumbnail line and the loop's thumbnail line).

- [ ] **Step 13: Commit**

```bash
git add portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php img/Tours/Andes/*_thumb.webp img/Tours/Maipo/*_thumb.webp img/Tours/Stgo/*_thumb.webp img/Tours/Valpo/*_thumb.webp img/Tours/Cruise/*_thumb.webp
git commit -m "Add right-sized gallery thumbnail images across all 5 tour pages"
```

---

### Task 2: Verify thumbnails render correctly and the slide view is unaffected

**Files:**
- None modified — this task only verifies. If a check fails, fix the markup from Task 1 in place, then re-verify.

**Interfaces:**
- Consumes: the working markup + files from Task 1.
- Produces: verification evidence only.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/maipo-valley-wine-tour-santiago.php
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/cruise-transfer.php
```

Expected: `200` for both.

- [ ] **Step 2: Confirm thumbnails render at their expected fixed size, and the correct file is actually selected**

Using Puppeteer, load each of the 5 tour pages and, for each, read the `currentSrc`/`src` of the first `.sp-thumbnail` element and its `getBoundingClientRect()`, at 3 viewport widths (375, 768, 1470 — matching the widths used to establish the "fixed regardless of viewport" finding in the design spec).

Expected, per tour page: `src` ends in `_thumb.webp` (or `portada_thumb.webp`/`cover_thumb.webp` for the cover), at all 3 widths; the rendered size stays the same fixed value across all 3 widths on a given page (confirming the earlier finding still holds — thumbnails don't scale with viewport).

- [ ] **Step 3: Confirm the slide (full-size) view is unaffected**

On the same page loads, read the first `.sp-slide .sp-image`'s `data-src` attribute (or, after the slider plugin initializes and swaps it in, its actual `src`/`currentSrc`). Confirm it still references a `_medium.webp` (or `portada.webp`/`cover.webp` for the cover slide) file, unchanged from before this plan.

- [ ] **Step 4: Visual regression check**

Screenshot the thumbnail strip on at least 2 tour pages (e.g. Maipo, Andes) at 375px and 1470px widths. Confirm the thumbnails look the same as before — same images, correctly cropped/sized, no visible quality loss, no layout shift in the thumbnail strip's overall height or position.

- [ ] **Step 5: Confirm aggregate byte savings match expectations**

```bash
for tour in Andes Maipo Stgo Valpo Cruise; do
  old=$(du -ck img/Tours/$tour/*_medium.webp 2>/dev/null | tail -1 | cut -f1)
  new=$(du -ck img/Tours/$tour/*_thumb.webp 2>/dev/null | tail -1 | cut -f1)
  echo "$tour: old=${old}KB new=${new}KB"
done
```

Expected: numbers matching (or very close to) the figures recorded in Global Constraints (Andes 9976→560, Maipo 1096→172, Stgo 960→116, Valpo 4368→708, Cruise 2340→344).

- [ ] **Step 6: Lint and tag-balance check**

```bash
for f in portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php; do
  php -l "$f"
  echo "$f: <div>=$(grep -c '<div' "$f") </div>=$(grep -c '</div>' "$f")"
done
```

Expected: no syntax errors; `<div>`/`</div>` counts match on each file (unchanged from before this plan).

- [ ] **Step 7: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 8: If any check failed, fix and re-verify**

Repeat Steps 1-7 after any fix. Do not proceed to Task 3 until every check in Steps 2-6 passes.

- [ ] **Step 9: Commit (only if Step 8 required a fix)**

```bash
git add portillo-inca-lagoon-andes-mountains-vineyard.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php
git commit -m "Fix issue found during gallery thumbnail verification"
```

If no fix was needed, skip this step.

---

### Task 3: Deploy and confirm production

**Files:**
- None modified — this task pushes already-committed changes and confirms the live site.

**Interfaces:**
- Consumes: the commits from Tasks 1-2.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push
```

- [ ] **Step 2: Remind the user to deploy**

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to pull on the cPanel server (via Git Version Control's Pull or Deploy), and per the caching issue discovered earlier this session, likely also needs to purge the Cloudflare cache for the 127 new image files and the 5 updated tour pages to actually be served.

- [ ] **Step 3: Once deployed and cache-purged, spot-check the live site**

Load at least 2 tour pages in a real browser, open the photo gallery, and confirm thumbnails display correctly with no visible quality loss. This is a direct, unconditional byte-weight win independent of any LCP/CLS hypothesis — no PSI recheck is required to confirm success, though one can be run as an optional sanity check on the "image delivery" diagnostic.
