# Content Pages Script Trim Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove 3 confirmed-unused `<script>` tags from `includes/content-scripts.php`, cutting real page weight on the 6 pages that share it, with zero visual or functional change.

**Architecture:** Pure deletion. A site-wide grep (done during brainstorming) confirmed no call site exists anywhere in the repo for `.sliderPro(`, `.theiaStickySidebar(`, or any common jQuery-UI widget method on any of the 6 pages that include this file.

**Tech Stack:** Plain PHP include. No build step.

## Global Constraints

- Only `includes/content-scripts.php` changes. No page file changes.
- Remove exactly 3 lines: the jQuery-UI CDN script, the Slider Pro script, the theia-sticky-sidebar script.
- `js/jquery-3.7.1.min.js`, `js/bootstrap.bundle.min.js`, `js/common_scripts_min.js`, `js/functions.js` all stay — out of scope per the design doc (both `common_scripts_min.js` trimming and the `bootstrap.bundle.min.js`/`common_scripts_min.js` possible-duplication question are explicitly deferred to a future effort).
- No `defer`/timing changes in this plan — pure removal only.

---

### Task 1: Remove the 3 unused script tags

**Files:**
- Modify: `includes/content-scripts.php`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing consumed by other tasks — single-task plan.

- [ ] **Step 1: Replace the file's content**

Current content of `includes/content-scripts.php` (verify it still matches before editing — if it doesn't, stop and report):

```php
<?php /* includes/content-scripts.php
 * Shared trailing <script> block for the 3 content pages
 * (contact-us, privacy, refunds-cancellations). No parameters.
 */
?>
<!-- Scripts (jQuery, Bootstrap, plugins) -->
<script src="/js/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.3/jquery-ui.min.js"></script>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/jquery.sliderPro.min.js"></script>
<script src="/js/theia-sticky-sidebar.js"></script>
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
 */
?>
<!-- Scripts (jQuery, Bootstrap, plugins) -->
<script src="/js/jquery-3.7.1.min.js"></script>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/common_scripts_min.js"></script>
<script src="/js/functions.js"></script>
```

- [ ] **Step 2: Verify with `php -l`**

```bash
php -l includes/content-scripts.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify exactly the intended 4 scripts remain**

```bash
grep -n "<script" includes/content-scripts.php
```

Expected: exactly 4 lines — `jquery-3.7.1.min.js`, `bootstrap.bundle.min.js`, `common_scripts_min.js`, `functions.js`. No `jquery-ui`, no `jquery.sliderPro`, no `theia-sticky-sidebar`.

- [ ] **Step 4: Commit**

```bash
git add includes/content-scripts.php
git commit -m "perf: remove unused jQuery-UI/Slider Pro/sticky-sidebar scripts from content pages"
```

---

### Task 2: Verify all 6 affected pages load cleanly

**Files:**
- None created or modified — verification only.

**Interfaces:**
- Consumes: Task 1's change.
- Produces: functional-regression evidence for the task reviewer and final review.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP && php -S localhost:8080 -t .
```

- [ ] **Step 2: Load all 6 pages and check for console errors**

Using Puppeteer (reuse the install at `/Users/miguelmontero/.claude/jobs/60089a79/tmp/critical-gen/node_modules`), visit each of these 6 URLs served from `http://localhost:8080/...` and capture `console` + `pageerror` events:

- `contact-us.php`
- `privacy.php`
- `refunds-cancellations.php`
- `blog.php`
- `blog-post.php` (use whatever query string/slug an existing post needs — check `blog.php`'s output for a real link, or read `blog-post.php`'s top for how it expects to be invoked)
- `gallery.php`

Expected: zero JS console errors or page errors on any of the 6 pages. This is the real safety net for the "no confirmed call site" claim from the design doc — if something references one of the removed libraries in a way the earlier grep didn't catch (e.g. a dynamically-constructed method name), it will surface here as an error, not silently.

- [ ] **Step 3: Spot-check visual output**

Take a full-page screenshot of each of the 6 pages (mobile viewport 390x844) and confirm nothing looks obviously broken (missing content, broken layout, visible error text). Since the removed libraries were never invoked, there should be no way for their removal to change rendered output — this step exists to catch anything the console-error check might miss (e.g. a silent CSS-only dependency, unlikely but worth a look).

- [ ] **Step 4: Confirm reduced page weight**

```bash
curl -sI http://localhost:8080/contact-us.php | grep -i content-length
curl -s http://localhost:8080/js/jquery.sliderPro.min.js -o /dev/null -w "%{size_download}\n"
curl -s http://localhost:8080/js/theia-sticky-sidebar.js -o /dev/null -w "%{size_download}\n"
```

Confirm the two removed local files' sizes (should be ~96KB and ~16KB respectively, matching what was measured during brainstorming) are no longer part of any of the 6 pages' network requests (check via Puppeteer's `page.on('response', ...)` during Step 2's page loads, or the browser's own network log) — the jQuery-UI CDN request should also be absent.

- [ ] **Step 5: Stop the local PHP server**

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

- [ ] **Step 3: Spot-check 2-3 of the 6 pages on live production**

Load `https://stampstour.com/contact-us.php` and 2 others (e.g. `gallery.php`, `blog.php`) in a real or headless browser, confirm no console errors and normal appearance, same as Task 2's local check.

## Verification

1. `php -l` passes on the modified file (Task 1).
2. Exactly 4 `<script>` tags remain in `includes/content-scripts.php`, the 3 unused ones are gone (Task 1).
3. All 6 affected pages load with zero console/page errors locally (Task 2).
4. Confirmed network-level absence of the 3 removed resources from all 6 pages' requests (Task 2).
5. Production spot-check confirms the same result live (Task 3).

## Risks

- Effectively none, per the design doc — this removes code with zero confirmed call sites anywhere in the repo. Task 2's console-error check across all 6 pages is the real safety net for the residual risk that the grep patterns missed something (a dynamically-constructed method name, for instance).
