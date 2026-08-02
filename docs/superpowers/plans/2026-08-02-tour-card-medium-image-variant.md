# Andes/Santiago Medium Image Variant Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a third "medium" WebP variant to the Andes and Santiago homepage tour card images so PageSpeed Insights' actual mobile test profile (Moto G Power, DPR 1.75) stops falling through to the full-size file, and correct those two cards' two-column `sizes` value so the new third candidate is selected correctly at all widths.

**Architecture:** Two new files (`img/Tours/Andes/portada-medium.webp`, `img/Tours/Stgo/portada-medium.webp`, both already generated and visually verified) sit alongside each tour's existing `-mobile` and full-size files. Only these 2 cards' `<source>` tags change — a third `srcset` candidate is added, and the two-column `sizes` branch changes from `50vw` to `60vw`. Valparaíso, Maipo, and Cruise are untouched.

**Tech Stack:** Plain HTML `<picture>`/`srcset`/`sizes`, no build step. The medium-variant files were generated with ImageMagick (`-resize 1100x1100> -quality 80`) from the existing full-size WebP files and confirmed visually lossless-at-display-size before this plan was written — that step is already done, this plan only wires up the markup.

## Global Constraints

- Exact new files, already generated, verified, and present in the working tree:
  - `img/Tours/Andes/portada-medium.webp` (142,654 bytes, 1100×786)
  - `img/Tours/Stgo/portada-medium.webp` (156,112 bytes, 1100×733)
- Only `index.php` lines 152 and 176 change. Valparaíso, Maipo, and Cruise cards (lines 92, 122, 196) are not touched — their native full-size files are already close to or below the real DPR-1.75 need, so a medium tier wouldn't help them (see design spec's Context table).
- The single-column `sizes` branch (`600px`) does NOT change on any card, including Andes/Stgo. It was deliberately tuned in the previous plan's Task 2 fix and re-widening it would re-break DPR-1 selection across the 576-767px range.
- The two-column `sizes` branch changes from `50vw` to `60vw` on Andes and Santiago ONLY (accounts for the sitewide `.img_container img { transform: scale(1.2) }` CSS rule inflating real rendered size beyond the raw layout box — see design spec). Valparaíso/Maipo/Cruise keep `50vw`.
- `srcset` width descriptors: `600w` (mobile, unchanged), `1100w` (new medium, both images), and each tour's existing native full width (`1400w` Andes, `1440w` Stgo).
- Only the `<source>` tag's `srcset`/`sizes` attributes change. The fallback `<img>` tags are untouched.

---

### Task 1: Wire up the medium variant on Andes and Santiago cards

**Files:**
- Modify: `index.php:152` (Andes)
- Modify: `index.php:176` (Santiago)

**Interfaces:**
- Consumes: the 2 already-generated `portada-medium.webp` files listed in Global Constraints.
- Produces: nothing new for later tasks — Task 2 verifies this exact change.

- [ ] **Step 1: Andes card**

Find (line 152):
```html
                                    <source srcset="img/Tours/Andes/portada-mobile.webp 600w, img/Tours/Andes/portada.webp 1400w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
```

Replace with:
```html
                                    <source srcset="img/Tours/Andes/portada-mobile.webp 600w, img/Tours/Andes/portada-medium.webp 1100w, img/Tours/Andes/portada.webp 1400w" sizes="(max-width: 767px) 600px, 60vw" type="image/webp">
```

- [ ] **Step 2: Santiago card**

Find (line 176):
```html
                                    <source srcset="img/Tours/Stgo/portada-mobile.webp 600w, img/Tours/Stgo/portada.webp 1440w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
```

Replace with:
```html
                                    <source srcset="img/Tours/Stgo/portada-mobile.webp 600w, img/Tours/Stgo/portada-medium.webp 1100w, img/Tours/Stgo/portada.webp 1440w" sizes="(max-width: 767px) 600px, 60vw" type="image/webp">
```

- [ ] **Step 3: Lint and verify the new files are tracked**

```bash
php -l index.php
git status --short img/Tours/Andes/portada-medium.webp img/Tours/Stgo/portada-medium.webp
```

Expected: no syntax errors; both new files show as untracked (`??`), ready to be added.

- [ ] **Step 4: Confirm the other 3 cards are byte-for-byte unchanged**

```bash
git diff index.php | grep -A1 -B1 "Valpo\|Maipo\|Cruise"
```

Expected: no output (no lines touching those 3 cards appear in the diff).

