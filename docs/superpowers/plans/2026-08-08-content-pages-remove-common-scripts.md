# Content Pages Remove common_scripts_min.js Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove `js/common_scripts_min.js` (208KB — the single largest JS asset these pages load) from the 6 content pages (contact-us, privacy, refunds-cancellations, blog, blog-post, gallery), since none of it is used, to directly reduce both bandwidth contention (helps LCP) and main-thread parse/execute cost (helps TTI).

**Architecture:** Pure deletion, one line, in the already-trimmed shared include `includes/content-scripts.php`. Confirmed safe via investigation (not assumed): `common_scripts_min.js` bundles moment.js, daterangepicker, Magnific Popup, WOW.js, and a duplicate copy of Bootstrap. None of the 6 pages use any of the first three (already confirmed in the earlier `content-pages-script-trim` plan's investigation). `js/functions.js`'s calls to `new WOW()` and `.magnificPopup()` are both properly guarded (`if (typeof WOW !== 'undefined')`, `if ($.fn.magnificPopup)`) so they safely no-op without the library present. The one place Magnific Popup could have been needed site-wide — a "Sign in" modal link (`#access_link`) in the shared header — is commented-out dead markup, never rendered. Bootstrap itself is already loaded separately via `bootstrap.bundle.min.js` on these pages, so the embedded duplicate inside `common_scripts_min.js` was pure waste too.

**Tech Stack:** Plain PHP include, no build step.

## Global Constraints

- Only `includes/content-scripts.php` changes. No other file.
- Remove exactly 1 line: `<script src="/js/common_scripts_min.js"></script>`.
- The remaining 3 scripts (`jquery-3.7.1.min.js`, `bootstrap.bundle.min.js`, `functions.js`) stay, unchanged, in the same order.
- This is scoped to the 6 content pages only — `cruise-transfer.php`, `shopping.php`, `login.php`, `return.php`, `admin.php` also load `common_scripts_min.js` directly (not via this include) and are explicitly out of scope; they genuinely may need parts of it (daterangepicker on `cruise-transfer.php`/`return.php`, for instance) and were not part of this investigation.

---

### Task 1: Remove `common_scripts_min.js` from `includes/content-scripts.php`

**Files:**
- Modify: `includes/content-scripts.php`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing consumed by other tasks — single-task plan for the code change.

- [ ] **Step 1: Replace the file's content**

Current content of `includes/content-scripts.php` (verify it still matches before editing — if not, stop and report):

```php
<?php /* includes/content-scripts.php
 * Shared trailing <script> block for the 6 content pages
 * (contact-us, privacy, refunds-cancellations, blog, blog-post, gallery).
 * No parameters.
 *
 * jQuery-UI, Slider Pro, and theia-sticky-sidebar were removed
 * 2026-08-08 after confirming via a site-wide grep that none of these 6
 * pages call any function from any of the three (no .sliderPro(,
 * .theiaStickySidebar(, or jQuery-UI widget method call site exists on
 * any of them) - those libraries were pure dead weight here. See
 * docs/superpowers/specs/2026-08-08-content-pages-script-trim-design.md
 */
?>
<!-- Scripts (jQuery, Bootstrap, plugins) -->
<script src="/js/jquery-3.7.1.min.js"></script>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/common_scripts_min.js"></script>
<script src="/js/functions.js"></script>
```

Replace it entirely with:

```php
<?php /* includes/content-scripts.php
 * Shared trailing <script> block for the 6 content pages
 * (contact-us, privacy, refunds-cancellations, blog, blog-post, gallery).
 * No parameters.
 *
 * jQuery-UI, Slider Pro, and theia-sticky-sidebar were removed
 * 2026-08-08 after confirming via a site-wide grep that none of these 6
 * pages call any function from any of the three (no .sliderPro(,
 * .theiaStickySidebar(, or jQuery-UI widget method call site exists on
 * any of them) - those libraries were pure dead weight here. See
 * docs/superpowers/specs/2026-08-08-content-pages-script-trim-design.md
 *
 * common_scripts_min.js (208KB) removed 2026-08-08 after confirming it
 * bundles only moment.js, daterangepicker, Magnific Popup, WOW.js, and a
 * duplicate copy of Bootstrap - none of which any of these 6 pages use.
 * js/functions.js's calls into WOW/Magnific Popup are both guarded
 * (`if (typeof WOW !== 'undefined')`, `if ($.fn.magnificPopup)`) so they
 * safely no-op without this file. Bootstrap itself is already covered by
 * bootstrap.bundle.min.js below. See
 * docs/superpowers/plans/2026-08-08-content-pages-remove-common-scripts.md
 */
?>
<!-- Scripts (jQuery, Bootstrap, plugins) -->
<script src="/js/jquery-3.7.1.min.js"></script>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/functions.js"></script>
```

- [ ] **Step 2: Verify with `php -l`**

```bash
php -l includes/content-scripts.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify exactly 3 scripts remain**

```bash
grep -c "<script" includes/content-scripts.php
grep -n "<script" includes/content-scripts.php
```

Expected: 3 lines — `jquery-3.7.1.min.js`, `bootstrap.bundle.min.js`, `functions.js`. No `common_scripts_min.js`.

- [ ] **Step 4: Commit**

```bash
git add includes/content-scripts.php
git commit -m "perf: remove unused common_scripts_min.js (208KB) from content pages"
```

---

### Task 2: Local verification

**Files:**
- None created or modified — verification only.

**Interfaces:**
- Consumes: Task 1's change.
- Produces: functional-regression and network-weight evidence for the task reviewer and final review.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP && php -S localhost:8080 -t .
```

- [ ] **Step 2: Load all 6 pages and check for console errors**

Using Puppeteer (reuse the install at `/Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen/node_modules`), visit each of the 6 pages served from `http://localhost:8080/...` and capture `console`/`pageerror` events:

- `contact-us.php`
- `privacy.php`
- `refunds-cancellations.php`
- `blog.php`
- `blog-post.php?slug=valparaiso-day-trip-what-to-know` (a real, published post — confirmed via direct DB query during the prior plan's verification; use this exact slug rather than a placeholder, since real post content is the one thing static template review can't fully cover)
- `gallery.php`

Expected: zero JS console errors or page errors on any of the 6 pages. Specifically confirm no `WOW is not defined` or similar reference errors — this is the exact failure mode this task exists to rule out.

- [ ] **Step 3: Confirm `common_scripts_min.js` is no longer requested**

Via Puppeteer's `page.on('response', ...)`, confirm none of the 6 pages request `/js/common_scripts_min.js`.

- [ ] **Step 4: Visual sanity check**

Take a full-page screenshot of each of the 6 pages (mobile viewport 390x844) and confirm nothing looks broken. Specifically check the header/nav (menu toggle, any dropdown behavior) and footer render and behave normally, since those are shared across every page and the most likely place a silent dependency would surface.

- [ ] **Step 5: Confirm network weight reduction**

For one of the 6 pages, confirm total JS payload dropped by ~208KB (compare against the file size of `js/common_scripts_min.js`, confirmed 207,812 bytes).

- [ ] **Step 6: Re-measure LCP, TTI (approximate), and CLS under throttled conditions**

Using the same methodology established earlier this session (CDP `Network.emulateNetworkConditions` at ~1.6 Mbps down / 750 Kbps up / 150ms latency, `Emulation.setCPUThrottlingRate` rate 4, mobile viewport 390x844, `page.setCacheEnabled(false)`), measure on `contact-us.php` and `refunds-cancellations.php`:

- LCP via `PerformanceObserver({type: 'largest-contentful-paint', buffered: true})`.
- An approximate TTI: `domContentLoadedEventEnd` from the Navigation Timing API, adjusted forward past the end of any Long Tasks (`PerformanceObserver({type: 'longtask', buffered: true})`) that run after it.
- CLS via `PerformanceObserver({type: 'layout-shift', buffered: true})`, including scrolling down and back up per the established methodology, to confirm the font-swap shift identified during brainstorming (all text nodes shifting ~1-2px around the time the Montserrat webfont loads) is unaffected in magnitude (removing JS shouldn't change font-loading behavior, but confirm this rather than assume it) — this plan does not fix that CLS source, only documents its current state for comparison after this change.

Record all numbers for comparison against the pre-this-plan baseline (captured earlier this session under identical conditions): contact-us.php was LCP 5.9s, refunds-cancellations.php CLS 0.048.

- [ ] **Step 7: Stop the local PHP server**

This task creates no repo changes — nothing to commit. Confirm `git status` is clean before reporting DONE.

---

### Task 3: Deploy and confirm production

**Files:**
- None — deployment and verification only.

- [ ] **Step 1: Push the commit**

```bash
git push
```

(If rejected due to upstream changes, `git fetch origin`, confirm no overlap with `includes/content-scripts.php`, `git merge origin/main --no-edit`, then push again.)

- [ ] **Step 2: Ask the user to pull on the server**

Tell the user: "Pushed. Please pull this on production, then let me know once it's live so I can do a final check."

Wait for confirmation before continuing.

- [ ] **Step 3: Spot-check on live production**

Load 2-3 of the 6 pages on `https://stampstour.com/...` with a cache-busting query param, confirm no console errors, confirm `common_scripts_min.js` is not requested, and visually confirm normal appearance.

- [ ] **Step 4: Re-run the throttled LCP/TTI/CLS measurement from Task 2, Step 6 against production**

Use a cache-busting query param and confirm via response headers (`cf-cache-status`, `last-modified`) that you're measuring the live deploy, not a stale cached response — this exact mistake produced a false-positive "fixed" reading once already earlier in this session's work (see the `tour-gallery-defer-scripts` plan's history).

- [ ] **Step 5: Report the before/after comparison to the user**

Compare against the pre-this-plan baseline captured during brainstorming (contact-us.php LCP ~5.9s; refunds-cancellations.php CLS 0.048) and report how much of the LCP/TTI slowness this fix alone resolves, so the user can decide whether the remaining stylesheet-priority and font-swap-CLS fixes discussed during brainstorming are still needed, and at what scope.

## Verification

1. `php -l` passes on the modified file (Task 1).
2. Exactly 3 `<script>` tags remain in `includes/content-scripts.php` (Task 1).
3. All 6 pages (including a real blog post, not just the template) load with zero console/page errors locally, with `common_scripts_min.js` confirmed absent from all requests (Task 2).
4. LCP/TTI/CLS measured and recorded under throttled conditions locally, for comparison (Task 2).
5. Production spot-check and re-measurement confirm the same result live, with a before/after comparison reported to the user (Task 3).

## Risks

- **Very low functional risk** — every dependency this change removes was confirmed either genuinely unused (moment.js, daterangepicker, real Magnific Popup usage) or safely guarded in the one place it's called (`functions.js`'s WOW/Magnific Popup init). Task 2's console-error check across all 6 pages, including real database-driven blog content, is the concrete safety net for anything a static investigation could have missed.
- **This fix alone may not fully resolve LCP/TTI** — it removes one real contributor (bandwidth contention + parse/execute cost from a 208KB bundle) but the separately-identified stylesheet-priority tie (documented in `docs/superpowers/specs/2026-08-02-tour-pages-lcp-priority-fix-design.md`) is a distinct mechanism this plan does not touch. Task 3's before/after comparison exists specifically to show how much headroom remains, informing whether that follow-up is still needed.
- **Does not address the font-swap CLS finding** from brainstorming (Montserrat webfont swap shifting text nodes ~1-2px when it loads) — flagged as a distinct, separate mechanism during investigation, not fixed here. Task 2/3 measure it for record-keeping only.
