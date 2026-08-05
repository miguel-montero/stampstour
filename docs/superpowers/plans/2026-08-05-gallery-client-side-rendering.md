# Gallery Client-Side Rendering + Thumbnail Sizing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cut the gallery page's first-visit weight by moving photo-grid rendering from server-side HTML (all photos' markup shipped on every visit) to client-side JS building only the revealed batch from a small embedded JSON payload, and by shrinking thumbnail generation (500px/quality 80 → 350px/quality 72) across every path that creates them, including a one-time backfill of the 166 already-live thumbnails.

**Architecture:** `gallery.php` keeps its existing manifest-reading/sorting/tag-computation logic, but stops looping to emit per-photo HTML — instead it JSON-encodes a lightweight array (`id`, `thumb`, `large`, `tags`, `dateLabel`) into a `<script type="application/json">` block, with an empty `.gallery-grid` container and a `<noscript>` fallback message. `js/gallery.js` is rewritten to parse that JSON and progressively `appendChild` real `.gallery-item` DOM nodes for one batch at a time (reusing the existing `IntersectionObserver`-on-a-sentinel reveal mechanism and tag-filter pills, unchanged in behavior). Thumbnail generation shrinks in the two places that create images (`gallery-pipeline/lib/image-processing.js` for the local pipeline, `admin/gallery-upload.php` for the production admin upload), plus a new one-time script regenerates the 166 already-published thumbnails from their preserved originals.

**Tech Stack:** PHP (gallery.php, admin/gallery-upload.php), vanilla JS (js/gallery.js), Node.js + sharp (gallery-pipeline lib), Node's built-in `node:test` runner, Puppeteer for browser verification (matching this project's established pattern).

## Global Constraints

- Thumbnail generation: width 500px → **350px**, WebP quality 80 → **72**. This applies to `gallery-pipeline/lib/image-processing.js`'s thumb output, `admin/gallery-upload.php`'s thumb output, and the one-time backfill of all 166 existing thumbnails. The `large` variant (1600px, quality 80) is **unchanged** everywhere — it's the lightbox full-size image, only fetched on click, not part of this optimization.
- JSON payload fields per photo, exact names: `id`, `thumb`, `large`, `tags`, `dateLabel` (a pre-formatted string like `"Aug 4, 2026"`, computed server-side with PHP's `date('M j, Y', ...)` — same format `gallery.php` already used for the visible caption). `title` and `sourceFile` are dropped from the client payload (unused by the client).
- `json_encode()` for the embedded JSON block must use `JSON_UNESCAPED_UNICODE` but **NOT** `JSON_UNESCAPED_SLASHES` — default slash-escaping (`/` → `\/`) is a deliberate defense against a literal `</script>` sequence ever breaking out of the `<script type="application/json">` block, since `thumb`/`large` values contain `/` characters.
- `js/gallery.js` must reproduce the exact same DOM structure `gallery.php` used to render server-side, so `css/gallery.css` needs no structural changes: `div.gallery-item[data-tags="tag1|tag2"]` → `a.gallery-item-link[data-lightbox="gallery"][data-title="Upload date: ..."]` → `img[loading="lazy"]`, plus a sibling `p.gallery-item-date` (only when `dateLabel` is non-empty) reading `Upload date: <dateLabel>`.
- Batch size stays **16**, `IntersectionObserver` `rootMargin` stays **`'200px'`** — both already tuned in the current implementation, not part of this change.
- Client-side batch reveal must **append** new items on scroll, never destroy and rebuild already-rendered ones (that would re-request already-loaded images and cause visible flicker). A full rebuild only happens when the active tag filter changes.
- `img.alt` text on client-built images: since `title` isn't sent to the client, use the static string `"Stamps Tour gallery photo"` for every image (acceptable per the design's decision to drop title from the payload).
- Regenerating a thumbnail for an already-published photo requires its original source file, which lives at `gallery-pipeline/incoming/_published/<sourceFile>` (the `sourceFile` field already present in each `gallery-data.json` entry).

---

### Task 1: Shrink thumbnail generation in the local Node pipeline

**Files:**
- Modify: `gallery-pipeline/lib/image-processing.js`
- Test: `gallery-pipeline/test/image-processing.test.js`

