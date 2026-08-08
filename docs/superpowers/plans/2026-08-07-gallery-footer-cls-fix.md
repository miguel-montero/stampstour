# Gallery Footer CLS Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the 0.42 CLS score on `gallery.php` (footer shifting ~678px) caused by the photo grid jumping from 0 height to full height when JS appends the first batch on `DOMContentLoaded`.

**Architecture:** Server-render the first 16 photo tiles directly in `gallery.php`'s initial HTML (same markup shape `buildItem()` currently builds client-side in `js/gallery.js`), so the grid has its real height from first paint. `js/gallery.js` detects the pre-rendered tiles on load and treats them as already-revealed instead of rebuilding them, then continues appending batches 17+ via the existing `IntersectionObserver`.

**Tech Stack:** PHP (`gallery.php`), vanilla JS (`js/gallery.js`), Puppeteer for verification (matching this project's established pattern), Lighthouse CLI for the final CLS measurement.

## Global Constraints

- Batch size stays hardcoded at 16 in both PHP and JS — no shared config layer between them, consistent with how the codebase already handles this constant.
- Server-rendered tiles must use `loading="lazy"` on their `<img>`, matching current JS behavior exactly (JS sets `img.loading = 'lazy'` unconditionally for every tile) — do not make them eager, that could regress LCP.
- The `<noscript><p class="gallery-noscript">Enable JavaScript to view the gallery.</p></noscript>` copy stays exactly as-is — explicitly out of scope, do not reword it.
- All dynamic PHP output must pass through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`, matching the escaping already used elsewhere in `gallery.php`.
- `resetAndRenderFirstBatch()` (used by the filter-pill click handler) is unchanged — this fix only touches the initial, unfiltered page-load path.
- `appendNextBatch()`'s scroll-triggered behavior for items 17+ is unchanged.

---

### Task 1: Server-render the first batch and update JS to detect it

**Files:**
- Modify: `gallery.php:85` (currently `<div class="gallery-grid"></div>`)
- Modify: `js/gallery.js:87-95` (the end of the `DOMContentLoaded` handler, currently ending in a bare `resetAndRenderFirstBatch();` call)

**Interfaces:**
- Consumes: `$photosForJs` (already built at `gallery.php:70-83`, an array of `['id', 'thumb', 'large', 'tags', 'dateLabel']` per photo) — unchanged, still passed in full (unsliced) into the JSON payload for JS.
- Consumes: `js/gallery.js`'s existing `grid`, `sentinel`, `revealedCount`, `currentMatching()`, `appendNextBatch()` — all already defined earlier in the same closure, unchanged by this task.
- Produces: nothing consumed by a later task in this plan (Task 2 only deploys).

- [ ] **Step 1: Replace the empty grid div in `gallery.php` with server-rendered tiles**

Find (`gallery.php:85`):
```php
        <div class="gallery-grid"></div>
```

Replace with:
```php
        <div class="gallery-grid">
          <?php foreach (array_slice($photosForJs, 0, 16) as $photo): ?>
            <div class="gallery-item" data-tags="<?= htmlspecialchars(implode('|', $photo['tags']), ENT_QUOTES, 'UTF-8') ?>">
              <a href="/<?= htmlspecialchars($photo['large'], ENT_QUOTES, 'UTF-8') ?>" data-lightbox="gallery" class="gallery-item-link"<?php if ($photo['dateLabel'] !== ''): ?> data-title="Upload date: <?= htmlspecialchars($photo['dateLabel'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
                <img src="/<?= htmlspecialchars($photo['thumb'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" alt="Stamps Tour gallery photo">
              </a>
              <?php if ($photo['dateLabel'] !== ''): ?>
                <p class="gallery-item-date">Upload date: <?= htmlspecialchars($photo['dateLabel'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
```

This must produce exactly the same DOM shape `buildItem()` in `js/gallery.js` builds client-side: `div.gallery-item[data-tags]` > `a.gallery-item-link[href, data-lightbox, data-title?]` > `img[src, loading=lazy, alt]`, plus a sibling `p.gallery-item-date` when `dateLabel` is non-empty.

- [ ] **Step 2: Lint the PHP**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l gallery.php
```
Expected: `No syntax errors detected in gallery.php`

- [ ] **Step 3: Verify the server-rendered HTML directly, before touching JS**

```bash
php -S localhost:8899 > /tmp/cls-fix-server.log 2>&1 &
sleep 1
curl -s http://localhost:8899/gallery.php | grep -o 'class="gallery-item"' | wc -l
curl -s http://localhost:8899/gallery.php | grep -c 'data-lightbox="gallery"'
curl -s http://localhost:8899/gallery.php | grep -c 'gallery-item-date'
```
Expected: the first count is `16` (exactly 16 server-rendered tiles — if the live `gallery-data.json` has fewer than 16 photos total, this will be lower; check `wc -l gallery-pipeline/gallery-data.json`-equivalent photo count first if the first number looks wrong). The second count is `16` too (each tile has `data-lightbox="gallery"`). The third count is `>0` (at least some tiles have upload dates).

Leave the server running — Step 6 reuses it (Steps 4-5 just edit and lint the JS file, no server needed).

- [ ] **Step 4: Update `js/gallery.js` to detect pre-rendered tiles instead of always rebuilding**

Find (`js/gallery.js:87-95`):
```js
  var observer = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting) {
      appendNextBatch();
    }
  }, { rootMargin: '200px' });
  observer.observe(sentinel);

  resetAndRenderFirstBatch();
});
```

Replace with:
```js
  var observer = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting) {
      appendNextBatch();
    }
  }, { rootMargin: '200px' });
  observer.observe(sentinel);

  // The first batch is server-rendered in gallery.php (real HTML at
  // first paint, avoiding the CLS jump from building it here). Detect
  // it and just track how many are already on the page, rather than
  // wiping and rebuilding them.
  var preRendered = grid.querySelectorAll('.gallery-item').length;
  if (preRendered > 0) {
    revealedCount = preRendered;
    sentinel.style.display = currentMatching().length > revealedCount ? 'block' : 'none';
  } else {
    appendNextBatch();
  }
});
```

- [ ] **Step 5: Verify JS syntax**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
node --check js/gallery.js
```
Expected: no output (exit code 0 means valid syntax).

- [ ] **Step 6: Puppeteer verification — no duplication, correct revealed count, scroll still works**

```bash
mkdir -p /tmp/cls-fix-verify && cd /tmp/cls-fix-verify
npm init -y >/dev/null 2>&1
npm install puppeteer >/dev/null 2>&1
cat > check.js <<'JSEOF'
const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();

  // 1. With JS enabled: confirm no duplication (still exactly 16 on load).
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844 });
  await page.goto('http://localhost:8899/gallery.php', { waitUntil: 'domcontentloaded' });
  await new Promise((r) => setTimeout(r, 200));
  const initialCount = await page.evaluate(() => document.querySelectorAll('.gallery-item').length);
  console.log('Initial .gallery-item count (expect 16, not 32):', initialCount);

  // 2. Scroll to bottom repeatedly to trigger IntersectionObserver batches.
  for (let i = 0; i < 5; i++) {
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await new Promise((r) => setTimeout(r, 300));
  }
  const afterScrollCount = await page.evaluate(() => document.querySelectorAll('.gallery-item').length);
  console.log('After scrolling, .gallery-item count (expect > 16):', afterScrollCount);

  // 3. Filter pill still works (click a non-"All" pill if one exists).
  const pillCount = await page.evaluate(() => document.querySelectorAll('.gallery-filter-pill').length);
  if (pillCount > 1) {
    await page.click('.gallery-filter-pill:not([data-tag=""])');
    await new Promise((r) => setTimeout(r, 300));
    const filteredCount = await page.evaluate(() => document.querySelectorAll('.gallery-item').length);
    console.log('After clicking a filter pill, .gallery-item count:', filteredCount, '(should be <= total matching that tag, grid should have rebuilt)');
  } else {
    console.log('Only one filter pill (All) present - skipping filter-click check.');
  }

  await page.close();

  // 4. No-JS: confirm 16 real photos are visible without JS running.
  const noJsPage = await browser.newPage();
  await noJsPage.setJavaScriptEnabled(false);
  await noJsPage.goto('http://localhost:8899/gallery.php', { waitUntil: 'domcontentloaded' });
  const noJsCount = await noJsPage.evaluate(() => document.querySelectorAll('.gallery-item').length);
  const noJsMsgPresent = await noJsPage.evaluate(() => !!document.querySelector('.gallery-noscript'));
  console.log('No-JS .gallery-item count (expect 16):', noJsCount);
  console.log('No-JS noscript message still present (expect true):', noJsMsgPresent);
  await noJsPage.close();

  await browser.close();
})();
JSEOF
node check.js
```
Expected:
- `Initial .gallery-item count (expect 16, not 32): 16` — confirms Step 4's detection logic prevented duplication.
- `After scrolling, .gallery-item count (expect > 16): <N>` where N > 16 — confirms `appendNextBatch()` for items 17+ still works.
- If a non-"All" filter pill exists: a filtered count is printed (confirms `resetAndRenderFirstBatch()` still runs correctly on filter-click and didn't break).
- `No-JS .gallery-item count (expect 16): 16` — confirms the 16 photos are genuinely visible without JS now (this is the point of the fix).
- `No-JS noscript message still present (expect true): true` — confirms the unchanged `<noscript>` copy is still there.

- [ ] **Step 7: Clean up verification artifacts**

```bash
pkill -f "php -S localhost:8899"
rm -rf /tmp/cls-fix-verify /tmp/cls-fix-server.log
```

- [ ] **Step 8: Fresh Lighthouse mobile audit to confirm the CLS fix**

```bash
mkdir -p /tmp/cls-fix-lighthouse && cd /tmp/cls-fix-lighthouse
npm init -y >/dev/null 2>&1
npm install lighthouse >/dev/null 2>&1
php -S localhost:8899 --docroot /Users/miguelmontero/Documents/superpowers/STAMP > /tmp/cls-fix-lh-server.log 2>&1 &
sleep 1
node_modules/.bin/lighthouse http://localhost:8899/gallery.php --preset=perf --form-factor=mobile --screenEmulation.mobile --throttling-method=simulate --output=json --output-path=./gallery-cls-check.json --chrome-flags="--headless" --quiet
python3 -c "
import json
with open('gallery-cls-check.json') as f:
    data = json.load(f)
