# Content CSS `.row>*` Dedup CLS Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the confirmed, traced desktop CLS bug (0.1878–0.1882 on `contact-us.php`, and any other page using `includes/critical/content.css`'s critical CSS) caused by a duplicated `.row>*` reset rule winning a same-specificity cascade conflict against page-specific `.col-*` column-width rules.

**Architecture:** `includes/critical/content.css` contains the exact same `.row>*{...}` rule 10 times (once per page-type block duplicated into this shared critical CSS file). Delete 9 of the 10 occurrences, keeping only the first — already verified to sit before every `.col-*` rule in the file, so no repositioning is needed, only deletion of the 9 redundant later copies.

**Tech Stack:** Plain CSS (no build tooling — same as the prior icon-font-fallback plan in this repo).

## Global Constraints

- The exact rule text being deduplicated (byte-for-byte, confirmed present 10 times in the current file): `.row>*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x)*.5);padding-left:calc(var(--bs-gutter-x)*.5);margin-top:var(--bs-gutter-y)}`
- Keep exactly the **first** occurrence (already positioned before every `.col-*` rule in the file — verified, no repositioning required). Delete the other 9.
- Do **not** touch the `.row{...}` rule that immediately precedes each `.row>*` occurrence — `.row` itself is not duplicated-and-conflicting (no competing same-specificity override exists for it anywhere in the file) and must remain present in all 10 of its current positions, unchanged.
- Do not touch `includes/critical/home.css` or `includes/critical/tour.css` — confirmed not exposed to this failure mode (each has `.row>*` only once already).
- Spec: `docs/superpowers/specs/2026-08-12-content-css-row-star-dedup-cls-design.md`

---

### Task 1: Deduplicate `.row>*` in `includes/critical/content.css`

**Files:**
- Modify: `includes/critical/content.css`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: a `content.css` with exactly 1 occurrence of the `.row>*` rule (down from 10), fixing the cascade-conflict CLS bug for every page using this critical-CSS variant.

- [ ] **Step 1: Confirm the file still has exactly 10 occurrences before editing (sanity check against drift since the spec was written)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
grep -co '\.row>\*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x)\*\.5);padding-left:calc(var(--bs-gutter-x)\*\.5);margin-top:var(--bs-gutter-y)}' includes/critical/content.css
```

Expected: `10`. If it prints a different number, stop and report — the file has drifted from what this plan assumes; do not proceed with the script below.

- [ ] **Step 2: Also confirm the `.row{...}` rule (the one NOT being touched) still appears 10 times, so Step 5's final check has a correct baseline**

```bash
grep -co '^\.row{--bs-gutter-x:1.5rem' includes/critical/content.css 2>/dev/null || grep -co '\.row{--bs-gutter-x:1.5rem' includes/critical/content.css
```

Expected: `10`.

- [ ] **Step 3: Run the deduplication script**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
path = 'includes/critical/content.css'
content = open(path).read()
old = '.row>*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x)*.5);padding-left:calc(var(--bs-gutter-x)*.5);margin-top:var(--bs-gutter-y)}'
parts = content.split(old)
assert len(parts) == 11, f'expected 10 occurrences (11 parts after split), found {len(parts)-1} occurrences'
new_content = parts[0] + old + ''.join(parts[1:])
assert new_content.count(old) == 1, 'dedup did not leave exactly 1 occurrence'
open(path, 'w').write(new_content)
print('deduplicated: 10 -> 1 occurrence')
"
```

Expected output: `deduplicated: 10 -> 1 occurrence`.

