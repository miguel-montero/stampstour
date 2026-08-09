# Montserrat Font-Swap CLS Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the measured, reproducible CLS caused by Montserrat's font-swap (0.0635 homepage / 0.0310 tour / 0.0070 content, all cold-visit mobile) by adding a metric-matched fallback font that occupies the same line-box dimensions as Montserrat during the ~2.5s download window.

**Architecture:** Add one new `@font-face` declaration (pre-computed `size-adjust`/`ascent-override`/`descent-override` values matching Montserrat's real metrics to Arial) to each of the site's three critical/inlined CSS files, and insert it into the `body` font-family stack between Montserrat and plain Arial.

**Tech Stack:** CSS only, no JS/PHP changes.

## Global Constraints

- The exact override values are `size-adjust:112.8307%;ascent-override:85.7923%;descent-override:22.2457%;line-gap-override:0%` — computed via `fontaine`+`capsize` against the real font file (see the spec's Design section for the reproducible command), not to be altered or re-derived by hand.
- New font-family name is exactly `"Montserrat-fallback"` (quoted, matching the existing quoting convention for other multi-word font names in this codebase).
- `includes/critical/content.css` has a pre-existing, established structure: 10 near-identical concatenated chunks (one Montserrat `@font-face` + one `body` rule per chunk). This plan's changes must be applied to **all 10 occurrences**, mirroring the file's own existing duplication pattern — do not add the fallback only once or "deduplicate" the file as part of this fix (that would be unrelated scope creep; the file's duplication is pre-existing and out of scope here).
- `includes/critical/home.css` and `includes/critical/tour.css` each have exactly 1 occurrence (no duplication) — do not run the multi-occurrence approach against these two files.
- All three critical CSS files are single-line-minified (not hand-formatted) — preserve that style; do not reformat.

---

### Task 1: Add fallback font to `home.css` and `tour.css`

**Files:**
- Modify: `includes/critical/home.css`
- Modify: `includes/critical/tour.css`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: nothing consumed by later tasks — Tasks 1 and 2 are independent (different files).

- [ ] **Step 1: Update `includes/critical/home.css`**

Current (single occurrence):

```css
@font-face{font-family:Montserrat;font-style:normal;font-weight:100 900;font-display:swap;src:url(/fonts/montserrat-v31-latin-variable.woff2) format("woff2-variations"),url(/fonts/montserrat-v31-latin-variable.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
```

Change to (append the new `@font-face` immediately after, same line, no line break — matching the file's single-line style):

```css
@font-face{font-family:Montserrat;font-style:normal;font-weight:100 900;font-display:swap;src:url(/fonts/montserrat-v31-latin-variable.woff2) format("woff2-variations"),url(/fonts/montserrat-v31-latin-variable.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}@font-face{font-family:"Montserrat-fallback";src:local("Arial");size-adjust:112.8307%;ascent-override:85.7923%;descent-override:22.2457%;line-gap-override:0%}
```

Then find (single occurrence):

```css
body{background:#f9f9f9;font-size:14px;line-height:1.5;font-family:Montserrat,Arial,sans-serif;color:#2a2a2a}
```

Change to:

```css
body{background:#f9f9f9;font-size:14px;line-height:1.5;font-family:Montserrat,"Montserrat-fallback",Arial,sans-serif;color:#2a2a2a}
```

- [ ] **Step 2: Update `includes/critical/tour.css`**

Apply the exact same two changes as Step 1 (both the `@font-face` insertion and the `body` font-family update) — `tour.css` has byte-identical text for both target strings as `home.css` (confirmed: both files have exactly 1 occurrence each of the Montserrat `@font-face` and the `body` rule, with identical content).

- [ ] **Step 3: Verify locally**

```bash
grep -c 'Montserrat-fallback' /Users/miguelmontero/Documents/superpowers/STAMP/includes/critical/home.css
grep -c 'Montserrat-fallback' /Users/miguelmontero/Documents/superpowers/STAMP/includes/critical/tour.css
```

Expected: `2` for each file (one from the `@font-face` declaration, one from the `body` font-family reference).

```bash
php -S localhost:8765 -t /Users/miguelmontero/Documents/superpowers/STAMP &
sleep 1
curl -s http://localhost:8765/index.php | grep -o '"Montserrat-fallback"[^;]*;[^;]*;[^;]*;[^;]*;'
curl -s http://localhost:8765/discover-santiago-city-tour.php | grep -o 'font-family:Montserrat,"Montserrat-fallback"'
kill %1
```

Expected: the first command shows the new `@font-face`'s properties inlined in the homepage's rendered HTML; the second confirms the tour page's `body` rule includes the new fallback name.

- [ ] **Step 4: Commit**

```bash
git add includes/critical/home.css includes/critical/tour.css
git commit -m "fix: add metric-matched fallback font to reduce Montserrat swap CLS (home, tour)"
```

---

### Task 2: Add fallback font to `content.css` (10 duplicated occurrences)

**Files:**
- Modify: `includes/critical/content.css`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: nothing consumed by later tasks.

`content.css` has a pre-existing structure of 10 concatenated, near-identical chunks (confirmed: exactly 10 occurrences of both the Montserrat `@font-face` and the `body` font-family rule, byte-identical to each other within the file). Manually editing 10 near-identical text blocks is error-prone; use a scripted replacement instead.

- [ ] **Step 1: Run the scripted replacement**

```bash
python3 -c "
path = '/Users/miguelmontero/Documents/superpowers/STAMP/includes/critical/content.css'
content = open(path).read()

# Insert the fallback @font-face immediately after each Montserrat @font-face's
# closing unicode-range (U+FFFD} is a unique, safe anchor: it only appears at the
# end of the Montserrat font-face block in this file, confirmed via prior
# investigation - the icon fonts declared in this same file have no unicode-range
# property at all).
old_anchor = 'U+2212,U+2215,U+FEFF,U+FFFD}'
new_fallback = '@font-face{font-family:\"Montserrat-fallback\";src:local(\"Arial\");size-adjust:112.8307%;ascent-override:85.7923%;descent-override:22.2457%;line-gap-override:0%}'
count_anchor = content.count(old_anchor)
assert count_anchor == 10, f'expected 10 occurrences of the anchor, found {count_anchor}'
content = content.replace(old_anchor, old_anchor + new_fallback)

old_body = 'font-family:Montserrat,Arial,sans-serif'
new_body = 'font-family:Montserrat,\"Montserrat-fallback\",Arial,sans-serif'
count_body = content.count(old_body)
assert count_body == 10, f'expected 10 occurrences of the body rule, found {count_body}'
content = content.replace(old_body, new_body)

open(path, 'w').write(content)
print('Done: replaced', count_anchor, 'font-face anchors and', count_body, 'body font-family rules')
"
```

Expected output: `Done: replaced 10 font-face anchors and 10 body font-family rules`. If either assertion fails (occurrence count isn't exactly 10), STOP and report BLOCKED — do not proceed with a mismatched count, since that would mean the file's structure has changed since this plan was written and the replacement needs to be re-verified against the actual current file before proceeding.

- [ ] **Step 2: Verify the replacement**

```bash
grep -c 'Montserrat-fallback' /Users/miguelmontero/Documents/superpowers/STAMP/includes/critical/content.css
```

Expected: `20` (10 `@font-face` insertions + 10 `body` rule updates).

```bash
php -S localhost:8765 -t /Users/miguelmontero/Documents/superpowers/STAMP &
sleep 1
curl -s http://localhost:8765/contact-us.php | grep -c 'Montserrat-fallback'
kill %1
```

Expected: `20` (the entire 160KB `content.css` file — all 10 chunks — is inlined into every content page's `<head>` today, per the existing `$critical_css_file` mechanism; this is pre-existing behavior, not something this task changes).

- [ ] **Step 3: Commit**

```bash
git add includes/critical/content.css
git commit -m "fix: add metric-matched fallback font to reduce Montserrat swap CLS (content pages)"
```

---

### Task 3: Local functional and visual verification

**Files:** none modified — verification only, unless a real defect is found, in which case fix it in the relevant file from Task 1 or 2 before marking this task done.

**Interfaces:**
- Consumes: Tasks 1 and 2 together (all three critical CSS files).

- [ ] **Step 1: Functional check across all three page types**

Using a local `php -S` server, load one page of each type (e.g. `index.php`, `discover-santiago-city-tour.php`, `contact-us.php`). Confirm:
- Zero console errors or `@font-face` parse warnings.
- `document.fonts` reports the new `"Montserrat-fallback"` face registered (check via `Array.from(document.fonts).some(f => f.family === 'Montserrat-fallback')`).
- The page's visible text renders normally (not obviously broken, oversized, or missing).

- [ ] **Step 2: Visual comparison — interim (fallback) vs. final (Montserrat) state**

Using a headless Puppeteer session (available at `/Users/miguelmontero/.npm/_npx/7d92d9a2d2ccc630/node_modules/puppeteer`), block the Montserrat font file request via CDP (`Network.setRequestInterception` or `page.setRequestInterception`) to force the page to stay in the fallback-rendered state indefinitely, and screenshot each of the 3 pages' above-the-fold text. Then screenshot the same 3 pages with the font allowed to load normally (final Montserrat state). Compare: text should occupy visually similar space in both screenshots (same rough line count, no dramatically different sizing) — some minor letterform differences are expected and fine (Arial and Montserrat are different typefaces), but no gross layout difference (e.g., text wrapping to a different number of lines, or a noticeably different overall text block height).

- [ ] **Step 3: Record findings**

If Steps 1-2 pass, proceed to Task 4. If a real defect is found (e.g., `Montserrat-fallback` not registering, broken CSS parse, grossly mismatched fallback sizing), fix it in the relevant file from Task 1/2 and re-run the failing check before proceeding.

---

### Task 4: Deploy and confirm production

**Files:** none modified — deployment/verification only.

**Interfaces:**
- Consumes: all commits from Tasks 1-3.

- [ ] **Step 1: Push to `main`**

```bash
git push origin main
```

- [ ] **Step 2: Ask the user to pull (and clear the Cloudflare cache) on the HostGator production server**

This site deploys via manual `git pull` on the production server. Per the incident encountered in the prior tour-card lazy-load plan, Cloudflare edge-caches static assets for up to 4 hours (`cache-control: public, max-age=14400`) and does not automatically invalidate on a git-pull deploy — ask the user to both pull AND clear the Cloudflare cache (or at minimum verify via `curl -sI` that the relevant page's HTML reflects the new CSS, not a stale cached copy), then confirm once done. Note: these critical CSS files are inlined directly into each page's HTML `<head>` (not served as standalone cacheable files), so the specific stale-`.js`-file failure mode from the prior incident may not apply the same way here — but the PAGE HTML itself could still be edge-cached under some Cloudflare page-rule configurations, so verify directly rather than assuming either way.

- [ ] **Step 3: Confirm production — CLS re-measurement (the actual regression test)**

Once the user confirms the pull, using the same cold-browser-context methodology established in this session's investigation (fresh incognito `browser.createBrowserContext()` per run, NOT sequential page loads in one browser context, since a warm font cache silently masks the swap-timing effect and produced misleadingly clean readings earlier in this investigation) — measure CLS on all three page types against the live production URLs, at least 3 cold runs per page, at a 390×844 mobile viewport throttled to 1.6Mbps/150ms latency. Compare against this plan's baseline: homepage 0.0635, tour page 0.0310, content page 0.0070. Report the actual post-fix numbers — expect all three to drop substantially (ideally near-zero), but per this session's established practice, verify with a real measurement rather than assuming the metric-matching math alone guarantees the result.

- [ ] **Step 4: Confirm production — visual spot check**

Screenshot the same 3 production pages at a mobile viewport and visually confirm text renders correctly and matches expectations (no obviously broken/oversized/undersized text, no console errors).
