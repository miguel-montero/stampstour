# Content Pages vendors-core-only CSS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cut ~104KB of dead CSS per page (48% of the vendor bundle) on the 6 content pages (contact-us, privacy, refunds-cancellations, blog, blog-post, gallery) by loading `css/vendors-core.css` alone instead of the full `css/vendors.css`, using the existing core/variant split infrastructure.

**Architecture:** `includes/head.php` already supports loading `vendors-core.css` + a variant file (`home`/`tour`) via `$vendor_css_variant`. Add a third case — `$vendor_css_variant = 'core'` — that loads `vendors-core.css` alone, no second file. Set that flag on each of the 6 pages. Confirmed via direct investigation (grepping every class name from Magnific Popup, the switch toggle, Slider Pro, daterangepicker, and WOW/Animate.css against all 6 pages plus their shared `header.php`/`footer.php`/`cookie-banner.php` includes, and confirming `vendors-core.css` itself contains zero traces of any of those libraries — it's icon fonts only) that these 6 pages use nothing beyond what `vendors-core.css` already provides.

**Tech Stack:** Plain PHP includes, no build step.

## Global Constraints

- Only `includes/head.php` and the 6 named pages change. No CSS file content changes — `vendors-core.css`, `vendors.css`, `vendors-home.css`, `vendors-tour.css` are all used as-is, unmodified.
- The existing `home`/`tour` variant behavior in `includes/head.php` must be completely unaffected — every page that doesn't set `$vendor_css_variant` (or sets it to `'home'`/`'tour'`) must render byte-identical HTML to before this change.
- `$vendor_css_variant = 'core'` must be added to exactly these 6 files: `contact-us.php`, `privacy.php`, `refunds-cancellations.php`, `blog.php`, `blog-post.php`, `gallery.php`. No other page.
- `includes/head.php` has two near-duplicate CSS-loading blocks (one for pages with `$critical_css_file` set, using non-blocking preload+swap; one for pages without it, using plain render-blocking `<link>` tags) — both need the new `'core'` case, since 5 of the 6 pages use the preload path and `blog-post.php` currently uses the plain path.

---

### Task 1: Add `'core'` variant support to `includes/head.php`

**Files:**
- Modify: `includes/head.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: the `$vendor_css_variant = 'core'` contract that Task 2's 6 pages rely on.

- [ ] **Step 1: Update the doc comment**

Find this doc comment near the top of the file (verify it still matches before editing — if not, stop and report):

```php
 *   $vendor_css_variant (optional) - 'home' or 'tour'. When set, loads
 *                        css/vendors-core.css + css/vendors-{variant}.css
 *                        instead of the full css/vendors.css (see
 *                        docs/superpowers/specs/2026-08-03-homepage-tour-bundle-split-design.md).
 *                        Unset (the default) preserves today's behavior
 *                        exactly, so every page that doesn't opt in is
 *                        untouched.
```

Replace it with:

```php
 *   $vendor_css_variant (optional) - 'home', 'tour', or 'core'. 'home'/'tour'
 *                        load css/vendors-core.css + css/vendors-{variant}.css
 *                        instead of the full css/vendors.css (see
 *                        docs/superpowers/specs/2026-08-03-homepage-tour-bundle-split-design.md).
 *                        'core' loads css/vendors-core.css alone, no second
 *                        file - for pages that use icon classes (fontello/
 *                        icon_set_1, all that's in vendors-core.css) but
 *                        nothing from Magnific Popup, the switch toggle,
 *                        Slider Pro, daterangepicker, or WOW/Animate.css (see
 *                        docs/superpowers/plans/2026-08-08-content-pages-vendors-core-only.md
 *                        for how this was verified page-by-page). Unset (the
 *                        default) preserves today's behavior exactly, so
 *                        every page that doesn't opt in is untouched.
```

- [ ] **Step 2: Update the preload-path CSS block**

Find this exact block (verify it still matches before editing — if not, stop and report):

```php
<?php if (!empty($vendor_css_variant) && in_array($vendor_css_variant, ['home', 'tour'], true)): ?>
<link rel="preload" href="/css/vendors-core.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-core.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/vendors-<?= $vendor_css_variant ?>.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors-<?= $vendor_css_variant ?>.css" rel="stylesheet"></noscript>
<?php else: ?>
<link rel="preload" href="/css/vendors.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<?php endif; ?>
```

Replace it with:

```php
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
```

(Note, corrected post-review: guard with `!empty($vendor_css_variant) &&` before the `=== 'core'` check — most pages never set this variable at all, so an unguarded read throws a PHP undefined-variable warning. Every other optional variable in this file follows this same `!empty()`-guard convention.)

- [ ] **Step 3: Update the plain render-blocking-path CSS block**

Find this exact block (verify it still matches before editing — if not, stop and report):

```php
<?php if (!empty($vendor_css_variant) && in_array($vendor_css_variant, ['home', 'tour'], true)): ?>
<link href="/css/vendors-core.css" rel="stylesheet"/>
<link href="/css/vendors-<?= $vendor_css_variant ?>.css" rel="stylesheet"/>
<?php else: ?>
<link href="/css/vendors.css" rel="stylesheet"/>
<?php endif; ?>
```

Replace it with:

```php
<?php if (!empty($vendor_css_variant) && in_array($vendor_css_variant, ['home', 'tour'], true)): ?>
<link href="/css/vendors-core.css" rel="stylesheet"/>
<link href="/css/vendors-<?= $vendor_css_variant ?>.css" rel="stylesheet"/>
<?php elseif (!empty($vendor_css_variant) && $vendor_css_variant === 'core'): ?>
<link href="/css/vendors-core.css" rel="stylesheet"/>
<?php else: ?>
<link href="/css/vendors.css" rel="stylesheet"/>
<?php endif; ?>
```

(Same correction as Step 2: guard with `!empty($vendor_css_variant) &&` before the `=== 'core'` check.)

- [ ] **Step 4: Verify with `php -l`**

```bash
php -l includes/head.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 5: Verify the new branches are present and the existing ones are untouched**

```bash
grep -n "vendor_css_variant" includes/head.php
```

Expected: the doc comment block plus 4 conditional checks (2 in the preload path, 2 in the plain path — each path now has `in_array(...['home','tour']...)` and `$vendor_css_variant === 'core'`).

- [ ] **Step 6: Commit**

```bash
git add includes/head.php
git commit -m "perf: add vendors-core-only CSS variant option to head.php"
```

---

### Task 2: Set `$vendor_css_variant = 'core'` on the 6 content pages

**Files:**
- Modify: `contact-us.php`
- Modify: `privacy.php`
- Modify: `refunds-cancellations.php`
- Modify: `blog.php`
- Modify: `blog-post.php`
- Modify: `gallery.php`

**Interfaces:**
- Consumes: Task 1's `'core'` variant support in `includes/head.php`.
- Produces: nothing consumed by other tasks.

- [ ] **Step 1: `contact-us.php`**

Find this line (verify it still matches before editing — if not, stop and report):

```php
$critical_css_file = __DIR__ . '/includes/critical/content.css';
```

Add immediately after it:

```php
$vendor_css_variant = 'core';
```

- [ ] **Step 2: `privacy.php`**

Same as Step 1 — find `$critical_css_file = __DIR__ . '/includes/critical/content.css';` and add `$vendor_css_variant = 'core';` immediately after it.

- [ ] **Step 3: `refunds-cancellations.php`**

Same pattern.

- [ ] **Step 4: `blog.php`**

Same pattern.

- [ ] **Step 5: `gallery.php`**

Same pattern.

- [ ] **Step 6: `blog-post.php`**

This file doesn't set `$critical_css_file` and has a different structure (branches on whether a post was found). Find this exact line (verify it still matches before editing — if not, stop and report):

```php
$page_canonical = 'https://stampstour.com/blog/' . rawurlencode($slug);
```

Add immediately after it (before the `if ($post) { ... } else { ... }` block, since it applies regardless of whether the post is found):

```php
$vendor_css_variant = 'core';
```

- [ ] **Step 7: Verify with `php -l` on all 6 files**

```bash
php -l contact-us.php
php -l privacy.php
php -l refunds-cancellations.php
php -l blog.php
php -l blog-post.php
php -l gallery.php
```

Expected: `No syntax errors detected` on all 6.

- [ ] **Step 8: Verify the flag is set exactly once per file**

```bash
grep -c "vendor_css_variant = 'core'" contact-us.php privacy.php refunds-cancellations.php blog.php blog-post.php gallery.php
```

Expected: `1` for each of the 6 files.

- [ ] **Step 9: Commit**

```bash
git add contact-us.php privacy.php refunds-cancellations.php blog.php blog-post.php gallery.php
git commit -m "perf: load vendors-core.css only (not full vendors.css) on 6 content pages"
```

---

### Task 3: Local verification

**Files:**
- None created or modified — verification only.

**Interfaces:**
- Consumes: Tasks 1-2's changes.
- Produces: visual/functional and network-weight evidence for the task reviewer and final review.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP && php -S localhost:8080 -t .
```

- [ ] **Step 2: Confirm each of the 6 pages now loads `vendors-core.css` and NOT `vendors.css`**

Using Puppeteer (reuse the install at `/Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen/node_modules`), load each of the 6 pages from `http://localhost:8080/...` and inspect network requests (`page.on('response', ...)`): confirm a request for `/css/vendors-core.css` exists and NO request for `/css/vendors.css` exists, for all 6 pages. Also confirm `blog-post.php` needs `?slug=<real-or-placeholder>` the same way Task 2 of the prior `content-pages-script-trim` plan handled it (check `blog.php`'s output for a real slug, or use a placeholder and confirm the 404 branch still loads the CSS variant correctly, since Task 1's change to `blog-post.php` applies before the found/not-found branch).

- [ ] **Step 3: Visual sanity check on all 6 pages**

Take a full-page screenshot of each page (mobile viewport 390x844) and confirm: icons render correctly (nav icons, social icons, any `icon-*`/`icon_set_1_*` class usage), no visibly broken/missing styling, no layout differences from what these pages looked like before (spot-check against the earlier `content-pages-script-trim` plan's screenshots if still available, or just confirm nothing looks obviously broken).

- [ ] **Step 4: Console/page error check**

Same as the prior plan's Task 2 — confirm zero JS console errors or page errors on all 6 pages (a missing CSS class reference alone wouldn't throw a JS error, but this catches anything else affected).

- [ ] **Step 5: Confirm other pages are unaffected**

Spot-check 2 pages that use the `home`/`tour` variants (e.g. `index.php`, one tour page) and 1 page that still uses the full `vendors.css` default (e.g. `cruise-transfer.php` or `shopping.php` — pick one that doesn't set `$vendor_css_variant` at all) to confirm their CSS loading is completely unchanged: `index.php`/tour pages still request `vendors-core.css` + their variant file, the untouched page still requests full `vendors.css`, none request an unexpected combination.

- [ ] **Step 6: Confirm network weight reduction**

For one of the 6 pages, compare `vendors-core.css`'s response size against `vendors.css`'s size (already known: ~114KB vs ~218KB) and confirm the page's total CSS payload dropped by roughly that difference.

- [ ] **Step 7: Stop the local PHP server**

---

### Task 4: Deploy and confirm production

**Files:**
- None — deployment and verification only.

- [ ] **Step 1: Push the commits**

```bash
git push
```

(If rejected due to upstream changes, `git fetch origin`, confirm no overlap with `includes/head.php` or the 6 page files, `git merge origin/main --no-edit`, then push again.)

- [ ] **Step 2: Ask the user to pull on the server**

Tell the user: "Pushed. Please pull this on production, then let me know once it's live so I can do a final check."

Wait for confirmation before continuing.

- [ ] **Step 3: Spot-check on live production**

Load 2-3 of the 6 pages (e.g. `contact-us.php`, `gallery.php`) on `https://stampstour.com/...` with a cache-busting query param, confirm `vendors-core.css` is requested and `vendors.css` is not, confirm no console errors, and visually confirm icons/styling look correct.

## Verification

1. `php -l` passes on all 7 modified files (Task 1, Task 2).
2. The `'core'` variant branch is present in both CSS-loading paths in `includes/head.php`, existing `home`/`tour`/default behavior untouched (Task 1).
3. All 6 pages set `$vendor_css_variant = 'core'` exactly once (Task 2).
4. Local verification confirms all 6 pages load `vendors-core.css` and not `vendors.css`, zero console errors, no visual regressions, and pages using other variants (or no variant) are completely unaffected (Task 3).
5. Production spot-check confirms the same result on live URLs (Task 4).

## Risks

- **Low risk overall** — this reuses existing, already-proven split infrastructure (the `home`/`tour` variants have been live and working since an earlier plan) and the "nothing beyond icons is needed" claim was verified by grepping actual class usage across all 6 pages and their shared includes, plus confirming `vendors-core.css` itself contains zero traces of the libraries being dropped.
- **`blog-post.php`'s dynamic content** (individual blog posts, not just the shared template) could theoretically use a class from the dropped libraries that a static grep of the PHP template wouldn't catch, since post content comes from a database. Task 3's visual check should include loading at least one real, published post (not just the 404 branch) to rule this out — if the plan's Task 3 Step 2 only tests the 404 branch, extend it to also test a real post before considering this task done.
