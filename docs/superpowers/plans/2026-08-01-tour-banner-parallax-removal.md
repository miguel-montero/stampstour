# Tour Banner Parallax Removal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the `parallax.js`-driven, JS-computed-height banner on all 5 tour pages (the same CLS-causing pattern already fixed once on the homepage hero — confirmed via production PSI audit: CLS 0.741 on the Maipo page) and replace it with a plain `<img>` + CSS-sized section, with zero JS dependency.

**Architecture:** Each page's opening `<section class="parallax-window" data-parallax="scroll" data-image-src="..." data-natural-height="470">` becomes `<section class="tour-banner">` with a new `<img class="tour-banner-bg">` inserted as its first child. Everything else inside the section (TripAdvisor badge where present, save badge, title, price) is untouched — only the wrapping tag and background mechanism change. Real breakpoint heights were measured directly against production via Puppeteer + CDP device-metrics override (not `--window-size`, which is known from earlier work this session to silently clamp below 500px): a single breakpoint at 768px, flat 360px below it and flat 470px at/above it, identical across all 5 pages regardless of each banner image's own aspect ratio.

**Tech Stack:** Plain PHP includes, vanilla CSS, no build step. `parallax.js`'s plugin logic is bundled inside the shared `js/common_scripts.js`/`common_scripts_min.js` vendor file (confirmed: no separate per-page `<script src="js/parallax.js">` tag exists to remove) — there is nothing to delete from any page's script includes; removing the `data-parallax`/`class="parallax-window"` attributes is sufficient to fully stop the plugin from touching these elements, since it auto-discovers `[data-parallax]` elements rather than being explicitly initialized per page.

## Global Constraints

