# Stylesheet Priority media=print Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the LCP-critical hero image genuinely win the bandwidth contest against deferred stylesheets, by converting them from `<link rel="preload" as="style" fetchpriority="low">` (which Chrome/Blink caps at the `High` priority tier, tying with the image, regardless of the `low` hint) to the `media="print"` onload-swap idiom (which reaches Blink's genuine `Low` tier).

**Architecture:** Change the `<link>` tag syntax in the shared `includes/head.php` preload block and in every page's own `css/timeline.css` reference. Pure loading-mechanism change — no CSS content changes, no change to what styles ultimately apply, only when/how they're fetched.

**Tech Stack:** Plain PHP includes, no build step.

## Global Constraints

- The `<noscript>` fallback for every converted stylesheet stays functionally equivalent (a plain blocking `<link rel="stylesheet">`) — JS-disabled visitors must see fully-styled pages exactly as before.
- The conditional branching structure in `includes/head.php` (which stylesheet-loading path runs based on `$critical_css_file`, `$vendor_css_variant`) is unchanged — only the `<link>` tag syntax inside each branch changes.
- `shopping.php` and the checkout flow are out of scope — they don't use `includes/head.php`, untouched by this plan.
- No change to any CSS file's content — only to how/when the browser fetches it.
- Every occurrence of the old pattern (`rel="preload" ... fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'"`) becomes: `rel="stylesheet" ... media="print" onload="this.media='all';this.onload=null;"`.

---

### Task 1: Convert the shared `includes/head.php` preload block

**Files:**
- Modify: `includes/head.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by other tasks — this is the primary, highest-impact fix, independent of Task 2's `timeline.css` changes.

- [ ] **Step 1: Replace the preload block**

Find this exact block (verify it still matches before editing — if not, stop and report; this is `includes/head.php` starting around line 119):

```php
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<!-- Pages with matching critical CSS above already have everything needed
     for first paint inlined, so these stylesheets are preloaded with
     fetchpriority="low" instead of render-blocking <link rel="stylesheet">
     tags. In Chrome/Blink, fetchpriority="low" on a preloaded stylesheet only
     demotes it from the VeryHigh bucket (which render-blocking-style
     preloads get) down to High - it does NOT reach Blink's Low tier. Before
     this fix, these stylesheets (6 on pages using the full vendors.css, or
     7 on pages using the split vendors-core + vendors-home/tour variants)
     sat at VeryHigh while the LCP image (with
     fetchpriority="high") sat at High, so the image was strictly outranked
     by every stylesheet - a real priority inversion, not mere "same tier"
     contention. This fix brings the stylesheets down to High, tying them
     with the image's own explicit preload below (also fetchpriority="high"),
     which removes the inversion so the image gets a fair share of bandwidth
     instead of being starved below the sheets entirely. It does not achieve
     true separation (image outright beating the sheets); that would require
     a different technique, e.g. the media="print" onload-swap idiom, which
     does reach Blink's Low tier, if ever pursued as a follow-up. Measured via
     CDP against actual Chrome priority buckets. See
     docs/superpowers/specs/2026-08-02-tour-pages-lcp-priority-fix-design.md. -->
<link rel="preload" href="/fonts/fonts.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
<!-- COMMON CSS -->
<link rel="preload" href="/css/bootstrap.min.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bootstrap.min.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/style.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/style.css" rel="stylesheet"></noscript>
<?php if (!empty($vendor_css_variant) && in_array($vendor_css_variant, ['home', 'tour'], true)): ?>
<link rel="preload" href="/css/vendors-core.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/vendors-<?= $vendor_css_variant ?>.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-<?= $vendor_css_variant ?>.css" rel="stylesheet"></noscript>
<?php elseif (!empty($vendor_css_variant) && $vendor_css_variant === 'core'): ?>
<link rel="preload" href="/css/vendors-core.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<?php else: ?>
<link rel="preload" href="/css/vendors.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<?php endif; ?>
<link rel="preload" href="/css/bs-icon-font/bootstrap-icons.min.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"></noscript>
<!-- CUSTOM CSS -->
<link rel="preload" href="/css/custom.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/custom.css" rel="stylesheet"></noscript>
<?php else: ?>
```

Replace it with (only the block up to and including the `<?php else: ?>` — everything after that `else` in the file, which is the plain-blocking fallback for pages without critical CSS, stays completely untouched):

```php
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<!-- Pages with matching critical CSS above already have everything needed
     for first paint inlined, so these stylesheets load via the
     media="print" onload-swap idiom instead of render-blocking
     <link rel="stylesheet"> tags. An earlier fix (2026-08-02) tried
     rel="preload" + fetchpriority="low", but that only demotes Chrome/
     Blink's scheduling from the VeryHigh bucket down to High - it never
     reaches the real Low tier, so these stylesheets stayed tied with the
     LCP image's own fetchpriority="high" preload below, splitting
     bandwidth between them under a throttled connection instead of the
     image getting the share it needs. media="print" does reach Blink's
     genuine Low tier (the browser doesn't need it for the current
     screen-rendering context), so the image can now win outright. Once
     the file loads, onload flips media to 'all' and the browser applies
     it immediately, same as before. See
     docs/superpowers/specs/2026-08-08-stylesheet-priority-media-print-design.md
     and docs/superpowers/specs/2026-08-02-tour-pages-lcp-priority-fix-design.md
     for the full history. -->