- [ ] **Step 4: Add a hazard comment at the surviving `.row>*` rule, matching this codebase's established convention for drift-risk spots (e.g. `vendors.unminified.css`'s regeneration-source warning)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
path = 'includes/critical/content.css'
content = open(path).read()
old = '.row>*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x)*.5);padding-left:calc(var(--bs-gutter-x)*.5);margin-top:var(--bs-gutter-y)}'
comment = '/* 2026-08-12: kept ONCE deliberately - this rule has the same CSS specificity as .col-* width rules elsewhere in this file, and a duplicate copy positioned later in the cascade silently overrides the correct column width (caused a real, measured desktop CLS bug - see docs/superpowers/specs/2026-08-12-content-css-row-star-dedup-cls-design.md). Do not re-duplicate this rule if copying another page block into this file. */'
new = comment + old
assert content.count(old) == 1
content = content.replace(old, new)
open(path, 'w').write(content)
print('hazard comment added')
"
```

Expected output: `hazard comment added`.

- [ ] **Step 5: Verify the fix — exactly 1 occurrence of `.row>*`, exactly 10 occurrences of `.row{`, brace balance intact, first `.row>*` still sits before the earliest `.col-*` rule**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
import re
content = open('includes/critical/content.css').read()
assert content.count('{') == content.count('}'), 'brace mismatch'
row_star = '.row>*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x)*.5);padding-left:calc(var(--bs-gutter-x)*.5);margin-top:var(--bs-gutter-y)}'
assert content.count(row_star) == 1, f'expected 1 .row>* occurrence, found {content.count(row_star)}'
row_positions = [m.start() for m in re.finditer(re.escape('.row{--bs-gutter-x:1.5rem'), content)]
assert len(row_positions) == 10, f'expected 10 .row occurrences (untouched), found {len(row_positions)}'
row_star_pos = content.find(row_star)
col_positions = [m.start() for m in re.finditer(r'\.col-[a-z0-9-]+\{', content)]
earliest_col = min(col_positions)
assert row_star_pos < earliest_col, f'.row>* at {row_star_pos} must be before earliest .col-* at {earliest_col}'
print('OK: 1 .row>* occurrence, 10 .row occurrences (untouched), braces balanced, .row>* precedes all .col-* rules')
"
```

Expected: `OK: 1 .row>* occurrence, 10 .row occurrences (untouched), braces balanced, .row>* precedes all .col-* rules`.

- [ ] **Step 6: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add includes/critical/content.css
git commit -m "$(cat <<'EOF'
fix: dedupe .row>* in content.css to fix a same-specificity cascade bug

content.css duplicated Bootstrap's .row>*{width:100%} reset 10x (once
per page block). At equal CSS specificity with page-specific .col-*
width rules, a later duplicate belonging to an unrelated page's block
silently overrode the correct column width until bootstrap.min.css
finished loading - a real, traced desktop CLS bug (0.1878-0.1882 on
contact-us.php). Kept only the first occurrence, already positioned
before every .col-* rule in the file.
EOF
)"
```

---

### Task 2: Local verification

**Files:**
- No files modified — verification only.

**Interfaces:**
- Consumes: the deduplicated `content.css` from Task 1.
- Produces: confirmation the site renders correctly before deploying.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8765 &
sleep 1
```

- [ ] **Step 2: Load `contact-us.php` locally in a real browser (Puppeteer) and confirm the support box and footer grid render at their correct widths (not full-width), with no console errors**

```bash
node -e "
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  await page.setViewport({ width: 1920, height: 1080 });
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', err => errors.push(err.message));
  await page.goto('http://localhost:8765/contact-us.php', { waitUntil: 'networkidle0' });
  const info = await page.evaluate(() => {
    const col = document.querySelector('.col-lg-6.col-md-8');
    const rect = col ? col.getBoundingClientRect() : null;
    return { found: !!col, width: rect ? rect.width : null };
  });
  console.log('column info:', JSON.stringify(info));
  console.log('console errors:', errors.length ? errors : 'none');
  await browser.close();
})();
"
```

Expected: `found: true`, `width` should be roughly 50% of the container width (not the full row width — e.g. ~660px in a 1320px container, not ~1320px), and `console errors: none`.

- [ ] **Step 3: Also spot-check `privacy.php` (another page using `content.css`) loads without visual/console issues**

```bash
node -e "
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', err => errors.push(err.message));
  const resp = await page.goto('http://localhost:8765/privacy.php', { waitUntil: 'networkidle0' });
  console.log('status:', resp.status());
  console.log('console errors:', errors.length ? errors : 'none');
  await browser.close();
})();
"
```

Expected: `status: 200`, `console errors: none`.

