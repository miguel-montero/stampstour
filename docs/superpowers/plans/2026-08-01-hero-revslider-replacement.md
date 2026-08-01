# Homepage Hero: Replace Revolution Slider with Pure CSS — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove Revolution Slider entirely from `index.php`'s homepage hero and replace it with a plain `<img>` + CSS overlay + CSS zoom animation, sized via Bootstrap breakpoints, with no JS-driven resize.

**Architecture:** `index.php` currently renders the hero through RevSlider: a JS library (6 files) builds a slide's DOM at runtime and computes its own responsive height via JS. This plan deletes that markup and JS entirely and replaces it with static HTML (one `<img>`, one overlay `<div>`) sized purely by CSS `@media` rules in `css/custom.css`, using Bootstrap's breakpoints (576/768/992/1200px) with heights hand-picked to look equivalent to RevSlider's real (empirically measured) curve. There is no JavaScript in the new hero at all.

**Tech Stack:** Plain PHP includes (no build step), Bootstrap 5 (already loaded sitewide), vanilla CSS (`css/custom.css`), no test framework in this codebase — verification is `php -l`, grep-based checks, and headless-Chrome screenshot comparison (the pattern already used for every other frontend change this session).

## Global Constraints

- Homepage only — RevSlider is confirmed unused on every other page (`grep -rl "rev-slider-files\|rev_slider" *.php` returns only `index.php`). Do not touch other pages.
- The `<img>` must keep its exact current attributes: `src="img/Tours/portada.webp"`, `width="1883"`, `height="1059"`, `fetchpriority="high"`, `alt="Colorful hillside houses in Valparaíso, Chile"` — these are already correct LCP practice.
- Overlay color must stay `rgba(0, 0, 0, 0.35)` — matches the current RevSlider overlay exactly.
- `.hero-content` (the "Discover Chile" text block + CTA) is untouched — it was already independent of RevSlider.
- Breakpoint heights: default 260px, `≥576px` 340px, `≥768px` 420px, `≥992px` 520px, `≥1200px` 600px (Bootstrap's own breakpoints; values hand-picked against RevSlider's real measured curve, not formula-derived — see spec `docs/superpowers/specs/2026-08-01-hero-revslider-replacement-design.md`).
- Zoom animation: CSS `@keyframes`, `scale(1)` → `scale(1.08)`, `20s ease-in-out infinite alternate`, disabled under `prefers-reduced-motion: reduce`.
- `rev-slider-files/` directory itself stays on disk (nothing else references it after this change, but do not delete it — matches how the earlier partial trim was handled).

---

### Task 1: Replace RevSlider markup and remove its includes in `index.php`

**Files:**
- Modify: `index.php:28-32` (head CSS link)
- Modify: `index.php:60-124` (hero markup)
- Modify: `index.php:327-393` (script includes + init block)

**Interfaces:**
- Consumes: nothing (no dependency on other tasks)
- Produces: `index.php` with a `.hero-wrap` containing `img.hero-bg` + `div.hero-overlay` + the existing `.hero-content` block — these three class names (`hero-wrap`, `hero-bg`, `hero-overlay`) are what Task 2's CSS targets.

- [ ] **Step 1: Remove the RevSlider CSS link from `<head>`**

In `index.php`, find this block (currently lines 28-32):

```html
    <!-- REVOLUTION SLIDER CSS -->
    <!-- font-awesome.css dropped: it's only RevSlider's icon font for nav
         arrows/bullets/thumbnails, none of which this single-slide hero
         uses. settings.css is the core structural CSS and stays. -->
    <link rel="stylesheet" type="text/css" href="rev-slider-files/css/settings.css">
```

Delete it entirely (the line above it is `</style>` closing the inline price-list style block, and the line below is `</head>` — after deletion, `</style>` should be followed directly by `</head>`).

- [ ] **Step 2: Replace the RevSlider hero markup**

Find this block (currently lines 60-124, between `<h1 class="visually-hidden">...</h1>` and the `<!-- Hero text overlay -->` comment):

