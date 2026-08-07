# Tour Gallery CLS Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the Cumulative Layout Shift caused by the Slider Pro image gallery on all 5 tour pages by reserving its layout space in CSS before the plugin's JS runs.

**Architecture:** CSS-only fix. Two vendor bundle files carry a duplicated copy of the Slider Pro plugin CSS (`css/vendors-tour.css` for the 4 pages that use the split bundle, `css/vendors.css` for `cruise-transfer.php`, which still loads the old unsplit bundle). Both get the identical new rule block appended.

**Tech Stack:** Plain CSS, no build step (deploys are `git pull` only — see repo conventions).

## Global Constraints

- Do not modify `js/jquery.sliderPro.js`, `js/tours.js`, or any page's gallery markup — this is a CSS-only fix.
- The new CSS must go in BOTH `css/vendors-tour.css` and `css/vendors.css` — they are independently loaded by different pages (see Task 1 rationale) and must stay in sync for this gallery block.
- Do not touch `css/vendors-core.css` or `css/vendors-home.css` — neither loads on any page with the gallery.
- Target selectors must be `#Img_carousel .sp-slides` and `#Img_carousel .sp-thumbnails` — NOT `.sp-slides-container` or `.sp-thumbnails-container`. Those two container classes do not exist in the server-rendered HTML at all; `jquery.sliderPro.js` creates them via `$('<div class="sp-slides-container">').appendTo(...)` / `.insertAfter(...)` at init time (see `js/jquery.sliderPro.js:173` and `:1668`). CSS rules on the JS-created wrappers would have no effect on the pre-JS collapsed paint that causes the shift — the real DOM has `.sp-slides` and `.sp-thumbnails` as direct children of `#Img_carousel` before the script runs.
- `aspect-ratio: 960 / 500` on `#Img_carousel .sp-slides` is not an approximation — it exactly matches the plugin's own sizing formula. Confirmed by reading `js/jquery.sliderPro.js`: `this.settings.aspectRatio = this.settings.width / this.settings.height` (960/500 by default, `js/tours.js:2-14` and `cruise-transfer.php:379-384` both init with `width: 960, height: 500`), and `this.slideHeight = this.slideWidth / this.settings.aspectRatio` — i.e. the plugin always derives height from whatever width it resolves to, using this exact ratio, at every breakpoint. The reserved CSS space will match the plugin's real computed size, not just approximate it.
- `min-height: 180px` on `#Img_carousel .sp-thumbnails` matches the real thumbnail asset height exactly — every thumbnail across all 5 tours (`img/Tours/Cruise/*_thumb.webp`, `img/Tours/Andes/*_thumb.webp`, `img/Tours/Valpo/*_thumb.webp`, `img/Tours/Maipo/*_thumb.webp`, `img/Tours/Stgo/*_thumb.webp`) is rendered at exactly 180px tall (confirmed via `identify` on samples from each tour), only the width varies per image.

---

### Task 1: Add gallery CLS-reservation CSS to both vendor bundles

**Files:**
- Modify: `css/vendors-tour.css` (append at end of file)
- Modify: `css/vendors.css` (append at end of file)

**Interfaces:**
- Consumes: nothing (pure CSS addition).
- Produces: `#Img_carousel .sp-slides` and `#Img_carousel .sp-thumbnails` rules that later tasks verify visually and via CLS measurement. No other task depends on new function/class names — this is the only task in the plan besides verification/deploy.

**Context:** Both files currently end with the same minified daterangepicker CSS block (confirmed identical trailing content in both files: `...daterangepicker .drp-calendar.left{clear:none!important}}`). Both already contain the same Slider Pro plugin CSS earlier in the file (confirmed via `grep -c "sp-slides-container{position:relative}"` returning 1 in each). `css/vendors-tour.css` is loaded by `discover-santiago-city-tour.php`, `maipo-valley-wine-tour-santiago.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, and `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php` (all four via `includes/tour-scripts.php`'s sibling stylesheet loading in `includes/head.php`, gated by `$vendor_css_variant = 'tour'`). `css/vendors.css` is loaded by `cruise-transfer.php`, which never sets `$vendor_css_variant` and so falls through to `includes/head.php`'s unsplit-bundle branch.

- [ ] **Step 1: Append the new rule block to `css/vendors-tour.css`**

Add this exact text to the end of the file (the file has no trailing newline before this point — just append, no blank line needed first):

```css