- [ ] **Step 4: Stop the local server**

```bash
kill %1 2>/dev/null || true
```

- [ ] **Step 5: Commit (no-op if nothing changed — this task is verification-only, skip commit if `git status` is clean)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git status --short
```

If clean, no commit needed — proceed to Task 3.

---

### Task 3: Deploy and confirm production (real desktop CLS re-measurement)

**Files:**
- No files modified — deployment and measurement only.

**Interfaces:**
- Consumes: the change from Task 1, already committed.
- Produces: verified, deployed fix with real before/after CLS numbers.

- [ ] **Step 1: Push to the remote**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git push
```

- [ ] **Step 2: Ask the user to pull on production (HostGator cPanel, manual `git pull` on `main`) and clear the Cloudflare cache**

Say to the user: "Pushed. Please pull on production and clear the Cloudflare cache, then let me know when done — I'll re-measure CLS to confirm the fix."

Wait for confirmation before proceeding.

- [ ] **Step 3: Verify the cache is actually cleared (not stale) before measuring**

```bash
node -e "
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  const resp = await page.goto('https://stampstour.com/css/vendors.css', { waitUntil: 'networkidle0', timeout: 45000 });
  console.log('cf-cache-status:', resp.headers()['cf-cache-status']);
  console.log('age:', resp.headers()['age']);
  await browser.close();
})();
"
```

Note: production returns HTTP 409 to raw `curl` requests due to a pre-existing, unrelated anti-bot cookie-challenge mechanism — use Puppeteer (which executes JS and passes the challenge) for all production checks in this task, not `curl`.

Expected: `age` should be low/fresh (not a large stale value) if the cache was actually cleared. If `age` is large, ask the user to retry clearing the cache before measuring.

- [ ] **Step 4: Re-run the real desktop CLS measurement against the affected pages plus the already-good pages (regression check)**

```bash
cat > /tmp/row-star-dedup-verify.js <<'EOF'
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
const DESKTOP_NETWORK = { offline: false, downloadThroughput: 10*1024*1024/8, uploadThroughput: 5*1024*1024/8, latency: 40 };

const pages = [
  'https://stampstour.com/contact-us.php',
  'https://stampstour.com/privacy.php',
  'https://stampstour.com/refunds-cancellations.php',
  'https://stampstour.com/shopping.php',
  'https://stampstour.com/',
  'https://stampstour.com/discover-santiago-city-tour.php',
];

async function measureOnce(browser, url) {
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.setViewport({ width: 1920, height: 1080, deviceScaleFactor: 1 });
  const client = await page.target().createCDPSession();
  await client.send('Network.enable');
  await client.send('Network.emulateNetworkConditions', DESKTOP_NETWORK);
  await client.send('Emulation.setCPUThrottlingRate', { rate: 4 });
  await page.evaluateOnNewDocument(() => {
    window.__cls = 0;
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (!entry.hadRecentInput) window.__cls += entry.value;
      }
    }).observe({ type: 'layout-shift', buffered: true });
  });
  let cls = null;
  try {
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
    await new Promise(r => setTimeout(r, 1500));
    cls = await page.evaluate(() => window.__cls);
  } catch (e) {
    cls = 'ERROR: ' + e.message;
  }
  await context.close();
  return cls;
}

(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  for (const url of pages) {
    const result = await measureOnce(browser, url);
    console.log(`${url}: CLS=${typeof result === 'number' ? result.toFixed(4) : result}`);
  }
  await browser.close();
})();
EOF
node /tmp/row-star-dedup-verify.js
```

Expected: `contact-us.php` drops from the 0.1878–0.1882 baseline into "Good" range (≤0.1, ideally near-zero). `privacy.php`, `refunds-cancellations.php`, and `shopping.php` (also using `content.css`) should show low/Good CLS too. Homepage and `discover-santiago-city-tour.php` should remain at their prior baselines (0.0003 and 0.0037–0.0221) — no regression.

- [ ] **Step 5: Report the before/after numbers to the user**

Present a before/after table comparing this run's numbers against the confirmed pre-fix baseline (`contact-us.php`: 0.1878–0.1882).

---