<link rel="stylesheet" href="/fonts/fonts.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
<!-- COMMON CSS -->
<link rel="stylesheet" href="/css/bootstrap.min.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/bootstrap.min.css" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/css/style.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/style.css" rel="stylesheet"></noscript>
<?php if (!empty($vendor_css_variant) && in_array($vendor_css_variant, ['home', 'tour'], true)): ?>
<link rel="stylesheet" href="/css/vendors-core.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<link rel="stylesheet" href="/css/vendors-<?= $vendor_css_variant ?>.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/vendors-<?= $vendor_css_variant ?>.css" rel="stylesheet"></noscript>
<?php elseif (!empty($vendor_css_variant) && $vendor_css_variant === 'core'): ?>
<link rel="stylesheet" href="/css/vendors-core.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<?php else: ?>
<link rel="stylesheet" href="/css/vendors.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<?php endif; ?>
<link rel="stylesheet" href="/css/bs-icon-font/bootstrap-icons.min.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"></noscript>
<!-- CUSTOM CSS -->
<link rel="stylesheet" href="/css/custom.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/css/custom.css" rel="stylesheet"></noscript>
<?php else: ?>
```

- [ ] **Step 2: Verify with `php -l`**

```bash
php -l includes/head.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify no `fetchpriority="low"` remains in the converted block, and `media="print"` appears 8 times**

```bash
grep -c 'fetchpriority="low"' includes/head.php
grep -c 'media="print"' includes/head.php
```

Expected: `0` for `fetchpriority="low"` (the converted block had 8 occurrences before — 6 stylesheets across the 3 vendor-bundle branches counted individually, i.e. fonts/bootstrap/style/bs-icon-font/custom = 5, plus vendors-core+variant = 2 in the home/tour branch, or vendors-core alone = 1 in the core branch, or vendors.css alone = 1 in the default branch — the exact count depends on which branch is "active" in the raw source since all 3 branches exist in the file text regardless — just confirm `fetchpriority="low"` is fully gone and `media="print"` covers every `<link rel="stylesheet"` this task added). Read the file directly to visually confirm all 8 `<link>` tags (5 shared + 3 vendor-branch variants) were converted if the grep counts are ambiguous.

- [ ] **Step 4: Commit**

```bash
git add includes/head.php
git commit -m "perf: switch deferred stylesheets to media=print onload-swap for real low priority"
```

---

### Task 2: Convert `css/timeline.css` references site-wide

**Files:**
- Modify: `discover-santiago-city-tour.php`
- Modify: `maipo-valley-wine-tour-santiago.php`
- Modify: `portillo-inca-lagoon-andes-mountains-vineyard.php`
- Modify: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`
- Modify: `cruise-transfer.php`
- Modify: `contact-us.php`
- Modify: `refunds-cancellations.php`
- Modify: `privacy.php`

**Interfaces:**
- Consumes: nothing new (independent of Task 1's `includes/head.php` change — `timeline.css` is loaded separately, per-page, not through the shared block).
- Produces: nothing consumed by other tasks.

**Context:** `css/timeline.css` is currently loaded two different ways. Both become the same target pattern.

- [ ] **Step 1: `discover-santiago-city-tour.php`**

Find this exact block (verify it still matches before editing — if not, stop and report):

```html
  <link rel="preload" href="css/timeline.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
```

Replace with:

```html
  <link rel="stylesheet" href="css/timeline.css" media="print" onload="this.media='all';this.onload=null;">
  <noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
