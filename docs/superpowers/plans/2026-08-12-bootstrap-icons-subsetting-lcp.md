# Bootstrap Icons Subsetting LCP Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce mobile LCP (currently 5.3s, "Poor") by subsetting `bootstrap-icons.woff2` from its full ~2,000-icon set (127.7KB, fetched at `VeryHigh` browser priority, competing with the LCP hero image for bandwidth) down to the 7 glyphs actually used sitewide, and by removing a redundant third-party CDN load of the same font on `contact-us.php`.

**Architecture:** Subset the font with `fonttools` (same technique already proven twice this session for `fontello`/`icon_set_1`), point the site's one `@font-face` declaration at the new subset file, and delete one redundant `<link>`.

**Tech Stack:** `fonttools` (Python) for font subsetting, plain CSS/PHP for the rest — no build tooling.

## Global Constraints

- Exact codepoints to keep (verified against real sitewide usage via `grep -rhoE 'class="[^"]*\bbi-[a-z0-9-]+[^"]*"'`): `f26b` (bi-check-circle), `f30a` (bi-download), `f344` (bi-facebook), `f437` (bi-instagram), `f501` (bi-printer), `f618` (bi-whatsapp), `f623` (bi-x-circle).
- Output file: `css/bs-icon-font/fonts/bootstrap-icons-subset.woff2` (new file — do not overwrite the original `bootstrap-icons.woff2`/`bootstrap-icons.woff`, which stay in place as regeneration-source references, matching the `fontello`/`icon_set_1` convention).
- The subset font's cmap must contain **exactly** these 7 codepoints — no more, no less (a prior subsetting run this session hit a real `fonttools` cmap-leak bug; this must be independently re-verified here, not assumed clean).
- New `@font-face` in `css/bs-icon-font/bootstrap-icons.min.css`: single `woff2`-only `src`, no `.woff` fallback, no cache-busting query string (the new filename already serves that purpose) — exact text given in Task 1.
- `detalle_reservas.php` must NOT be touched — it's an internal admin page using a completely separate, standalone CDN-based Bootstrap+Icons setup with no self-hosted alternative.
- `font-display:block` stays unchanged.
- Spec: `docs/superpowers/specs/2026-08-12-bootstrap-icons-subsetting-lcp-design.md`

---

### Task 1: Generate the subset font and update `bootstrap-icons.min.css`

**Files:**
- Create: `css/bs-icon-font/fonts/bootstrap-icons-subset.woff2`
- Modify: `css/bs-icon-font/bootstrap-icons.min.css`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: `css/bs-icon-font/fonts/bootstrap-icons-subset.woff2` (new subset font file) and an updated `@font-face` in `bootstrap-icons.min.css` that later tasks' verification steps check against.

- [ ] **Step 1: Ensure `fonttools` is available**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "import fontTools; print('fonttools available:', fontTools.__version__)" 2>/dev/null || {
  python3 -m venv /tmp/bi-fontenv
  source /tmp/bi-fontenv/bin/activate
  pip install --quiet fonttools[woff]
  python3 -c "import fontTools; print('fonttools installed:', fontTools.__version__)"
}
```

Expected: prints a version number either way (either it was already available system-wide, or a fresh venv at `/tmp/bi-fontenv` was created and activated). If you created the venv, remember to `source /tmp/bi-fontenv/bin/activate` again before Step 2 if your shell session changed.

- [ ] **Step 2: Confirm the source font exists and note its size (baseline)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
ls -la css/bs-icon-font/fonts/bootstrap-icons.woff2
```

Expected: a file around 130,396 bytes. If the size differs significantly from this, the source file has changed since this plan was written — proceed with subsetting anyway (the technique works regardless of exact source size), but note the discrepancy in your task report.

- [ ] **Step 3: Run the subsetting command**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
fonttools subset css/bs-icon-font/fonts/bootstrap-icons.woff2 \
  --output-file=css/bs-icon-font/fonts/bootstrap-icons-subset.woff2 \
  --unicodes=f26b,f30a,f344,f437,f501,f618,f623 \
  --flavor=woff2 \
  --layout-features='' \
  --no-hinting \
  --desubroutinize
```

Expected: no error output, and a new file `css/bs-icon-font/fonts/bootstrap-icons-subset.woff2` appears (should be roughly 1-3KB, a ~99% reduction from the ~130KB original).

- [ ] **Step 4: Verify the subset font's cmap contains exactly the 7 requested codepoints (no more, no less)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
from fontTools.ttLib import TTFont
f = TTFont('css/bs-icon-font/fonts/bootstrap-icons-subset.woff2')
cmap = f.getBestCmap()
actual = set(cmap.keys())
expected = {0xf26b, 0xf30a, 0xf344, 0xf437, 0xf501, 0xf618, 0xf623}
assert actual == expected, f'MISMATCH: extra={actual - expected}, missing={expected - actual}'
print('OK: cmap contains exactly the 7 expected codepoints, no leak')
"
```

