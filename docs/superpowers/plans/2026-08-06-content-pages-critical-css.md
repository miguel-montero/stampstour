# Content Pages Critical CSS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate render-blocking CSS on `gallery.php`, `refunds-cancellations.php`, `contact-us.php`, `privacy.php`, and `blog.php` by extending the existing `$critical_css_file` mechanism (already live on the homepage and 5 tour pages) to these 5 pages, closing a gap the tour-pages spec explicitly named as future follow-up.

**Architecture:** `includes/head.php` already contains a complete, generic mechanism: when a page sets `$critical_css_file` before including it, the referenced CSS file's contents are inlined in a `<style>` block and the shared stylesheets switch from blocking `<link>` tags to a non-blocking preload/onload-swap pattern automatically — no changes to `includes/head.php` itself are needed. This plan generates one new critical-CSS file (via the `critical` npm package, run against real local renders of all 5 pages) and adds two lines to each page's existing top-of-file PHP block: `$critical_css_file` (the new mechanism) and `$lcp_preload_image` (an already-generic, already-proven companion mechanism that prevents a known priority-inversion bug between the hero image and the now-async-preloaded stylesheets — previously fixed on the tour pages under the same circumstances).

**Tech Stack:** PHP (5 pages + the untouched, already-generic `includes/head.php`), the `critical` npm package (scratch, local-only tool — not a project dependency), Puppeteer for verification (matching this project's established pattern).

## Global Constraints

- New file: `includes/critical/content.css` — the merged critical CSS, generated once from real renders of all 5 pages at 390×844 (mobile) and 1470×900 (desktop), matching the exact viewports already used for `includes/critical/home.css` and `includes/critical/tour.css`.
- Each of the 5 pages gets exactly two new lines, added immediately after that page's existing `$page_canonical = '...';` line, before the closing `?>` of that PHP block:
  ```php
  $critical_css_file = __DIR__ . '/includes/critical/content.css';
  $lcp_preload_image = 'img/Tours/Stgo/big.webp';
  ```
  Exact current insertion points (verified directly):
  - `gallery.php:33` (after `$page_canonical`, before the `?>` on line 34)
  - `refunds-cancellations.php:28` (before the `?>` on line 29)
  - `contact-us.php:4` (before the `?>` on line 5)
  - `privacy.php:28` (before the `?>` on line 29)
  - `blog.php:19` (before the `?>` on line 20)
- No changes to `includes/head.php` — both `$critical_css_file` and `$lcp_preload_image` are already fully generic there; this plan only sets them on 5 new pages.
- `shopping.php` and `return.php` are explicitly out of scope for this plan — do not modify them.
- The known risk from prior rounds: the `critical` extraction tool has previously (on the homepage) silently stripped icon-font glyph escapes, causing icons to disappear on first paint. Every verification step involving a screenshot must explicitly check icon rendering, not just layout.

---

### Task 1: Generate the merged critical CSS file

**Files:**
- Create: `includes/critical/content.css`

**Interfaces:**
- Consumes: real local renders of `gallery.php`, `refunds-cancellations.php`, `contact-us.php`, `privacy.php`, `blog.php` via a local PHP server.
- Produces: `includes/critical/content.css`, consumed by Task 2's `$critical_css_file` wiring on all 5 pages.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/critical-gen-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "gallery.php: %{http_code}\n" http://localhost:8899/gallery.php
curl -s -o /dev/null -w "refunds-cancellations.php: %{http_code}\n" http://localhost:8899/refunds-cancellations.php
curl -s -o /dev/null -w "contact-us.php: %{http_code}\n" http://localhost:8899/contact-us.php
curl -s -o /dev/null -w "privacy.php: %{http_code}\n" http://localhost:8899/privacy.php
curl -s -o /dev/null -w "blog.php: %{http_code}\n" http://localhost:8899/blog.php
```
Expected: `200` for all 5 (confirms the local server can render every page before attempting extraction against it).

- [ ] **Step 2: Set up the `critical` package in a scratch directory**

```bash
mkdir -p /tmp/content-critical-gen && cd /tmp/content-critical-gen
npm init -y >/dev/null 2>&1
npm install critical >/dev/null 2>&1
```

- [ ] **Step 3: Write and run the extraction script**

Create `/tmp/content-critical-gen/generate.js`:
```js
const critical = require('critical');
const fs = require('node:fs');

const pages = [
  'gallery.php',
  'refunds-cancellations.php',
  'contact-us.php',
  'privacy.php',
  'blog.php',
];
const dimensions = [
  { width: 390, height: 844 },
  { width: 1470, height: 900 },
];

async function run() {
  let combined = '';
  for (const page of pages) {
    for (const dim of dimensions) {
      console.log(`Extracting ${page} at ${dim.width}x${dim.height}...`);
      const { css } = await critical.generate({
        src: `http://localhost:8899/${page}`,
        width: dim.width,
        height: dim.height,
        inline: false,
        extract: false,
      });
      combined += `/* ${page} @ ${dim.width}x${dim.height} */\n${css}\n`;
    }
  }
  fs.writeFileSync('/tmp/content-critical-gen/content.css', combined);
  console.log('Done. Combined size:', combined.length, 'bytes');
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
```

```bash
cd /tmp/content-critical-gen
node generate.js
```
Expected: 10 "Extracting..." lines (5 pages × 2 dimensions), ending with `Done. Combined size: <N> bytes` where N is a real, non-trivial number (tens of thousands of bytes, comparable to `home.css`'s 20759 bytes and `tour.css`'s 27015 bytes — if it's only a few hundred bytes, something failed silently and needs investigation before proceeding).

- [ ] **Step 4: Sanity-check the output before committing it**

```bash
head -c 500 /tmp/content-critical-gen/content.css
grep -c "hero-bg-img\|opacity-mask\|intro_title" /tmp/content-critical-gen/content.css
grep -c "icon-" /tmp/content-critical-gen/content.css
```
Expected: real CSS content (not an error message or empty output); at least one match for the hero-related selectors (confirms the hero section — which every one of these 5 pages shares — was actually captured); at least one match for `icon-` (confirms icon-related rules survived extraction — a zero here would be an early warning sign of the known glyph-stripping issue, worth investigating now rather than waiting for the visual check in Task 2).

- [ ] **Step 5: Copy the result into the repo and stop the server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
mkdir -p includes/critical
cp /tmp/content-critical-gen/content.css includes/critical/content.css
wc -c includes/critical/content.css
pkill -f "php -S localhost:8899"
rm -rf /tmp/content-critical-gen
```
Expected: the file exists at `includes/critical/content.css` with the same byte count printed in Step 3.

- [ ] **Step 6: Commit**

```bash
git add includes/critical/content.css
git commit -m "Add merged critical CSS for the 5 content pages"
```

---

### Task 2: Wire up all 5 pages and verify

**Files:**
- Modify: `gallery.php:33-34`
- Modify: `refunds-cancellations.php:28-29`
- Modify: `contact-us.php:4-5`
- Modify: `privacy.php:28-29`
- Modify: `blog.php:19-20`

**Interfaces:**
- Consumes: `includes/critical/content.css` from Task 1; the already-existing, unmodified `$critical_css_file`/`$lcp_preload_image` handling in `includes/head.php`.
- Produces: nothing consumed by a later task — this is the last content-editing task before deploy.

- [ ] **Step 1: `gallery.php`**

Find:
```php
$page_canonical   = 'https://stampstour.com/gallery.php';
?>
```
Replace with:
```php
$page_canonical   = 'https://stampstour.com/gallery.php';
$critical_css_file = __DIR__ . '/includes/critical/content.css';
$lcp_preload_image = 'img/Tours/Stgo/big.webp';
?>
```

- [ ] **Step 2: `refunds-cancellations.php`**

Find:
```php
$page_canonical   = 'https://stampstour.com/refunds-cancellations.php';
?>
```
Replace with:
```php
$page_canonical   = 'https://stampstour.com/refunds-cancellations.php';
$critical_css_file = __DIR__ . '/includes/critical/content.css';
$lcp_preload_image = 'img/Tours/Stgo/big.webp';
?>
```

- [ ] **Step 3: `contact-us.php`**

Find:
```php
$page_canonical   = 'https://stampstour.com/contact-us.php';
?>
```
Replace with:
```php
$page_canonical   = 'https://stampstour.com/contact-us.php';
$critical_css_file = __DIR__ . '/includes/critical/content.css';
$lcp_preload_image = 'img/Tours/Stgo/big.webp';
?>
```

- [ ] **Step 4: `privacy.php`**

Find:
```php
$page_canonical   = 'https://stampstour.com/privacy.php';
?>
```
Replace with:
```php
$page_canonical   = 'https://stampstour.com/privacy.php';
$critical_css_file = __DIR__ . '/includes/critical/content.css';
$lcp_preload_image = 'img/Tours/Stgo/big.webp';
?>
```

- [ ] **Step 5: `blog.php`**

Find:
```php
$page_canonical   = 'https://stampstour.com/blog';
?>
```
Replace with:
```php
$page_canonical   = 'https://stampstour.com/blog';
$critical_css_file = __DIR__ . '/includes/critical/content.css';
$lcp_preload_image = 'img/Tours/Stgo/big.webp';
?>
```

- [ ] **Step 6: Lint all 5 files**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l gallery.php
php -l refunds-cancellations.php
php -l contact-us.php
php -l privacy.php
php -l blog.php
```
Expected: `No syntax errors detected` for all 5.

- [ ] **Step 7: Confirm the non-blocking stylesheet pattern is now active**

```bash
php -S localhost:8899 > /tmp/critical-verify-server.log 2>&1 &
sleep 1
curl -s http://localhost:8899/gallery.php | grep -c 'rel="preload" href="/css/bootstrap.min.css"'
curl -s http://localhost:8899/gallery.php | grep -o '<style>' | wc -l
curl -s http://localhost:8899/gallery.php | grep -o 'rel="preload" as="image" href="/img/Tours/Stgo/big.webp"'
```
Expected: the bootstrap preload line appears (confirms the non-blocking branch of `includes/head.php` is now active, not the plain blocking `<link>` fallback); at least one `<style>` tag present (the inlined critical CSS); the `lcp_preload_image` preload link for the hero image is present.

- [ ] **Step 8: Full visual + icon-glyph verification with Puppeteer, across all 5 pages, both viewports**

```bash
mkdir -p /tmp/content-critical-verify && cd /tmp/content-critical-verify
npm init -y >/dev/null 2>&1
npm install puppeteer >/dev/null 2>&1
cat > check.js <<'JSEOF'
const puppeteer = require('puppeteer');

const pages = ['gallery.php', 'refunds-cancellations.php', 'contact-us.php', 'privacy.php', 'blog.php'];
const viewports = [
  { width: 390, height: 844, label: 'mobile' },
  { width: 1470, height: 900, label: 'desktop' },
];

async function checkPage(browser, pagePath, viewport) {
  const page = await browser.newPage();
  await page.setViewport({ width: viewport.width, height: viewport.height });
  await page.goto(`http://localhost:8899/${pagePath}`, { waitUntil: 'domcontentloaded' });

  // Screenshot immediately (before deferred stylesheets have necessarily swapped in)
  await new Promise((r) => setTimeout(r, 50));
  const earlyShot = `/tmp/content-critical-verify/${pagePath}-${viewport.label}-early.png`;
  await page.screenshot({ path: earlyShot, clip: { x: 0, y: 0, width: viewport.width, height: 500 } });

  // Check icon glyphs specifically, early
  const earlyIconCheck = await page.evaluate(() => {
    const icons = document.querySelectorAll('[class*="icon-"], [class*="icon_set_"]');
    if (icons.length === 0) return { found: 0, hasFont: null };
    const el = icons[0];
    const fontFamily = getComputedStyle(el, ':before').fontFamily || getComputedStyle(el).fontFamily;
    return { found: icons.length, fontFamily };
  });

  await page.waitForNetworkIdle({ idleTime: 500 }).catch(() => {});
  const lateShot = `/tmp/content-critical-verify/${pagePath}-${viewport.label}-late.png`;
  await page.screenshot({ path: lateShot, clip: { x: 0, y: 0, width: viewport.width, height: 500 } });

  console.log(`${pagePath} @ ${viewport.label}: early icons=${JSON.stringify(earlyIconCheck)}`);
  await page.close();
}

(async () => {
  const browser = await puppeteer.launch();
  for (const p of pages) {
    for (const v of viewports) {
      await checkPage(browser, p, v);
    }
  }
  await browser.close();
})();
JSEOF
node check.js
```
Expected: one line of output per page/viewport combination (10 total) showing `found` > 0 icon elements and a real `fontFamily` value (not `null`/empty — a missing font-family here would be the exact glyph-stripping regression from the homepage's first pass). Then visually inspect at least the `-early.png` screenshots for `gallery.php` at both viewports (the page with the hero image this plan's `$lcp_preload_image` addition specifically targets) — confirm the hero renders correctly with no visible layout break, and that the header/nav icons are visible (not blank squares or missing glyphs) in the *early* screenshot specifically, not just the late one.

- [ ] **Step 9: Confirm JS-disabled visitors still get a fully-styled page**

```bash
cat > /tmp/content-critical-verify/nojs-check.js <<'JSEOF'
const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  await page.setJavaScriptEnabled(false);
  await page.goto('http://localhost:8899/gallery.php', { waitUntil: 'domcontentloaded' });
  const bootstrapLoaded = await page.evaluate(() => {
    const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
    return links.some((l) => l.href.includes('bootstrap.min.css'));
  });
  console.log('bootstrap.min.css loaded via noscript fallback:', bootstrapLoaded);
  await browser.close();
})();
JSEOF
node /tmp/content-critical-verify/nojs-check.js
```
Expected: `bootstrap.min.css loaded via noscript fallback: true` (confirms the `<noscript>` fallback `<link>` tags — already part of the existing, unmodified mechanism — correctly kick in when JS is disabled).

- [ ] **Step 10: Confirm unaffected pages are genuinely unaffected**

```bash
curl -s http://localhost:8899/ | grep -c 'critical/home.css\|Homepage-only inlined critical'
curl -s http://localhost:8899/discover-santiago-city-tour.php | grep -c 'critical/tour.css'
curl -s -o /dev/null -w "shopping.php (expect 302, unrelated to this change): %{http_code}\n" http://localhost:8899/shopping.php
pkill -f "php -S localhost:8899"
rm -rf /tmp/content-critical-verify
```
Expected: homepage still references its own `home.css` (untouched by this plan); the tour page still references its own `tour.css` (untouched); `shopping.php` still 302-redirects exactly as before (its own separate, unmodified `<head>` — this plan never touches it).

- [ ] **Step 11: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add gallery.php refunds-cancellations.php contact-us.php privacy.php blog.php
git commit -m "Wire up critical CSS + LCP image preload on 5 content pages"
```

---

### Task 3: Deploy

**Files:**
- None modified — this task pushes already-committed changes.

**Interfaces:**
- Consumes: the commits from Tasks 1-2.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push origin main
```

- [ ] **Step 2: Remind the user to deploy, purge cache, and re-audit**

State clearly: `git pull` on production is required, plus a Cloudflare cache purge for the changed static assets (the 5 PHP pages themselves are dynamic/uncached, but confirm `includes/critical/content.css` isn't itself directly web-served/cached anywhere relevant — it's inlined server-side via PHP, not fetched by the browser as a separate URL, so no separate cache-purge target exists for that specific new file). Once deployed and purged, re-run the Lighthouse mobile audit already used throughout this session against `gallery.php`, and compare the `render-blocking-insight` finding and overall performance score against the most recent documented baseline (42/100, LCP 11.3s, ~3,630ms estimated render-blocking savings) — the real test of whether this achieved its goal. Also spot-check one of the other 4 pages (not just gallery.php) to confirm the fix generalized correctly.
