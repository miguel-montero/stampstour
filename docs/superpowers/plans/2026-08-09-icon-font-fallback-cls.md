# Icon-Font Fallback CLS Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the icon-font FOIT/FOUT layout shift (confirmed 0.1879 CLS on `contact-us.php`, desktop viewport) by adding metric-matched fallback `@font-face` rules for `icon_set_1` and `fontello`, sitewide.

**Architecture:** Add two new `@font-face` declarations (`icon_set_1-fallback`, `fontello-fallback`) using `src: local('Arial')` with `ascent-override`/`descent-override`/`line-gap-override` computed from the real icon fonts' own hhea/OS2 metrics, then extend the existing bracket-selector `font-family` stacks to list the fallback second. This is the same technique already shipped for the Montserrat text-font CLS fix, applied to icon fonts.

**Tech Stack:** Plain CSS (no build tooling — this repo has no `package.json`/npm scripts; `css/vendors.unminified.css` is a hand-maintained regeneration source for `css/vendors.css`, kept in sync by hand-editing both, not by running a minifier).

## Global Constraints

- Fallback face for `icon_set_1`: `@font-face{font-family:icon_set_1-fallback;src:local('Arial');ascent-override:85%;descent-override:15%;line-gap-override:9%}` (minified style) — exact values computed via `fontTools` against `css/fontello/font/icon_set_1.ttf`'s `hhea`/`OS/2` tables (both agree exactly, so no browser ambiguity).
- Fallback face for `fontello`: `@font-face{font-family:fontello-fallback;src:local('Arial');ascent-override:85%;descent-override:15%;line-gap-override:9%}` (minified style) — identical values, confirmed via the same method against `css/fontello/font/fontello.ttf` (both fonts built by the same tool with the same design metrics).
- No `size-adjust` on either fallback face — icon glyphs are single `:before` pseudo-elements with explicit `width: 1em`, so box width never depends on font metrics; only the `line-height: 1em`-driven box *height* does, which `ascent-override`/`descent-override`/`line-gap-override` handle completely.
- `font-family` stack order: real font first, fallback second — `font-family:icon_set_1,icon_set_1-fallback` and `font-family:fontello,fontello-fallback`. Do not reorder or drop the real font.
- Match each target file's existing formatting exactly (minified single-line vs. `vendors.unminified.css`'s quoted multi-line style) — do not reformat surrounding code.
- `icon_set_2` is explicitly out of scope (spec's Non-goals) — do not touch it in any file.
- Spec: `docs/superpowers/specs/2026-08-09-icon-font-fallback-cls-design.md`

---

### Task 1: Add fallback faces to `css/vendors-core.css` and `css/vendors.css`

**Files:**
- Modify: `css/vendors-core.css`
- Modify: `css/vendors.css`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: `icon_set_1-fallback` and `fontello-fallback` font-family names now resolvable in these two files — later tasks (verification) rely on these names existing sitewide once all files are done.

These two files are byte-identical for the relevant blocks (confirmed via diff). Apply the same edit to both.

- [ ] **Step 1: In `css/vendors-core.css`, find this exact text:**

```
@font-face{font-family:fontello;src:url(fontello/font/fontello-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

Replace with:

```
@font-face{font-family:fontello;src:url(fontello/font/fontello-subset.woff2) format("woff2");font-weight:400;font-style:normal}@font-face{font-family:fontello-fallback;src:local('Arial');ascent-override:85%;descent-override:15%;line-gap-override:9%}
```

- [ ] **Step 2: In the same file, find this exact text:**

```
@font-face{font-family:icon_set_1;src:url(fontello/font/icon_set_1-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

Replace with:

```
@font-face{font-family:icon_set_1;src:url(fontello/font/icon_set_1-subset.woff2) format("woff2");font-weight:400;font-style:normal}@font-face{font-family:icon_set_1-fallback;src:local('Arial');ascent-override:85%;descent-override:15%;line-gap-override:9%}
```

- [ ] **Step 3: In the same file, find this exact text:**

```
[class*=" icon-"]:before,[class^=icon-]:before{font-family:fontello;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}
```

Replace `font-family:fontello;` with `font-family:fontello,fontello-fallback;` (only within this rule — do not touch the `@font-face` declarations again):

```
[class*=" icon-"]:before,[class^=icon-]:before{font-family:fontello,fontello-fallback;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}
```

- [ ] **Step 4: In the same file, find this exact text:**

```
[class*=icon_set_1_]:before,[class^=icon_set_1_]:before{font-family:icon_set_1;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}
```

Replace `font-family:icon_set_1;` with `font-family:icon_set_1,icon_set_1-fallback;`:

```
[class*=icon_set_1_]:before,[class^=icon_set_1_]:before{font-family:icon_set_1,icon_set_1-fallback;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}
```

- [ ] **Step 5: Repeat Steps 1-4 identically in `css/vendors.css`.**

`css/vendors.css` has byte-identical blocks (already confirmed via diff). Apply the exact same four find/replace operations to it.

- [ ] **Step 6: Verify no syntax errors — run a quick brace-balance sanity check**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
for f in ['css/vendors-core.css', 'css/vendors.css']:
    content = open(f).read()
    assert content.count('{') == content.count('}'), f'{f}: brace mismatch'
    assert 'icon_set_1-fallback' in content and 'fontello-fallback' in content, f'{f}: fallback faces missing'
    print(f, 'OK')
"
```

Expected: `css/vendors-core.css OK` and `css/vendors.css OK`, no assertion errors.

- [ ] **Step 7: Commit**

```bash
git add css/vendors-core.css css/vendors.css
git commit -m "fix: add metric-matched icon-font fallback faces to vendors CSS

Prevents layout shift when icon_set_1/fontello finish loading and swap
in over the browser's unmatched default fallback font."
```

---

### Task 2: Add fallback faces to `css/vendors.unminified.css` (regeneration source)

**Files:**
- Modify: `css/vendors.unminified.css`

**Interfaces:**
- Consumes: nothing from other tasks (independent file, different formatting convention).
- Produces: keeps this file in sync with Task 1's `vendors.css` changes, per this file's own documented role as the regeneration source (see the existing comment above each block).

This file uses quoted, multi-line formatting — different from Task 1's minified style. Match its existing convention exactly.

- [ ] **Step 1: Find this exact text (currently at approximately line 36-41):**

```css
@font-face {
	font-family: 'fontello';
	src: url(fontello/font/fontello-subset.woff2) format("woff2");
	font-weight: 400;
	font-style: normal
}
```

Replace with:

```css
@font-face {
	font-family: 'fontello';
	src: url(fontello/font/fontello-subset.woff2) format("woff2");
	font-weight: 400;
	font-style: normal
}

@font-face {
	font-family: 'fontello-fallback';
	src: local('Arial');
	ascent-override: 85%;
	descent-override: 15%;
	line-gap-override: 9%
}
```

- [ ] **Step 2: Find this exact text (currently at approximately line 43-58):**

```css
[class^="icon-"]:before,
[class*=" icon-"]:before {
	font-family: "fontello";
	font-style: normal;
	font-weight: 400;
	speak: none;
	display: inline-block;
	text-decoration: inherit;
	width: 1em;
	margin-right: .2em;
	text-align: center;
	font-variant: normal;
	text-transform: none;
	line-height: 1em;
	margin-left: .2em
}
```

Replace `font-family: "fontello";` with `font-family: "fontello", "fontello-fallback";`:

```css
[class^="icon-"]:before,
[class*=" icon-"]:before {
	font-family: "fontello", "fontello-fallback";
	font-style: normal;
	font-weight: 400;
	speak: none;
	display: inline-block;
	text-decoration: inherit;
	width: 1em;
	margin-right: .2em;
	text-align: center;
	font-variant: normal;
	text-transform: none;
	line-height: 1em;
	margin-left: .2em
}
```

- [ ] **Step 3: Find this exact text (currently at approximately line 7868-7873):**

```css
@font-face {
	font-family: 'icon_set_1';
	src: url(fontello/font/icon_set_1-subset.woff2) format("woff2");
	font-weight: 400;
	font-style: normal
}
```

Replace with:

```css
@font-face {
	font-family: 'icon_set_1';
	src: url(fontello/font/icon_set_1-subset.woff2) format("woff2");
	font-weight: 400;
	font-style: normal
}

@font-face {
	font-family: 'icon_set_1-fallback';
	src: local('Arial');
	ascent-override: 85%;
	descent-override: 15%;
	line-gap-override: 9%
}
```

- [ ] **Step 4: Find this exact text (currently at approximately line 7875-7890):**

```css
[class^="icon_set_1_"]:before,
[class*="icon_set_1_"]:before {
	font-family: "icon_set_1";
	font-style: normal;
	font-weight: 400;
	speak: none;
	display: inline-block;
	text-decoration: inherit;
	width: 1em;
	margin-right: .2em;
	text-align: center;
	font-variant: normal;
	text-transform: none;
	line-height: 1em;
	margin-left: .2em
}
```

Replace `font-family: "icon_set_1";` with `font-family: "icon_set_1", "icon_set_1-fallback";`:

```css
[class^="icon_set_1_"]:before,
[class*="icon_set_1_"]:before {
	font-family: "icon_set_1", "icon_set_1-fallback";
	font-style: normal;
	font-weight: 400;
	speak: none;
	display: inline-block;
	text-decoration: inherit;
	width: 1em;
	margin-right: .2em;
	text-align: center;
	font-variant: normal;
	text-transform: none;
	line-height: 1em;
	margin-left: .2em
}
```

- [ ] **Step 5: Verify no syntax errors**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
content = open('css/vendors.unminified.css').read()
assert content.count('{') == content.count('}'), 'brace mismatch'
assert content.count(\"font-family: 'icon_set_1-fallback'\") == 1
assert content.count(\"font-family: 'fontello-fallback'\") == 1
print('OK')
"
```

Expected: `OK`, no assertion errors.

- [ ] **Step 6: Commit**

```bash
git add css/vendors.unminified.css
git commit -m "fix: add metric-matched icon-font fallback faces to vendors.unminified.css

Keeps the regeneration source in sync with vendors.css per this file's
own documented drift-risk convention."
```

---

### Task 3: Add fallback faces to `includes/critical/home.css` and `includes/critical/tour.css`

**Files:**
- Modify: `includes/critical/home.css`
- Modify: `includes/critical/tour.css`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: fallback faces available on the homepage and tour pages' critical-CSS render path.

Both files have one occurrence each, in the same minified style, differing only in the font URL path (`/css/fontello/...` with a leading slash vs. Task 1's relative `fontello/...`).

- [ ] **Step 1: In `includes/critical/home.css`, find this exact text:**

```
@font-face{font-family:fontello;src:url(/css/fontello/font/fontello-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

Replace with:

```
@font-face{font-family:fontello;src:url(/css/fontello/font/fontello-subset.woff2) format("woff2");font-weight:400;font-style:normal}@font-face{font-family:fontello-fallback;src:local('Arial');ascent-override:85%;descent-override:15%;line-gap-override:9%}
```

- [ ] **Step 2: In the same file, find this exact text:**

```
@font-face{font-family:icon_set_1;src:url(/css/fontello/font/icon_set_1-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

Replace with:

```
@font-face{font-family:icon_set_1;src:url(/css/fontello/font/icon_set_1-subset.woff2) format("woff2");font-weight:400;font-style:normal}@font-face{font-family:icon_set_1-fallback;src:local('Arial');ascent-override:85%;descent-override:15%;line-gap-override:9%}
```

- [ ] **Step 3: In the same file, find this exact text:**

```
[class^=icon-]:before{font-family:fontello;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}
```

Replace `font-family:fontello;` with `font-family:fontello,fontello-fallback;`:

```
[class^=icon-]:before{font-family:fontello,fontello-fallback;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}
```

- [ ] **Step 4: In the same file, find this exact text:**

```
[class*=icon_set_1_]:before,[class^=icon_set_1_]:before{font-family:icon_set_1;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}
```

Replace `font-family:icon_set_1;` with `font-family:icon_set_1,icon_set_1-fallback;`:

```
[class*=icon_set_1_]:before,[class^=icon_set_1_]:before{font-family:icon_set_1,icon_set_1-fallback;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}
```

- [ ] **Step 5: Repeat Steps 1-4 identically in `includes/critical/tour.css`.**

`includes/critical/tour.css` has byte-identical blocks to `home.css` for these four pieces (already confirmed). Apply the exact same four find/replace operations to it.

- [ ] **Step 6: Verify no syntax errors**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
for f in ['includes/critical/home.css', 'includes/critical/tour.css']:
    content = open(f).read()
    assert content.count('{') == content.count('}'), f'{f}: brace mismatch'
    assert 'icon_set_1-fallback' in content and 'fontello-fallback' in content, f'{f}: fallback faces missing'
    print(f, 'OK')
"
```

Expected: both files print `OK`.

- [ ] **Step 7: Commit**

```bash
git add includes/critical/home.css includes/critical/tour.css
git commit -m "fix: add metric-matched icon-font fallback faces to home/tour critical CSS"
```

---

### Task 4: Add fallback faces to `includes/critical/content.css` (10 occurrences)

**Files:**
- Modify: `includes/critical/content.css`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: fallback faces available on every content-page critical-CSS render path (this file backs `contact-us.php`, the page where the bug was originally measured, plus `return.php`, `refunds-cancellations.php`, `privacy.php`, `shopping.php`, and others).

This file contains the exact same four blocks as Task 3's `home.css`/`tour.css` (same URL path convention: relative, no leading slash, matching Task 1's style — confirmed via earlier grep) but **duplicated 10 times** within the single file, byte-identical each time. All 10 must be updated, or pages routed through an un-fixed copy will keep the bug.

- [ ] **Step 1: Confirm all 10 occurrences are still identical before editing (sanity check against drift since the spec was written)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
grep -c '@font-face{font-family:fontello;src:url(css/fontello/font/fontello-subset.woff2) format("woff2");font-weight:400;font-style:normal}' includes/critical/content.css
grep -c '@font-face{font-family:icon_set_1;src:url(css/fontello/font/icon_set_1-subset.woff2) format("woff2");font-weight:400;font-style:normal}' includes/critical/content.css
```

Expected: both commands print `10`. If either prints a different number, stop and report — the file has drifted from what this plan assumes; do not proceed with a blind global replace.

- [ ] **Step 2: Global replace — all 10 fontello `@font-face` occurrences**

Use `sed` (macOS/BSD sed — note the `''` after `-i`) to replace every occurrence at once, since all 10 are byte-identical:

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
import re
path = 'includes/critical/content.css'
content = open(path).read()
old = '@font-face{font-family:fontello;src:url(css/fontello/font/fontello-subset.woff2) format(\"woff2\");font-weight:400;font-style:normal}'
new = old + '@font-face{font-family:fontello-fallback;src:local(\'Arial\');ascent-override:85%;descent-override:15%;line-gap-override:9%}'
count = content.count(old)
assert count == 10, f'expected 10 occurrences, found {count}'
content = content.replace(old, new)
open(path, 'w').write(content)
print(f'replaced {count} occurrences')
"
```

Expected output: `replaced 10 occurrences`.

- [ ] **Step 3: Global replace — all 10 icon_set_1 `@font-face` occurrences**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
import re
path = 'includes/critical/content.css'
content = open(path).read()
old = '@font-face{font-family:icon_set_1;src:url(css/fontello/font/icon_set_1-subset.woff2) format(\"woff2\");font-weight:400;font-style:normal}'
new = old + '@font-face{font-family:icon_set_1-fallback;src:local(\'Arial\');ascent-override:85%;descent-override:15%;line-gap-override:9%}'
count = content.count(old)
assert count == 10, f'expected 10 occurrences, found {count}'
content = content.replace(old, new)
open(path, 'w').write(content)
print(f'replaced {count} occurrences')
"
```

Expected output: `replaced 10 occurrences`.

- [ ] **Step 4: Global replace — all 10 fontello bracket-selector occurrences**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
path = 'includes/critical/content.css'
content = open(path).read()
old = '[class^=icon-]:before{font-family:fontello;font-style:normal'
new = '[class^=icon-]:before{font-family:fontello,fontello-fallback;font-style:normal'
count = content.count(old)
assert count == 10, f'expected 10 occurrences, found {count}'
content = content.replace(old, new)
open(path, 'w').write(content)
print(f'replaced {count} occurrences')
"
```

Expected output: `replaced 10 occurrences`.

- [ ] **Step 5: Global replace — all 10 icon_set_1 bracket-selector occurrences**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
path = 'includes/critical/content.css'
content = open(path).read()
old = '[class*=icon_set_1_]:before,[class^=icon_set_1_]:before{font-family:icon_set_1;font-style:normal'
new = '[class*=icon_set_1_]:before,[class^=icon_set_1_]:before{font-family:icon_set_1,icon_set_1-fallback;font-style:normal'
count = content.count(old)
assert count == 10, f'expected 10 occurrences, found {count}'
content = content.replace(old, new)
open(path, 'w').write(content)
print(f'replaced {count} occurrences')
"
```

Expected output: `replaced 10 occurrences`.

- [ ] **Step 6: Verify no syntax errors and full coverage**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
content = open('includes/critical/content.css').read()
assert content.count('{') == content.count('}'), 'brace mismatch'
assert content.count('icon_set_1-fallback') == 10, f'expected 10 icon_set_1-fallback refs, found {content.count(\"icon_set_1-fallback\")}'
assert content.count('fontello-fallback') == 10, f'expected 10 fontello-fallback refs, found {content.count(\"fontello-fallback\")}'
print('OK: 10/10 both fallbacks present, braces balanced')
"
```

Expected: `OK: 10/10 both fallbacks present, braces balanced`.

- [ ] **Step 7: Commit**

```bash
git add includes/critical/content.css
git commit -m "fix: add metric-matched icon-font fallback faces to content critical CSS

All 10 duplicated per-page-type blocks updated identically."
```

---

### Task 5: Local verification

**Files:**
- No files modified — verification only.

**Interfaces:**
- Consumes: all fallback-face changes from Tasks 1-4.
- Produces: confirmation the site renders correctly before deploying.

- [ ] **Step 1: Brace-balance and completeness check across all 6 files**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
python3 -c "
files = [
    'css/vendors-core.css', 'css/vendors.css', 'css/vendors.unminified.css',
    'includes/critical/home.css', 'includes/critical/tour.css', 'includes/critical/content.css',
]
for f in files:
    content = open(f).read()
    assert content.count('{') == content.count('}'), f'{f}: brace mismatch'
    n1 = content.count('icon_set_1-fallback')
    n2 = content.count('fontello-fallback')
    assert n1 >= 1 and n2 >= 1, f'{f}: missing fallback face (icon_set_1-fallback={n1}, fontello-fallback={n2})'
    print(f, f'icon_set_1-fallback x{n1}, fontello-fallback x{n2}')
"
```

Expected: all 6 files print with non-zero counts, no assertion errors. `content.css` should show `x10` for both.

- [ ] **Step 2: Start a local PHP server and visually spot-check icon rendering**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8765 &
sleep 1
```

- [ ] **Step 3: Load `contact-us.php` locally in a real browser (via Puppeteer) and confirm the `box_style_4` icon still renders (not blank/tofu) and no console errors**

```bash
node -e "
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  const errors = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', err => errors.push(err.message));
  await page.goto('http://localhost:8765/contact-us.php', { waitUntil: 'networkidle0' });
  const iconVisible = await page.evaluate(() => {
    const el = document.querySelector('.box_style_4 i');
    if (!el) return 'ELEMENT_NOT_FOUND';
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0 ? 'VISIBLE' : 'ZERO_SIZE';
  });
  console.log('icon status:', iconVisible);
  console.log('console errors:', errors.length ? errors : 'none');
  await browser.close();
})();
"
```

Expected: `icon status: VISIBLE`, `console errors: none`.

- [ ] **Step 4: Stop the local server**

```bash
kill %1 2>/dev/null || true
```

- [ ] **Step 5: Commit (no-op if nothing changed — this task is verification-only, skip commit if `git status` is clean)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git status --short
```

If clean, no commit needed — proceed to Task 6.

---

### Task 6: Deploy and confirm production (real desktop CLS re-measurement)

**Files:**
- No files modified — deployment and measurement only.

**Interfaces:**
- Consumes: all changes from Tasks 1-5, already committed and pushed.
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
curl -sI https://stampstour.com/contact-us.php | grep -i "cf-cache-status\|age:"
```

Expected: `cf-cache-status: MISS` or `EXPIRED` (not `HIT` with a large `age`) — if it shows `HIT` with a nonzero `age`, the cache wasn't actually cleared; ask the user to retry clearing it before measuring.

- [ ] **Step 4: Re-run the real desktop CLS measurement against all 5 `box_style_4` pages plus the 2 already-good pages (regression check)**

```bash
cat > /tmp/icon-fallback-verify.js <<'EOF'
const puppeteer = require('/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer');
const DESKTOP_NETWORK = { offline: false, downloadThroughput: 10*1024*1024/8, uploadThroughput: 5*1024*1024/8, latency: 40 };

const pages = [
  'https://stampstour.com/contact-us.php',
  'https://stampstour.com/return.php',
  'https://stampstour.com/refunds-cancellations.php',
  'https://stampstour.com/privacy.php',
  'https://stampstour.com/shopping.php',
  'https://stampstour.com/',
  'https://stampstour.com/discover-santiago-city-tour.php',
];

(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  for (const url of pages) {
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
    try {
      await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
      await new Promise(r => setTimeout(r, 1500));
      const cls = await page.evaluate(() => window.__cls);
      console.log(`${url}: CLS=${cls.toFixed(4)}`);
    } catch (e) {
      console.log(`${url}: ERROR ${e.message}`);
    }
    await context.close();
  }
  await browser.close();
})();
EOF
node /tmp/icon-fallback-verify.js
```

Expected: `contact-us.php` drops from the 0.1879 baseline into "Good" range (≤0.1, ideally near-zero). The other 4 `box_style_4` pages should also show low/Good CLS. Homepage and the tour page should remain at their already-good baseline (0.0003 and 0.0037-0.0221) — no regression.

- [ ] **Step 5: Report the before/after numbers to the user**

Present a before/after table comparing this run's numbers against the spec's baseline table.

---
