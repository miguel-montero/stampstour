# Responsive Tour Card Images Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a smaller "mobile" WebP variant for each of the 5 homepage tour card images, and wire it up via `srcset`/`sizes` so narrow phones download a properly-sized file instead of racing the hero image for bandwidth with an oversized one.

**Architecture:** Five new files (`img/Tours/<Tour>/portada-mobile.webp`, 600px wide, already generated and visually verified against the source images) sit alongside the existing full-size files. Each card's existing `<picture><source srcset="..." type="image/webp">` gets a `srcset` with both candidates plus a `sizes` attribute reflecting the real measured layout. The browser's own image-selection algorithm (accounting for viewport width and device pixel ratio) picks the right file — no JS involved.

**Tech Stack:** Plain HTML `<picture>`/`srcset`/`sizes`, no build step. Mobile-variant files were generated with ImageMagick (`-resize 600x600> -quality 80`) from the existing production WebP files and confirmed visually lossless-at-display-size before this plan was written — that step is already done, this plan only wires up the markup.

## Global Constraints

- Exact new files, already generated, verified, and present in the working tree:
  - `img/Tours/Valpo/portada-mobile.webp` (85,566 bytes, 600×408)
  - `img/Tours/Maipo/portada-mobile.webp` (62,444 bytes, 600×400)
  - `img/Tours/Andes/portada-mobile.webp` (52,246 bytes, 600×429)
  - `img/Tours/Stgo/portada-mobile.webp` (53,102 bytes, 600×400)
  - `img/Tours/Cruise/portada-mobile.webp` (47,538 bytes, 600×400)