Expected: `OK: cmap contains exactly the 7 expected codepoints, no leak`. If this assertion fails (a cmap leak like the one found earlier this session during `icon_set_1` subsetting), stop and investigate before proceeding — do not ship a font with unexpected extra or missing glyphs.

- [ ] **Step 5: Visually verify all 7 glyphs render correctly (not tofu/placeholder boxes)**

```bash
mkdir -p /tmp/bi-render-check
cp css/bs-icon-font/fonts/bootstrap-icons-subset.woff2 /tmp/bi-render-check/
cat > /tmp/bi-render-check/test.html <<'EOF'
<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
@font-face { font-family: 'bi-subset-check'; src: url('bootstrap-icons-subset.woff2') format('woff2'); }
body { background: white; }
.i { font-family: 'bi-subset-check'; font-size: 48px; display: inline-block; margin: 10px; border: 1px solid #ccc; padding: 5px; }
</style></head><body>
<span class="i" id="a">&#xf26b;</span>
<span class="i" id="b">&#xf30a;</span>
<span class="i" id="c">&#xf344;</span>
<span class="i" id="d">&#xf437;</span>
<span class="i" id="e">&#xf501;</span>
<span class="i" id="f">&#xf618;</span>
<span class="i" id="g">&#xf623;</span>
</body></html>
EOF
cd /tmp/bi-render-check
php -S localhost:8768 > /dev/null 2>&1 &
sleep 1
node -e "
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  await page.setViewport({ width: 500, height: 150 });
  await page.goto('http://localhost:8768/test.html', { waitUntil: 'networkidle0' });
  await new Promise(r => setTimeout(r, 500));
  await page.screenshot({ path: '/tmp/bi-render-check/screenshot.png' });
  const widths = await page.evaluate(() => {
    const out = {};
    for (const id of ['a','b','c','d','e','f','g']) out[id] = document.getElementById(id).getBoundingClientRect().width;
    return out;
  });
  console.log(JSON.stringify(widths));
  await browser.close();
})();
"
kill %1 2>/dev/null
```

Expected: all 7 widths print as a consistent, non-zero value (e.g. `{"a":60,"b":60,"c":60,"d":60,"e":60,"f":60,"g":60}` — a uniform width around 55-65px confirms glyphs are actually occupying space, not collapsing to zero-width tofu). Then read the screenshot at `/tmp/bi-render-check/screenshot.png` (via the Read tool, since it's an image) and visually confirm you see 7 distinct icon shapes (a checkmark circle, a download arrow, a Facebook "f", an Instagram camera, a printer, a WhatsApp phone, and an X circle) — not empty boxes or missing glyphs.

- [ ] **Step 6: Update the `@font-face` declaration in `bootstrap-icons.min.css`**

Find this exact text in `css/bs-icon-font/bootstrap-icons.min.css`:

```
@font-face{font-display:block;font-family:bootstrap-icons;src:url("fonts/bootstrap-icons.woff2?dd67030699838ea613ee6dbda90effa6") format("woff2"),url("fonts/bootstrap-icons.woff?dd67030699838ea613ee6dbda90effa6") format("woff")}
```

Replace with:

```
@font-face{font-display:block;font-family:bootstrap-icons;src:url("fonts/bootstrap-icons-subset.woff2") format("woff2")}
```

- [ ] **Step 7: Verify the CSS file still parses and the reference is correct**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
content = open('css/bs-icon-font/bootstrap-icons.min.css').read()
assert content.count('{') == content.count('}'), 'brace mismatch'
assert 'bootstrap-icons-subset.woff2' in content, 'new file reference missing'
assert 'bootstrap-icons.woff2?' not in content, 'old query-string reference still present'
assert 'format(\"woff\")' not in content.split('@font-face')[1].split('}')[0], 'woff fallback format not removed from @font-face'
print('OK: @font-face correctly updated')
"
```

Expected: `OK: @font-face correctly updated`.

- [ ] **Step 8: Clean up the test artifacts and commit**

```bash
rm -rf /tmp/bi-render-check
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add css/bs-icon-font/fonts/bootstrap-icons-subset.woff2 css/bs-icon-font/bootstrap-icons.min.css
git commit -m "$(cat <<'EOF'
fix: subset bootstrap-icons.woff2 to the 7 glyphs actually used

Was 130KB (~2000 icons), fetched at VeryHigh browser priority due to
font-display:block, directly competing with the LCP hero image for
bandwidth on mobile (measured LCP=5.3s, Poor). Subsetted to
check-circle/download/facebook/instagram/printer/whatsapp/x-circle -
the only 7 classes used anywhere on the site - down to ~1.2KB (99%
reduction). Verified exact cmap match (no leak) and correct visual
rendering of all 7 glyphs before committing.
EOF
)"
```

---

### Task 2: Remove the redundant CDN bootstrap-icons load from `contact-us.php`

**Files:**
- Modify: `contact-us.php`

**Interfaces:**
- Consumes: nothing from other tasks (independent change).
- Produces: `contact-us.php` no longer double-loads `bootstrap-icons` — relies solely on the self-hosted, now-subsetted version loaded via `includes/head.php` (which it already includes).

- [ ] **Step 1: Find this exact text in `contact-us.php`:**

```html
  <!-- Bootstrap Icons for WhatsApp icon -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