cls = data['audits']['cumulative-layout-shift']
print('CLS score:', cls.get('score'), 'value:', cls.get('displayValue'))
culprits = data['audits'].get('cls-culprits-insight', {})
print('CLS culprits score:', culprits.get('score'))
"
pkill -f "php -S localhost:8899"
cd /Users/miguelmontero/Documents/superpowers/STAMP
rm -rf /tmp/cls-fix-lighthouse /tmp/cls-fix-lh-server.log
```
Expected: CLS `score` close to `1` (Lighthouse's CLS audit scores near 1.0 for values below ~0.1) and `displayValue` well below the pre-fix `0.422` — comparable to the `0.019` already measured on `refunds-cancellations.php`. Note: this is a local, unthrottled-network audit against `localhost`, so absolute numbers may differ slightly from the production audit in Task 2 (which hits the real network) — the key signal here is CLS collapsing, not an exact match to a specific decimal.

- [ ] **Step 9: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add gallery.php js/gallery.js
git commit -m "Server-render first gallery batch to eliminate footer CLS shift"
```

---

### Task 2: Deploy

**Files:**
- None modified — this task pushes already-committed changes.

**Interfaces:**
- Consumes: the commit from Task 1.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git push origin main
```
Note: if this session is running from an isolated worktree (branch name other than `main`), push that branch to `origin/main` explicitly, e.g. `git push origin <current-branch-name>:main` — confirm the current branch name with `git branch --show-current` first. Before pushing, `git fetch origin` and merge any commits that landed on `origin/main` from concurrent work (`git merge origin/main --no-edit`) — never force-push, never rebase.

- [ ] **Step 2: Remind the user to deploy, purge cache, and re-audit**

State clearly: `git pull` on production is required for `gallery.php` and `js/gallery.js` to take effect, plus a Cloudflare cache purge for `js/gallery.js` specifically (`gallery.php` itself is dynamic/uncached, but the JS file is a static asset that may be cached). Once deployed and purged, re-run the Lighthouse mobile audit against the live `gallery.php` and confirm CLS has dropped from the `0.42` baseline measured immediately before this plan, comparable to the `0.019` already seen on `refunds-cancellations.php`.