- Exact measured heights: `360px` below `768px` width, `470px` at/above `768px` width. Single breakpoint, flat values, no other tiers.
- No overlay element is being added. `.parallax-content-2` (preserved, untouched) already provides its own `background: linear-gradient(to bottom, transparent, #000)` for text contrast — confirmed by reading `css/style.css:1771-1781`. Do not add a separate `.tour-banner-overlay` div; it would be redundant.
- No parallax scroll motion and no zoom animation — confirmed with the project owner: static banner only, matching the homepage hero's final treatment.
- The `<img class="tour-banner-bg">` must have `fetchpriority="high"`, no `loading="lazy"`, and its real native `width`/`height` attributes (not the display size) — same LCP-safe pattern as the homepage hero and tour card images.
- Only the opening `<section class="parallax-window" ...>` tag on each page changes. Every other line inside that section — TripAdvisor badge markup (present on Maipo and Valparaíso only, absent on Santiago/Andes/Cruise), save badge (absent/commented on Santiago), price display markup (Cruise has a hardcoded price, the other 4 have the async-fetched empty span fixed in an earlier piece of work) — must be byte-identical to what it is today. Do not touch it.
- `js/parallax.js` / `js/parallax.min.js` (the standalone files, separate from what's bundled in `common_scripts.js`) are not referenced by any page — confirmed via repo-wide grep. Leave them on disk untouched; deleting unreferenced files is out of scope for this plan.

---

### Task 1: Update the banner markup on all 5 pages

**Files:**
- Modify: `maipo-valley-wine-tour-santiago.php:29`
- Modify: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php:31`
- Modify: `discover-santiago-city-tour.php:29`
- Modify: `portillo-inca-lagoon-andes-mountains-vineyard.php:29`
- Modify: `cruise-transfer.php:49`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: the `tour-banner` / `tour-banner-bg` class names Task 2's CSS targets.

- [ ] **Step 1: `maipo-valley-wine-tour-santiago.php`**

Find (line 29):
```html
  <section class="parallax-window" data-parallax="scroll" data-image-src="img/Tours/Maipo/big.jpg" data-natural-height="470">
```

Replace with:
```html
  <section class="tour-banner">
   <img src="img/Tours/Maipo/big.jpg" width="720" height="480" fetchpriority="high" alt="Maipo Valley banner" class="tour-banner-bg">
```

Do not change anything else in the file — the very next line is `<div class="badge_tripadvisor_circle">`, which stays exactly as it is, along with everything else through the section's closing `</section>`.

- [ ] **Step 2: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`**

Find (line 31):
```html
  <section class="parallax-window" data-parallax="scroll" data-image-src="img/Tours/Valpo/big.jpg" data-natural-height="470">
```

Replace with:
```html
  <section class="tour-banner">
   <img src="img/Tours/Valpo/big.jpg" width="1920" height="716" fetchpriority="high" alt="Valparaíso banner" class="tour-banner-bg">
```

(Use the literal UTF-8 character í, not the escape sequence above — write `alt="Valparaíso banner"`.) Nothing else in the file changes.

- [ ] **Step 3: `discover-santiago-city-tour.php`**

Find (line 29):
```html
  <section class="parallax-window" data-parallax="scroll" data-image-src="img/Tours/Stgo/big.jpg" data-natural-height="470">
```

Replace with:
```html
  <section class="tour-banner">
   <img src="img/Tours/Stgo/big.jpg" width="1400" height="1050" fetchpriority="high" alt="Santiago banner" class="tour-banner-bg">
```

Nothing else in the file changes.

- [ ] **Step 4: `portillo-inca-lagoon-andes-mountains-vineyard.php`**

Find (line 29):
```html
  <section class="parallax-window" data-parallax="scroll" data-image-src="img/Tours/Andes/big.jpg" data-natural-height="470">
```

Replace with:
```html
  <section class="tour-banner">
   <img src="img/Tours/Andes/big.jpg" width="1920" height="1440" fetchpriority="high" alt="Andes banner" class="tour-banner-bg">
```

Nothing else in the file changes.

- [ ] **Step 5: `cruise-transfer.php`**

Find (line 49):
```html
<section class="parallax-window" data-parallax="scroll" data-image-src="img/Tours/Cruise/big.jpg" data-natural-height="470">
```

Replace with:
```html
<section class="tour-banner">
<img src="img/Tours/Cruise/big.jpg" width="2000" height="1269" fetchpriority="high" alt="Cruise transfer banner" class="tour-banner-bg">
```

Nothing else in the file changes (this page's next line is `<div class="parallax-content-2">` directly — it has no TripAdvisor badge — leave that and everything after it exactly as-is, including the hardcoded `<span id="dynamic_price">99</span>`).

- [ ] **Step 6: Lint and grep-verify**

```bash
php -l maipo-valley-wine-tour-santiago.php
php -l valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php
php -l discover-santiago-city-tour.php
php -l portillo-inca-lagoon-andes-mountains-vineyard.php
php -l cruise-transfer.php
grep -c "parallax-window\|data-parallax\|data-natural-height" maipo-valley-wine-tour-santiago.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php discover-santiago-city-tour.php portillo-inca-lagoon-andes-mountains-vineyard.php cruise-transfer.php
```

Expected: all 5 `php -l` calls report `No syntax errors detected`; the grep returns `0` for every file (no remaining references to the old class/attributes anywhere).

- [ ] **Step 7: Commit**

```bash
git add maipo-valley-wine-tour-santiago.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php discover-santiago-city-tour.php portillo-inca-lagoon-andes-mountains-vineyard.php cruise-transfer.php
git commit -m "Replace parallax.js banner with a plain img on all tour pages"
```

---

### Task 2: Add the CSS that sizes and displays the banner

**Files:**
- Modify: `css/custom.css` (append a new block; do not touch any existing rule)

**Interfaces:**
- Consumes: the `tour-banner` / `tour-banner-bg` class names produced by Task 1.
- Produces: a fully self-contained CSS block — no other task depends on anything from this one.

- [ ] **Step 1: Append this block to the end of `css/custom.css`**

```css
/* Tour page banners - plain CSS, replacing parallax.js entirely (see
   docs/superpowers/specs/2026-08-01-tour-banner-parallax-removal-design.md).
   Heights are real values measured directly against production via CDP
   device-metrics override, not derived from a formula: flat 360px below
   768px width, flat 470px at/above it (matches Bootstrap's own md
   breakpoint and the old data-natural-height="470" value exactly).
   No overlay element here - .parallax-content-2's own existing
   background gradient (css/style.css) already handles text contrast. */
.tour-banner {
	position: relative;
	overflow: hidden;
	height: 360px;
}
@media (min-width: 768px) {
	.tour-banner {
		height: 470px;
	}
}
.tour-banner-bg {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center center;
}
```

- [ ] **Step 2: Confirm `.parallax-content-2`'s positioning still resolves correctly**

`.parallax-content-2` (in `css/style.css:1771`) is `position: absolute; left: 0; bottom: 0;` — this requires a positioned ancestor to anchor against. `.tour-banner`'s new `position: relative` (Step 1) provides that, replacing what `.parallax-window` previously provided via the plugin's own JS-applied styles. Run:

```bash
grep -n "\.tour-banner {" -A 3 css/custom.css
```

Expected: the block shows `position: relative;` as the first declaration.

- [ ] **Step 3: Commit**

```bash
git add css/custom.css
git commit -m "Add CSS sizing for the tour page banners"
```

---

### Task 3: Local visual verification across all 5 pages and breakpoints

**Files:**
- None modified — this task only verifies. If a check fails, fix Task 1/2's files in place, then re-verify.

**Interfaces:**
- Consumes: the working markup and CSS from Tasks 1-2.
- Produces: visual confirmation only.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/maipo-valley-wine-tour-santiago.php
```

Expected: `200`.

- [ ] **Step 2: Capture screenshots of all 5 pages at breakpoint boundaries and mid-tier widths**

Since there's only one real breakpoint (768px) this time, the critical widths to check are narrower than the earlier hero work needed, but check the full spread anyway for consistency and to catch anything unexpected: `375, 576, 650, 767, 768, 769, 880, 992, 1200, 1470, 1920`. Note `767`/`768`/`769` specifically — that's the one boundary where height changes (360→470), and it's the single highest-risk point for a visible jump if something's wrong.

For any width below 500px, use a same-origin iframe or Chrome DevTools Protocol device-metrics override, not `--window-size` (confirmed this session: it silently clamps to a 500px floor below that value).

- [ ] **Step 3: For each page, confirm at every width:**

- The banner photo is visible, filling the section (not blank/broken).
- The gradient text-contrast band at the bottom is present and the title/price/badges are legible.
- Height is exactly 360px below 768px width and exactly 470px at/above it — no other value, no gradual scaling.
- No jump/glitch specifically at the 767→768→769px sequence.
- The TripAdvisor badge (Maipo, Valparaíso only) and save badge (Maipo, Valparaíso, Andes, Cruise — not Santiago) still render in their expected positions.

- [ ] **Step 4: Compare against current production**

Fetch each of the 5 live production pages (still running the old `parallax.js` version at this point) and visually compare banner appearance — same photo crop/positioning, same overall look — to confirm the new static version isn't a visible regression from what visitors see today.

- [ ] **Step 5: Lint and tag-balance check across all 5 files**

```bash
for f in maipo-valley-wine-tour-santiago.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php discover-santiago-city-tour.php portillo-inca-lagoon-andes-mountains-vineyard.php cruise-transfer.php; do
  php -l "$f"
  echo "$f: <div>=$(grep -c '<div' "$f") </div>=$(grep -c '</div>' "$f")"
done
```

Expected: no syntax errors; each file's `<div>`/`</div>` counts match each other (confirms Task 1's edits didn't disturb any existing markup).

- [ ] **Step 6: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 7: If any check failed, fix and re-verify**

Repeat Steps 1-6 for at least the affected page/width after any fix. Do not proceed to Task 4 until every check in Steps 3-5 passes for all 5 pages.

- [ ] **Step 8: Commit (only if Step 7 required a fix)**

```bash
git add -A
git commit -m "Fix tour banner issue found during visual verification"
```

If no fix was needed, skip this step.

---

### Task 4: Deploy and confirm production

**Files:**
- None modified — this task pushes already-committed changes and confirms the live site.

**Interfaces:**
- Consumes: the commits from Tasks 1-3.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push
```

- [ ] **Step 2: Remind the user to deploy**

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to `git pull` on the cPanel server to see this live.

- [ ] **Step 3: Once deployed, re-run PageSpeed Insights (mobile) against the Maipo tour page**

Compare CLS specifically against the recorded baseline (0.741) — this is the metric this plan exists to fix, so it's the real pass/fail signal for the whole project, not an optional follow-up. A dramatic drop (toward Google's "Good" threshold of ≤0.1) confirms the fix worked in the real world, not just in local screenshots.