```

- [ ] **Step 2: `maipo-valley-wine-tour-santiago.php`**

Same exact before/after as Step 1.

- [ ] **Step 3: `portillo-inca-lagoon-andes-mountains-vineyard.php`**

Same exact before/after as Step 1.

- [ ] **Step 4: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`**

Same exact before/after as Step 1.

- [ ] **Step 5: `cruise-transfer.php`**

Same exact before/after as Step 1 (this file's version is indented with 2 spaces, matching the others — verify before editing).

- [ ] **Step 6: `contact-us.php`**

Find this exact line (verify it still matches before editing — if not, stop and report):

```html
  <link href="css/timeline.css" rel="stylesheet"/>
```

Replace with:

```html
  <link rel="stylesheet" href="css/timeline.css" media="print" onload="this.media='all';this.onload=null;">
  <noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
```

- [ ] **Step 7: `refunds-cancellations.php`**

Same exact before/after as Step 6.

- [ ] **Step 8: `privacy.php`**

Same exact before/after as Step 6.

- [ ] **Step 9: Verify with `php -l` on all 8 files**

```bash
php -l discover-santiago-city-tour.php
php -l maipo-valley-wine-tour-santiago.php
php -l portillo-inca-lagoon-andes-mountains-vineyard.php
php -l "valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php"
php -l cruise-transfer.php
php -l contact-us.php
php -l refunds-cancellations.php
php -l privacy.php
```

Expected: `No syntax errors detected` on all 8.

- [ ] **Step 10: Verify no `fetchpriority="low"` or plain-blocking `timeline.css` link remains anywhere**

```bash
grep -rn "timeline.css" *.php
```

Expected: every match uses `rel="stylesheet" href="css/timeline.css" media="print"`, immediately followed by a `<noscript>` line. No `fetchpriority="low"`, no bare `<link href="css/timeline.css" rel="stylesheet"/>` without the print/noscript pair.

- [ ] **Step 11: Commit**

```bash
git add discover-santiago-city-tour.php maipo-valley-wine-tour-santiago.php portillo-inca-lagoon-andes-mountains-vineyard.php "valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php" cruise-transfer.php contact-us.php refunds-cancellations.php privacy.php
git commit -m "perf: switch timeline.css to media=print onload-swap site-wide"
```

---

### Task 3: Local functional/visual verification

**Files:**
- None created or modified — verification only.

**Interfaces:**
- Consumes: Tasks 1-2's changes.
- Produces: functional/visual evidence for the task reviewer and final review. Does NOT attempt to measure LCP timing locally — established this session that CDP network throttling doesn't meaningfully throttle `localhost` traffic, so local timing numbers are not trustworthy for this kind of fix. This task only confirms nothing broke; Task 4 measures real timing against production.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP && php -S localhost:8080 -t .
```

- [ ] **Step 2: Load a representative page from every affected category and check for console/page errors**

Using Puppeteer (reuse the install at `/Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen/node_modules`), load each of:
- `index.php` (homepage, `home` variant)
- `maipo-valley-wine-tour-santiago.php` (`tour` variant + timeline.css)
- `cruise-transfer.php` (default/full `vendors.css` + timeline.css)
- `contact-us.php` (`core` variant + timeline.css, previously fully-blocking)
- `privacy.php` (`core` variant + timeline.css, previously fully-blocking)
- `gallery.php` (`core` variant, no timeline.css — confirms the head.php fix alone doesn't need timeline.css present to work correctly)

Capture `console`/`pageerror` events. Expected: zero errors on any page.

- [ ] **Step 3: Confirm every converted stylesheet actually applies**

For each of the 6 pages above, use `page.evaluate` to check computed styles are correctly applied after the page settles (e.g., confirm `getComputedStyle(document.body).fontFamily` includes the expected font stack from `style.css`, confirm a Bootstrap-styled element has its expected computed styles, confirm an icon element's font-family resolves to `fontello`/`icon_set_1`/`bootstrap-icons` as appropriate) — this is the concrete check that the `media="print"` → `onload` → `media="all"` swap is actually completing and applying styles, not just that the page loads without JS errors.

- [ ] **Step 4: Visual sanity check**

Take a full-page screenshot of each of the 6 pages (mobile viewport 390x844) and confirm nothing looks unstyled or broken — header/nav, hero section, icons, and (where present) the timeline component all render normally.

- [ ] **Step 5: Confirm the network-level change**

Via `page.on('response', ...)` on 2-3 of the pages, confirm the converted stylesheets are still requested and return 200 (the mechanism change doesn't stop them from loading, just changes priority/timing) — this rules out a typo in the conversion silently breaking the fetch entirely.

- [ ] **Step 6: Stop the local PHP server**

This task creates no repo changes — nothing to commit. Confirm `git status` is clean before reporting DONE.

---

### Task 4: Deploy and confirm production, with controlled LCP measurement

**Files:**
- None — deployment and verification only.

- [ ] **Step 1: Push the commits**

```bash
git push
```

(If rejected due to upstream changes, `git fetch origin`, confirm no overlap with the files this plan touches, `git merge origin/main --no-edit`, then push again.)

- [ ] **Step 2: Ask the user to pull on the server**

Tell the user: "Pushed. Please pull this on production, then let me know once it's live so I can measure the real impact."

Wait for confirmation before continuing.

- [ ] **Step 3: Spot-check functional correctness on live production**

Load `contact-us.php`, a tour page, and the homepage on `https://stampstour.com/...` with a cache-busting query param (use a real browser via Puppeteer, not `curl` — this site's Cloudflare bot-protection returns a 409 challenge page to plain `curl` requests, established earlier this session; a headless-but-real browser passes it normally). Confirm no console errors and that pages look correctly styled, not broken/unstyled.

- [ ] **Step 4: Controlled LCP measurement against production**

Do NOT trust a single before/after comparison taken minutes apart — this session already found that host-load drift alone produced a false "regression" reading once. Use the same interleaved A/B approach already proven this session: temporarily revert `includes/head.php` (and the relevant `timeline.css` line) to the pre-this-plan version via `git show <base-commit>:<file>` piped to a local copy of the *production* server directory is not possible (this plan's fix runs on the live server, not locally) — so instead, perform the controlled comparison against a LOCAL server for the *relative* A/B signal (even though local absolute timing isn't trustworthy, the relative delta between two variants measured back-to-back on the same local server, same host-load conditions, is a valid signal — this is exactly how the previous plan's A/B resolved its own ambiguity), AND separately capture a real production LCP reading (single measurement, clearly labeled as directional, not a controlled A/B) for the record.

Concretely:
1. On a local `php -S` server, run an interleaved A/B (3+ rounds) between the pre-this-plan `includes/head.php`/timeline.css state (checked out via `git show <Task-1-base-commit>:<file>`) and the post-this-plan state, measuring LCP on `contact-us.php` under CDP-throttled conditions each time. This establishes the *relative* improvement (or lack thereof) with host-load drift controlled for, even though the absolute local numbers aren't representative of real-world timing.
2. Separately, take one real production LCP measurement (throttled CDP, cache-busted, confirmed via `cf-cache-status`/`last-modified` that you're hitting the live deploy) on `contact-us.php`, and compare it against this session's most recent production baseline for the same page (~5.5s, captured after the `common_scripts_min.js` removal plan).
3. Report both: the controlled local A/B's relative signal, and the single production reading, being explicit about which is which and not overstating the single production reading's precision.

- [ ] **Step 5: Report results to the user**

Summarize whether this fix delivered the larger improvement expected (recall: the `common_scripts_min.js` removal alone only closed ~2-3% of the gap; this fix targets the documented, larger priority-inversion mechanism) and whether any further LCP work looks warranted after this.

## Verification

1. `php -l` passes on all 9 modified files (Task 1, Task 2).
2. No `fetchpriority="low"` remains anywhere the plan converted; `media="print"` + matching `onload` present everywhere expected (Task 1, Task 2).
3. Local functional/visual verification: zero console errors, converted stylesheets confirmed actually applying (not just requested), no visual breakage, across all 6 representative pages (Task 3).
4. Production spot-check confirms no functional/visual regression (Task 4).
5. Controlled local A/B (host-load-drift-resistant) plus a labeled single production reading both reported, giving an honest picture of real-world impact (Task 4).

## Risks

- **If `onload` never fires, a stylesheet never applies** — not a new risk (see spec's Risks section), already true of the current production pattern.
- **Site-wide blast radius** — nearly every public page routes through the changed code. Mitigated by Task 3's broad functional/visual check across every page category before deploying, and Task 4's spot-check immediately after.
- **This fix's real-world impact is a documented hypothesis (from the 2026-02-02 spec), not yet directly proven on this exact codebase** — Task 4's measurement approach exists specifically to get an honest answer, including the possibility that this fix helps less than expected, which would itself be useful, actionable information rather than an assumed win.
