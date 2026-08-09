# JS-driven lazy load for homepage tour cards

## Context

The just-shipped responsive-hero-images plan (`docs/superpowers/specs/2026-08-08-responsive-hero-images-design.md`) cut the homepage hero from 265KB to an 86KB mobile-sized WebP. On tour pages (single hero, nothing else competing), this produced a dramatic real win: production LCP dropped to 2,132ms under a throttled mobile profile. On the homepage, LCP stayed flat at 5,580ms despite the hero itself downloading correctly and quickly-sized.

Root-caused via a real production resource-timing waterfall (CDP `Network.requestWillBeSent`/`loadingFinished`) at a 390×844 mobile viewport, throttled to 1.6Mbps/150ms latency:

- The hero image (`portada-mobile-hero.webp`, 86KB) starts at 500ms.
- All 5 tour-card thumbnails (`Valpo/Maipo/Andes/Stgo/Cruise portada-mobile.webp`, totaling ~294KB) start at **524ms** — 24ms after the hero, despite each having `loading="lazy"` and being genuinely below the fold.

A follow-up CDP priority check confirmed Chrome itself is doing the right thing: the hero gets `priority=High`, all 5 cards get `priority=Low` automatically (native lazy + below-fold heuristics). Yet the "High" hero still took 5,544ms to finish downloading a mere 86KB — because it's genuinely splitting a throttled ~200KB/s connection six ways with the five "Low" images that are also mid-flight. This matches a pattern already found earlier this session with the stylesheet `media="print"` fix: **the priority tier Chrome assigns is not being honored by the server/CDN's actual bandwidth allocation across concurrent HTTP/2 streams** (very plausibly a HostGator shared-hosting characteristic — proper priority-weighted stream scheduling needs server-side support many shared-hosting HTTP/2 stacks don't implement). So re-tagging priority again would not help here; it's already correctly tagged and still doesn't work.

The real, actionable root cause is *why* the 5 cards start downloading within 24ms of the hero at all: Chrome's native `loading="lazy"` uses an adaptive prefetch distance that **increases on slower effective connection types** (so images are "ready" by the time a user scrolls to them) — exactly backwards for this case. Combined with the homepage's compact 304px-tall mobile hero (a pre-existing, deliberate design choice, confirmed via `git log` to predate this plan), the first tour card sits at `top: 690px` — well within an 844px viewport, and comfortably within Chrome's slow-connection lazy-load margin. Under throttle, that adaptive margin is large enough that all 5 cards are already "close enough" to trigger an immediate fetch.

## Goals

- Stop the 5 homepage tour-card images from starting their download within the hero's critical LCP window, by replacing native `loading="lazy"`'s connection-adaptive (and here, counterproductive) prefetch distance with a small, fixed, connection-independent margin.
- Preserve existing behavior for JS-disabled visitors.
- Preserve the existing no-CLS behavior (`width`/`height` attributes already reserve layout space for all 5 cards; this plan doesn't touch that).

## Non-goals

- Any other lazy-loaded image on the site (blog listing thumbnails, gallery images, etc.) — this plan is scoped specifically to the 5 homepage tour cards, the only place this contention was actually measured. Other pages may have a similar pattern but haven't been profiled; extending this fix elsewhere is a natural, separate follow-up once (if) a similar problem is confirmed there.
- The two small `tripadvisor` badge `<picture>` elements inside each card (~5.8KB each) — negligible size, not part of the measured contention, explicitly left untouched.
- Reducing the byte size of the 5 card thumbnails further, or any other image-weight optimization — this plan only changes *when* the existing files are requested, not their size or encoding.
- The homepage's hero height/layout (the compact 304px mobile hero that puts the first card in-viewport) — a separate, pre-existing, deliberate design choice; changing it is out of scope here.

## Design

### 1. Move each card's real image URLs into `data-*` attributes

Current markup (repeated 5×, one per tour card, e.g. Valparaíso — `index.php` lines 97-100):

```html
<picture>
    <source srcset="img/Tours/Valpo/portada-mobile.webp 600w, img/Tours/Valpo/portada.webp 955w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
    <img src="img/Tours/Valpo/portada.jpeg" width="800" height="533" class="img-fluid" alt="Valparaíso tour" loading="lazy">
</picture>
```

New:

```html
<div class="lazy-tour-card-wrap">
    <picture class="lazy-tour-card">
        <source data-srcset="img/Tours/Valpo/portada-mobile.webp 600w, img/Tours/Valpo/portada.webp 955w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
        <img data-src="img/Tours/Valpo/portada.jpeg" width="800" height="533" class="img-fluid" alt="Valparaíso tour">
    </picture>
    <noscript>
        <style>.lazy-tour-card-wrap .lazy-tour-card { display: none; }</style>
        <picture>
            <source srcset="img/Tours/Valpo/portada-mobile.webp 600w, img/Tours/Valpo/portada.webp 955w" sizes="(max-width: 767px) 600px, 50vw" type="image/webp">
            <img src="img/Tours/Valpo/portada.jpeg" width="800" height="533" class="img-fluid" alt="Valparaíso tour" loading="lazy">
        </picture>
    </noscript>
</div>
```

Applied identically (only the URLs/`alt` text differ) to all 5 cards: Valparaíso, Maipo, Andes, Discover Santiago, Cruise Transfer. `sizes` stays on the real `<source>` (harmless with no `srcset` yet — the browser just has nothing to select from until `data-srcset` is copied over) so the swap step doesn't need to also manage that attribute. `loading="lazy"` is removed from the JS-driven `<img>` (this plan's whole point is to replace that mechanism) but kept in the `<noscript>` copy, matching today's behavior exactly for JS-disabled visitors. The `lazy-tour-card` class is the JS observer's selector hook — deliberately not reusing `.img_container` (the existing wrapper `<div>`, one level up) so the observer's selector is self-contained and doesn't depend on unrelated layout markup.

**Why the extra `.lazy-tour-card-wrap` div and the `<noscript><style>` trick:** `<noscript>` is not a valid child of `<picture>` per the HTML content model (only `<source>`, one `<img>`, and script-supporting elements are allowed there) — nesting it directly, as an earlier draft of this design did, is invalid HTML. Making `<noscript>` a *sibling* of `<picture>` instead fixes the validity problem but introduces a real visual bug: for a JS-disabled visitor, the primary `<picture>` still renders (browsers don't hide a sibling just because a `<noscript>` exists nearby) — its `<img>` has no `src` but does have `width`/`height`, so it reserves a real, empty box via the same intrinsic-aspect-ratio mechanism that prevents CLS for everyone else, stacking a blank box on top of the noscript fallback's real image. The `<noscript><style>...</style>...</noscript>` pattern fixes this correctly: when scripting is disabled, the browser renders the `<noscript>`'s content, including that inline `<style>`, which hides `.lazy-tour-card` — collapsing the empty primary box to nothing and leaving only the fallback's working image. When scripting is enabled (the vast majority of visitors), the entire `<noscript>` block — style tag included — is simply never rendered at all, so there's zero cost or risk to the primary path. `.img_container img{transform:scale(1.2)}` (existing CSS, unrelated to this plan) is a descendant selector and still matches through the added wrapper `<div>` with no changes needed.

### 2. Add the IntersectionObserver to `js/functions.js`

`js/functions.js` is already loaded on `index.php` (and only `index.php`'s script list needs it — it's already shared, but this code guards itself to no-op harmlessly on pages with no `.lazy-tour-card` elements, matching the existing guarded-call pattern this file already uses for WOW/Magnific Popup/parallax per `includes/content-scripts.php`'s doc comment). Add near the end of the file, inside the existing `(function ($) { ... })(window.jQuery);` IIFE (consistent with the rest of the file, though this code itself doesn't need jQuery):

```javascript
/* Lazy-load homepage tour cards with a fixed, connection-independent margin.
   Native loading="lazy" was replaced here because Chrome's adaptive prefetch
   distance grows on slow connections, which was causing these cards to fetch
   within ~24ms of the hero image and contend for bandwidth during the LCP
   window. See docs/superpowers/specs/2026-08-08-homepage-tour-card-lazy-load-design.md */
if ('IntersectionObserver' in window) {
    var lazyCards = document.querySelectorAll('.lazy-tour-card');
    if (lazyCards.length) {
        var lazyObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var picture = entry.target;
                var source = picture.querySelector('source[data-srcset]');
                if (source) {
                    source.setAttribute('srcset', source.getAttribute('data-srcset'));
                    source.removeAttribute('data-srcset');
                }
                var img = picture.querySelector('img[data-src]');
                if (img) {
                    img.setAttribute('src', img.getAttribute('data-src'));
                    img.removeAttribute('data-src');
                }
                observer.unobserve(picture);
            });
        }, { rootMargin: '200px' });
        lazyCards.forEach(function (picture) { lazyObserver.observe(picture); });
    }
}
```

**Why `rootMargin: '200px'` and not native `loading="lazy"`'s default:** 200px is enough buffer that a normally-scrolling visitor never sees a blank/placeholder flash (the image is already loading by the time the card's top edge is 200px from the viewport), while being small and *fixed* — unlike Chrome's adaptive distance, it doesn't balloon on a throttled connection, so on the homepage specifically it keeps the cards from starting until well after the hero has had a real head start.

**No `IntersectionObserver` fallback:** browsers without `IntersectionObserver` support are effectively unsupported/ancient at this point (feature has been in every major browser since 2019); the `if ('IntersectionObserver' in window)` guard means those visitors simply keep seeing broken/never-loaded card images with JS on — acceptable given `<noscript>` isn't relevant there (JS *is* enabled, it's just missing one API). This mirrors the file's own existing pattern of guarding features rather than polyfilling them (`if (typeof WOW !== 'undefined')`, etc.).