**Interfaces:**
- Consumes: nothing new.
- Produces: `generateVariants(sourcePath, outDir, slug)` unchanged in signature and return shape (`{thumbPath, largePath}`) — only the thumb's internal `resize`/`webp` parameters change. `publish.js`, `bulk-publish.js`, and the new backfill script (Task 3) all call this function and are unaffected by the signature.

- [ ] **Step 1: Update the test to assert the new thumbnail width**

In `gallery-pipeline/test/image-processing.test.js`, find:
```js
  assert.ok(thumbMeta.width <= 500);
```
Replace with:
```js
  assert.ok(thumbMeta.width <= 350);
```

- [ ] **Step 2: Run the test to verify it fails against the current implementation**

```bash
cd gallery-pipeline && node --test test/image-processing.test.js
```
Expected: FAIL — `thumbMeta.width` is currently 350 or less only if the source happens to be narrow; the 2000x1500 test fixture resizes to exactly 500px wide today, so `500 <= 350` is false and the assertion fails.

- [ ] **Step 3: Shrink the thumb generation parameters**

In `gallery-pipeline/lib/image-processing.js`, find:
```js
  await sharp(sourcePath)
    .resize({ width: 500, withoutEnlargement: true })
    .webp({ quality: 80 })
    .toFile(thumbPath);
```
Replace with:
```js
  await sharp(sourcePath)
    .resize({ width: 350, withoutEnlargement: true })
    .webp({ quality: 72 })
    .toFile(thumbPath);
```
Leave the `large` variant's `sharp(sourcePath).resize({ width: 1600, ... }).webp({ quality: 80 })` block immediately below completely unchanged.

- [ ] **Step 4: Run the test to verify it passes**

```bash
cd gallery-pipeline && node --test test/image-processing.test.js
```
Expected: PASS — 1 test, thumb width now ≤350, large variant unaffected.

- [ ] **Step 5: Run the full test suite to confirm nothing else broke**

```bash
cd gallery-pipeline && npm test
```
Expected: all 20 tests still pass (this task only touches `image-processing.js`/`.test.js`, no other module imports thumb-specific numbers).

- [ ] **Step 6: Commit**

```bash
git add gallery-pipeline/lib/image-processing.js gallery-pipeline/test/image-processing.test.js
git commit -m "Shrink pipeline thumbnail generation to 350px/quality 72"
```

---

### Task 2: Shrink thumbnail generation in the admin-panel upload path

