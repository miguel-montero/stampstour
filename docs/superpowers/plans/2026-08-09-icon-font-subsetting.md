# Icon Font Subsetting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cut the `fontello` icon font from 313KB (WOFF, no WOFF2) down to ~7.6KB and `icon_set_1` from 41.8KB down to ~10.7KB, by subsetting each to exactly the glyphs actually used sitewide and adding real WOFF2 output — fixing a Lighthouse "avoid oversized web fonts" finding.

**Architecture:** Generate two new WOFF2-only font files via `fonttools subset` using precisely audited codepoint lists (verified against both static markup and dynamic JS usage), then swap the `src` list in all 28 existing `@font-face` occurrences (14 per font, across 5 files) to point at the new files with `format("woff2")` only, dropping the legacy EOT/WOFF/TTF/SVG sources. Font-family names and every CSS selector referencing these fonts stay unchanged.

**Tech Stack:** `fonttools` (Python) for subsetting, CSS-only changes to the site itself.

## Global Constraints

- Fontello codepoints (36 total, verified against both static PHP markup and JS-driven `.toggleClass()` usage): `U+ea35,U+ed5a,U+ee4e,U+e87a,U+e816,U+e815,U+e931,U+e9ec,U+eea6,U+e839,U+e83f,U+e932,U+e996,U+e82f,U+e8dd,U+eaf4,U+e90e,U+ed75,U+e81a,U+e872,U+e814,U+eb76,U+eb20,U+ed80,U+e8fa,U+ed84,U+e8f6,U+e99c,U+eba1,U+e899,U+e80f,U+ea9c,U+e827,U+eb9a,U+e821,U+e825` — not to be altered.
- icon_set_1 codepoints (18 total): `U+2f,U+30,U+22,U+36,U+39,U+3c,U+3d,U+42,U+45,U+24,U+4c,U+5c,U+67,U+6d,U+6e,U+73,U+77,U+79` — not to be altered.
- New files: `css/fontello/font/fontello-subset.woff2` and `css/fontello/font/icon_set_1-subset.woff2` — same directory as the originals, `-subset` suffix. The original `.eot`/`.woff`/`.ttf`/`.svg` files for both fonts are NOT deleted — they remain on disk as the regeneration source for any future icon addition.
- Font-family names (`fontello`, `icon_set_1`) and every existing CSS selector (`.icon-phone:before`, `.icon_set_1_icon-89:before`, etc.) stay completely unchanged — this plan only swaps each `@font-face`'s `src` list.
- **Three distinct path conventions exist across the 5 files touched by this plan — match each occurrence's existing style exactly:**
  - `css/vendors.css`, `css/vendors-core.css`: relative, no leading slash, no `css/` prefix (e.g. `fontello/font/fontello-subset.woff2`) — correct since these files already live inside `css/`.
  - `includes/critical/home.css`, `includes/critical/tour.css`: root-relative with `/css/` prefix (e.g. `/css/fontello/font/fontello-subset.woff2`).
  - `includes/critical/content.css`: relative with `css/` prefix but no leading slash (e.g. `css/fontello/font/fontello-subset.woff2`).
- All 5 CSS files are single-line-minified (or, for `content.css`, minified per-chunk) — preserve that style, no reformatting.
- `content.css` has its established 10-duplicated-chunk structure (confirmed identical to the pattern already handled in the Montserrat font-swap plan) — both fonts' `@font-face` occur exactly 10 times each in this one file; use a scripted replacement with an occurrence-count assertion, not manual editing.

---

### Task 1: Generate the subset WOFF2 files

**Files:**
- Create: `css/fontello/font/fontello-subset.woff2`
- Create: `css/fontello/font/icon_set_1-subset.woff2`

**Interfaces:**
- Consumes: nothing from other tasks — standalone asset generation.
- Produces: the two files above, referenced by path in Tasks 2-4.