/* --- CLS fix (2026-08-07): reserve space for the Slider Pro gallery
   before its JS runs and computes real dimensions. #Img_carousel >
   .sp-slides and > .sp-thumbnails are the actual elements present in the
   server-rendered HTML - jquery.sliderPro.js moves them into JS-created
   wrapper divs (.sp-slides-container/.sp-mask, .sp-thumbnails-container)
   at init, but by then this CSS has already prevented the initial-paint
   collapse to 0 height. The aspect-ratio matches the plugin's own sizing
   formula exactly (width:960/height:500 init config -> aspectRatio 1.92,
   see js/jquery.sliderPro.js). The thumbnail min-height matches every
   real thumbnail asset's actual height (180px) across all 5 tours. */
#Img_carousel .sp-slides {
  aspect-ratio: 960 / 500;
}
#Img_carousel .sp-thumbnails {
  min-height: 180px;
}
```

- [ ] **Step 2: Append the identical rule block to `css/vendors.css`**

Add the exact same text block from Step 1 to the end of `css/vendors.css`.

- [ ] **Step 3: Verify both files end identically for the new block**

```bash
tail -c 700 css/vendors-tour.css
tail -c 700 css/vendors.css
```

Expected: both outputs end with the identical new CSS block from Step 1 (comment + two rules).

- [ ] **Step 4: Confirm no other file needs the same fix**

```bash
grep -l "sp-slides-container{position:relative}" css/*.css
```

Expected: only `css/vendors-tour.css` and `css/vendors.css` (confirms `css/vendors-core.css` and `css/vendors-home.css` don't carry this plugin CSS and don't need the fix).

- [ ] **Step 5: Commit**

```bash
git add css/vendors-tour.css css/vendors.css
git commit -m "fix: reserve gallery space to eliminate tour-page CLS"
```

---

### Task 2: Local verification

**Files:**
- None created or modified — verification only.

**Interfaces:**
- Consumes: the CSS from Task 1.
- Produces: a before/after CLS comparison and a visual confirmation that the gallery still displays and functions correctly, for the task reviewer and final review to check against.

**Context:** This site has no build step; pages are plain PHP served directly. A local PHP server can serve the repo root as docroot since `db_config.php` (one level above the repo root, per this project's established layout) is already present locally from earlier work this session. The CLS-measurement approach (Puppeteer + native `PerformanceObserver({type:'layout-shift'})` at mobile viewport) was already built and used earlier this session at `/Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen/cls-check.js` — reuse that Puppeteer install (`node_modules` already present in that directory) rather than reinstalling.

- [ ] **Step 1: Start a local PHP server**

From the repo root:

```bash
php -S localhost:8080 -t .
```

Leave it running in the background for the rest of this task.

- [ ] **Step 2: Adapt the CLS-check script to local URLs**

Copy `/Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen/cls-check.js` to `/Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen/cls-check-local.js`, and change its `pages` array (currently `https://stampstour.com/...`) to just these 5 URLs, all under `http://localhost:8080/`:

```js
const pages = [
  'http://localhost:8080/cruise-transfer.php',
  'http://localhost:8080/portillo-inca-lagoon-andes-mountains-vineyard.php',
  'http://localhost:8080/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php',
  'http://localhost:8080/maipo-valley-wine-tour-santiago.php',
  'http://localhost:8080/discover-santiago-city-tour.php',
];
```

Leave the rest of the script (viewport, `PerformanceObserver` setup, scroll/wait logic, output format) unchanged.

- [ ] **Step 3: Run the local CLS check against the current (fixed) code**

```bash
cd /Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen && node cls-check-local.js
```

Record the CLS total and top shift sources for each of the 5 URLs.

- [ ] **Step 4: Confirm the gallery shift is gone or dramatically reduced**

For all 5 URLs, the top shift sources reported should no longer include `img.sp-thumbnail`, `div.sp-thumbnails`, or any Slider Pro element as a dominant contributor. Some small residual shift from unrelated elements (e.g. the minor `nav.col-9, div#logo` shift noted during the original investigation) is expected and fine — that's a separate, low-priority, pre-existing issue not in scope for this plan.

If any page still shows a significant gallery-related shift, do not proceed — investigate before moving to Task 3 (see this plan's Global Constraints for the exact selectors/values that should be in effect; check the running page's computed styles in a browser to confirm the new CSS actually applied and matches what Task 1 wrote).

- [ ] **Step 5: Visual and functional check**

Open each of the 5 URLs in a real browser pointed at `http://localhost:8080/`. For each page, confirm:
- The main gallery image displays at a reasonable size immediately (not a collapsed sliver, not oversized/distorted).
- The thumbnail strip displays at its normal height, no visible empty gap or overlap.
- Clicking a thumbnail still switches the main slide (confirms Slider Pro's JS init still runs correctly against the modified layout).
- Resize the browser window (or use devtools device toolbar) from mobile width to desktop width — the gallery should resize smoothly with no stuck/frozen sizing or visible snapping glitch beyond what existed before this change.

- [ ] **Step 6: Stop the local PHP server**

```bash
# kill the php -S process started in Step 1
```

---

### Task 3: Deploy and confirm production

**Files:**
- None — deployment and verification only.

**Interfaces:**
- Consumes: the committed changes from Task 1, confirmed locally in Task 2.
- Produces: final confirmation for the plan's SDD final review.

- [ ] **Step 1: Push the commit**

```bash
git push
```

- [ ] **Step 2: Ask the user to pull on the server**

Tell the user: "Pushed. Please pull this on the production server (`git pull` via cPanel or however you normally deploy), then let me know once it's live so I can re-measure."

Wait for the user's confirmation before continuing.

- [ ] **Step 3: Re-run the CLS check against production**

Using the original (unmodified) `/Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen/cls-check.js` (the one pointed at `https://stampstour.com/...`), re-run it for just the 5 tour-page URLs:

```bash
cd /Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen && node cls-check.js
```

- [ ] **Step 4: Compare against the original measurement**

Confirm the 5 tour pages' CLS scores have dropped from their original values (cruise-transfer 0.3868, portillo/Andes 0.2631, Maipo 0.1487, Valparaíso 0.0693, Discover Santiago 0.0016) and that `img.sp-thumbnail`/gallery elements are no longer dominant shift sources. Report the before/after table to the user.

---

## Verification

1. `tail -c 700` on both modified CSS files shows the identical new block appended (Task 1, Step 3).
2. Local CLS measurement (Task 2) shows the gallery shift eliminated or dramatically reduced on all 5 tour pages, with no visual/functional regression in the gallery itself.
3. Production CLS measurement (Task 3) confirms the same result on live URLs, compared against the original Search-Console-triggered measurement from earlier this session.

## Risks

- **`.sp-slides`/`.sp-thumbnails` selector correctness depends on the plugin never being reconfigured to skip its DOM-restructuring step.** If a future change to `js/tours.js` or `cruise-transfer.php`'s inline init ever changes the `width`/`height` init values away from 960/500, the `aspect-ratio: 960 / 500` reservation would no longer exactly match the plugin's computed size — it would still reserve *some* space (much better than the current zero), just not a perfect match. Low risk: both current init sites use these values today, confirmed by reading both files directly.
- **Duplicated CSS across two files is a deliberate, pre-existing pattern** (the bundle-split work earlier this session already made this exact tradeoff for the whole vendor CSS bundle) — not a new risk introduced by this plan, just inherited. A future drift between `vendors-tour.css` and `vendors.css` on this specific block is possible if someone edits one without the other; low-stakes since it's a pure CSS visual fix, not payment-critical logic.
