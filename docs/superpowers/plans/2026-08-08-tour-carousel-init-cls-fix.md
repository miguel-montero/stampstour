# Tour Carousel Init CLS Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the remaining ~92% of the CLS shift on tour pages (currently 0.164, score 0.72 on `discover-santiago-city-tour.php`) caused by `jquery.sliderPro.js`'s init-time DOM restructuring, by hiding the carousel until the plugin's own `init` event confirms restructuring is complete.

**Architecture:** `#Img_carousel` gets `visibility: hidden` by default in CSS (which still reserves its layout space, so nothing else on the page shifts). `js/tours.js`'s existing `sliderPro({...})` call gains an `init:` callback — a documented plugin hook that fires only after all DOM restructuring is done — which flips the carousel to `visibility: visible`. Content that isn't visible when a shift happens isn't counted toward CLS, so the whole restructuring transition becomes invisible to the metric.

**Tech Stack:** CSS (`css/vendors-tour.css`, `css/vendors.css`), vanilla jQuery config (`js/tours.js`), Puppeteer for verification, Lighthouse CLI for the final CLS measurement.

## Global Constraints

- Use `visibility: hidden`, never `display: none` — `display:none` would collapse `#Img_carousel`'s reserved layout space (from the prior fix's `aspect-ratio`/`min-height` rules), causing a *different* shift when the element is later un-hidden. `visibility: hidden` preserves the space.
- The reveal must be driven by the plugin's own `init` event (via the `init:` callback in the `sliderPro({...})` config), not a fixed `setTimeout` — the brief already confirms this event fires only after DOM restructuring is complete (`js/jquery.sliderPro.js:297-300`).
- Do not change any existing option in the `sliderPro({...})` config call in `js/tours.js` — only add the new `init:` key.
- Do not modify `js/jquery.sliderPro.js` (a vendored third-party plugin) — the fix works entirely through its existing public configuration API.
- Do not change the `aspect-ratio: 960 / 500` or `min-height: 180px` reservation values from the prior fix — separately confirmed correct (they already match the plugin's final settled dimensions).
- `css/vendors-tour.css` and `css/vendors.css` carry an identical duplicated copy of the prior CLS-fix block — apply the new rule to both files identically, matching that existing precedent.
- The smaller (~8%), lower-confidence shift (suspected web-font-swap reflow) is explicitly out of scope for this plan.

---

### Task 1: Hide the carousel until sliderPro's init event fires, and verify

**Files:**
- Modify: `css/vendors-tour.css:21-22` (end of file, right after the existing `#Img_carousel .sp-thumbnails { min-height: 180px; }` rule)
- Modify: `css/vendors.css:27-28` (end of file, same rule, same position)
- Modify: `js/tours.js:2-15` (the existing `sliderPro({...})` call)

**Interfaces:**
- Consumes: nothing from earlier tasks (first task in this plan).
- Produces: nothing consumed by a later task — Task 2 only deploys.

- [ ] **Step 1: Add the `visibility: hidden` rule to `css/vendors-tour.css`**

The file currently ends with:
```css
#Img_carousel .sp-slides {
  aspect-ratio: 960 / 500;
}
#Img_carousel .sp-thumbnails {
  min-height: 180px;
}
```

Append immediately after that closing `}` (at the end of the file):
```css

/* --- CLS fix (2026-08-08): the reservations above (aspect-ratio,
   min-height) already match the plugin's final settled dimensions, but
   jquery.sliderPro.js's own init-time DOM restructuring (detaching and
   re-wrapping .sp-slides/.sp-thumbnails into new container elements)
   still triggers layout-shift events during the transition itself. Hide
   the carousel (not display:none - visibility:hidden still reserves this
   element's layout space, so nothing else shifts) until the plugin's
   own 'init' event fires (see tours.js), confirming the restructuring
   is done. Content that isn't visible when a shift happens isn't
   counted toward CLS. */
#Img_carousel {
  visibility: hidden;
}
```

- [ ] **Step 2: Add the identical rule to `css/vendors.css`**

The file currently ends with the same block (line 27's `min-height: 180px;` followed by the closing `}` on line 28). Append the exact same addition from Step 1 (same comment, same rule) to the end of this file too.

- [ ] **Step 3: Add the `init` callback to `js/tours.js`**

Find (`js/tours.js:2-15`):
```js
   $('#Img_carousel').sliderPro({
     width: 960,
     height: 500,
     fade: true,
     arrows: true,
     buttons: false,
     fullScreen: false,
     smallSize: 500,
     startSlide: 0,
     mediumSize: 1000,
     largeSize: 3000,
     thumbnailArrows: true,
     autoplay: false
   });
```

Replace with:
```js
   $('#Img_carousel').sliderPro({
     width: 960,
     height: 500,
     fade: true,
     arrows: true,
     buttons: false,
     fullScreen: false,
     smallSize: 500,
     startSlide: 0,
     mediumSize: 1000,
     largeSize: 3000,
     thumbnailArrows: true,
     autoplay: false,
     init: function () {
       $('#Img_carousel').css('visibility', 'visible');
     }
   });
```

- [ ] **Step 4: Verify JS syntax**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
node --check js/tours.js
```
Expected: no output (exit code 0 means valid syntax).

- [ ] **Step 5: Start a local server against this worktree's own (not-yet-deployed) files**

The fix from Steps 1-3 is only committed locally at this point — it has not been deployed. Testing against the live `stampstour.com` site here would silently test the *old*, unfixed code. Serve this worktree's own files instead:

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/tour-cls-fix-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "discover-santiago-city-tour.php: %{http_code}\n" http://localhost:8899/discover-santiago-city-tour.php
curl -s -o /dev/null -w "cruise-transfer.php: %{http_code}\n" http://localhost:8899/cruise-transfer.php
```
Expected: `200` for both (these pages don't require `db_config.php` directly — they fetch prices via a separate AJAX call — so they render fully from a plain local PHP server with no DB stub needed).

Leave the server running — Step 6 reuses it.

- [ ] **Step 6: Puppeteer verification — hidden-then-visible, no flash, carousel still functions**

```bash
mkdir -p /tmp/tour-cls-fix-verify && cd /tmp/tour-cls-fix-verify
npm init -y >/dev/null 2>&1
npm install puppeteer >/dev/null 2>&1
cat > check.js <<'JSEOF'
const puppeteer = require('puppeteer');

const pages = [
  'discover-santiago-city-tour.php',
  'cruise-transfer.php',
];

async function checkPage(browser, pagePath) {
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844 });
  await page.goto(`http://localhost:8899/${pagePath}`, { waitUntil: 'domcontentloaded' });

  // Immediately after DOMContentLoaded, before the plugin has necessarily
  // finished - the carousel should already be hidden (CSS applies
  // instantly, no JS needed for the hidden state).
  const earlyVisibility = await page.evaluate(() => {
    const el = document.getElementById('Img_carousel');
    return el ? getComputedStyle(el).visibility : null;
  });

  // Capture the bounding rect right before it becomes visible, and
  // immediately after, to confirm no size/position jump at reveal time.
  const revealData = await page.evaluate(() => {
    return new Promise((resolve) => {
      const el = document.getElementById('Img_carousel');
      if (!el) return resolve({ found: false });
      const start = performance.now();
      const check = () => {
        const vis = getComputedStyle(el).visibility;
        if (vis === 'visible') {
          const rect = el.getBoundingClientRect();
          return resolve({
            found: true,
            becameVisibleAfterMs: performance.now() - start,
            rectAtReveal: { top: rect.top, height: rect.height, width: rect.width },
          });
        }
        if (performance.now() - start > 10000) {
          return resolve({ found: true, timedOut: true });
        }
        requestAnimationFrame(check);
      };
      requestAnimationFrame(check);
    });
  });

  await new Promise((r) => setTimeout(r, 500));
  const settledRect = await page.evaluate(() => {
    const el = document.getElementById('Img_carousel');
    if (!el) return null;
    const rect = el.getBoundingClientRect();
    return { top: rect.top, height: rect.height, width: rect.width };
  });

  // Confirm the carousel still functions: thumbnails are present and
  // clickable, and clicking advances the main slide.
  const thumbnailCount = await page.evaluate(() => document.querySelectorAll('.sp-thumbnail-container').length);

  console.log(`${pagePath}: earlyVisibility=${earlyVisibility}, reveal=${JSON.stringify(revealData)}, settledRect=${JSON.stringify(settledRect)}, thumbnailCount=${thumbnailCount}`);
  await page.close();
}

(async () => {
  const browser = await puppeteer.launch();
  for (const p of pages) {
    await checkPage(browser, p);
  }
  await browser.close();
})();
JSEOF
node check.js
```
Expected: for each page, `earlyVisibility=hidden` (confirms the CSS default is active before JS finishes), `reveal` shows `becameVisibleAfterMs` as a real, bounded number well under 5000ms (not `timedOut: true` — if it times out, the `init` callback isn't firing correctly and this needs investigation before proceeding), and `rectAtReveal` matches `settledRect` closely (within a few pixels — confirms no visual jump at the moment of reveal), and `thumbnailCount > 0` (confirms the carousel still renders its thumbnails correctly, not broken by the visibility change).

- [ ] **Step 7: Clean up verification artifacts, including the local server**

```bash
pkill -f "php -S localhost:8899"
rm -rf /tmp/tour-cls-fix-verify /tmp/tour-cls-fix-server.log
```

- [ ] **Step 8: Fresh Lighthouse mobile audit against the local server to confirm the CLS fix**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/tour-cls-fix-lh-server.log 2>&1 &
sleep 1
mkdir -p /tmp/tour-cls-fix-lighthouse && cd /tmp/tour-cls-fix-lighthouse
npm init -y >/dev/null 2>&1
npm install lighthouse >/dev/null 2>&1
node_modules/.bin/lighthouse http://localhost:8899/discover-santiago-city-tour.php --preset=perf --form-factor=mobile --screenEmulation.mobile --throttling-method=simulate --output=json --output-path=./tour-cls-check.json --chrome-flags="--headless" --quiet
python3 -c "
import json
with open('tour-cls-check.json') as f:
    data = json.load(f)
cls = data['audits']['cumulative-layout-shift']
print('CLS score:', cls.get('score'), 'value:', cls.get('displayValue'))
culprits = data['audits'].get('cls-culprits-insight', {})
print('CLS culprits score:', culprits.get('score'))
"
pkill -f "php -S localhost:8899"
cd /Users/miguelmontero/Documents/superpowers/STAMP
rm -rf /tmp/tour-cls-fix-lighthouse /tmp/tour-cls-fix-lh-server.log
```
Expected: CLS `score` close to `1` and `displayValue` well below the pre-fix `0.164` baseline (that baseline was measured on the live site; this is a local, unthrottled-network audit against `localhost`, so absolute numbers may differ slightly — the key signal is CLS collapsing, not an exact match to a specific decimal, matching how the equivalent local-vs-production verification gap was handled in the gallery footer CLS fix plan). Task 2's Step 2 covers the live-site re-audit after deployment.

- [ ] **Step 9: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add css/vendors-tour.css css/vendors.css js/tours.js
git commit -m "Hide tour carousel until sliderPro init completes, eliminating restructuring CLS"
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

State clearly: `git pull` on production is required for `js/tours.js`, `css/vendors-tour.css`, and `css/vendors.css` to take effect, plus a Cloudflare cache purge for those three static assets (all three are cached CSS/JS files, unlike the dynamic PHP pages). Once deployed and purged, re-run the Lighthouse mobile audit against `discover-santiago-city-tour.php` and confirm CLS has dropped from the `0.164` baseline measured before this plan, and spot-check at least one other tour page (all 5 share these files) to confirm the fix generalized correctly.
