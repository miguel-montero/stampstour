# Footer Reveal Removal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the `footerReveal` plugin initialization from `js/functions.js`, fixing a site-wide bug where resizing a desktop browser window narrower after page load leaves the footer stuck `position: fixed` near the top of the viewport instead of flowing normally at the bottom.

**Architecture:** Delete a single self-contained block (7 lines) from `js/functions.js`. Nothing replaces it — the footer's default, already-correct static-flow rendering (already what every mobile visitor sees today, since the plugin never initializes below 768px) becomes the only behavior, on every page.

**Tech Stack:** Plain jQuery, no build step. The `footerReveal` plugin itself stays bundled inside `js/common_scripts.js` (a shared vendor file) — nothing calls it anymore, so it's simply never invoked. Matches how `js/parallax.js` was left in place, unreferenced, after an earlier fix this session.

## Global Constraints

- Delete exactly the block shown in Task 1 — nothing more, nothing less. No other code in `js/functions.js` changes.
- Do not touch `js/common_scripts.js`, `js/parallax.js`, or any of the 11 PHP pages' `<footer class="revealed">` markup — the class is confirmed inert once nothing reads it via JS (no standalone `.revealed` CSS rule exists anywhere in the stylesheets).
- The fix must be verified as fixing the bug on more than one of the 11 affected pages, not just `index.php` (the page used for diagnosis).

---

### Task 1: Remove the footer-reveal init block

**Files:**
- Modify: `js/functions.js:230-237`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing — this is the only task that changes code; Task 2 verifies it.

- [ ] **Step 1: Delete the block**

Find (currently lines 230-237):

```js
/* Footer reveal */
if ($(window).width() >= 768) {
	$('footer.revealed').footerReveal({
	shadow: false,
	opacity:0.6,
	zIndex: 0
});
}
```

Delete it entirely, including the blank lines immediately before and after it if that leaves two consecutive blank lines where there should be one (check the actual surrounding context — the line before is `});` closing the previous block, followed by a blank line, then this block, then a blank line, then `/* Search */` starting the next block; after deletion there should be exactly one blank line between `});` and `/* Search */`, matching the file's existing spacing convention elsewhere).

- [ ] **Step 2: Verify**

```bash
node --check js/functions.js
grep -c "footerReveal\|Footer reveal" js/functions.js
```

Expected: `node --check` produces no output (valid syntax); the grep returns `0` (no remaining references in this file).

- [ ] **Step 3: Commit**

```bash
git add js/functions.js
git commit -m "Remove footer-reveal plugin init (fixes stuck-footer bug on desktop resize)"
```

---

### Task 2: Verify the fix across multiple pages

**Files:**
- None modified — this task only verifies. If the fix doesn't work, revisit Task 1's edit.

**Interfaces:**
- Consumes: the change from Task 1.
- Produces: verification evidence only.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/index.php
```

Expected: `200`.

- [ ] **Step 2: Reproduce the exact bug scenario, before-fix baseline not needed (already documented in the spec) — confirm the fix works**

Using Puppeteer with a CDP `Emulation.setDeviceMetricsOverride` session (not `--window-size`, which silently clamps below 500px — not relevant at these widths, but keep using the reliable method established this session regardless):

1. Set viewport to 1400×900, `mobile: false`.
2. Navigate to a page with `waitUntil: 'networkidle2'`.
3. Change the CDP device metrics to 400×900, still `mobile: false` (desktop mode throughout — this matters, it's what makes the bug reproduce).
4. Dispatch a `resize` event: `page.evaluate(() => window.dispatchEvent(new Event('resize')))`.
5. Wait briefly (500-800ms) for any handlers to settle.
6. Read the footer's computed `position` and its `getBoundingClientRect()`.

Do this for at least 3 of the 11 affected pages: `index.php`, `maipo-valley-wine-tour-santiago.php`, and one more (`blog.php`, `shopping.php`, or another tour page).

Expected for each: `position` is `static` (or whatever the footer's normal, non-`fixed` computed position is — confirm it is NOT `fixed`), and the footer's `top` (relative to the viewport) is near the bottom of `document.body.scrollHeight`, not a small number near the top.

- [ ] **Step 3: Confirm normal (non-resized) rendering is unchanged**

Load the same pages fresh at both a desktop width (e.g. 1400px) and a mobile width (e.g. 390px, via the same CDP method) without any resize step, and visually confirm (screenshot) the footer still appears exactly where it does today — at the bottom of the page, styled the same as before. This change should be invisible under normal use.

- [ ] **Step 4: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 5: If any check failed, fix and re-verify**

Revisit Task 1's edit, fix, repeat Steps 1-4. Do not proceed to Task 3 until every check in Steps 2-3 passes.

- [ ] **Step 6: Commit (only if Step 5 required a fix)**

```bash
git add js/functions.js
git commit -m "Fix footer-reveal removal issue found during verification"
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

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to `git pull` on the cPanel server to see this live. Also remind them that a Cloudflare cache purge may be needed for `js/functions.js` to actually update at the edge, per the caching behavior discovered earlier this session (CSS and JS assets were found to be cached at Cloudflare's edge for extended periods, serving stale content even after a successful deploy).

- [ ] **Step 3: Once deployed and cache-purged, spot-check on the real live site**

Load a real desktop browser to `stampstour.com`, resize the window narrower after load, and confirm the footer no longer sticks near the top. This is the real-world confirmation that both the code fix and the cache purge succeeded.