```html
        <!-- Slider -->
        <div class="hero-wrap">
        <div id="rev_slider_66_1_wrapper" class="rev_slider_wrapper fullwidthbanner-container" data-alias="image-hero" data-source="gallery" style="margin:0px auto;background:transparent;padding:0px;margin-top:0px;margin-bottom:0px;">

            <div id="rev_slider_66_1" class="rev_slider fullwidthabanner" style="display:none;" data-version="5.4.1">
                <ul>
                    <li
                        data-index="rs-170"
                        data-transition="zoomout"
                        data-slotamount="default"
                        data-hideafterloop="0"
                        data-hideslideonmobile="off"
                        data-easein="Power3.easeInOut"
                        data-easeout="Power3.easeInOut"
                        data-masterspeed="3000"
                        data-thumb="img/Tours/portada.webp"
                        data-rotate="0"
                        data-saveperformance="off"
                        data-title="Intro"
                        data-description="">

                        <!-- MAIN IMAGE / HERO BACKGROUND -->
                        <img
                            src="img/Tours/portada.webp"
                            width="1883"
                            height="1059"
                            fetchpriority="high"
                            alt="Colorful hillside houses in Valparaíso, Chile"
                            data-bgposition="center center"
                            data-bgfit="cover"
                            data-bgparallax="10"
                            class="rev-slidebg"
                            data-no-retina>

                        <!-- DARK OVERLAY -->
                        <div
                            class="tp-caption tp-shape tp-shapewrapper"
                            id="slide-170-layer-10"
                            data-x="['center','center','center','center']"
                            data-hoffset="['0','0','0','0']"
                            data-y="['middle','middle','middle','middle']"
                            data-voffset="['0','0','0','0']"
                            data-width="full"
                            data-height="full"
                            data-whitespace="nowrap"
                            data-type="shape"
                            data-basealign="slide"
                            data-responsive_offset="on"
                            data-responsive="off"
                            data-frames='[{"delay":750,"speed":1500,"frame":"0","from":"opacity:0;","to":"o:1;","ease":"Power3.easeInOut"},{"delay":"wait","speed":300,"frame":"999","ease":"nothing"}]'
                            data-textAlign="['left','left','left','left']"
                            data-paddingtop="[0,0,0,0]"
                            data-paddingright="[0,0,0,0]"
                            data-paddingbottom="[0,0,0,0]"
                            data-paddingleft="[0,0,0,0]"
                            style="z-index: 5;background-color:rgba(0, 0, 0, 0.35);">
                        </div>

                    </li>
                </ul>

                <div class="tp-bannertimer tp-bottom" style="visibility: hidden !important;"></div>
            </div>
        </div>
        <!-- END REVOLUTION SLIDER -->

        <!-- Hero text overlay: plain Bootstrap-friendly markup, positioned
             with CSS only (not RevSlider layers), so it stays reliably
             centered on mobile without per-breakpoint pixel tuning. -->
```

Replace it with:

```html
        <!-- Hero background image, sized and animated by pure CSS
             (css/custom.css: .hero-wrap / .hero-bg / .hero-overlay) -->
        <div class="hero-wrap">
        <img
            src="img/Tours/portada.webp"
            width="1883"
            height="1059"
            fetchpriority="high"
            alt="Colorful hillside houses in Valparaíso, Chile"
            class="hero-bg">
        <div class="hero-overlay"></div>

        <!-- Hero text overlay: plain Bootstrap-friendly markup, positioned
             with CSS only, so it stays reliably centered on mobile
             without per-breakpoint pixel tuning. -->
```

(The `.hero-content` block and its closing `</div><!-- End .hero-wrap -->` immediately after stay exactly as they are — do not modify them.)

- [ ] **Step 3: Remove the RevSlider script includes and init block**

Find this block (currently lines 327-393, between the `functions.js` script tag and the next `<script>` block that handles menu scroll behavior):

