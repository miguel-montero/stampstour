# Preloader Timing Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the sitewide preloader overlay disappear as soon as the DOM is ready, not after the full `window.load` event — unlocking the real-world benefit of the homepage critical-CSS work, which currently paints correctly underneath an overlay that doesn't lift until everything (including the deferred stylesheets) finishes loading.

**Architecture:** One-line trigger change in a single shared JS file (`js/functions.js`), verified with network-throttled headless Chrome (via Puppeteer, already available in this environment) so the check can actually fail if the fix doesn't work — unlike `php -S`'s instant localhost timing, which would pass this check vacuously regardless of correctness.

**Tech Stack:** jQuery (already loaded sitewide), Node.js + Puppeteer for verification only (not part of the deployed site).

## Global Constraints

- The change must be sitewide (`js/functions.js` is shared by every page template) — no per-page gating needed, per the design spec's safety analysis.
- Only the trigger event changes (`window.load` → DOM-ready). The handler body (`$('#status').fadeOut()`, `$('#preloader').delay(350).fadeOut('slow')`, the `body` overflow reset, `$(window).scroll()`) must be byte-identical to what it is today.
- `js/pop_up_func.js` (promo popup) and the Owl Carousel `autoHeight` handler in `js/common_scripts.js` are explicitly out of scope — do not touch either file.

---

### Task 1: Change the preloader trigger from `window.load` to DOM-ready

**Files:**
- Modify: `js/functions.js:5-13`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: nothing new for later tasks — Task 2 verifies this exact change, Task 3 deploys it.

- [ ] **Step 1: Make the change**

Find (currently lines 5-13):

```js
/* Preload */
$(window).on('load', function () { // makes sure the whole site is loaded
	$('#status').fadeOut(); // will first fade out the loading animation
	$('#preloader').delay(350).fadeOut('slow'); // will fade out the white DIV that covers the website.
	$('body').delay(350).css({
		'overflow': 'visible'
	});
	$(window).scroll();
})
```

Replace with:

```js
/* Preload */
$(function () { // fires once the DOM is ready - on pages with blocking CSS
                 // (everywhere except the homepage) that already implies the
                 // page is fully styled; on the homepage the inlined critical
                 // CSS covers everything visible without scrolling, so this
                 // is also correct there. See
                 // docs/superpowers/specs/2026-08-01-preloader-timing-fix-design.md
                 // for the full analysis - this used to wait for window.load
                 // (every subresource including images and, since a related
                 // change, deferred stylesheets), which meant this overlay
                 // masked any benefit from making those stylesheets non-blocking.
	$('#status').fadeOut(); // will first fade out the loading animation
	$('#preloader').delay(350).fadeOut('slow'); // will fade out the white DIV that covers the website.
	$('body').delay(350).css({
		'overflow': 'visible'
	});
	$(window).scroll();
})
```

Only the trigger (`$(window).on('load', function () {...})` → `$(function () {...})`) and the explanatory comment change. The function body is untouched.

- [ ] **Step 2: Sanity-check the file is still valid JS**

```bash
node --check js/functions.js
```

Expected: no output (no syntax errors). `node --check` parses without executing, safe to run against a jQuery plugin file that expects a browser environment.

- [ ] **Step 3: Commit**

```bash
git add js/functions.js
git commit -m "Reveal the page on DOM-ready instead of waiting for full window.load"
```

---

### Task 2: Verify with network-throttled headless Chrome

**Files:**
- None modified — this task only verifies. If the check fails, fix `js/functions.js` from Task 1 in place, then re-verify.

**Interfaces:**
- Consumes: the change from Task 1.
- Produces: verification evidence only.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/index.php
```

Expected: `200`.

- [ ] **Step 2: Set up Puppeteer in a scratch directory**

```bash
mkdir -p /tmp/preloader-verify
cd /tmp/preloader-verify
npm init -y > /dev/null 2>&1
npm install --no-save puppeteer 2>&1 | tail -5
```

This is a throwaway local dependency for verification only — nothing here is committed to the repo.

- [ ] **Step 3: Write and run the throttled timing check**

Create `/tmp/preloader-verify/check.mjs` with exactly this content:

```js
import puppeteer from 'puppeteer';