```

Delete both lines entirely (including the comment).

- [ ] **Step 2: Verify the file still has valid PHP/HTML structure and the CDN reference is gone**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l contact-us.php
grep -c "cdn.jsdelivr.net/npm/bootstrap-icons" contact-us.php
```

Expected: `php -l` prints `No syntax errors detected in contact-us.php`, and the `grep -c` prints `0`.

- [ ] **Step 3: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add contact-us.php
git commit -m "$(cat <<'EOF'
fix: remove redundant CDN bootstrap-icons load from contact-us.php

contact-us.php already includes includes/head.php, which loads the
self-hosted (now-subsetted) bootstrap-icons.min.css - the third-party
CDN link was fetching the exact same font (unsubsetted) a second time
for the same single icon (bi-whatsapp).
EOF
)"
```

---

### Task 3: Local verification across affected pages

**Files:**
- No files modified — verification only.

**Interfaces:**
- Consumes: the subset font and CSS changes from Task 1, the markup change from Task 2.
- Produces: confirmation the site renders correctly before deploying.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8769 &
sleep 1
```

- [ ] **Step 2: Load the homepage (uses `bi-facebook`/`bi-instagram` in the shared header/footer) and confirm the icons render, with no console errors, and confirm the CDN request no longer fires anywhere on the page**

```bash
node -e "
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  const errors = [];
  const cdnRequests = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', err => errors.push(err.message));
  page.on('request', req => { if (req.url().includes('cdn.jsdelivr.net')) cdnRequests.push(req.url()); });
  await page.goto('http://localhost:8769/index.php', { waitUntil: 'networkidle0' });
  const iconInfo = await page.evaluate(() => {
    const el = document.querySelector('.bi-facebook, .bi-instagram');
    if (!el) return { found: false };
    const rect = el.getBoundingClientRect();
    return { found: true, width: rect.width, height: rect.height };
  });
  console.log('icon info:', JSON.stringify(iconInfo));
  console.log('console errors:', errors.length ? errors : 'none');
  console.log('CDN requests (should be empty):', cdnRequests);
  await browser.close();
})();
"
```

Expected: `found: true` with non-zero `width`/`height`, `console errors: none`, `CDN requests (should be empty): []`.

- [ ] **Step 3: Load `contact-us.php` and confirm the WhatsApp icon renders, no console errors, and the CDN request is gone**

```bash
node -e "
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  const errors = [];
  const cdnRequests = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', err => errors.push(err.message));
  page.on('request', req => { if (req.url().includes('cdn.jsdelivr.net')) cdnRequests.push(req.url()); });
  await page.goto('http://localhost:8769/contact-us.php', { waitUntil: 'networkidle0' });
  const iconInfo = await page.evaluate(() => {
    const el = document.querySelector('.bi-whatsapp');
    if (!el) return { found: false };
    const rect = el.getBoundingClientRect();
    return { found: true, width: rect.width, height: rect.height };
  });
  console.log('WhatsApp icon info:', JSON.stringify(iconInfo));
  console.log('console errors:', errors.length ? errors : 'none');
  console.log('CDN requests (should be empty):', cdnRequests);
  await browser.close();
})();
"
```

Expected: `found: true` with non-zero `width`/`height`, `console errors: none`, `CDN requests (should be empty): []`.

- [ ] **Step 4: Stop the local server**

```bash
kill %1 2>/dev/null || true
```