**Files:**
- Modify: `admin/gallery-upload.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: no change to any function signature consumed elsewhere (this file has no other consumers) — but the thumb output's actual pixel width/quality changes, mirroring Task 1's change for the production upload path.

- [ ] **Step 1: Add a quality parameter to the resize helper**

In `admin/gallery-upload.php`, find:
```php
function stamp_gallery_generate_variant(string $sourcePath, string $outDir, string $baseName, int $maxWidth): ?array
{
```
Replace with:
```php
function stamp_gallery_generate_variant(string $sourcePath, string $outDir, string $baseName, int $maxWidth, int $webpQuality): ?array
{
```

- [ ] **Step 2: Use the new parameter instead of the hardcoded quality**

Find:
```php
    $ok = $useWebp ? imagewebp($image, $outPath, 80) : imagejpeg($image, $outPath, 82);
```
Replace with:
```php
    $ok = $useWebp ? imagewebp($image, $outPath, $webpQuality) : imagejpeg($image, $outPath, 82);
```
(The JPEG-fallback quality of 82 is a degraded-environment path when this GD build lacks WebP support at all — left untouched, out of scope for this thumbnail-size optimization.)

- [ ] **Step 3: Update both call sites with explicit width and quality**

Find:
```php
        $thumb = stamp_gallery_generate_variant($tmpPaths[$i], $GALLERY_IMG_DIR, "{$id}-thumb", 500);
        $large = stamp_gallery_generate_variant($tmpPaths[$i], $GALLERY_IMG_DIR, "{$id}-large", 1600);
```
Replace with:
```php
        $thumb = stamp_gallery_generate_variant($tmpPaths[$i], $GALLERY_IMG_DIR, "{$id}-thumb", 350, 72);
        $large = stamp_gallery_generate_variant($tmpPaths[$i], $GALLERY_IMG_DIR, "{$id}-large", 1600, 80);
```

- [ ] **Step 4: Lint**

```bash
php -l admin/gallery-upload.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 5: Functional verification against a real photo**

This file's functions can't run standalone (they're defined inside a page that also does session-auth and `$_FILES` handling), so extract them into a throwaway harness the same way this project has verified PHP image logic before:

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 - <<'PYEOF'
content = open('admin/gallery-upload.php').read()
start = content.index("function stamp_gallery_slugify")
end = content.index('$gdMissing = !function_exists')
functions = content[start:end]
harness = "<?php\n" + functions + """
$src = $argv[1];
$outDir = sys_get_temp_dir() . '/gallery-upload-thumb-test';
@mkdir($outDir, 0755, true);
$thumb = stamp_gallery_generate_variant($src, $outDir, 'test-thumb', 350, 72);
$large = stamp_gallery_generate_variant($src, $outDir, 'test-large', 1600, 80);
var_dump($thumb, $large);
"""
open('/tmp/gallery-upload-thumb-harness.php', 'w').write(harness)
PYEOF
SAMPLE=$(ls gallery-pipeline/incoming/_published/ | head -1)
php -l /tmp/gallery-upload-thumb-harness.php
php /tmp/gallery-upload-thumb-harness.php "gallery-pipeline/incoming/_published/$SAMPLE"
```
Expected: no PHP errors/warnings besides the pre-existing harmless `imagedestroy()` deprecation notice (PHP 8.5+ only, this dev machine's version — production runs PHP 8.3 per `.htaccess`, where it's not deprecated); both `var_dump` calls show `array(2) { ["path"]=> ... ["ext"]=> string(4) "webp" }` with no `null` results.

- [ ] **Step 6: Confirm the generated thumb is actually narrower than before**

```bash
file /var/folders/*/T/gallery-upload-thumb-test/test-thumb.webp 2>/dev/null || find /tmp /var -maxdepth 4 -name "test-thumb.webp" 2>/dev/null -exec file {} \;
```
Expected: `VP8 encoding, 350x<N>` (width capped at 350, not 500).

- [ ] **Step 7: Clean up test artifacts**

```bash
rm -f /tmp/gallery-upload-thumb-harness.php
find /tmp /var/folders -maxdepth 4 -type d -name "gallery-upload-thumb-test" -exec rm -rf {} + 2>/dev/null
git status --short
```
Expected: only `admin/gallery-upload.php` shows as modified — no stray test files.

- [ ] **Step 8: Commit**

```bash
git add admin/gallery-upload.php
git commit -m "Shrink admin-upload thumbnail generation to 350px/quality 72"
```

---

### Task 3: Backfill the 166 already-published thumbnails

**Files:**
- Create: `gallery-pipeline/regenerate-thumbnails.js`

**Interfaces:**
- Consumes: `lib/manifest.js`'s `readManifest(manifestPath)` (Task 3 of the original gallery-pipeline plan), `lib/image-processing.js`'s `generateVariants(sourcePath, outDir, slug)` (this plan's Task 1, already shrunk to 350px/quality 72 by the time this task runs).
- Produces: overwritten `-thumb.webp` (and re-written but unchanged-in-settings `-large.webp`) files in `STAMP/img/Gallery/` for all 166 entries in the git-tracked `gallery-data.json`. No manifest changes — filenames are identical (same `id`-based naming), this only replaces file contents.

- [ ] **Step 1: Write the backfill script**

Create `gallery-pipeline/regenerate-thumbnails.js`:
```js
// One-time backfill: regenerate thumbnails for already-published photos at
// the new smaller size/quality (docs/superpowers/specs/2026-08-05-gallery-
// client-side-rendering-design.md). Re-running is harmless (same output)
// but pointless once the live thumbnails are already at the new size - this
// is not meant to be part of the regular publish workflow.
const path = require('node:path');
const fs = require('node:fs');
const { readManifest } = require('./lib/manifest');
const { generateVariants } = require('./lib/image-processing');

const REPO_ROOT = path.join(__dirname, '..');
const PUBLISHED_DIR = path.join(__dirname, 'incoming', '_published');
const MANIFEST_PATH = path.join(__dirname, 'gallery-data.json');
const GALLERY_IMG_DIR = path.join(REPO_ROOT, 'img', 'Gallery');

async function regenerate() {
  const manifest = readManifest(MANIFEST_PATH);
  let regenerated = 0;
  let skipped = 0;

  for (const entry of manifest) {
    const sourcePath = path.join(PUBLISHED_DIR, entry.sourceFile);
    if (!fs.existsSync(sourcePath)) {
      console.warn(`Skipping ${entry.id}: source file ${entry.sourceFile} not found in incoming/_published/`);
      skipped++;
      continue;
    }
    await generateVariants(sourcePath, GALLERY_IMG_DIR, entry.id);
    regenerated++;
    console.log(`Regenerated: ${entry.id}`);
  }

  console.log(`Done: ${regenerated} regenerated, ${skipped} skipped.`);
}

regenerate().catch((err) => {
  console.error(err);
  process.exit(1);
});
```

- [ ] **Step 2: Syntax check**

```bash
node --check gallery-pipeline/regenerate-thumbnails.js
```
Expected: no output (valid syntax).

- [ ] **Step 3: Record baseline file sizes before regenerating**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
du -sh img/Gallery/ 2>/dev/null
ls img/Gallery/*-thumb.webp | wc -l
```
Expected: shows current total size and confirms 166 existing thumb files (one per manifest entry).

- [ ] **Step 4: Run the backfill for real**

```bash
cd gallery-pipeline && node regenerate-thumbnails.js
```
Expected: 166 lines of `Regenerated: <id>`, ending with `Done: 166 regenerated, 0 skipped.` (0 skipped confirms every manifest entry's original source file was found — if any are skipped, investigate before proceeding rather than assuming it's fine).

- [ ] **Step 5: Confirm the new files are smaller and still valid images**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
du -sh img/Gallery/
SAMPLE=$(ls img/Gallery/*-thumb.webp | head -1)
file "$SAMPLE"
```
Expected: total `img/Gallery/` size is meaningfully smaller than Step 3's baseline (166 thumbs at ~60KB → ~1MB less overall, exact savings depend on image content); the sampled file shows `VP8 encoding, <width>x<height>` with width ≤350.

- [ ] **Step 6: Visual spot-check**

Open 3-4 of the regenerated thumbnails from `img/Gallery/` in Preview (or any image viewer) and confirm they look correct — not corrupted, not visibly degraded at their actual display size. This is a manual judgment step; if quality looks noticeably worse than acceptable, that's a signal to reconsider the quality-72 setting before proceeding (not something an automated check can catch).

- [ ] **Step 7: Commit**

```bash
git add gallery-pipeline/regenerate-thumbnails.js img/Gallery/
git commit -m "Add thumbnail backfill script and regenerate all 166 existing thumbnails"
```

---

### Task 4: `gallery.php` — emit JSON instead of per-photo markup

**Files:**
- Modify: `gallery.php`
- Modify: `css/gallery.css`

**Interfaces:**
- Consumes: nothing new (same `$photos` array this file already builds from the two manifest files).
- Produces: a `<script type="application/json" id="gallery-photos-data">` element containing a JSON array of `{id, thumb, large, tags, dateLabel}` objects, and an empty `<div class="gallery-grid"></div>` — both consumed by Task 5's `js/gallery.js`.

- [ ] **Step 1: Replace the photo-loop markup with JSON emission**

In `gallery.php`, find:
```php
        <div class="gallery-grid">
          <?php foreach ($photos as $photo):
            $uploadDateFormatted = '';
            if (!empty($photo['dateAdded'])) {
                $ts = strtotime($photo['dateAdded']);
                if ($ts !== false) $uploadDateFormatted = date('M j, Y', $ts);
            }
            $lightboxCaption = $uploadDateFormatted !== '' ? 'Upload date: ' . $uploadDateFormatted : ($photo['title'] ?? '');
          ?>
            <div class="gallery-item" data-tags="<?= htmlspecialchars(implode('|', $photo['tags'] ?? []), ENT_QUOTES, 'UTF-8') ?>">
              <a href="/<?= htmlspecialchars($photo['large'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 data-lightbox="gallery"
                 data-title="<?= htmlspecialchars($lightboxCaption, ENT_QUOTES, 'UTF-8') ?>"
                 class="gallery-item-link">
                <img src="/<?= htmlspecialchars($photo['thumb'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($photo['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     loading="lazy">
              </a>
              <?php if ($uploadDateFormatted !== ''): ?>
                <p class="gallery-item-date">Upload date: <?= htmlspecialchars($uploadDateFormatted, ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
```
Replace with:
```php
        <?php
          $photosForJs = array_map(function ($photo) {
              $dateLabel = '';
              if (!empty($photo['dateAdded'])) {
                  $ts = strtotime($photo['dateAdded']);
                  if ($ts !== false) $dateLabel = date('M j, Y', $ts);
              }
              return [
                  'id' => $photo['id'] ?? '',
                  'thumb' => $photo['thumb'] ?? '',
                  'large' => $photo['large'] ?? '',
                  'tags' => $photo['tags'] ?? [],
                  'dateLabel' => $dateLabel,
              ];
          }, $photos);
        ?>
        <div class="gallery-grid"></div>
        <noscript>
          <p class="gallery-noscript">Enable JavaScript to view the gallery.</p>
        </noscript>
        <script type="application/json" id="gallery-photos-data"><?= json_encode($photosForJs, JSON_UNESCAPED_UNICODE) ?></script>
```
Note: no `JSON_UNESCAPED_SLASHES` flag — this is deliberate, see Global Constraints (prevents a literal `</script>` breaking the page).

- [ ] **Step 2: Lint**

```bash
php -l gallery.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Add noscript styling**

In `css/gallery.css`, find:
```css
.gallery-item.gallery-hidden {
  display: none;
}
```
Replace with:
```css
.gallery-item.gallery-hidden {
  display: none;
}

.gallery-noscript {
  padding: 2rem;
  text-align: center;
  color: #666;
}
```

- [ ] **Step 4: Verify structurally with a local server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/gallery-json-test.log 2>&1 &
sleep 1
curl -s http://localhost:8899/gallery.php | grep -c 'class="gallery-item"'
curl -s http://localhost:8899/gallery.php | grep -o '<div class="gallery-grid"></div>'
curl -s http://localhost:8899/gallery.php | grep -o 'id="gallery-photos-data"'
curl -s http://localhost:8899/gallery.php | python3 -c "
import sys, re, json
html = sys.stdin.read()
m = re.search(r'id=\"gallery-photos-data\">(.*?)</script>', html, re.DOTALL)
data = json.loads(m.group(1))
print('photo count in JSON:', len(data))
print('sample entry keys:', sorted(data[0].keys()))
print('sample entry:', data[0])
"
pkill -f "php -S localhost:8899"
```
Expected: the `gallery-item` count is `0` (no server-rendered items left), the empty grid div is found, the JSON script tag is found, and the JSON parses successfully with 166 entries, each with exactly the keys `dateLabel`, `id`, `large`, `tags`, `thumb` (alphabetical from `sorted()`) — confirming `title`/`sourceFile` were correctly dropped.

- [ ] **Step 5: Commit**

```bash
git add gallery.php css/gallery.css
git commit -m "gallery.php: emit photo data as JSON instead of per-photo markup"
```

---

### Task 5: `js/gallery.js` — build DOM progressively from the JSON payload

**Files:**
- Modify: `js/gallery.js`

**Interfaces:**
- Consumes: the `#gallery-photos-data` JSON script tag and empty `.gallery-grid` div produced by Task 4.
- Produces: the same visible/interactive behavior as before (tag filtering, scroll-triggered batch reveal, lightbox) — this is the last task where the two intentionally-decoupled pieces (Task 4's server output, this task's client consumer) get verified working together for the first time.

- [ ] **Step 1: Replace the entire file**

Replace the full contents of `js/gallery.js` with:
```js
document.addEventListener('DOMContentLoaded', function () {
  var BATCH_SIZE = 16;

  var dataScript = document.getElementById('gallery-photos-data');
  var grid = document.querySelector('.gallery-grid');
  if (!dataScript || !grid) return;

  var allPhotos = JSON.parse(dataScript.textContent || '[]');
  if (allPhotos.length === 0) return;

  var pills = document.querySelectorAll('.gallery-filter-pill');
  var activeTag = '';
  var revealedCount = 0;

  var sentinel = document.createElement('div');
  sentinel.setAttribute('aria-hidden', 'true');
  grid.insertAdjacentElement('afterend', sentinel);

  function matchesFilter(photo) {
    if (activeTag === '') return true;
    return (photo.tags || []).indexOf(activeTag) !== -1;
  }

  function currentMatching() {
    return allPhotos.filter(matchesFilter);
  }

  function buildItem(photo) {
    var item = document.createElement('div');
    item.className = 'gallery-item';
    item.setAttribute('data-tags', (photo.tags || []).join('|'));

    var link = document.createElement('a');
    link.href = '/' + photo.large;
    link.setAttribute('data-lightbox', 'gallery');
    link.className = 'gallery-item-link';
    if (photo.dateLabel) {
      link.setAttribute('data-title', 'Upload date: ' + photo.dateLabel);
    }

    var img = document.createElement('img');
    img.src = '/' + photo.thumb;
    img.loading = 'lazy';
    img.alt = 'Stamps Tour gallery photo';
    link.appendChild(img);
    item.appendChild(link);

    if (photo.dateLabel) {
      var caption = document.createElement('p');
      caption.className = 'gallery-item-date';
      caption.textContent = 'Upload date: ' + photo.dateLabel;
      item.appendChild(caption);
    }

    return item;
  }

  // Appends the next batch on top of what's already rendered - never
  // destroys existing items (that would re-request already-loaded images
  // and cause a visible flicker on every scroll-triggered reveal).
  function appendNextBatch() {
    var matching = currentMatching();
    var nextItems = matching.slice(revealedCount, revealedCount + BATCH_SIZE);
    nextItems.forEach(function (photo) {
      grid.appendChild(buildItem(photo));
    });
    revealedCount += nextItems.length;
    sentinel.style.display = matching.length > revealedCount ? 'block' : 'none';
  }

  function resetAndRenderFirstBatch() {
    grid.innerHTML = '';
    revealedCount = 0;
    appendNextBatch();
  }

  pills.forEach(function (pill) {
    pill.addEventListener('click', function () {
      pills.forEach(function (p) { p.classList.remove('active'); });
      pill.classList.add('active');
      activeTag = pill.getAttribute('data-tag');
      resetAndRenderFirstBatch();
      window.scrollTo({ top: grid.offsetTop - 100, behavior: 'smooth' });
    });
  });

  var observer = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting) {
      appendNextBatch();
    }
  }, { rootMargin: '200px' });
  observer.observe(sentinel);

  resetAndRenderFirstBatch();
});
```

- [ ] **Step 2: Syntax check**

```bash
node --check js/gallery.js
```
Expected: no output (valid syntax).

- [ ] **Step 3: Full browser verification with Puppeteer**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/gallery-js-test.log 2>&1 &
sleep 1
mkdir -p /tmp/gallery-jsrender-verify && cd /tmp/gallery-jsrender-verify
npm init -y >/dev/null 2>&1
npm install puppeteer >/dev/null 2>&1
cat > check.js <<'JSEOF'
const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  await page.goto('http://localhost:8899/gallery.php', { waitUntil: 'networkidle0' });

  await page.evaluate(() => {
    const btn = document.querySelector('#cookie-consent-accept');
    if (btn) btn.click();
  });
  await new Promise((r) => setTimeout(r, 300));

  const domCountInitial = await page.$$eval('.gallery-item', (els) => els.length);
  console.log('DOM .gallery-item count on initial load (expect 16, NOT 166):', domCountInitial);

  const dateVisible = await page.$eval('.gallery-item .gallery-item-date', (el) => el.textContent.trim());
  console.log('first item date caption:', dateVisible);

  // Track a src from the first rendered image, scroll, and confirm that
  // exact DOM node is still present afterward (append-only, not rebuilt).
  const firstImgSrcBefore = await page.$eval('.gallery-item img', (el) => el.src);
  for (let i = 0; i < 3; i++) {
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await new Promise((r) => setTimeout(r, 400));
  }
  const domCountAfterScroll = await page.$$eval('.gallery-item', (els) => els.length);
  const firstImgSrcAfter = await page.$eval('.gallery-item img', (el) => el.src);
  console.log('DOM .gallery-item count after scrolling:', domCountAfterScroll);
  console.log('first image node preserved across scroll (append-only, not rebuilt):', firstImgSrcBefore === firstImgSrcAfter);

  await page.click('.gallery-item .gallery-item-link');
  await new Promise((r) => setTimeout(r, 800));
  const lightboxVisible = await page.$eval('#lightbox', (el) => getComputedStyle(el).display !== 'none');
  const caption = await page.$eval('.lb-caption', (el) => el.textContent);
  console.log('lightbox visible:', lightboxVisible, '| caption:', caption);

  await browser.close();
})();
JSEOF
node check.js
pkill -f "php -S localhost:8899"
rm -rf /tmp/gallery-jsrender-verify
```
Expected: DOM count on initial load is `16` (not 166 — confirms client-side batching, not just CSS-hidden markup); date caption shows a real formatted date; DOM count after scrolling is greater than 16 (more batches appended); the first image node is preserved (`true`) across scrolling, confirming append-only behavior; lightbox opens with `caption` reading `Upload date: <date>`.

- [ ] **Step 4: Verify no-JS fallback**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/gallery-nojs-test.log 2>&1 &
sleep 1
mkdir -p /tmp/gallery-nojs-verify && cd /tmp/gallery-nojs-verify
npm init -y >/dev/null 2>&1
npm install puppeteer >/dev/null 2>&1
cat > check.js <<'JSEOF'
const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  await page.setJavaScriptEnabled(false);
  await page.goto('http://localhost:8899/gallery.php', { waitUntil: 'domcontentloaded' });
  const noscriptText = await page.$eval('.gallery-noscript', (el) => el.textContent.trim());
  console.log('noscript message shown:', noscriptText);
  await browser.close();
})();
JSEOF
node check.js
pkill -f "php -S localhost:8899"
rm -rf /tmp/gallery-nojs-verify
```
Expected: `noscript message shown: Enable JavaScript to view the gallery.`

- [ ] **Step 5: Commit**

```bash
git add js/gallery.js
git commit -m "gallery.js: build gallery DOM progressively from embedded JSON"
```

---

### Task 6: Full-suite regression check and page-weight comparison

**Files:**
- None modified — this task only verifies. If anything fails, fix in place in the relevant file from Tasks 1-5, then re-verify.

**Interfaces:**
- Consumes: everything from Tasks 1-5.
- Produces: verification evidence only.

- [ ] **Step 1: Run the full Node test suite**

```bash
cd gallery-pipeline && npm test
```
Expected: all 20 tests pass.

- [ ] **Step 2: Lint every touched PHP file**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l gallery.php
php -l admin/gallery-upload.php
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 3: Measure real page weight locally, before/after comparison**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/gallery-weight-test.log 2>&1 &
sleep 1
echo "HTML size:"
curl -s http://localhost:8899/gallery.php | wc -c
echo "Sum of first 16 thumb images (bytes):"
curl -s http://localhost:8899/gallery.php | python3 -c "
import sys, re, json
html = sys.stdin.read()
m = re.search(r'id=\"gallery-photos-data\">(.*?)</script>', html, re.DOTALL)
data = json.loads(m.group(1))
for p in data[:16]:
    print(p['thumb'])
" | while read f; do
  curl -s -o /dev/null -w "%{size_download}\n" "http://localhost:8899/$f"
done | awk '{sum+=$1} END {print sum, "bytes total"}'
pkill -f "php -S localhost:8899"
```
Expected: HTML size dramatically smaller than the pre-change baseline of 113278 bytes (should be well under half); first-16-thumbs total noticeably smaller than the pre-change baseline of ~1,053,850 bytes (1053.85 KB), reflecting both the smaller per-image size (Task 1-3) and — combined with the HTML shrink — a much lighter overall first visit.

- [ ] **Step 4: Confirm git status is clean**

```bash
git status --short
```
Expected: no output (everything from Tasks 1-5 already committed, no stray test artifacts left behind).

---

### Task 7: Deploy

**Files:**
- None modified — this task pushes already-committed changes.

**Interfaces:**
- Consumes: the commits from Tasks 1-6.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push origin main
```

- [ ] **Step 2: Remind the user to deploy and purge cache**

State clearly: pushing to `origin/main` does not deploy automatically — `git pull` on the production server is required, and per this exact site's confirmed behavior earlier this session, static assets (`js/gallery.js`, `css/gallery.css`, and the regenerated files under `img/Gallery/`) are cached at Cloudflare's edge independently of the dynamic `gallery.php` response — a Cloudflare cache purge (dashboard → Caching → Purge Cache, either the specific changed URLs or "Purge Everything") is needed after pulling for the changes to actually reach visitors.
