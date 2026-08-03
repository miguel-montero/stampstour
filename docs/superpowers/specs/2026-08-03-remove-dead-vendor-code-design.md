# Remove confirmed-dead vendor code from the shared CSS/JS bundles (Phase 1)

## Context

A research audit (full read-through of `js/common_scripts.js` and `css/vendors.unminified.css`, cross-referenced against every customer-facing page's markup and init scripts) found that roughly 40% of the shared JS bundle, and a smaller but real slice of the shared CSS bundle, is dead code — not "used on some pages, not others," but confirmed unused **anywhere in the current codebase**:

| Component | In `common_scripts.js` | In `vendors.unminified.css` | Confirmed unused because |
|---|---|---|---|
| Ion.RangeSlider | 13.2% (lines 9115–11568) | 1.4% (lines 15858–16113) | No `.ionRangeSlider(` call site exists; `js/functions.js:215` calls it unconditionally but targets `#range`, which no page's markup contains |
| Owl Carousel | 18.5% (lines 12046–15498) | 1.4% (lines 16114–16355) | No page's markup contains the carousel container classes `js/functions.js:281,310,336` target |
| footer-reveal.js | 0.3% (lines 11569–11628) | — | Its own init call was already removed sitewide earlier this session (commit `d0580fc3`) — the plugin code itself was left in place at the time as out of scope; now safe to remove for real |
| Bootstrap Notify | 2.2% (lines 11629–12045) | — | Only ever called from `js/notify_func.js`, which is not `<script src>`'d by any page |
| bootstrap-timepicker | 5.9% (lines 17555–18654) | 0.76% (lines 17213–17347) | No `.timepicker(` call site exists anywhere in the repo |

Total: roughly 40% of the JS bundle (~127KB of the 319KB minified file, by proportion) and about 3.6% of the CSS bundle, removable with **no per-page routing complexity** — every page currently loads all of this, and no page needs any of it, so deleting it is a pure sitewide win, not a "homepage vs. tour pages" split (that's the separate, larger Phase 2 project).

**The one real hazard**, and the reason this needs a careful plan rather than a quick deletion: `js/functions.js` is a single flat script every page runs top-to-bottom, and it unconditionally calls several of the plugins being removed here — `.ionRangeSlider()` at `js/functions.js:215`, `.owlCarousel()` at `js/functions.js:281,310,336`. Today these are harmless no-ops (the target elements don't exist on any page, so jQuery silently does nothing) — but the *plugin method itself* still exists on `$.fn`, so the call doesn't throw. Once the plugin code is removed from the bundle, `$.fn.ionRangeSlider`/`$.fn.owlCarousel` won't exist at all, and calling an undefined method **throws a JavaScript error that halts the rest of `functions.js`** — silently breaking every later feature in that file: the cart dropdown, hamburger menu, scroll-to-top button, panel dropdowns, menu-hover fades, and background-image swaps, on every single page. This must be fixed (guard each call) before the plugin code is removed, not as an afterthought.

## Goals

- Remove the 5 confirmed-dead components from both `js/common_scripts.js`/`js/common_scripts_min.js` and (where applicable) `css/vendors.unminified.css`/`css/vendors.css`, reducing shared bundle weight sitewide.
- Guard every remaining `functions.js` call to a plugin being removed, so removing the plugin can't break anything later in that file, on any page.
- Zero visible or functional change on any page — this is dead-code removal, not a feature change. If anything currently works (even by coincidence), it must keep working identically.

## Non-goals

- Splitting bundles per-page (homepage vs. tour pages) — that's Phase 2, a separate, larger project, deliberately sequenced after this one so its "guard-then-remove" pattern is proven on the lower-risk, sitewide-dead subset first.
- Removing anything used by even one page — Bootstrap core, WOW.js/Animate.css, Magnific Popup, Slider Pro, daterangepicker/moment.js, and the icon-font CSS all stay, since real pages depend on them.
- Fixing unrelated findings the audit surfaced in passing (the duplicate-Bootstrap-JS issue on `blog.php`/`contact-us.php`/etc., the `login.php` password-field ID mismatch, the dead standalone plugin files sitting unused in `js/`, `includes/tour-scripts.php`'s reference to a `css/slider-pro.min.css` file that doesn't exist). These are real but separate — noted for a future pass, not this one.
- Regenerating the production minified files via an automated build pipeline. Same approach as every other asset change this session: edit the readable source, regenerate the minified output once locally with a real minifier tool (used as a scratch dependency, not a repo addition), verify, commit the result as a static file.

## Design

### 1. Guard the at-risk calls in `js/functions.js` first

Before removing any plugin code, wrap each call to a plugin being removed in a feature check, so the call becomes a safe no-op if the plugin isn't present — matching a defensive pattern already partially visible in the codebase's own style:

```js
// Before:
$('#range').ionRangeSlider({ ... });

// After:
if ($.fn.ionRangeSlider) {
  $('#range').ionRangeSlider({ ... });
}
```

Same treatment for the 3 `.owlCarousel()` call sites. This step alone is fully safe to ship and verify independently (it changes behavior only in the hypothetical case the plugin is absent — today it's always present, so this step is a no-op change verifiable via before/after screenshot comparison, not a functional change yet).

### 2. Remove the dead sections from the unminified source files

From `js/common_scripts.js`: delete the 5 line ranges identified in Context (Ion.RangeSlider, Owl Carousel, footer-reveal.js, Bootstrap Notify, bootstrap-timepicker + its embedded moment.js dependency — moment.js is confirmed used only by daterangepicker, which stays, so moment.js itself must stay too; only the timepicker section proper is removed).

From `css/vendors.unminified.css`: delete the 3 applicable line ranges (Ion.RangeSlider CSS, Owl Carousel CSS, bootstrap-timepicker CSS). `footer-reveal` and Bootstrap Notify have no CSS component.

Exact line ranges will be re-confirmed against the files' current state at planning time (line numbers shift slightly as edits are made in sequence) rather than assumed frozen from the audit's read.

### 3. Regenerate the minified production files

`js/common_scripts_min.js` and `css/vendors.css` are the actual files every page loads — they must be regenerated from the trimmed unminified sources, not hand-edited (minified code is too dense to safely edit directly). Use a real minifier as a one-time local tool (matching this session's established pattern for `critical`/`postcss`): a JS minifier (e.g. `terser`) for the JS file, a CSS minifier (e.g. `clean-css` or `csso`) for the CSS file. Compare the regenerated file's effective behavior against the current production file for everything that's *supposed* to stay (not just smaller size) — verification below covers this.

### 4. Verify sitewide, not just on the homepage

Because this touches files loaded by every page — including the checkout flow — verification must cover the full page-type matrix: at least one page from each category found in the audit (a page using Magnific Popup + Slider Pro + daterangepicker, e.g. Maipo; a page using none of the removed-or-kept feature plugins, e.g. a static page like `contact-us.php`; the homepage; and `shopping.php`, given its elevated stakes and the fact it loads these same bundles independently via its own `<head>`).

## Verification

1. Guard step (functions.js): confirm no visible/behavioral change on any page via before/after screenshot at a few widths, on at least 3 pages.
2. After bundle trimming + reminification: `node --check` (or equivalent) on the new JS, and a CSS parse-validity check, before deploying.
3. Load every page category (homepage, a tour page with a gallery, a static content page, shopping.php) locally and confirm: cart dropdown opens, hamburger menu opens on mobile width, scroll-to-top button appears/works, panel dropdowns and menu-hover fades behave as before, background-image swaps still happen where used. These are exactly the `functions.js` features that would silently break if the guard step (1) were skipped or done wrong — testing them directly is the real proof the fix worked, not just "no console errors."
4. On the tour pages specifically: confirm the photo gallery (Magnific Popup + Slider Pro) still opens and functions identically — these plugins are being kept, but sit in the same files being edited, so a mistake in the surrounding edit could accidentally clip them.
5. Confirm total byte-weight reduction matches expectations (compare old vs. new `common_scripts_min.js` and `vendors.css` file sizes).
6. Once deployed and cache-purged, this is a direct, unconditional byte-weight reduction — no PSI recheck is required to confirm success, though one can be run as an optional sanity check, with the same caveat established earlier this session that Lighthouse's simulated LCP metric for this site has repeatedly diverged from real improvement and shouldn't be the sole judge of whether this worked.

## Risks

- **The `functions.js` guard is the load-bearing safety mechanism for this entire plan.** If it's incomplete or wrong, the failure mode is silent and sitewide (broken cart/menu/scroll-to-top on every page), not a loud error visible in casual testing. Verification step 3 exists specifically to catch this by testing the *symptoms*, not just checking for console errors.
- **Re-minification could subtly change output** in ways a byte-count comparison wouldn't catch (e.g. a minifier bug mangling a selector). Verification steps 2-4 test actual behavior, not just file validity, specifically to catch this.
- **Line-range removal in a large single-file bundle is mechanically fragile** — an off-by-one on a section boundary could clip part of a component meant to stay (e.g. accidentally removing part of Magnific Popup while removing the adjacent Owl Carousel section). Exact ranges get re-confirmed at planning time against the live file, and verification step 4 specifically re-tests the kept components most likely to be affected by an adjacent-section mistake.
- **shopping.php loads these bundles independently** (its own `<head>`, not `includes/head.php`) — it must be included in verification explicitly, not assumed covered by testing other pages, given the stakes of anything breaking on the live checkout flow.