```html
    <!-- SLIDER REVOLUTION SCRIPTS -->
    <!-- Trimmed to the core engine + the extensions this single-slide hero
         actually uses (slideanims: the "zoomout" entrance transition that
         reveals the slide at all - even a single slide needs this, it's
         not just for transitioning between multiple slides; kenburn:
         continuous background pan/zoom; layeranimation: overlay fade-in;
         parallax: scroll parallax set in the init config below). Dropped
         actions/carousel/navigation (no hotspots, thumbnails, or
         arrows/bullets are used - single slide) and migration (only
         needed for pre-5.0 slide XML format). -->
    <script type="text/javascript" src="rev-slider-files/js/jquery.themepunch.tools.min.js"></script>
    <script type="text/javascript" src="rev-slider-files/js/jquery.themepunch.revolution.min.js"></script>
    <script type="text/javascript" src="rev-slider-files/js/extensions/revolution.extension.slideanims.min.js"></script>
    <script type="text/javascript" src="rev-slider-files/js/extensions/revolution.extension.kenburn.min.js"></script>
    <script type="text/javascript" src="rev-slider-files/js/extensions/revolution.extension.layeranimation.min.js"></script>
    <script type="text/javascript" src="rev-slider-files/js/extensions/revolution.extension.parallax.min.js"></script>

    <script type="text/javascript">
        var tpj = jQuery;
        var revapi66;

        tpj(document).ready(function() {
            if (tpj("#rev_slider_66_1").revolution == undefined) {
                revslider_showDoubleJqueryError("#rev_slider_66_1");
            } else {
                revapi66 = tpj("#rev_slider_66_1").show().revolution({
                    sliderType: "standard",
                    jsFileLocation: "rev-slider-files/js/",
                    sliderLayout: "fullwidth",
                    dottedOverlay: "none",
                    delay: 9000,
                    navigation: {
                        onHoverStop: "off",
                    },
                    responsiveLevels: [1240, 1024, 778, 480],
                    visibilityLevels: [1240, 1024, 778, 480],
                    gridwidth: [1240, 1024, 778, 480],
                    gridheight: [600, 500, 400, 400],
                    lazyType: "none",
                    parallax: {
                        type: "scroll",
                        origo: "slidercenter",
                        speed: 2000,
                        levels: [2,3,4,5,6,7,12,16,10,50,47,48,49,50,51,55],
                    },
                    shadow: 0,
                    spinner: "off",
                    stopLoop: "on",
                    stopAfterLoops: 0,
                    stopAtSlide: 1,
                    shuffle: "off",
                    autoHeight: "off",
                    disableProgressBar: "on",
                    hideThumbsOnMobile: "off",
                    hideSliderAtLimit: 0,
                    hideCaptionAtLimit: 0,
                    hideAllCaptionAtLilmit: 0,
                    debugMode: false,
                    fallbacks: {
                        simplifyAll: "off",
                        nextSlideOnWindowFocus: "off",
                        disableFocusListener: false,
                    }
                });
            }
        });
    </script>
```

Delete it entirely. (After deletion, the `functions.js` script tag should be followed directly by the next `<script>` block, the one starting with `jQuery(function($){` that handles the sticky-menu-on-scroll behavior.)

- [ ] **Step 4: Lint, grep-verify, and check div-tag balance**

Run:

```bash
php -l index.php
grep -c "rev-slider-files\|rev_slider" index.php
grep -c "<div" index.php
grep -c "</div>" index.php
```

Expected: `No syntax errors detected in index.php`; the `rev-slider-files\|rev_slider` grep returns `0` (no remaining references); the `<div` and `</div>` counts match each other exactly (confirms no tag was accidentally left unclosed or double-closed during the markup replacement in Step 2).

- [ ] **Step 5: Commit**

```bash
git add index.php
git commit -m "Remove Revolution Slider markup and scripts from homepage hero"
```

---

### Task 2: Add the CSS that sizes, overlays, and animates the hero

**Files:**
- Modify: `css/custom.css:11-13` (the existing `.hero-wrap { position: relative; }` rule)

**Interfaces:**
- Consumes: the `hero-wrap` / `hero-bg` / `hero-overlay` class names produced by Task 1.
- Produces: a fully self-contained CSS block — no other task depends on anything from this one.

- [ ] **Step 1: Replace the `.hero-wrap` rule with the full sizing/overlay/animation block**

In `css/custom.css`, find (currently lines 8-13):

```css
/* Homepage hero text overlay - plain CSS positioning (not Revolution
   Slider layers), so it stays reliably centered at every screen size
   without per-breakpoint pixel tuning. */
.hero-wrap {
	position: relative;
}
```

Replace with:

```css
/* Homepage hero - plain CSS, replacing Revolution Slider entirely (see
   index.php and docs/superpowers/specs/2026-08-01-hero-revslider-
   replacement-design.md). Heights snap to Bootstrap's own breakpoints
   (576/768/992/1200px) rather than RevSlider's non-standard curve -
   values were hand-picked to look visually equivalent at each tier, not
   formula-matched to RevSlider's exact numbers. */
.hero-wrap {
	position: relative;
	overflow: hidden;
	height: 260px;
}
@media (min-width: 576px) {
	.hero-wrap {
		height: 340px;
	}
}
@media (min-width: 768px) {
	.hero-wrap {
		height: 420px;
	}
}
@media (min-width: 992px) {
	.hero-wrap {
		height: 520px;
	}
}
@media (min-width: 1200px) {
	.hero-wrap {
		height: 600px;
	}
}
.hero-bg {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center center;
	animation: hero-zoom 20s ease-in-out infinite alternate;
}
.hero-overlay {
	position: absolute;
	inset: 0;
	z-index: 10;
	background-color: rgba(0, 0, 0, 0.35);
}
@keyframes hero-zoom {
	from { transform: scale(1); }
	to   { transform: scale(1.08); }
}
@media (prefers-reduced-motion: reduce) {
	.hero-bg {
		animation: none;
	}
}
```

