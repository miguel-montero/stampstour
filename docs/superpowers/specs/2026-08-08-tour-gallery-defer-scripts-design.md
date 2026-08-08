# Tour gallery: defer script loading + visibility safety net

## Context

`docs/superpowers/plans/2026-08-07-tour-gallery-cls-fix.md` shipped a CSS-only fix (`aspect-ratio`/`min-height`/`max-height`/`overflow:hidden` on the Slider Pro gallery) that eliminates CLS under fast/cached conditions, but a follow-up rigorous re-verification under real throttled mobile conditions (1.6 Mbps, 150ms latency, 4x CPU throttle) found it doesn't fully hold: the site's script loading is the actual root cause. `includes/tour-scripts.php` (4 pages: Valparaíso, Maipo, Portillo/Andes, Discover Santiago) and `cruise-transfer.php`'s own script block both load jQuery and every plugin as plain, render-blocking `<script src>` tags with no `defer`/`async` — on a slow connection this can take 5-9 seconds to fully download and execute before Slider Pro's JS ever attaches to the gallery, during which the raw markup is exposed to layout instability no per-element CSS reservation can fully absorb.

A site-wide script investigation (this session) confirmed: only these 5 pages need to change (homepage and the 6 "content" pages — contact-us, privacy, refunds-cancellations, blog, blog-post, gallery — use similar blocking-script patterns but have no JS-dependent layout element on the page, confirmed via a real throttled CLS measurement on contact-us.php scoring 0.0081/Good). No duplicate jQuery loads exist anywhere on the site. The two script-loading blocks in scope are otherwise structurally simple: a linear jQuery → plugin-bundle → page-script chain with exactly two inline `<script>` blocks that call `jQuery(...)`/`$(...)` immediately without any ready-check.

## Goals

- Meaningfully reduce the time between page-load-start and Slider Pro's gallery JS actually attaching, under real slow-mobile conditions — the actual root cause behind the residual CLS the CSS-only fix couldn't fully absorb.
- Never let the gallery's unstable pre-JS layout become visible to the user, regardless of how long script loading ends up taking — a defense-in-depth safety net independent of the timing fix above.
- Zero behavior change to anything else on these 5 pages: menus, sticky sidebar, date pickers, booking flow, WOW animations, Magnific Popup, parallax — all must work identically to today.
- Stay scoped to exactly the 5 pages that have this specific problem (homepage and the 6 content pages are out of scope — confirmed unaffected).

## Non-goals

- Site-wide script-loading changes (homepage, content pages, checkout/admin flows) — explicitly out of scope per the scoping decision above; a separate future effort if ever needed.
- Bundling/consolidating scripts into fewer files — doesn't address the root cause (execution still blocks render unless also deferred) and cuts against the earlier bundle-*split* work done this session for cacheability. Not pursued.
- Removing or replacing any plugin (Slider Pro, theia-sticky-sidebar, etc.) — out of scope, this is a loading-strategy fix only.

## Design

### 1. `defer` the script chain

**`includes/tour-scripts.php`** (4 pages) — add `defer` to all 6 `<script src>` tags: `jquery-3.7.1.min.js`, `vendors-core.min.js`, `vendors-tour.min.js`, `functions.js`, `jquery.sliderPro.min.js`, `theia-sticky-sidebar.js`, `tours.js`.

**`cruise-transfer.php`** — add `defer` to its 7 `<script src>` tags: `jquery-3.7.1.min.js`, `js/vendor/jquery-ui-autocomplete.js`, `bootstrap.bundle.min.js`, `jquery.sliderPro.min.js`, `theia-sticky-sidebar.js`, `common_scripts_min.js`, `functions.js`. (`js/transfer.js` already has `defer` — no change needed there.)

Deferred scripts download in parallel (instead of the browser waiting for each one to fully download+execute before starting the next) and don't block HTML parsing, but the spec guarantees they still **execute in the exact order they appear in the document**, relative to each other. This means the jQuery → vendor-bundle → plugin → page-script dependency chain inside `tours.js`'s and `cruise-transfer.php`'s external files is preserved automatically — no changes needed inside `tours.js` itself, since by the time any deferred script executes, every earlier deferred script in document order has already run.

### 2. Fix the 2 fragile inline scripts

Inline `<script>` blocks (no `src`) are never deferred by the `defer` attribute — they always execute immediately at their position during HTML parsing, regardless of any `defer` on surrounding scripts. Two inline blocks call `jQuery(...)`/`$(...)` immediately without checking readiness, and would break (crash calling an undefined `jQuery`) once their preceding `<script src>` becomes deferred:

**`includes/tour-scripts.php`**, the sticky-sidebar init — currently:
```html
<script>
jQuery(function($){
  if ($.fn.theiaStickySidebar) {
    $('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
  }
});
</script>
```
becomes:
```html
<script>
document.addEventListener('DOMContentLoaded', function () {
  jQuery(function($){
    if ($.fn.theiaStickySidebar) {
      $('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
    }
  });
});
</script>
```