- [ ] **Step 1: Set up `fonttools` if not already available**

```bash
python3 -m venv /tmp/fontenv
source /tmp/fontenv/bin/activate
pip install --quiet fonttools brotli
```

- [ ] **Step 2: Generate both subsets**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP

fonttools subset css/fontello/font/fontello.ttf \
  --unicodes="U+ea35,U+ed5a,U+ee4e,U+e87a,U+e816,U+e815,U+e931,U+e9ec,U+eea6,U+e839,U+e83f,U+e932,U+e996,U+e82f,U+e8dd,U+eaf4,U+e90e,U+ed75,U+e81a,U+e872,U+e814,U+eb76,U+eb20,U+ed80,U+e8fa,U+ed84,U+e8f6,U+e99c,U+eba1,U+e899,U+e80f,U+ea9c,U+e827,U+eb9a,U+e821,U+e825" \
  --output-file=css/fontello/font/fontello-subset.woff2 \
  --flavor=woff2 --name-IDs='*' --layout-features='*'

fonttools subset css/fontello/font/icon_set_1.ttf \
  --unicodes="U+2f,U+30,U+22,U+36,U+39,U+3c,U+3d,U+42,U+45,U+24,U+4c,U+5c,U+67,U+6d,U+6e,U+73,U+77,U+79" \
  --output-file=css/fontello/font/icon_set_1-subset.woff2 \
  --flavor=woff2 --name-IDs='*' --layout-features='*'
```

- [ ] **Step 3: Verify file sizes**

```bash
ls -la css/fontello/font/fontello-subset.woff2 css/fontello/font/icon_set_1-subset.woff2
```

Expected: `fontello-subset.woff2` around 7,600 bytes (verified during brainstorming at 7,608 bytes — allow some variance since exact byte count can shift slightly with fonttools version), `icon_set_1-subset.woff2` around 10,700 bytes (verified at 10,680 bytes). If either is dramatically different (e.g., still hundreds of KB, meaning the subset didn't apply, or under 1KB, meaning glyphs are missing), STOP and investigate before proceeding — do not commit a broken or ineffective subset.

- [ ] **Step 4: Commit**

```bash
git add css/fontello/font/fontello-subset.woff2 css/fontello/font/icon_set_1-subset.woff2
git commit -m "assets: add subsetted WOFF2 variants of fontello and icon_set_1 icon fonts