- `sizes` value for all 5 cards: `(max-width: 767px) 100vw, 50vw` — matches the real measured layout (single column below Bootstrap's 768px `md` breakpoint, two columns from 768px up).
- `srcset` width descriptor for the mobile variant is always `600w` (its real width). The width descriptor for the existing full-size file is its own real native width, which differs per tour — use the exact values below, not a placeholder:
  - Valpo: `955w` (native `img/Tours/Valpo/portada.webp` is 955×650)
  - Maipo: `720w` (native `img/Tours/Maipo/portada.webp` is 720×480)
  - Andes: `1400w` (native `img/Tours/Andes/portada.webp` is 1400×1000)
  - Stgo: `1440w` (native `img/Tours/Stgo/portada.webp` is 1440×959)
  - Cruise: `900w` (native `img/Tours/Cruise/portada.webp` is 900×600)
- Only the `<source>` tag's `srcset` attribute changes on each card. The fallback `<img>` (`src`, `width="800"`, `height="533"`, `class="img-fluid"`, `alt`, `loading="lazy"`) is untouched on all 5 cards.
- Only `index.php` is modified — these five images are homepage-grid-specific; no other page references them the same way (this was confirmed as part of the design spec's scope).

---

### Task 1: Wire up srcset/sizes on all 5 homepage cards

**Files:**
- Modify: `index.php:92` (Valparaíso)
- Modify: `index.php:122` (Maipo)
- Modify: `index.php:152` (Andes)
- Modify: `index.php:176` (Santiago)
- Modify: `index.php:196` (Cruise)

**Interfaces:**
- Consumes: the 5 already-generated `portada-mobile.webp` files listed in Global Constraints.
- Produces: nothing new for later tasks — Task 2 verifies this exact change.

- [ ] **Step 1: Valparaíso card**

Find (line 92):
```html
                                    <source srcset="img/Tours/Valpo/portada.webp" type="image/webp">
```

Replace with:
```html
                                    <source srcset="img/Tours/Valpo/portada-mobile.webp 600w, img/Tours/Valpo/portada.webp 955w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
```

- [ ] **Step 2: Maipo card**

Find (line 122):
```html
                                    <source srcset="img/Tours/Maipo/portada.webp" type="image/webp">
```

Replace with:
```html
                                    <source srcset="img/Tours/Maipo/portada-mobile.webp 600w, img/Tours/Maipo/portada.webp 720w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
```

- [ ] **Step 3: Andes card**

Find (line 152):
```html
                                    <source srcset="img/Tours/Andes/portada.webp" type="image/webp">
```

Replace with:
```html
                                    <source srcset="img/Tours/Andes/portada-mobile.webp 600w, img/Tours/Andes/portada.webp 1400w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
```

- [ ] **Step 4: Santiago card**

Find (line 176):
```html
                                    <source srcset="img/Tours/Stgo/portada.webp" type="image/webp">
```

Replace with:
```html
                                    <source srcset="img/Tours/Stgo/portada-mobile.webp 600w, img/Tours/Stgo/portada.webp 1440w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
```

- [ ] **Step 5: Cruise card**

Find (line 196):
```html
                                    <source srcset="img/Tours/Cruise/portada.webp" type="image/webp">
```

Replace with:
```html
                                    <source srcset="img/Tours/Cruise/portada-mobile.webp 600w, img/Tours/Cruise/portada.webp 900w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
```

- [ ] **Step 6: Lint and verify the new files are tracked**

```bash
php -l index.php
git status --short img/Tours/*/portada-mobile.webp
```

Expected: no syntax errors; the 5 new files show as untracked (`??`), ready to be added.

- [ ] **Step 7: Commit**

```bash
git add index.php img/Tours/Valpo/portada-mobile.webp img/Tours/Maipo/portada-mobile.webp img/Tours/Andes/portada-mobile.webp img/Tours/Stgo/portada-mobile.webp img/Tours/Cruise/portada-mobile.webp
git commit -m "Add responsive srcset to homepage tour card images"
```

---

### Task 2: Verify the browser actually selects the right file per width

**Files:**
- None modified — this task only verifies. If the `sizes` value is wrong (picking the large file on mobile, or the small file on desktop where it'd look soft), fix it in place in `index.php`, then re-verify.

**Interfaces:**
- Consumes: the markup from Task 1.
- Produces: verification evidence only.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/index.php
```

Expected: `200`.

- [ ] **Step 2: Check the browser's actual selected `currentSrc`, not just the markup**

A `sizes` mismatch is a real, common bug class where the markup looks correct but the browser picks the wrong candidate. Use Puppeteer with CDP device-metrics override (not `--window-size`, which silently clamps below 500px) to load the homepage at several widths and read each card's `<img>`... actually `<picture>`'s active `<source>` — read the rendered `<img>` element's `currentSrc` property (this reflects which `srcset` candidate the browser actually chose, accounting for both `sizes` and device pixel ratio):

```js
// example for one width; repeat for the full list below
const results = await page.evaluate(() => {
  return Array.from(document.querySelectorAll('.tour_container .img_container img')).map(img => img.currentSrc);
});
```

Test at: `375, 480, 650, 768, 992, 1470` — both at `deviceScaleFactor: 1` and `deviceScaleFactor: 2` (retina) for at least the 375px and 1470px cases, since `sizes`/`srcset` selection depends on both viewport width and pixel density.

Expected:
- At `375`/`480`/`650` width, `deviceScaleFactor: 1`: all 5 `currentSrc` values end in `portada-mobile.webp`.
- At `1470` width, `deviceScaleFactor: 1`: all 5 `currentSrc` values end in `portada.webp` (the full-size file, not `-mobile`).
- At `375` width, `deviceScaleFactor: 2`: likely still `portada-mobile.webp` won't be enough resolution for a full 2x need at that width per the design's own math, and the browser may select the full-size file instead — this is expected and correct (the mobile variant is a floor, not a hard ceiling), not a bug. Confirm whichever file is selected is at least as large as the mobile variant, not something unexpected.

- [ ] **Step 3: Visual confirmation**

Screenshot the homepage tour grid at 375px and 1470px widths, confirm no visible quality loss or layout shift compared to the current production appearance.

- [ ] **Step 4: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 5: If Step 2 showed unexpected selections, fix and re-verify**

If narrow widths aren't picking the mobile variant, the most likely cause is the `sizes` value not matching real layout closely enough — revisit the measured render-width table in the design spec and adjust the `sizes` breakpoint/value in `index.php` (all 5 cards use the same value, so one fix applies everywhere). Repeat Steps 1-4 after any fix.

- [ ] **Step 6: Commit (only if Step 5 required a fix)**

```bash
git add index.php
git commit -m "Fix sizes attribute after srcset verification"
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

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to `git pull` on the cPanel server, and per the caching issue discovered earlier this session, likely also needs to purge the Cloudflare cache for the new image files and the updated `index.php` to actually be served.

- [ ] **Step 3: Once deployed and cache-purged, re-run PageSpeed Insights (mobile) against the homepage**

Compare LCP specifically against the most recent baseline (10.0s). Per the design spec: if LCP doesn't improve meaningfully, that's a valid, informative negative result about the bandwidth-contention hypothesis — not a sign this plan failed, since the byte-weight reduction (confirmed via Task 1's file sizes) is a real win on its own regardless of whether it moves the specific LCP metric.
