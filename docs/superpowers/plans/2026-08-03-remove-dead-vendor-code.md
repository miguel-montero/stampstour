# Remove Dead Vendor Code Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove 5 confirmed-dead-everywhere components (Ion.RangeSlider, Owl Carousel, footer-reveal.js, Bootstrap Notify, bootstrap-timepicker) from the shared `js/common_scripts.js`/`css/vendors.unminified.css` sources, regenerate the production minified `js/common_scripts_min.js`/`css/vendors.css` from the trimmed sources, and guard the 2 `js/functions.js` call sites that would otherwise throw once the removed plugins are gone — reducing sitewide bundle weight by ~35% (JS) and ~4% (CSS) with zero functional change on any page.

**Architecture:** All 5 files touched by this plan have already been generated, edited, and independently verified during planning (matching this session's established practice for large-asset changes) — this plan's tasks commit and further verify that already-correct working state, not regenerate it from scratch.

**Tech Stack:** Plain JS/CSS, no build step in the deployed site. `terser` (JS) and `clean-css-cli` (CSS) were used as one-time local scratch tools to regenerate the minified files from the trimmed unminified sources — nothing from that tooling ships or is added to the repo.

## Global Constraints

- Exact changes already made, verified, and present in the working tree:
  - `js/functions.js`: 2 call sites now guarded with `if ($.fn.ionRangeSlider) {...}` / `if ($.fn.owlCarousel) {...}` (3 call sites total for owlCarousel, all wrapped — see Task 1 for exact diffs). No other lines changed.
  - `js/common_scripts.js`: two line ranges deleted from the pre-edit file — lines 9115–15498 (Ion.RangeSlider + footer-reveal.js + Bootstrap Notify + Owl Carousel, which were 4 consecutive sections with no gaps) and lines 17555–18654 (Timepicker, ran to end of file). File went from 18,654 to 11,170 lines. Confirmed via direct grep: 0 remaining references to any removed component's name; all components meant to stay (Parallax, Magnific Popup, WOW animate on scroll, SHOW PASSWORD/moment.js/daterangepicker) still present with their section markers intact.
  - `css/vendors.unminified.css`: two line ranges deleted — lines 15858–16356 (Range Slider + Owl Carousel, consecutive) and lines 17213–17348 (Bootstrap Time picker). File went from 17,761 to 17,126 lines from the deletions alone, then to 17,132 lines after also removing a stale `@import` and updating the table-of-contents comment (see below) — both of those add explanatory comment lines, netting +6. 17,132 is the final, correct line count.
  - **A real, separate bug found and fixed along the way**: `css/vendors.unminified.css` had drifted out of sync with production `css/vendors.css` — it still contained `@import url("bs-icon-font/bootstrap-icons.min.css");` (line 22), which was deliberately removed from the production file on Aug 1 (commit `41cb8055`) as a render-blocking fix. This was corrected in the unminified reference file too (import line removed, replaced with an explanatory comment), so re-minifying from this source doesn't reintroduce an already-fixed bug. This was the only drift found between the two files.
  - `js/common_scripts_min.js`: regenerated via `terser <src> -c -m -o <out>` from the trimmed `js/common_scripts.js`. 319,337 → 207,812 bytes (−34.9%). Verified: 0 occurrences of `ionRangeSlider`/`owlCarousel` as plugin-definition patterns; `magnificPopup`/`daterangepicker` still present (62 occurrences); `node --check` passes.
  - `css/vendors.css`: regenerated via `cleancss -o <out> <src>` from the trimmed, drift-corrected `css/vendors.unminified.css`. 223,881 → 214,195 bytes (−4.3%). Verified: 0 occurrences of `.irs-`/`.owl-` prefixed selectors; `.sp-slides` (3), `.mfp-` (150), `.daterangepicker` (91) all still present.
  - Local behavioral verification already performed (Puppeteer against `php -S`, with these 5 files in place): homepage and `contact-us.php` load with zero JS console errors; `maipo-valley-wine-tour-santiago.php` loads with only the same pre-existing, unrelated `css/slider-pro.min.css` 404 already known from earlier this session (not a new issue). On both homepage and Maipo, the `#toTop` scroll-to-top button correctly becomes visible after scrolling (proving `js/functions.js` executes to completion past the newly-guarded blocks without throwing — this is the single most important safety property this plan depends on). On Maipo specifically: the Slider Pro photo gallery correctly initializes (`sp-horizontal` class added by the plugin's own JS, slides visible) and the daterangepicker widget correctly attaches to the `.date-pick` field (correct generated DOM/classes) — confirming both *kept* plugins still work correctly after the surrounding file edits.
- Nothing else changes. `js/notify_func.js` (the only caller of the now-removed Bootstrap Notify, itself not `<script src>`'d by any page) is untouched — it was already dead before this plan and stays that way, out of scope.
- `shopping.php` was **not** covered by local verification (it requires a valid database reservation session, which isn't available in the local/sandboxed environment) — Task 4 includes an explicit post-deploy verification step for it specifically, using the same test-booking technique established earlier this session.

---

### Task 1: Commit the already-verified changes

**Files:**
- Modify: `js/functions.js` (already edited)
- Modify: `js/common_scripts.js` (already edited)
- Modify: `js/common_scripts_min.js` (already regenerated)
- Modify: `css/vendors.unminified.css` (already edited)
- Modify: `css/vendors.css` (already regenerated)

**Interfaces:**
- Consumes: nothing (first task).
- Produces: nothing new for later tasks — Task 2 re-verifies this exact change.

- [ ] **Step 1: Confirm the current working-tree state matches Global Constraints exactly**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
node --check js/functions.js
node --check js/common_scripts.js
node --check js/common_scripts_min.js
wc -l js/common_scripts.js css/vendors.unminified.css
wc -c js/common_scripts_min.js css/vendors.css
grep -c "ionRangeSlider\|owlCarousel" js/common_scripts_min.js
grep -o "\.irs-\|\.owl-" css/vendors.css | wc -l
grep -o "\.mfp-\|\.daterangepicker\|\.sp-slides" css/vendors.css | wc -l
```

Expected: all `node --check` calls report no errors. `common_scripts.js` is 11,170 lines; `vendors.unminified.css` is 17,132 lines. `common_scripts_min.js` is 207,812 bytes; `vendors.css` is 214,195 bytes. The `ionRangeSlider`/`owlCarousel` grep returns `0`. The `.irs-`/`.owl-` grep returns `0`. The `.mfp-`/`.daterangepicker`/`.sp-slides` grep returns a large positive number (hundreds).

If any of these don't match, STOP — do not proceed to commit; investigate whether the working tree has diverged from what this plan documents before continuing.

- [ ] **Step 2: Commit**

```bash
git add js/functions.js js/common_scripts.js js/common_scripts_min.js css/vendors.unminified.css css/vendors.css
git commit -m "Remove confirmed-dead vendor code (Ion.RangeSlider, Owl Carousel, footer-reveal.js, Bootstrap Notify, bootstrap-timepicker)"
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

- [ ] **Step 2: Re-confirm zero new JS console errors across a wider page sample**

Using Puppeteer, load each of: `index.php`, `maipo-valley-wine-tour-santiago.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php` (a second gallery page, to catch anything Maipo-specific that isn't actually general), `contact-us.php`, `blog.php`, `privacy.php`. Capture `pageerror` and console `error`-level events on each. Expected: zero errors on all pages except the already-known, pre-existing `css/slider-pro.min.css` 404 on the gallery pages (Andes, Maipo) — no other new errors anywhere.

- [ ] **Step 3: Re-confirm the `functions.js` completion signal and hamburger menu on the second gallery page**

Same methodology as the Global Constraints' already-performed check (scroll down, confirm `#toTop` becomes visible) on `portillo-inca-lagoon-andes-mountains-vineyard.php`, plus explicitly test the hamburger menu open/close behavior at a mobile viewport width (390px) on at least 2 pages — click `.cmn-toggle-switch`, confirm the `.main-menu` element's computed `transform` actually changes from its closed-state value (a translateX offset) to an open-state value, not just that no error was thrown.

- [ ] **Step 4: Re-confirm the cart dropdown and panel-dropdown behavior**

These weren't explicitly tested during the Global Constraints verification pass — locate the cart-dropdown trigger element (search the codebase for what class/ID `js/functions.js`'s panel-dropdown code targets, around the `.panel-dropdown` selector) and confirm clicking it toggles the `active` class as expected, on at least one page.

- [ ] **Step 5: Visual regression screenshots**

Screenshot the homepage and Maipo at 375px and 1470px widths, full page. Compare against the current production appearance (or against screenshots from an earlier verification pass this session if still available) — confirm no visual difference anywhere, not just in the specific interactive elements already tested.

- [ ] **Step 6: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 7: If any check failed, fix and re-verify**

Repeat Steps 1-6 after any fix, and re-run Task 1 Step 1's exact-state checks before considering the fix complete. Do not proceed to Task 3 until every check in Steps 2-5 passes.

- [ ] **Step 8: Commit (only if Step 7 required a fix)**

```bash
git add -A
git commit -m "Fix issue found during dead-vendor-code verification"
```

If no fix was needed, skip this step.

---

### Task 3: Deploy and confirm production, including shopping.php

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

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to pull on the cPanel server (via Git Version Control's Pull or Deploy), and per the caching issue discovered earlier this session, likely also needs to purge the Cloudflare cache for `js/common_scripts_min.js` and `css/vendors.css` specifically (and ideally everything, given how sitewide this change is) to actually be served.

- [ ] **Step 3: Once deployed and cache-purged, spot-check production directly**

Load the live homepage, a tour page, and a static page in a real browser (or via the same Puppeteer console-error-capture technique used in Task 2), confirming zero new JS errors and that the hamburger menu / scroll-to-top / gallery all still work.

- [ ] **Step 4: Verify `shopping.php` specifically, using a test booking**

This page wasn't covered by local verification (requires a live database session). Using the same technique established earlier this session: drive `stampstour.com/booking_manual.php` (vendor ID and process to be confirmed with the user at execution time, since this creates a real database record) to create one test reservation, then load the resulting `shopping.php?reference_id=...` URL and confirm zero new JS console errors and that the page renders correctly (booking summary, payment method tiles, etc. all display as expected). Afterward, give the user the exact SQL to delete the test `reservas`/`titulares` rows, the same way it was handled earlier today — do not leave test data in production without telling the user exactly what to clean up.

- [ ] **Step 5: Once confirmed, this is a direct, unconditional byte-weight reduction**

No PSI recheck is required to confirm success — this plan removes genuinely dead code with no behavioral change, so the win doesn't depend on any simulated metric. A PSI check can still be run as an optional sanity check on the "unused JavaScript"/"unused CSS" diagnostics specifically, if useful for the ongoing record.