313KB fontello WOFF -> ~7.6KB WOFF2 (36 of 1929 glyphs actually used,
verified against both static markup and JS-driven .toggleClass usage).
41.8KB icon_set_1 WOFF -> ~10.7KB WOFF2 (18 of 100 glyphs used).
Original full-glyph files kept on disk as the regeneration source for
any future icon addition."
```

---

### Task 2: Update `css/vendors.css` and `css/vendors-core.css`

**Files:**
- Modify: `css/vendors.css`
- Modify: `css/vendors-core.css`

**Interfaces:**
- Consumes: `css/fontello/font/fontello-subset.woff2` and `css/fontello/font/icon_set_1-subset.woff2` from Task 1.
- Produces: nothing consumed by later tasks — Tasks 2, 3, and 4 are independent (different files).

- [ ] **Step 1: Update `css/vendors.css`'s fontello `@font-face`**

Current:

```css
@font-face{font-family:fontello;src:url(fontello/font/fontello.eot?32974303);src:url(fontello/font/fontello.eot?32974303#iefix) format("embedded-opentype"),url(fontello/font/fontello.woff?32974303) format("woff"),url(fontello/font/fontello.ttf?32974303) format("truetype"),url(fontello/font/fontello.svg?32974303#fontello) format("svg");font-weight:400;font-style:normal}
```

Change to:

```css
@font-face{font-family:fontello;src:url(fontello/font/fontello-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

- [ ] **Step 2: Update `css/vendors.css`'s icon_set_1 `@font-face`**

Current:

```css
@font-face{font-family:icon_set_1;src:url(fontello/font/icon_set_1.eot?55361665);src:url(fontello/font/icon_set_1.eot?55361665#iefix) format("embedded-opentype"),url(fontello/font/icon_set_1.woff?55361665) format("woff"),url(fontello/font/icon_set_1.ttf?55361665) format("truetype"),url(fontello/font/icon_set_1.svg?55361665#icon_set_1) format("svg");font-weight:400;font-style:normal}
```

Change to:

```css
@font-face{font-family:icon_set_1;src:url(fontello/font/icon_set_1-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

- [ ] **Step 3: Apply the identical two changes to `css/vendors-core.css`**

`css/vendors-core.css` has byte-identical `@font-face` declarations for both fonts (same relative path convention, same query strings) — apply the exact same before/after transformations as Steps 1-2.

- [ ] **Step 4: Verify**

```bash
grep -c 'fontello-subset\|icon_set_1-subset' /Users/miguelmontero/Documents/superpowers/STAMP/css/vendors.css
grep -c 'fontello-subset\|icon_set_1-subset' /Users/miguelmontero/Documents/superpowers/STAMP/css/vendors-core.css
```

Expected: `2` for each file (one per font).

- [ ] **Step 5: Commit**

```bash
git add css/vendors.css css/vendors-core.css
git commit -m "fix: point vendors.css/vendors-core.css icon fonts at the new WOFF2 subsets"
```

---

### Task 3: Update `includes/critical/home.css` and `includes/critical/tour.css`

**Files:**
- Modify: `includes/critical/home.css`
- Modify: `includes/critical/tour.css`

**Interfaces:**
- Consumes: `css/fontello/font/fontello-subset.woff2` and `css/fontello/font/icon_set_1-subset.woff2` from Task 1.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Update `includes/critical/home.css`'s fontello `@font-face`**

Current:

```css
@font-face{font-family:fontello;src:url(/css/fontello/font/fontello.eot?32974303);src:url(/css/fontello/font/fontello.eot?32974303#iefix) format("embedded-opentype"),url(/css/fontello/font/fontello.woff?32974303) format("woff"),url(/css/fontello/font/fontello.ttf?32974303) format("truetype"),url(/css/fontello/font/fontello.svg?32974303#fontello) format("svg");font-weight:400;font-style:normal}
```

Change to:

```css
@font-face{font-family:fontello;src:url(/css/fontello/font/fontello-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

- [ ] **Step 2: Update `includes/critical/home.css`'s icon_set_1 `@font-face`**

Current:

```css
@font-face{font-family:icon_set_1;src:url(/css/fontello/font/icon_set_1.eot?55361665);src:url(/css/fontello/font/icon_set_1.eot?55361665#iefix) format("embedded-opentype"),url(/css/fontello/font/icon_set_1.woff?55361665) format("woff"),url(/css/fontello/font/icon_set_1.ttf?55361665) format("truetype"),url(/css/fontello/font/icon_set_1.svg?55361665#icon_set_1) format("svg");font-weight:400;font-style:normal}
```

Change to:

```css
@font-face{font-family:icon_set_1;src:url(/css/fontello/font/icon_set_1-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

- [ ] **Step 3: Apply the identical two changes to `includes/critical/tour.css`**

`includes/critical/tour.css` has byte-identical `@font-face` declarations for both fonts (same root-relative `/css/` path convention as home.css) — apply the exact same before/after transformations as Steps 1-2.

- [ ] **Step 4: Verify**

```bash
grep -c 'fontello-subset\|icon_set_1-subset' /Users/miguelmontero/Documents/superpowers/STAMP/includes/critical/home.css
grep -c 'fontello-subset\|icon_set_1-subset' /Users/miguelmontero/Documents/superpowers/STAMP/includes/critical/tour.css
```

Expected: `2` for each file.

```bash
php -S localhost:8765 -t /Users/miguelmontero/Documents/superpowers/STAMP &
sleep 1
curl -s http://localhost:8765/index.php | grep -o "fontello-subset\|icon_set_1-subset"
kill %1
```

Expected: both strings appear in the rendered homepage's inlined `<style>` block.

- [ ] **Step 5: Commit**

```bash
git add includes/critical/home.css includes/critical/tour.css
git commit -m "fix: point home.css/tour.css critical CSS icon fonts at the new WOFF2 subsets"
```

---

### Task 4: Update `includes/critical/content.css` (10 duplicated occurrences per font)

**Files:**
- Modify: `includes/critical/content.css`

**Interfaces:**
- Consumes: `css/fontello/font/fontello-subset.woff2` and `css/fontello/font/icon_set_1-subset.woff2` from Task 1.
- Produces: nothing consumed by later tasks.

`content.css` uses a THIRD path convention (relative with `css/` prefix, no leading slash) and has its established 10-duplicated-chunk structure — use a scripted replacement, matching the exact approach already proven in this session's Montserrat font-swap plan.

- [ ] **Step 1: Run the scripted replacement**

```bash
python3 -c "
path = '/Users/miguelmontero/Documents/superpowers/STAMP/includes/critical/content.css'
content = open(path).read()

old_fontello = '@font-face{font-family:fontello;src:url(css/fontello/font/fontello.eot?32974303);src:url(css/fontello/font/fontello.eot?32974303#iefix) format(\"embedded-opentype\"),url(css/fontello/font/fontello.woff?32974303) format(\"woff\"),url(css/fontello/font/fontello.ttf?32974303) format(\"truetype\"),url(css/fontello/font/fontello.svg?32974303#fontello) format(\"svg\");font-weight:400;font-style:normal}'
new_fontello = '@font-face{font-family:fontello;src:url(css/fontello/font/fontello-subset.woff2) format(\"woff2\");font-weight:400;font-style:normal}'
count_fontello = content.count(old_fontello)
assert count_fontello == 10, f'expected 10 fontello occurrences, found {count_fontello}'
content = content.replace(old_fontello, new_fontello)

old_iconset1 = '@font-face{font-family:icon_set_1;src:url(css/fontello/font/icon_set_1.eot?55361665);src:url(css/fontello/font/icon_set_1.eot?55361665#iefix) format(\"embedded-opentype\"),url(css/fontello/font/icon_set_1.woff?55361665) format(\"woff\"),url(css/fontello/font/icon_set_1.ttf?55361665) format(\"truetype\"),url(css/fontello/font/icon_set_1.svg?55361665#icon_set_1) format(\"svg\");font-weight:400;font-style:normal}'
new_iconset1 = '@font-face{font-family:icon_set_1;src:url(css/fontello/font/icon_set_1-subset.woff2) format(\"woff2\");font-weight:400;font-style:normal}'
count_iconset1 = content.count(old_iconset1)
assert count_iconset1 == 10, f'expected 10 icon_set_1 occurrences, found {count_iconset1}'
content = content.replace(old_iconset1, new_iconset1)

open(path, 'w').write(content)
print('Done: replaced', count_fontello, 'fontello and', count_iconset1, 'icon_set_1 occurrences')
"
```

Expected output: `Done: replaced 10 fontello and 10 icon_set_1 occurrences`. If either assertion fails, STOP and report BLOCKED — do not proceed with a mismatched count.

- [ ] **Step 2: Verify**

```bash
grep -o 'fontello-subset\|icon_set_1-subset' /Users/miguelmontero/Documents/superpowers/STAMP/includes/critical/content.css | sort | uniq -c
```

Expected: `10 fontello-subset` and `10 icon_set_1-subset`.

```bash
php -S localhost:8765 -t /Users/miguelmontero/Documents/superpowers/STAMP &
sleep 1
curl -s http://localhost:8765/contact-us.php | grep -o "fontello-subset\|icon_set_1-subset" | sort | uniq -c
kill %1
```

Expected: `10 fontello-subset` and `10 icon_set_1-subset` in the rendered page (the entire `content.css` — all 10 chunks — is inlined per the existing `$critical_css_file` mechanism, matching this session's already-established, pre-existing behavior).

- [ ] **Step 3: Commit**

```bash
git add includes/critical/content.css
git commit -m "fix: point content.css critical CSS icon fonts at the new WOFF2 subsets"
```

---

### Task 5: Exhaustive visual verification

**Files:** none modified — verification only, unless a real defect is found, in which case fix it in the relevant file from Tasks 1-4 before marking this task done.

**Interfaces:**
- Consumes: Tasks 1-4 together (all 5 CSS files + 2 new font assets).

- [ ] **Step 1: Render every one of the 36 fontello + 18 icon_set_1 glyphs**

Using a local `php -S` server, build a small standalone HTML test page (not committed to the repo — a scratch file) that loads the new subset fonts and renders every one of the 36 fontello codepoints and 18 icon_set_1 codepoints listed in this plan's Global Constraints, each labeled with its class name. Screenshot the page and visually confirm every glyph displays as a real, recognizable icon shape (not a missing-glyph box, not blank). This is the exhaustive version of the 7-glyph spot-check already done during brainstorming.

- [ ] **Step 2: Specifically test the JS-driven accordion toggle**

Load a page containing an `.accordion_styled` element (check `grep -rl "accordion_styled" --include="*.php" .` to find one), click to expand and collapse it, and confirm the chevron indicator icon (`icon-plus`/`icon-minus`) swaps correctly in both directions — this is the one glyph pair not visible from static markup alone, and the reason the codepoint list grew from 34 to 36 during brainstorming.

- [ ] **Step 3: No 404s / console errors across all 3 critical-CSS page types**

Load one page each from the home/tour/content critical-CSS variants (e.g. `index.php`, `discover-santiago-city-tour.php`, `contact-us.php`), confirm zero console errors, zero failed font requests, and that icons visible on each page (nav icons, hero badges, itinerary icons, etc.) render correctly.

- [ ] **Step 4: Record findings**

If Steps 1-3 all pass, proceed to Task 6. If a real defect is found (a missing glyph, a broken accordion icon, a 404), fix it — likely means the codepoint list was incomplete and Task 1's subset needs regenerating with an additional codepoint — and re-run the failing check before proceeding.

---

### Task 6: Deploy and confirm production

**Files:** none modified — deployment/verification only.

**Interfaces:**
- Consumes: all commits from Tasks 1-5.

- [ ] **Step 1: Push to `main`**

```bash
git push origin main
```

- [ ] **Step 2: Ask the user to pull AND clear the Cloudflare cache on the HostGator production server**

This site's deploy pipeline has no automatic CDN cache invalidation — per two separate incidents already encountered this session (the tour-card lazy-load plan and the Montserrat font-swap plan), a git-pull alone can leave Cloudflare serving stale CSS/JS for up to 4 hours. Ask the user to both pull and clear the cache, then confirm via `curl -sI` that the relevant CSS/HTML reflects the new font references (not stale) before proceeding.

- [ ] **Step 3: Confirm production — network-level spot check**

Fetch the live homepage, a tour page, and a content page (`curl -s https://stampstour.com/...`) and confirm each contains `fontello-subset`/`icon_set_1-subset` references, and that requesting the new WOFF2 files directly (`curl -sI https://stampstour.com/css/fontello/font/fontello-subset.woff2`) returns HTTP 200 with the expected small file size (not a 404).

- [ ] **Step 4: Confirm production — visual spot check**

Using a headless browser against the live production URLs, screenshot the same pages checked in Task 5 and confirm icons still render correctly in production, matching local verification. Specifically re-check the accordion toggle on a live production page if one exists on a publicly reachable page.
