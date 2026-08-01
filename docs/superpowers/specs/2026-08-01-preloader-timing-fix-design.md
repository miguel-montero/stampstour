# Preloader timing fix: reveal the page on DOM-ready, not full window load

## Context

The homepage critical-CSS work (`docs/superpowers/specs/2026-08-01-homepage-critical-css-design.md`, shipped) was meant to let visitors see a correctly-styled header and hero as soon as possible, without waiting for the site's full stylesheet payload to download. The final review on that work found the improvement doesn't actually reach real users: a full-screen white overlay (`#preloader`, `z-index: 999999999`, defined in `css/style.css`) sits on top of the entire page and is only removed by a handler in `js/functions.js` that waits for the browser's `window.load` event — which fires only after *every* subresource on the page finishes, including images and (now) the deferred stylesheets. So the critical CSS paints correctly underneath, but visitors don't see it any sooner than before this project started; only Lighthouse's FCP measurement improved, because it measures paint under the overlay, not visibility.

This is scoped as its own piece, separate from the critical-CSS work, because it touches a shared, sitewide JS file (`js/functions.js`) used by every page template, not just the homepage.

## Goals

- The preloader overlay should disappear as soon as the page's visible content is actually ready to be seen, not after every last subresource (including below-the-fold images) has finished loading.
- Unlock the real-world benefit of the homepage critical-CSS work: visitors should see the styled header/hero as soon as it's paintable, without the preloader masking it.
- Sitewide fix, not homepage-only — the same `window.load`-gated pattern exists on every page, and the fix is safe everywhere (see Design).

## Non-goals

- Removing the preloader/spinner UI itself, or changing how it looks. Only *when* it disappears changes.
- Touching the promo popup (`js/pop_up_func.js`) or Owl Carousel's `autoHeight` recalculation (`js/common_scripts.js`), both of which also use `window.load` — confirmed during investigation that neither depends on the preloader's timing, so they're left as-is.
- Any further per-page critical CSS work (tour pages, blog, etc.) — that's separate, already-identified follow-up work from the critical-CSS project.

## Design

### The change

In `js/functions.js`, the preload-removal block currently reads:

```js
/* Preload */
$(window).on('load', function () { // makes sure the whole site is loaded
	$('#status').fadeOut(); // will first fade out the loading animation
	$('#preloader').delay(350).fadeOut('slow'); // will fade out the white DIV that covers the website.
	$('body').delay(350).css({
		'overflow': 'visible'
	});
	$(window).scroll();
})
```

Change the trigger from `$(window).on('load', function () {...})` to `$(function () {...})` — jQuery's shorthand for "run once the DOM is fully parsed" (equivalent to `DOMContentLoaded`, not `window.load`). The handler body itself is unchanged.

### Why this is safe on every page, not just the homepage

- **Pages with blocking CSS** (every page except the homepage, per the critical-CSS project's final-review fix): a plain `<link rel="stylesheet">` tag blocks HTML parsing until it finishes downloading. That means by the time the DOM is fully parsed, all the page's CSS has *already* been applied — "DOM ready" already implies "styled correctly" on these pages. Firing the preloader-removal earlier here just means visitors stop waiting for irrelevant things like below-the-fold images before they can see the page — a straightforward improvement, not a behavior change in what they'll see.
- **The homepage** (critical CSS inlined, remaining stylesheets deferred): the inlined critical CSS covers the header and hero — everything visible without scrolling — so by the time the DOM is parsed, that visible content is already styled correctly, even though the deferred stylesheets haven't necessarily finished yet. This is exactly the case the critical-CSS project was built to unlock.

### Checked for side effects

Two other `window.load` handlers exist on the site:
- `js/pop_up_func.js` (the "20% off" promo popup) — independent of the preloader; it'll now simply appear a bit after the page is already visible, rather than appearing at the same moment the preloader lifts. Not a regression.
- `js/common_scripts.js` (Owl Carousel's `autoHeight` recalculation, vendored library code) — recalculates a carousel's height once everything's loaded; unrelated to preloader visibility, unaffected by this change.

No other page-specific JS depends on `window.load` for anything that would look wrong if revealed earlier.

## Verification

1. Local `php -S` server is not sufficient on its own for this check — its instant localhost response times would make "preloader disappears before deferred stylesheets finish" trivially true regardless of whether the fix works, the same way the original bug slipped through Task 3's earlier, unthrottled check. Verify with artificially delayed/throttled responses for the deferred stylesheets (e.g. a small proxy or delayed PHP route) so the check can actually fail if the fix is wrong.
2. Confirm on the homepage: the header and hero become visible (preloader gone) before the deferred stylesheets finish loading, and the visible content is fully and correctly styled at that moment (no unstyled flash).
3. Confirm on at least one non-homepage page (e.g. a tour page): no visual regression — page still reveals cleanly, nothing looks broken or shifts unexpectedly.
4. `php -l` is not applicable (JS-only change); run the site's normal linting if any exists for JS, otherwise a manual syntax check is sufficient given the change is a one-line trigger swap.
5. Once deployed, re-run PageSpeed Insights (mobile + desktop) against production. This is the step that should finally show a real improvement beyond what the critical-CSS work alone delivered — compare against the most recent baseline.

## Risks

- Low overall risk given the analysis above, but the throttled verification in step 1 is important — the whole reason this bug existed in the first place was a verification check that couldn't fail due to unrealistic local timing. Don't skip it.
