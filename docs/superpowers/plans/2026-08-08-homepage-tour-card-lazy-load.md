# Homepage Tour-Card Lazy-Load Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop the homepage's 5 tour-card thumbnails from downloading within milliseconds of the hero image by replacing native `loading="lazy"` (whose prefetch distance grows on slow connections — backwards for this case) with a small, fixed-margin `IntersectionObserver`.

**Architecture:** Move each card's real image URLs into `data-*` attributes so nothing fetches until JS decides to; a single shared `IntersectionObserver` (`rootMargin: '200px'`) in `js/functions.js` swaps them into real `src`/`srcset` attributes once a card nears the viewport. A `<noscript><style>` block preserves the original native-lazy behavior for JS-disabled visitors.

**Tech Stack:** PHP (markup only, no server-side logic changes), vanilla JavaScript (`IntersectionObserver`), no new dependencies.

## Global Constraints

- Scope is exactly the 5 homepage tour-card images (Valparaíso, Maipo, Andes, Discover Santiago, Cruise Transfer) in `index.php`. Do not touch the two `tripadvisor` badge `<picture>` elements inside each card, the hero image, or any other page.
- Every existing `width`/`height`/`class`/`alt` value on each card `<img>` must be preserved exactly. `sizes` on each `<source>` must be preserved exactly.
- `index.php` uses LF line endings — preserve that.
- `<noscript>` must never be nested inside a `<picture>` element (invalid per the HTML content model — only `<source>`, one `<img>`, and script-supporting elements are allowed as children of `<picture>`). Use the wrapper-div + `<noscript><style>` pattern specified in Task 1, not a naive `<picture><noscript>...</noscript></picture>` nesting.
- The `rootMargin` value is `'200px'` — an explicit, deliberate choice from the spec (balances avoiding a visible pop-in flash against genuinely clearing the hero's critical download window), not a placeholder to tune later.

---

### Task 1: Convert the 5 tour-card `<picture>` blocks to lazy-load markup

**Files:**
- Modify: `index.php` (5 separate blocks, each ~10 lines, non-contiguous — search for each by its distinctive `href` on the surrounding `<a>` tag)

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: 5 `<picture class="lazy-tour-card">` elements with `data-src`/`data-srcset` (no live `src`/`srcset` yet) — Task 2's `IntersectionObserver` selects elements by this exact class name and reads `data-src`/`data-srcset` by these exact attribute names. Also produces 5 `<noscript>` fallback blocks using `.lazy-tour-card-wrap` as the scoping class for the hide-rule — must match Task 2's selector exactly (`.lazy-tour-card`, not e.g. `.lazy-card` or `.tour-card-lazy`).

- [ ] **Step 1: Convert the Valparaíso card**

Current (`index.php`, inside the `<a href="valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php">` block):

```html
                                <picture>
                                    <source srcset="img/Tours/Valpo/portada-mobile.webp 600w, img/Tours/Valpo/portada.webp 955w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
                                    <img src="img/Tours/Valpo/portada.jpeg" width="800" height="533" class="img-fluid" alt="Valparaíso tour" loading="lazy">
                                </picture>
```

Change to:

```html
                                <div class="lazy-tour-card-wrap">
                                <picture class="lazy-tour-card">
                                    <source data-srcset="img/Tours/Valpo/portada-mobile.webp 600w, img/Tours/Valpo/portada.webp 955w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
                                    <img data-src="img/Tours/Valpo/portada.jpeg" width="800" height="533" class="img-fluid" alt="Valparaíso tour">
                                </picture>
                                <noscript>
                                    <style>.lazy-tour-card-wrap .lazy-tour-card { display: none; }</style>
                                    <picture>
                                        <source srcset="img/Tours/Valpo/portada-mobile.webp 600w, img/Tours/Valpo/portada.webp 955w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
                                        <img src="img/Tours/Valpo/portada.jpeg" width="800" height="533" class="img-fluid" alt="Valparaíso tour" loading="lazy">
                                    </picture>
                                </noscript>
                                </div>
```

- [ ] **Step 2: Convert the Maipo card**

Current (inside `<a href="maipo-valley-wine-tour-santiago.php">`):

```html
                                <picture>
                                    <source srcset="img/Tours/Maipo/portada-mobile.webp 600w, img/Tours/Maipo/portada.webp 720w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
                                    <img src="img/Tours/Maipo/portada.jpg" width="800" height="533" class="img-fluid" alt="Maipo Wine Tour" loading="lazy">
                                </picture>
```

Change to:

```html
                                <div class="lazy-tour-card-wrap">
                                <picture class="lazy-tour-card">
                                    <source data-srcset="img/Tours/Maipo/portada-mobile.webp 600w, img/Tours/Maipo/portada.webp 720w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
                                    <img data-src="img/Tours/Maipo/portada.jpg" width="800" height="533" class="img-fluid" alt="Maipo Wine Tour">
                                </picture>
                                <noscript>
                                    <style>.lazy-tour-card-wrap .lazy-tour-card { display: none; }</style>
                                    <picture>
                                        <source srcset="img/Tours/Maipo/portada-mobile.webp 600w, img/Tours/Maipo/portada.webp 720w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
                                        <img src="img/Tours/Maipo/portada.jpg" width="800" height="533" class="img-fluid" alt="Maipo Wine Tour" loading="lazy">
                                    </picture>
                                </noscript>
                                </div>
```

- [ ] **Step 3: Convert the Andes card**

Current (inside `<a href="portillo-inca-lagoon-andes-mountains-vineyard.php">`):

```html
                                <picture>
                                    <source srcset="img/Tours/Andes/portada-mobile.webp 600w, img/Tours/Andes/portada-medium.webp 1100w, img/Tours/Andes/portada.webp 1400w" sizes="(max-width: 767px) 600px, 60vw" type="image/webp">
                                    <img src="img/Tours/Andes/portada.jpg" width="800" height="533" class="img-fluid" alt="Andes tour" loading="lazy">
                                </picture>
```

Change to:

```html
                                <div class="lazy-tour-card-wrap">
                                <picture class="lazy-tour-card">
                                    <source data-srcset="img/Tours/Andes/portada-mobile.webp 600w, img/Tours/Andes/portada-medium.webp 1100w, img/Tours/Andes/portada.webp 1400w" sizes="(max-width: 767px) 600px, 60vw" type="image/webp">
                                    <img data-src="img/Tours/Andes/portada.jpg" width="800" height="533" class="img-fluid" alt="Andes tour">
                                </picture>
                                <noscript>
                                    <style>.lazy-tour-card-wrap .lazy-tour-card { display: none; }</style>
                                    <picture>
                                        <source srcset="img/Tours/Andes/portada-mobile.webp 600w, img/Tours/Andes/portada-medium.webp 1100w, img/Tours/Andes/portada.webp 1400w" sizes="(max-width: 767px) 600px, 60vw" type="image/webp">
                                        <img src="img/Tours/Andes/portada.jpg" width="800" height="533" class="img-fluid" alt="Andes tour" loading="lazy">
                                    </picture>
                                </noscript>
                                </div>
```

- [ ] **Step 4: Convert the Discover Santiago card**

Current (inside `<a href="discover-santiago-city-tour.php">`):

```html
                                <picture>
                                    <source srcset="img/Tours/Stgo/portada-mobile.webp 600w, img/Tours/Stgo/portada-medium.webp 1100w, img/Tours/Stgo/portada.webp 1440w" sizes="(max-width: 767px) 600px, 60vw" type="image/webp">
                                    <img src="img/Tours/Stgo/portada.jpg" width="800" height="533" class="img-fluid" alt="Santiago City Tour" loading="lazy">
                                </picture>
```

Change to:

```html
                                <div class="lazy-tour-card-wrap">
                                <picture class="lazy-tour-card">
                                    <source data-srcset="img/Tours/Stgo/portada-mobile.webp 600w, img/Tours/Stgo/portada-medium.webp 1100w, img/Tours/Stgo/portada.webp 1440w" sizes="(max-width: 767px) 600px, 60vw" type="image/webp">
                                    <img data-src="img/Tours/Stgo/portada.jpg" width="800" height="533" class="img-fluid" alt="Santiago City Tour">
                                </picture>
                                <noscript>
                                    <style>.lazy-tour-card-wrap .lazy-tour-card { display: none; }</style>
                                    <picture>
                                        <source srcset="img/Tours/Stgo/portada-mobile.webp 600w, img/Tours/Stgo/portada-medium.webp 1100w, img/Tours/Stgo/portada.webp 1440w" sizes="(max-width: 767px) 600px, 60vw" type="image/webp">
                                        <img src="img/Tours/Stgo/portada.jpg" width="800" height="533" class="img-fluid" alt="Santiago City Tour" loading="lazy">
                                    </picture>
                                </noscript>
                                </div>
```

- [ ] **Step 5: Convert the Cruise Transfer card**

Current (inside `<a href="cruise-transfer.php">`):

```html
                                <picture>
                                    <source srcset="img/Tours/Cruise/portada-mobile.webp 600w, img/Tours/Cruise/portada.webp 900w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
                                    <img src="img/Tours/Cruise/portada.jpeg" width="800" height="533" class="img-fluid" alt="Cruise transfer with Valparaíso tour" loading="lazy">
                                </picture>
```

Change to:

```html
                                <div class="lazy-tour-card-wrap">
                                <picture class="lazy-tour-card">
                                    <source data-srcset="img/Tours/Cruise/portada-mobile.webp 600w, img/Tours/Cruise/portada.webp 900w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
                                    <img data-src="img/Tours/Cruise/portada.jpeg" width="800" height="533" class="img-fluid" alt="Cruise transfer with Valparaíso tour">
                                </picture>
                                <noscript>
                                    <style>.lazy-tour-card-wrap .lazy-tour-card { display: none; }</style>
                                    <picture>
                                        <source srcset="img/Tours/Cruise/portada-mobile.webp 600w, img/Tours/Cruise/portada.webp 900w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
                                        <img src="img/Tours/Cruise/portada.jpeg" width="800" height="533" class="img-fluid" alt="Cruise transfer with Valparaíso tour" loading="lazy">
                                    </picture>
                                </noscript>
                                </div>
```

- [ ] **Step 6: Verify all 5 conversions locally**

```bash
php -S localhost:8765 -t /Users/miguelmontero/Documents/superpowers/STAMP &
sleep 1
curl -s http://localhost:8765/index.php | grep -c 'class="lazy-tour-card"'
curl -s http://localhost:8765/index.php | grep -c 'lazy-tour-card-wrap .lazy-tour-card { display: none; }'
curl -s http://localhost:8765/index.php | grep -c 'data-src='
kill %1
```

Expected: first count = 5 (one `.lazy-tour-card` picture per card), second count = 5 (one noscript style block per card), third count = 10 (one `data-src` per `<img>` + confirm `data-srcset` also present — adjust grep as needed to check both attributes across all 5 cards). Also visually confirm in the raw HTML that no `<picture>` contains a nested `<noscript>` (grep for `<noscript>` occurring between a `<picture>` and its matching `</picture>` — should find none; the `<noscript>` blocks are all after each `<picture>`'s closing tag).

- [ ] **Step 7: Commit**

```bash
git add index.php
git commit -m "feat: convert homepage tour cards to JS-driven lazy load markup"
```

---

### Task 2: Add the IntersectionObserver to `js/functions.js`

**Files:**
- Modify: `js/functions.js` (append near the end, inside the existing IIFE)

**Interfaces:**
- Consumes: `.lazy-tour-card` class and `data-src`/`data-srcset` attributes produced by Task 1.
- Produces: nothing consumed by later tasks — this is the last code change; Task 3 verifies the two together.

- [ ] **Step 1: Locate the insertion point**

`js/functions.js` ends with:

```javascript
// Header background
	$('.background-image').each(function(){
		$(this).css('background-image', $(this).attr('data-background'));
	});

})(window.jQuery);
```

- [ ] **Step 2: Insert the observer before the closing `})(window.jQuery);`**

```javascript
// Header background
	$('.background-image').each(function(){
		$(this).css('background-image', $(this).attr('data-background'));
	});

/* Lazy-load homepage tour cards with a fixed, connection-independent margin.
   Native loading="lazy" was replaced here because Chrome's adaptive prefetch
   distance grows on slow connections, which was causing these cards to fetch
   within ~24ms of the hero image and contend for bandwidth during the LCP
   window. See docs/superpowers/specs/2026-08-08-homepage-tour-card-lazy-load-design.md */
if ('IntersectionObserver' in window) {
    var lazyCards = document.querySelectorAll('.lazy-tour-card');
    if (lazyCards.length) {
        var lazyObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var picture = entry.target;
                var source = picture.querySelector('source[data-srcset]');
                if (source) {
                    source.setAttribute('srcset', source.getAttribute('data-srcset'));
                    source.removeAttribute('data-srcset');
                }
                var img = picture.querySelector('img[data-src]');
                if (img) {
                    img.setAttribute('src', img.getAttribute('data-src'));
                    img.removeAttribute('data-src');
                }
                observer.unobserve(picture);
            });
        }, { rootMargin: '200px' });
        lazyCards.forEach(function (picture) { lazyObserver.observe(picture); });
    }
}

})(window.jQuery);
```

- [ ] **Step 3: Verify no syntax errors**

```bash
node --check /Users/miguelmontero/Documents/superpowers/STAMP/js/functions.js
```

Expected: no output (exit code 0). `node --check` parses the file without executing it, so jQuery/DOM not being available doesn't matter.

- [ ] **Step 4: Commit**

```bash
git add js/functions.js
git commit -m "feat: add IntersectionObserver for homepage lazy tour cards"
```

---

### Task 3: Local functional, visual, and no-JS verification

**Files:** none modified — verification only, unless a real defect is found, in which case fix it in the relevant file from Task 1 or 2 before marking this task done.

**Interfaces:**
- Consumes: Task 1's markup and Task 2's observer together.

- [ ] **Step 1: DOM-state check before/after scroll**

Using a headless Puppeteer session (available at `/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer` in this environment — `require()` that absolute path) against a local `php -S` server:
- Load `index.php`, confirm all 5 `.lazy-tour-card` `<img>` elements have a `data-src` attribute and no `src` attribute immediately after load (before any scroll).
- Scroll to bring each card into view (or scroll to the bottom of the tours section in one motion), wait briefly, then confirm each `<img>` now has a real `src` (matching its original `data-src` value) and no `data-src` attribute remains, and that the image actually rendered (non-zero `naturalWidth`).
- Confirm zero console errors throughout.

- [ ] **Step 2: No-JS fallback check**

Using Puppeteer with JavaScript disabled (`page.setJavaScriptEnabled(false)`), reload `index.php`, and for each of the 5 cards confirm: (a) the noscript fallback `<img>` is present with a real `src` and renders (non-zero `naturalWidth`), and (b) the primary `.lazy-tour-card` picture is actually hidden — check `getComputedStyle(element).display === 'none'` on the `.lazy-tour-card` element, not just that the fallback shows. This directly tests the fix for the `<noscript>`-in-`<picture>` bug caught during the spec's self-review.

- [ ] **Step 3: Visual check**

Screenshot the tours section before and after scroll-triggered loading; confirm no broken-image icons, no unexpected blank gaps, and that each card's image matches its expected tour (Valparaíso/Maipo/Andes/Santiago/Cruise) by comparing against the `alt` text.

- [ ] **Step 4: Record findings**

If Steps 1-3 all pass, proceed to Task 4. If any check fails, fix the specific defect (in `index.php` from Task 1 or `js/functions.js` from Task 2) and re-run the failing check before proceeding.

---

### Task 4: Deploy and confirm production

**Files:** none modified — deployment/verification only.

**Interfaces:**
- Consumes: all commits from Tasks 1-3.

- [ ] **Step 1: Push to `main`**

```bash
git push origin main
```

- [ ] **Step 2: Ask the user to pull on the HostGator production server**

This site deploys via manual `git pull` on the production server — ask the user to pull, then confirm once done.

- [ ] **Step 3: Confirm production — request-timing verification (the actual regression test)**

Once the user confirms the pull, using the same CDP methodology as the hero-image investigation (390×844 mobile viewport, throttled to 1.6Mbps down / 150ms latency, `Network.requestWillBeSent` timestamps) against the live homepage (`https://stampstour.com/`): confirm the 5 tour-card image requests now start meaningfully later than the hero image request — not within ~24ms as measured before this fix. Report the actual gap observed.

- [ ] **Step 4: Confirm production — LCP re-measurement**

Using the same throttled-mobile LCP measurement methodology used throughout this session, measure the homepage's LCP in production and compare against the 5,580ms baseline captured before this fix. Report the actual number — per this session's established practice (the stylesheet-priority fix earlier was mechanically correct yet produced no LCP improvement), a real, verified measurement is required here, not an assumption that the request-timing fix from Step 3 automatically translates to a proportional LCP improvement.