- [ ] **Step 5: Commit (no-op if nothing changed — this task is verification-only, skip commit if `git status` is clean)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git status --short
```

If clean, no commit needed — proceed to Task 4.

---

### Task 4: Deploy and confirm production (real Lighthouse LCP/SI re-measurement)

**Files:**
- No files modified — deployment and measurement only.

**Interfaces:**
- Consumes: all changes from Tasks 1-3, already committed.
- Produces: verified, deployed fix with real before/after LCP and Speed Index numbers.

- [ ] **Step 1: Push to the remote**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git push
```

- [ ] **Step 2: Ask the user to pull on production (HostGator cPanel, manual `git pull` on `main`) and clear the Cloudflare cache**

Say to the user: "Pushed. Please pull on production and clear the Cloudflare cache, then let me know when done — I'll re-run Lighthouse to confirm the improvement."

Wait for confirmation before proceeding.

- [ ] **Step 3: Verify the deployed font is actually the subset version (not stale cache) before measuring**

```bash
node -e "
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  let resp;
  for (let i = 0; i < 3; i++) {
    try {
      resp = await page.goto('https://stampstour.com/css/bs-icon-font/bootstrap-icons.min.css', { waitUntil: 'networkidle0', timeout: 45000 });
      break;
    } catch (e) { await new Promise(r => setTimeout(r, 2000)); }
  }
  const text = await resp.text();
  console.log('references subset file:', text.includes('bootstrap-icons-subset.woff2'));
  console.log('cf-cache-status:', resp.headers()['cf-cache-status'], 'age:', resp.headers()['age']);
  const fontResp = await page.goto('https://stampstour.com/css/bs-icon-font/fonts/bootstrap-icons-subset.woff2', { waitUntil: 'networkidle0', timeout: 45000 });
  console.log('subset font status:', fontResp.status(), 'size:', fontResp.headers()['content-length']);
  await browser.close();
})();
"
```

Expected: `references subset file: true`, subset font `status: 200` with a small `content-length` (roughly 1000-3000 bytes). If `cf-cache-status` shows `HIT` with a large `age`, ask the user to retry clearing the cache before proceeding to measurement.

- [ ] **Step 4: Run a real Lighthouse audit against the production homepage (mobile) and record LCP/Speed Index**

```bash
mkdir -p /tmp/lighthouse-verify
cd /tmp/lighthouse-verify
npx --yes lighthouse https://stampstour.com/ \
  --output=json --output-path=./home-mobile-after.json \
  --only-categories=performance \
  --chrome-flags="--headless=new" \
  --preset=perf \
  --quiet
python3 -c "
import json
d = json.load(open('home-mobile-after.json'))
audits = d['audits']
print('Performance score:', d['categories']['performance']['score'])
for key in ['largest-contentful-paint','speed-index','cumulative-layout-shift','total-blocking-time']:
    a = audits.get(key, {})
    print(f'{key}: {a.get(\"displayValue\")} (score={a.get(\"score\")})')
lcp_breakdown = audits.get('lcp-breakdown-insight', {}).get('details', {}).get('items', [{}])[0].get('items', [])
print('LCP breakdown:', lcp_breakdown)
"
```

Expected: LCP should drop meaningfully from the 5.3s baseline (exact amount depends on real network conditions — this is a genuine re-measurement, not a guaranteed specific number). Speed Index should also improve, since it's driven by the same network contention. Compare against the baseline table from the spec's Context section.

- [ ] **Step 5: Confirm `bootstrap-icons.woff2` (unsubsetted) no longer appears in the network-requests waterfall, and the CDN request from `contact-us.php` is gone in production**

```bash
python3 -c "
import json
d = json.load(open('/tmp/lighthouse-verify/home-mobile-after.json'))
items = d['audits']['network-requests']['details']['items']
bi_requests = [it for it in items if 'bootstrap-icons' in it.get('url','')]
for it in bi_requests:
    print(it['url'], it.get('transferSize'), it.get('priority'))
"
node -e "
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  const cdnRequests = [];
  page.on('request', req => { if (req.url().includes('cdn.jsdelivr.net')) cdnRequests.push(req.url()); });
  await page.goto('https://stampstour.com/contact-us.php', { waitUntil: 'networkidle0', timeout: 45000 });
  console.log('CDN requests on contact-us.php (should be empty):', cdnRequests);
  await browser.close();
})();
"
```

Expected: the only `bootstrap-icons` entry in the Lighthouse waterfall should be the small subset file (not the old 130KB one), and the CDN-requests check on `contact-us.php` should print an empty array.

- [ ] **Step 6: Report the before/after numbers to the user**

Present a before/after table: LCP (5.3s → measured), Speed Index (5.3s → measured), and confirmation that `bootstrap-icons.woff2`'s unsubsetted 127.7KB entry is gone from the production waterfall.

---
