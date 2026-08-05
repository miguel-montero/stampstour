# Split Homepage/Tour-Page Vendor Bundles Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single shared `js/common_scripts_min.js`/`css/vendors.css` on the homepage and the 4 uniform tour pages with a 3-way split (shared core + page-type-specific extras), cutting homepage's combined JS+CSS payload by ~38% and the tour pages' by ~16%, with zero visible or functional change on any page — including the 7+ pages that keep loading the original unified bundles unchanged.

**Architecture:** All files touched by this plan have already been generated, edited, and verified during planning (matching this session's established practice for large-asset changes) — this plan's tasks commit and further verify that already-correct working state, not regenerate it from scratch.

**Tech Stack:** Plain JS/CSS, no build step in the deployed site. `terser` (JS) and `clean-css-cli` (CSS) were used as one-time local scratch tools, same as Phase 1 (`docs/superpowers/plans/2026-08-03-remove-dead-vendor-code.md`) — nothing from that tooling ships or is added to the repo.

## Global Constraints

- Full design rationale, byte-count derivation, and section-boundary evidence: `docs/superpowers/specs/2026-08-03-homepage-tour-bundle-split-design.md`. Read it before starting — this plan assumes its Context/Design sections as background.
- Exact changes already made, verified, and present in the working tree:
  - **6 new files generated** from the existing, unmodified `js/common_scripts.js` (11,170 lines) and `css/vendors.unminified.css` (17,132 lines) via `terser -c -m` / `clean-css-cli`:
    - `js/vendors-core.min.js` (from `common_scripts.js` lines 1–6,318, the Bootstrap bundle) — 80,558 bytes
    - `js/vendors-home.min.js` (from lines 8,599–9,114, WOW.js) — 8,329 bytes
    - `js/vendors-tour.min.js` (from lines 6,319–8,598 + 9,115–11,170, Parallax + Magnific Popup + daterangepicker/moment) — 119,110 bytes
    - `css/vendors-core.css` (from `vendors.unminified.css` lines 1–11,156, icon fonts) — 114,708 bytes
    - `css/vendors-home.css` (from lines 11,157–14,653, Animate.css) — 58,675 bytes
    - `css/vendors-tour.css` (from lines 14,654–17,132, Magnific Popup/switch/Slider Pro/daterangepicker CSS) — 40,812 bytes
    - The 3 CSS files' combined bytes (214,195) exactly match the original `vendors.css`; concatenating the 3 files reproduces it byte-for-byte, proving no boundary gap or overlap for CSS. **Correction (found during final whole-branch review): the equivalent JS claim was wrong.** The 3 JS files' combined bytes (207,997) are 185 bytes MORE than the original `common_scripts_min.js` (207,812) — terser mangles less aggressively across 3 separate top-level scopes than in one combined minify, so byte-sum equality doesn't hold for JS and was never valid evidence for it. JS equivalence was instead confirmed by the final reviewer via a string-literal census (742 distinct literals in the original, 742 in the union of the 3 variants) and by the source line ranges above summing to exactly 11,170 (the original file's line count) with no gap or overlap.
  - **`js/functions.js`: 4 call sites guarded**, matching Phase 1's established pattern, because this split makes plugins conditionally absent in *both* directions (WOW missing on tour pages, Parallax/Magnific Popup/hideShowPassword missing on homepage) rather than Phase 1's single direction:
    - `new WOW().init();` → wrapped in `if (typeof WOW !== 'undefined') { ... }`
    - `$('.parallax-window').parallax(...)` → wrapped in `if ($.fn.parallax) { ... }`
    - Both `.magnificPopup(...)` calls inside the main `$(function(){...})` block (`.video` and `.magnific-gallery`) → wrapped together in one `if ($.fn.magnificPopup) { ... }`
    - The standalone `#access_link` `.magnificPopup(...)` call (outside that block) → wrapped in its own `if ($.fn.magnificPopup) { ... }`
    - `$('#password').hidePassword(...)` → wrapped in `if ($.fn.hidePassword) { ... }` — found during planning (not in the original design spec's guard list): `hideShowPassword` lives in the same source range as daterangepicker (9,115–11,170), so it's present in `vendors-tour.js` but absent from `vendors-home.js`. No page currently has `id="password"` (confirmed via repo-wide grep), so this call is a no-op everywhere today, but it must still be guarded since the plugin method itself won't exist on homepage.
    - `node --check js/functions.js` passes.
  - **`includes/head.php`**: new optional `$vendor_css_variant` variable (`'home'` | `'tour'` | unset). When set, both the critical-CSS branch and the legacy branch load `css/vendors-core.css` + `css/vendors-{variant}.css` instead of `css/vendors.css`. Unset (the default) is byte-identical to before — verified via local diff against the pre-change render of `contact-us.php` and `blog.php` (0 differences).
  - **`index.php`**: sets `$vendor_css_variant = 'home';` alongside the existing `$critical_css_file`/`$lcp_preload_image` assignments; script tags changed from `js/common_scripts_min.js` to `js/vendors-core.min.js` + `js/vendors-home.min.js`.
  - **`includes/tour-scripts.php`**: script tags changed the same way, to `js/vendors-core.min.js` + `js/vendors-tour.min.js`. This file is shared by all 4 uniform tour pages, so this one edit covers all of them.
  - **The 4 uniform tour pages** (`maipo-valley-wine-tour-santiago.php`, `discover-santiago-city-tour.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`): each sets `$vendor_css_variant = 'tour';` alongside its existing `$critical_css_file`/`$lcp_preload_image` assignments.
  - Local verification already performed (Puppeteer against `php -S`): zero `pageerror` events on homepage, all 4 tour pages, and `contact-us.php` (an untouched page). Tour pages show only the same pre-existing, unrelated `css/slider-pro.min.css` 404 already known from earlier sessions. Homepage confirms `typeof WOW !== 'undefined'` with 9 `.wow` elements present; tour pages confirm `$.fn.magnificPopup` exists, Slider Pro initializes (`.sp-horizontal`/`.sp-slides` present), and `input.date-pick` has a live `daterangepicker` instance attached. A pre-existing homepage-only hamburger-menu quirk (transform doesn't change on click) was found and confirmed via parity test against the unmodified code (`git stash`) to already exist before this plan's changes — not a regression, not in scope to fix.
  - Untouched pages verified byte-identical before/after via local diff: `contact-us.php`, `blog.php` (neither sets `$vendor_css_variant`, neither references any new file).
- Nothing else changes. `shopping.php`, `login.php`, `admin.php`, `cruise-transfer.php`, `return.php`, `success.php`, `blog.php`, `blog-post.php`, `contact-us.php`, `gallery.php`, `refunds-cancellations.php`, `privacy.php` all keep loading the original `js/common_scripts_min.js`/`css/vendors.css` — those files are **not deleted or modified**, they remain the default output for every page that doesn't opt into a variant.

---

### Task 1: Commit the already-verified changes

**Files:**
- Create: `js/vendors-core.min.js`, `js/vendors-home.min.js`, `js/vendors-tour.min.js`, `css/vendors-core.css`, `css/vendors-home.css`, `css/vendors-tour.css` (already generated)
- Modify: `js/functions.js`, `includes/head.php`, `includes/tour-scripts.php`, `index.php`, `maipo-valley-wine-tour-santiago.php`, `discover-santiago-city-tour.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php` (already edited)

**Interfaces:**
- Consumes: nothing (first task).
- Produces: nothing new for later tasks — Task 2 re-verifies this exact change.

- [ ] **Step 1: Confirm the current working-tree state matches Global Constraints exactly**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
node --check js/functions.js
php -l includes/head.php
php -l index.php
php -l includes/tour-scripts.php
php -l maipo-valley-wine-tour-santiago.php
php -l discover-santiago-city-tour.php
php -l portillo-inca-lagoon-andes-mountains-vineyard.php
php -l valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php
wc -c js/vendors-core.min.js js/vendors-home.min.js js/vendors-tour.min.js
wc -c css/vendors-core.css css/vendors-home.css css/vendors-tour.css
grep -c "vendor_css_variant = 'home'" index.php
grep -c "vendor_css_variant = 'tour'" maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php portillo-inca-lagoon-andes-mountains-vineyard.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php
```

Expected: all `node --check`/`php -l` calls report no errors. `vendors-core.min.js` is 80,558 bytes; `vendors-home.min.js` is 8,329 bytes; `vendors-tour.min.js` is 119,110 bytes. `vendors-core.css` is 114,708 bytes; `vendors-home.css` is 58,675 bytes; `vendors-tour.css` is 40,812 bytes. Each `grep -c` returns `1`.

If any of these don't match, STOP — do not proceed to commit; investigate whether the working tree has diverged from what this plan documents before continuing.

- [ ] **Step 2: Commit**

```bash
git add js/vendors-core.min.js js/vendors-home.min.js js/vendors-tour.min.js \
        css/vendors-core.css css/vendors-home.css css/vendors-tour.css \
        js/functions.js includes/head.php includes/tour-scripts.php index.php \
        maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php \
        portillo-inca-lagoon-andes-mountains-vineyard.php \
        valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php
git commit -m "Split homepage/tour-page vendor bundles into shared core + page-type extras"
```

---

### Task 2: Broader local verification across page types

**Files:**
- None modified — this task only verifies. If a check fails, fix the affected file in place, then re-verify, then re-run Task 1's Step 1 checks before re-committing (`git commit --amend` if the only prior commit was this plan's own Task 1 commit and it hasn't been pushed yet, otherwise a new fix commit).

**Interfaces:**
- Consumes: the committed state from Task 1.
- Produces: verification evidence only.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/index.php
```

Expected: `200`.

- [ ] **Step 2: Re-confirm zero new JS errors and correct plugin presence across all 5 changed pages**

Using Puppeteer, load `index.php`, `maipo-valley-wine-tour-santiago.php`, `discover-santiago-city-tour.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`. Capture `pageerror` and console `error`-level events on each. Expected: zero `pageerror` events on all 5; the 4 tour pages may show only the already-known `css/slider-pro.min.css` 404 (not a new issue).

On `index.php` specifically: confirm `typeof WOW !== 'undefined'` and that at least one `.wow` element exists (there are 9 in the current markup).

On each of the 4 tour pages: confirm `typeof jQuery.fn.magnificPopup !== 'undefined'`, that `.sp-horizontal` or `.sp-slides` exists (Slider Pro initialized), and that `jQuery('input.date-pick').data('daterangepicker')` is defined (daterangepicker attached).

- [ ] **Step 3: Re-confirm the `functions.js` completion signal correctly this time**

Per the correction recorded in Phase 1's final review (`docs/superpowers/plans/2026-08-03-remove-dead-vendor-code.md`, Global Constraints), the `#toTop` scroll button is NOT a valid signal (it's bound upstream of the guarded blocks). This step originally also named `.panel-dropdown` and `.background-image[data-background]` as the downstream proof — **correction (found during this plan's own final whole-branch review): neither exists on any of the 5 pages this plan touches** (`.panel-dropdown` has no markup anywhere in the site; `data-background` only appears on pages this plan doesn't change — `blog.php`, `contact-us.php`, `shopping.php`, etc.). That check was vacuous on every page it was actually run against. The valid signal for THIS plan is zero `pageerror` events alone (already checked in Step 2) — sufficient on its own because `functions.js` is wrapped in `(function($){...})(window.jQuery)`, so a top-level throw surfaces uncaught, and jQuery 3.x's ready pipeline (`jQuery.readyException`) re-throws handler exceptions asynchronously, so a throw inside the file's `$(function(){...})` block also surfaces as `pageerror`. For a future phase, pick a completion signal that actually exists on the pages under test (e.g. the `ul#top_links` hover block, present via `includes/header.php` on every page) rather than reusing this one unverified.

- [ ] **Step 4: Confirm untouched pages are byte-identical**

```bash
for p in contact-us.php blog.php shopping.php login.php admin.php; do
  echo "=== $p ==="
  curl -s "http://localhost:8899/$p" | grep -o 'vendors[a-z-]*\.\(css\|js\)\|common_scripts_min\.js' | sort -u
done
```

Expected: every page prints only `vendors.css` and/or `common_scripts_min.js` — never `vendors-core`, `vendors-home`, or `vendors-tour`. (`shopping.php`/`login.php`/`admin.php` have their own separate `<head>`, not `includes/head.php`, so this also re-confirms they were never at risk of picking up the new variable.)

- [ ] **Step 5: Visual regression screenshots**

Screenshot the homepage and Maipo at 375px and 1470px widths, full page. Compare against production or against screenshots from an earlier verification pass this session if still available — confirm no visual difference anywhere.

- [ ] **Step 6: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 7: If any check failed, fix and re-verify**

Repeat Steps 1-6 after any fix, and re-run Task 1 Step 1's exact-state checks before considering the fix complete. Do not proceed to Task 3 until every check in Steps 2-5 passes.

- [ ] **Step 8: Commit (only if Step 7 required a fix)**

```bash
git add -A
git commit -m "Fix issue found during homepage/tour bundle split verification"
```

If no fix was needed, skip this step.

---

### Task 3: Deploy and confirm production

**Files:**
- None modified — this task pushes already-committed changes and confirms the live site.

**Interfaces:**
- Consumes: the commits from Tasks 1-2.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push
```

- [ ] **Step 2: Remind the user to deploy**

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to pull on the cPanel server, and per the caching issue discovered earlier this session, purge the Cloudflare cache (covering all pages, since `includes/head.php` and `includes/tour-scripts.php` are shared includes even though the byte-level change only affects 5 pages' rendered output).

- [ ] **Step 3: Once deployed and cache-purged, spot-check production directly**

Load the live homepage and one tour page (e.g. Maipo) in a real browser or via the Puppeteer console-error-capture technique used in Task 2, confirming zero new JS errors, WOW animations fire on homepage, and the tour page's gallery/date-picker still work. Also spot-check one untouched page (e.g. `contact-us.php`) to confirm it's still serving the original `vendors.css`/`common_scripts_min.js` unchanged.

- [ ] **Step 4: Once confirmed, this is a direct, unconditional byte-weight reduction**

No PSI recheck is required to confirm success — this plan removes nothing behaviorally, it only serves less code per page. A PSI check can still be run as an optional sanity check on the "unused JavaScript"/"unused CSS" diagnostics specifically, with the same caveat established earlier this session that Lighthouse's simulated LCP metric for this site has repeatedly diverged from real improvement and shouldn't be the sole judge of success.