async function checkPage(url) {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  const client = await page.target().createCDPSession();
  await client.send('Network.enable');
  // Throttle enough that window.load meaningfully lags DOM-ready: ~200kbps,
  // 200ms latency. Real slow-3G-ish conditions, not localhost-instant.
  await client.send('Network.emulateNetworkConditions', {
    offline: false,
    latency: 200,
    downloadThroughput: (200 * 1024) / 8,
    uploadThroughput: (200 * 1024) / 8,
  });

  const start = Date.now();
  let windowLoadTime = null;
  let preloaderGoneTime = null;

  page.on('load', () => {
    windowLoadTime = Date.now() - start;
  });

  const pollTimer = setInterval(async () => {
    try {
      const opacity = await page.$eval('#preloader', el => getComputedStyle(el).opacity).catch(() => null);
      if (preloaderGoneTime === null && (opacity === null || parseFloat(opacity) < 0.9)) {
        preloaderGoneTime = Date.now() - start;
      }
    } catch (e) { /* page may not be ready yet, ignore */ }
  }, 50);

  await page.goto(url, { waitUntil: 'load', timeout: 60000 });
  clearInterval(pollTimer);
  await browser.close();

  console.log(`\n=== ${url} ===`);
  console.log('preloader started fading at (ms):', preloaderGoneTime);
  console.log('window.load fired at (ms):', windowLoadTime);
  if (preloaderGoneTime !== null && windowLoadTime !== null && preloaderGoneTime < windowLoadTime) {
    console.log('PASS: preloader disappeared before window.load');
    return true;
  }
  console.log('FAIL: preloader did not disappear before window.load (or #preloader not found)');
  return false;
}

const homeOk = await checkPage('http://localhost:8899/index.php');
const tourOk = await checkPage('http://localhost:8899/maipo-valley-wine-tour-santiago.php');

process.exit(homeOk && tourOk ? 0 : 1);
```

Run it:

```bash
cd /tmp/preloader-verify
node check.mjs
echo "exit code: $?"
```

Expected: `PASS` for both URLs, exit code `0`. The `preloader started fading at (ms)` value should be meaningfully smaller than `window.load fired at (ms)` for both — under real (unthrottled) conditions this gap would be much smaller, but the throttling in this script is specifically what makes the gap visible enough to assert on reliably.

If either page reports FAIL: re-check that Task 1's change was applied correctly (the trigger really is DOM-ready, not still `window.load`) before assuming anything else is wrong.

- [ ] **Step 4: Visual confirmation**

Take a headless-Chrome screenshot of both URLs at a normal width (e.g. 1000px) after Task 1's change, using the same throttled network conditions if convenient, or a plain screenshot otherwise — confirm the homepage shows the styled header/hero (not blank, not unstyled) and the tour page also renders cleanly, with no visible glitch from the trigger change.

- [ ] **Step 5: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
rm -rf /tmp/preloader-verify
```

- [ ] **Step 6: If Step 3 failed, fix and re-verify**

Fix `js/functions.js` (from Task 1) and repeat Steps 1-5. Do not proceed to Task 3 until Step 3 passes for both URLs.

- [ ] **Step 7: Commit (only if Step 6 required a fix)**

```bash
git add js/functions.js
git commit -m "Fix preloader trigger after verification failure"
```

If no fix was needed, skip this step.

---

### Task 3: Deploy and confirm production

**Files:**
- None modified — this task pushes already-committed changes and confirms the live site.

**Interfaces:**
- Consumes: the commit(s) from Tasks 1-2.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push
```

- [ ] **Step 2: Remind the user to deploy**

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to `git pull` on the cPanel server to see this live.

- [ ] **Step 3: Once deployed, re-run PageSpeed Insights (mobile + desktop) against production**

This is the step that should finally show a real improvement beyond what the critical-CSS work alone delivered on its own — compare mobile/desktop Lighthouse score, FCP, and LCP against the most recent recorded baseline. Not a blocking gate for this plan, but the actual measure of whether the combined critical-CSS + preloader-timing work paid off.