### 3. No CSS changes

`width="800" height="533"` on each `<img>` already reserves layout space (existing site-wide convention, confirmed working — this is why removing `src` initially causes no layout shift, the box is already sized by the attributes before the image itself loads).

## Testing

- **Local functional/visual:** load `index.php` locally, confirm all 5 cards are present in the DOM with `data-src`/`data-srcset` (not `src`/`srcset`) before scroll, confirm each swaps to a real `src` and actually renders once scrolled into view, confirm no visible blank/broken-image flash at a normal scroll speed, confirm zero console errors.
- **No-JS fallback:** disable JavaScript (Puppeteer supports this directly), reload, confirm all 5 `<noscript>` fallback `<picture>` blocks render their images AND confirm the primary `.lazy-tour-card` picture is actually hidden (e.g. `getComputedStyle(el).display === 'none'`) — not just that the fallback shows, but that there's no leftover empty reserved box stacked above/before it (this is the one case a local check can fully validate, unlike the network-timing claims below).
- **Production network-timing verification (the actual regression test for this fix):** using the same CDP methodology as the hero-image investigation — 390×844 mobile viewport, throttled to 1.6Mbps/150ms — confirm the 5 card requests now start meaningfully later than the hero request (not within ~24ms as before), and re-measure homepage LCP against the 5,580ms baseline captured this session. A real, verified improvement is expected but must be measured, not assumed, per this session's established practice (the stylesheet-priority fix earlier this session was mechanically correct yet produced no LCP improvement — a reminder that "should help" claims need real production verification here too).

## Risks

- **A very fast scroll (flick) could still occasionally outrun a 200px margin on a slow connection**, showing a brief blank card before its image finishes loading. This is a real, accepted tradeoff of any lazy-load margin value that isn't enormous — 200px was chosen specifically to make this rare under normal scrolling while still meaningfully fixing the measured hero-contention problem; a larger margin would reduce this risk but reopen more of the original contention.
- **This plan does not guarantee the homepage LCP number will drop to the tour pages' ~2.1s** — the cards were one confirmed contributor, not necessarily the only one; the Testing section's production re-measurement is required specifically to find out, not to confirm a foregone conclusion.