Leave every other rule in the file (`.hero-content`, `.hero-title`, `.hero-subtitle`, `a.btn_1.hero-cta`, the mobile `@media (max-width: 767px)` block for those, and everything below `.badge_tripadvisor`) exactly as-is — they already work and don't reference RevSlider.

- [ ] **Step 2: Confirm z-index stacking is still correct**

Run:

```bash
grep -n "hero-content {" -A 3 css/custom.css
```

Expected: the `.hero-content` rule still has `z-index: 20;` — this must stay higher than `.hero-overlay`'s `z-index: 10` so the text renders above the dark overlay. If it's not there, stop and re-check — do not proceed to Task 3 with broken stacking.

- [ ] **Step 3: Commit**

```bash
git add css/custom.css
git commit -m "Add pure-CSS sizing, overlay, and zoom animation for the homepage hero"
```

---

### Task 3: Local visual verification across breakpoints

**Files:**
- None modified — this task only runs a local server and captures/compares screenshots. If any comparison fails, fix the CSS from Task 2 in place (same file, same rules) before committing again.

**Interfaces:**
- Consumes: the working `index.php` + `css/custom.css` from Tasks 1-2.
- Produces: visual confirmation only — no new interfaces for later tasks.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/index.php
```

Expected: `200`.

- [ ] **Step 2: Capture screenshots at breakpoint boundaries AND mid-tier widths**

Use headless Chrome (`/Applications/Google Chrome.app/Contents/MacOS/Google Chrome --headless --disable-gpu --screenshot=... --window-size=W,900 --hide-scrollbars http://localhost:8899/index.php`) at these widths — boundaries and mid-tier both, since a mid-tier check is what would have caught the discontinuity bug from the earlier (reverted) attempt:

`375, 576, 650, 768, 880, 992, 1100, 1200, 1470, 1920`

- [ ] **Step 3: Compare each screenshot against the real RevSlider baseline**

For each width, confirm:
- The Valparaíso hillside photo is visible filling the hero (not blank/white/broken).
- A visible dark tint is present over the photo.
- "Discover Chile" + subtitle + "EXPLORE OUR TOURS" button are centered and fully legible.
- No sudden height jump compared to the adjacent width in the list above (e.g. the 650px screenshot's hero should look close in height to both 576px and 768px, not wildly different from either).

If earlier screenshots from this session are available for reference (they were captured to a job-specific tmp directory during the original RevSlider investigation), compare against those directly. If not available, judge against the criteria above — the RevSlider original always showed a fully visible, fully tinted, fully legible hero at every width, with a height that scaled up smoothly and gradually as width increased, never jumping.

- [ ] **Step 4: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 5: If any check in Step 3 failed, fix and re-verify**

Adjust the specific breakpoint height in `css/custom.css` (from Task 2) that's producing the bad result, then repeat Steps 1-4 for at least the widths adjacent to the one that failed. Do not proceed to Task 4 until every width in Step 2's list passes Step 3's checks.

- [ ] **Step 6: Commit (only if Step 5 required a fix)**

```bash
git add css/custom.css
git commit -m "Adjust hero breakpoint height after visual verification"
```

If no fix was needed, skip this step — there's nothing new to commit.

---

### Task 4: Deploy and confirm production

**Files:**
- None modified — this task pushes the already-committed changes and confirms the live site.

**Interfaces:**
- Consumes: the commits from Tasks 1-3.
- Produces: nothing further — this is the final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push
```

- [ ] **Step 2: Remind the user to deploy**

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to `git pull` on the cPanel server, same as every other change this session.

- [ ] **Step 3: Once deployed, re-run PageSpeed Insights as a sanity check (not a blocking gate)**

This is optional follow-up, not required for the plan to be considered complete — CrUX field data and a full Lighthouse run need real production traffic/state to be meaningful, and this plan's goal (remove RevSlider, keep the same look, no JS-driven resize) is already verified by Task 3. If run, compare against the last recorded baseline (mobile: 41/100, LCP 9.9s, CLS 0.42; desktop: 67/100, LCP 1.7s, CLS 0.422) and note any CLS improvement — full LCP improvement is expected to require the separate render-blocking-CSS work, not this change alone.
