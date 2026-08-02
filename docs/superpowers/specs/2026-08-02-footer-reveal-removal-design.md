# Remove the footer-reveal plugin (fixes a site-wide desktop-resize bug)

## Context

While verifying the tour-banner parallax removal, a real bug surfaced: on desktop, resizing the browser window narrower after page load causes the site footer to render `position: fixed` near the top of the viewport instead of at the bottom of the page, with a stale hardcoded pixel width from load time.

Root cause, traced to `js/functions.js:230-236`:

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

`footerReveal` (a third-party jQuery plugin, `js/common_scripts.js:11584`) implements a "curtain reveal" effect: the footer sits `position: fixed` underneath the page content and is revealed as the visitor scrolls to the very bottom. The plugin is only initialized once, conditionally, if the window is ≥768px wide *at page load* — there is no resize handler to recalculate anything afterward. A real phone (already narrow at load) never triggers it at all, which is why the bug is invisible there. A desktop browser loaded wide does trigger it, and if the window is later resized narrower, the plugin's stale computed width and fixed positioning no longer match the new viewport — the footer appears stuck near the top.

Confirmed via direct testing (Puppeteer + CDP device-metrics override, desktop-mode throughout, wide load then resize down without reload) that this reproduces identically on `index.php` — a page fixed and thoroughly verified much earlier this session, completely unrelated to the tour-banner work. This is a pre-existing, site-wide bug affecting all 11 pages with `<footer class="revealed">` (`index.php`, `blog.php`, `blog-post.php`, `admin.php`, `cruise-transfer.php`, the 4 remaining tour pages, `success.php`, `shopping.php`), not something introduced by any work done this session.

## Goals

- The footer must render correctly (normal document flow, at the bottom of the page) regardless of whether the browser window is resized after load, on every page.
- Fix applies site-wide in one change, since the bug is identical everywhere the effect is used.

## Non-goals

- Preserving the curtain-reveal visual effect. Confirmed with the project owner: drop it entirely, matching the same static/simple treatment already chosen twice this session for other JS-driven positioning effects (the homepage hero's Revolution Slider, the tour page banners' parallax.js).
- Removing the `class="revealed"` attribute from the 11 pages' `<footer>` tags. The class has no CSS meaning on its own (confirmed: no standalone `.revealed` rule anywhere in the stylesheets) — once nothing reads it via JS, it's inert. Leaving it in place matches this session's established pattern of not chasing incidental cleanup (e.g. `rev-slider-files/`, `js/parallax.js` were left on disk, unreferenced, after their respective removals).
- Touching `js/common_scripts.js` (the bundled plugin code itself) — same reasoning as the earlier parallax.js work: it's a shared vendor bundle, and simply never calling the plugin is sufficient and lower-risk than trying to strip code out of a minified third-party bundle.

## Design

In `js/functions.js`, delete the entire "Footer reveal" block (lines 230-236, the `if ($(window).width() >= 768) { $('footer.revealed').footerReveal({...}); }` statement, including its comment). Nothing replaces it — the footer simply remains in normal document flow, which is already its correct, default rendering (confirmed: this is exactly how it already renders on mobile today, since the plugin never initializes there).

This is a same-file continuation of the exact fix already applied once this session to `js/functions.js` (the preloader-timing fix changed the trigger on a different block in the same file) — same low-risk profile: a small, self-contained deletion in a file we fully control, not a vendored bundle.

## Verification

1. Local `php -S` server, Puppeteer + CDP device-metrics override (not `--window-size`, which silently clamps below 500px): load a page wide (≥768px), resize narrower without reloading, dispatch a `resize` event, and confirm the footer's computed `position` is `static` (not `fixed`) and its `getBoundingClientRect().top` is near the bottom of `document.body.scrollHeight`, not near the top of the viewport.
2. Repeat on at least 2-3 of the 11 affected pages (not just `index.php`, which was used for diagnosis) to confirm the fix is genuinely site-wide, not page-specific.
3. Confirm the footer still renders identically to its current (already-correct) appearance on a normal, non-resized page load, at both desktop and mobile widths — this change should be invisible under normal use, only fixing the resize-after-load case.
4. `node --check js/functions.js` for JS syntax validity.
5. Once deployed, spot-check the fix on the live site by resizing a real desktop browser window narrower on at least one page.

## Risks

Very low. This deletes a small, self-contained block in a first-party file, removing a purely decorative effect with a confirmed bug and no confirmed working case that this fix would regress (the effect's "correct" desktop-wide, never-resized appearance is not something worth preserving over a real, reproducible layout bug).