**`cruise-transfer.php`**, both the gallery init and the sticky-sidebar init get the same treatment, combined into one `DOMContentLoaded` listener wrapping both (they're adjacent in the file):
```html
<script>
document.addEventListener('DOMContentLoaded', function () {
  $('#Img_carousel').sliderPro({
    width: 960,
    height: 500,
    fade: true,
    arrows: true,
    buttons: false,
    fullScreen: false,
    smallSize: 500,
    startSlide: 0,
    mediumSize: 1000,
    largeSize: 3000,
    thumbnailArrows: true,
    autoplay: false
  }).css('visibility', 'visible');

  jQuery('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
});
</script>
```
(The `.css('visibility','visible')` chained onto the gallery init is the reveal step for Design item 3 below — added here since this is the one place `cruise-transfer.php`'s gallery gets initialized.)

`window.EXP_NAME = '...'` (the other inline block in `tour-scripts.php`) needs no change — it's plain JS with no jQuery dependency, safe regardless of defer/timing since `tours.js` (deferred, reads it later) always executes after this line runs.

`tours.js` (used by the 4 shared-bundle pages) has its own unwrapped top-level `$('#Img_carousel').sliderPro({...})` call as its first statement — this does NOT need a `DOMContentLoaded` wrapper, since `tours.js` itself is now a deferred **external** script (not inline), and deferred external scripts already execute in guaranteed document order after their dependencies. Only the reveal chain needs adding here:
```js
$('#Img_carousel').sliderPro({
  width: 960,
  height: 500,
  fade: true,
  arrows: true,
  buttons: false,
  fullScreen: false,
  smallSize: 500,
  startSlide: 0,
  mediumSize: 1000,
  largeSize: 3000,
  thumbnailArrows: true,
  autoplay: false
}).css('visibility', 'visible');
```

### 3. Visibility safety net

Add to `css/vendors-tour.css`, `css/vendors.css`, and `css/vendors.unminified.css` (same 3-file sync pattern already established for this gallery's CSS fixes):

```css
#Img_carousel {
  visibility: hidden;
  animation: sp-force-reveal 0s 10s forwards;
}
@keyframes sp-force-reveal {
  to { visibility: visible; }
}
```

Elements with `visibility: hidden` occupy their normal layout space but are never painted — the Layout Instability API only records shifts for content that's actually rendered, so whatever the raw pre-JS markup does internally (however much it grows or wraps) generates zero CLS while hidden. The already-shipped `min-height`/`max-height`/`overflow:hidden`/`aspect-ratio` reservations keep the *page's* layout stable throughout (nothing below the gallery jumps when it's revealed, since the reserved box size never changes) — the two techniques are complementary, not redundant: the size reservation stabilizes surrounding content, `visibility:hidden` stabilizes the gallery's own internal chaos.

The `animation: ... 10s forwards` is a pure-CSS fallback: if JS never runs at all (script blocked, network failure), the gallery would otherwise stay invisible forever. After 10 seconds it force-reveals regardless of JS state — accepting a possible late shift in that rare failure case rather than a gallery that never appears. In the normal case (JS runs within a few seconds), the JS-driven `.css('visibility','visible')` reveal happens first and the animation never gets a chance to matter.

## Testing

Both the CSS-only fix and this follow-up were shipped with strong local-conditions verification that didn't reflect real-world timing — that gap is exactly what caused the need for this follow-up. This time, verification must happen under the same throttled conditions (CDP `Network.emulateNetworkConditions` at ~1.6 Mbps/750 Kbps/150ms latency, `Emulation.setCPUThrottlingRate` at 4x, `page.setCacheEnabled(false)`) that surfaced the original gap, not just fast/cached conditions — both locally before deploy and against production after.

Functional regression testing (menus, sticky sidebar, gallery thumbnail-click, date pickers, booking flow) must be checked on all 5 pages, since `defer` is a real behavioral change to script timing across the whole page, not just the gallery.

## Risks

- **`defer` timing changes could theoretically expose other latent unwrapped-jQuery-call bugs** beyond the 2 already found — the site-wide script investigation checked for this specifically (searched for top-level `$(...)`/`jQuery(...)` calls not wrapped in a ready-handler) and found only these 2 within the 5 in-scope pages' script chains, but functional testing across all 5 pages after the change is the real safety net here, not just static analysis.
- **The visibility-based safety net could mask a regression in the timing fix** if not tested carefully — since a broken timing fix combined with the safety net would still show low CLS (nothing painted = no shift recorded) even if the underlying "time to gallery-ready" didn't actually improve. Testing must check both the CLS number AND the actual wall-clock time-to-visible-gallery, not just CLS in isolation.