- [ ] **Step 5: Commit**

```bash
git add index.php img/Tours/Andes/portada-medium.webp img/Tours/Stgo/portada-medium.webp
git commit -m "Add medium-size srcset variant for Andes/Santiago tour cards"
```

---

### Task 2: Verify browser selection, especially at PSI's real test profile

**Files:**
- None modified — this task only verifies. If any check fails, fix in place in `index.php` (or regenerate a file if it's a sizing issue), then re-verify.

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

- [ ] **Step 2: Check `currentSrc` for the Andes and Santiago cards specifically**

Use Puppeteer with CDP device-metrics override (not `--window-size`, which silently clamps below 500px). Read the rendered `<img>` element's `currentSrc` for just these two cards:

```js
const results = await page.evaluate(() => {
  const cards = Array.from(document.querySelectorAll('.tour_container .img_container img'));
  return cards.map(img => img.currentSrc);
});
// index 2 = Andes, index 3 = Santiago, matching card order in index.php (Valpo, Maipo, Andes, Stgo, Cruise)
```

Confirm the card order assumption first by also reading each `<img>`'s `alt` attribute alongside `currentSrc`, so results are matched to the correct tour by name, not by assumed position.

Test these exact scenarios:

| Scenario | Viewport width | Device scale factor | Expected `currentSrc` (Andes & Santiago) |
|---|---|---|---|
| PSI's real mobile profile — the primary case this plan exists to fix | 412 | 1.75 | ends in `portada-medium.webp` |
| Single-column, DPR 1 (previous fix's behavior, must be unaffected) | 375 | 1 | ends in `portada-mobile.webp` |
| Single-column, DPR 1 (previous fix's behavior, must be unaffected) | 650 | 1 | ends in `portada-mobile.webp` |
| Single-column, very high DPR (medium tier still a floor, not ceiling — falling to full size here is correct) | 375 | 3 | ends in `portada.webp` (full size, NOT `-medium` or `-mobile`) |
| Two-column, DPR 1, with new `60vw` value | 992 | 1 | ends in `portada-mobile.webp` — Bootstrap's container caps at 960px here (not fluid to the raw viewport), so the real rendered need (~547px) is already covered by the 600w file; this is correct, not under-selection (corrected after the final review found the original expectation here was based on naive viewport-percentage math instead of the real, container-capped layout width) |
| Two-column, DPR 2, with new `60vw` value | 1470 | 2 | ends in `portada.webp` (full size) |

- [ ] **Step 3: Confirm Valparaíso, Maipo, and Cruise are completely unaffected**

At the same 6 scenarios from Step 2, read `currentSrc` for the other 3 cards (matched by `alt` text) and confirm their selected file is identical to what Task 2 of the previous plan (`2026-08-02-responsive-tour-card-images.md`) already verified — i.e., these 3 cards' behavior did not change:

- Single-column widths (375, 650) at DPR 1: `portada-mobile.webp`.
- 1470px width at DPR 1: `portada.webp` (full size).

- [ ] **Step 4: Visual confirmation**

Screenshot the homepage tour grid at 375px, 412px (DPR 1.75), and 1470px widths. Confirm no visible quality loss and no layout shift compared to current production appearance, for all 5 cards.

- [ ] **Step 5: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 6: If any check in Steps 2-3 failed, fix and re-verify**

If the 412px/DPR-1.75 case doesn't select the medium variant, the likely cause is the calculated threshold being wrong — re-check the arithmetic in the design spec (`docs/superpowers/specs/2026-08-02-tour-card-medium-image-variant-design.md`) against the actual `1100w` file width, and either adjust the `sizes` value or regenerate the medium file wider. If the two-column case at 992px unexpectedly selects `-mobile` (under-selection), the `60vw` value likely still needs adjustment. Repeat Steps 1-5 after any fix.

- [ ] **Step 7: Commit (only if Step 6 required a fix)**

```bash
git add index.php img/Tours/Andes/portada-medium.webp img/Tours/Stgo/portada-medium.webp
git commit -m "Fix sizes/srcset after medium-variant verification"
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

Compare both the reported LCP and the total transferred byte weight against the most recent baseline (LCP ~10.0s, weight ~2.10MB, from the previous plan's post-deploy check). This is the first PSI check that tests conditions where Andes/Santiago's byte weight is actually reduced under PSI's real profile — per the design spec, if LCP still doesn't move, that's a valid, informative negative result about the bandwidth-contention hypothesis, not a sign this plan failed.
