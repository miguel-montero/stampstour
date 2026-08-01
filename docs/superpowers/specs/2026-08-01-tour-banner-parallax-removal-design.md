# Tour page banners: replace parallax.js with pure CSS

## Context

Every tour page (`maipo-valley-wine-tour-santiago.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`, `discover-santiago-city-tour.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`) and `cruise-transfer.php` opens with a full-width banner section:

```html
<section class="parallax-window" data-parallax="scroll" data-image-src="img/Tours/Maipo/big.jpg" data-natural-height="470">
   <div class="badge_tripadvisor_circle">...</div>
   <div class="parallax-content-2">
    <div class="container">
     <div class="row">
      <div class="col-md-8">
        <div class="badge_save">Save<strong>10%</strong></div>
       <h1>Small-Group Maipo Valley Wine Tour: 4 Vineyards from Santiago</h1>
      </div>
      <div class="col-md-4">
       <div id="price_single_main">
        Special offer
        <span><sup>$</sup><span id="dynamic_price"></span></span>
       </div>
      </div>
     </div>
    </div>
   </div>
  </section>
```

`js/parallax.js` sets the background image and the section's height at runtime. Reading its source (`js/parallax.js:138`): `self.naturalHeight = this.naturalHeight || this.height || 1;` — the section's height is computed from the background image's *natural* dimensions, which the plugin can't know until the image has downloaded enough to report them. There is no CSS rule for `.parallax-window` anywhere in the stylesheets providing a fallback height. Until the JS runs *and* the image loads, the browser has no idea how tall this section is.

A PageSpeed Insights audit against the live Maipo page confirmed the real-world cost: **CLS 0.741** — worse than the original homepage hero bug (0.42) this session already fixed via the same root-cause pattern (Revolution Slider, also JS-computed height, also zero CSS fallback). This spec applies the same fix recipe to the tour-page banners.

This is scoped separately from, and must ship *before*, the planned critical-CSS work for these same pages (`docs/superpowers/specs/2026-08-01-homepage-critical-css-design.md`'s stated follow-up) — critical CSS has to be generated against the final, stable markup, not markup that's about to change underneath it.

## Goals

- Eliminate the JS-computed-height CLS bug on all 5 pages: the banner's height must be knowable from CSS alone, before any JS runs or any image loads.
- Same visual appearance: full-width background photo, dark overlay for text contrast, the existing overlaid content (TripAdvisor badge, save badge, `<h1>` title, price) unchanged in position and behavior.
- Remove the `js/parallax.js` (and its minified build) dependency from all 5 pages once nothing on them references it.

## Non-goals

- Preserving the scroll-parallax motion effect (background moving slower than the page on scroll). Confirmed with the project owner: drop it, matching the homepage hero's earlier fix — same static-banner treatment, zero JS, zero CLS risk.
- Any other content on these pages (tour description, itinerary, booking form, gallery) — out of scope.
- The critical-CSS extension to these pages — separate, dependent follow-up spec, not this one.
- Re-deriving exact per-page banner heights during this design pass — that's mechanical measurement work that belongs in the implementation plan (same as how the hero fix's exact breakpoint values were pinned down during planning, not brainstorming).

## Design

### Markup change (all 5 pages, identical pattern)

Replace:
```html
<section class="parallax-window" data-parallax="scroll" data-image-src="img/Tours/<Name>/big.jpg" data-natural-height="470">
  <div class="badge_tripadvisor_circle">...</div>
  <div class="parallax-content-2">...</div>
</section>
```

With:
```html
<section class="tour-banner">
  <img src="img/Tours/<Name>/big.jpg" width="<native-width>" height="<native-height>" fetchpriority="high" alt="<tour name> banner" class="tour-banner-bg">
  <div class="tour-banner-overlay"></div>
  <div class="badge_tripadvisor_circle">...</div>
  <div class="parallax-content-2">...</div>
</section>
```

`.badge_tripadvisor_circle` and `.parallax-content-2` (and everything inside them — title, badges, price) are copied through unchanged; only the wrapping section and the background mechanism change. The `<img>` follows the same LCP-safe pattern established for the homepage hero: explicit `width`/`height`, `fetchpriority="high"`, no `loading="lazy"`.

### CSS (new rules, one shared block since all 5 pages use identical class names)

`.tour-banner` gets `position: relative; overflow: hidden;` plus per-breakpoint `height` rules (exact values determined during planning via the same reliable measurement method used for the hero — a same-origin iframe or CDP device-metrics override, not `--window-size`, which was found this session to silently clamp below 500px width). `.tour-banner-bg` gets `position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center center;` plus the same `width`/`height: 100%` fallback pair the hero's CSS already uses for pre-`inset`-shorthand browser support. `.tour-banner-overlay` gets `position: absolute; inset: 0;` plus the same dark tint color the parallax version currently renders (to be read from the plugin's own default/config before removal, so the overlay darkness doesn't visibly change).

No zoom animation is being added here (unlike the homepage hero) unless requested — the tour banners never had one; parallax.js's only visual effect was the scroll motion, which is being dropped, not swapped for a different effect. If a subtle static-zoom is wanted for visual consistency with the homepage, that's a one-line addition during implementation, but it's not assumed here.

### JS removal

Once all 5 pages no longer use `.parallax-window`/`data-parallax`, remove the `<script src="js/parallax.js">` (or `.min.js`) include from each. Confirm via grep that no other page or shared include references `.parallax-window`, `data-parallax`, or loads `parallax.js` before removing the file itself from the repo (out of caution, matching how `rev-slider-files/` was left on disk rather than deleted after the Revolution Slider work — if truly orphaned, deletion can be a small separate cleanup, not required for this spec's goals).

## Verification

1. Local `php -S` server, headless Chrome screenshots of all 5 pages at the same breakpoint set used throughout this session (375, 576, 650, 768, 880, 992, 1100, 1200, 1470, 1920), using the CDP/iframe method for anything below 500px.
2. Confirm no visible flash and no layout shift between first paint and full load — same before/after-load screenshot comparison method used for the critical-CSS work, since a naive `php -S`-timed check would pass vacuously regardless of correctness (the exact gap that let a bug through earlier this session).
3. Confirm the overlay color and banner appearance visually match the current (parallax.js) rendering closely enough that the change isn't noticeable to a returning visitor — direct screenshot comparison against the current production pages.
4. `php -l` on all 5 modified files, div/tag balance checks.
5. Confirm no remaining references to `parallax-window`, `data-parallax`, or `parallax.js`/`parallax.min.js` in any of the 5 files after the edit.
6. Once deployed, re-run PageSpeed Insights (mobile) against at least one tour page and compare CLS against the recorded baseline (Maipo: 0.741) — this is the metric this spec exists to fix, so it's the real pass/fail signal, not a "nice to have" follow-up.

## Risks

- **Five pages share one CSS/markup pattern, so a mistake reproduces five times** — mitigated by the per-task review process (SDD) checking each page, and by the fact that this is a well-precedented fix (same recipe as the hero, already proven to work) rather than novel design work.
- **The overlay color/darkness needs to be read from the current rendering, not guessed** — parallax.js may apply its own default overlay via a CSS class or inline style not obvious from a static code read. Verification step 3 exists specifically to catch a mismatch here.
- **This does not by itself fix the tour pages' other CWV problems** (render-blocking CSS chain, LCP) — those are separately scoped, already-identified follow-up work.
