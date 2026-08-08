# Tour Carousel Init CLS Fix — Design

## Problem

A post-deploy Lighthouse mobile audit of `discover-santiago-city-tour.php`
(run after the earlier "reserve gallery space to eliminate tour-page CLS"
fix, commits `87abacae`/`4c564958`, already shipped by a separate session)
still measures CLS 0.164 (score 0.72) — down from before, but not clean.

Live instrumentation with the browser's own
`PerformanceObserver('layout-shift')` API (the same mechanism Lighthouse
uses internally) found the remaining shift is dominated (~92% of the
measured value) by a single mechanism: `jquery.sliderPro.js`, on init,
detaches the server-rendered `#Img_carousel > .sp-slides` and
`> .sp-thumbnails` elements and re-wraps them inside new container
elements it creates (`.sp-slides-container`, `.sp-mask`,
`.sp-thumbnails-container`). For a brief moment mid-transition, the
browser's layout-shift tracking observes these elements' boxes collapse to
zero and reappear — confirmed directly: captured `LayoutShift` entries
show `.sp-thumbnails` and a sibling `.row` briefly reporting a
`currentRect` of `{top:0, height:0, width:0}` before settling.

This is not a wrong reserved-space value — separately measured, the
*final, settled* dimensions of `.sp-slides` and `.sp-thumbnails` already
match the existing `aspect-ratio: 960 / 500` / `min-height: 180px`
reservations from the prior fix, across mobile, tablet, and desktop
viewports (`.sp-thumbnails` settles to `height: 180px` exactly, driven by
the `min-height` rule winning over the plugin's own `height: 80px` inline
style, per normal CSS `min-height`-as-a-floor behavior). The shift is
purely a byproduct of the detach/reattach transition itself, which the
Layout Instability API penalizes regardless of whether the before/after
states are dimensionally identical.

A second, smaller shift (~8% of the total, likely a web-font-swap reflow
affecting several unrelated text elements simultaneously, not specifically
the async price fetch as first suspected) was also found during this
investigation. Per explicit direction, it is **out of scope for this fix**
— to be investigated separately later.

## Fix

Hide `#Img_carousel` (via `visibility: hidden`, not `display: none`) until
`jquery.sliderPro.js` has finished its init-time DOM restructuring, then
reveal it. `visibility: hidden` still reserves the element's layout
space — matching the mobile/desktop widths and the `aspect-ratio`/
`min-height` reservations already in place — so hiding and revealing the
carousel does not itself cause any additional shift to surrounding
content. Content that isn't visible when a shift happens isn't counted by
the Layout Instability API, so the entire restructuring transition becomes
invisible to CLS.

The plugin already exposes the exact hook needed for this: `init()` in
`js/jquery.sliderPro.js` fires `this.trigger({ type: 'init' })` and calls
`this.settings.init.call(this, { type: 'init' })` (lines 297-300) as the
*last* step of initialization — after all DOM restructuring and sizing is
complete. This is a documented plugin configuration callback, not a
timing guess.

### `css/vendors-tour.css` and `css/vendors.css`

Both files carry an identical duplicated copy of the prior CLS-fix block
(confirmed: the earlier fix touched both files symmetrically). Add,
immediately after the existing `#Img_carousel .sp-thumbnails { min-height:
180px; }` rule in both files:

```css
/* --- CLS fix (2026-08-08): the reservations above (aspect-ratio,
   min-height) already match the plugin's final settled dimensions, but
   jquery.sliderPro.js's own init-time DOM restructuring (detaching and
   re-wrapping .sp-slides/.sp-thumbnails into new container elements)
   still triggers layout-shift events during the transition itself. Hide
   the carousel (not display:none - visibility:hidden still reserves this
   element's layout space, so nothing else shifts) until the plugin's
   own 'init' event fires (see tours.js), confirming the restructuring
   is done. Content that isn't visible when a shift happens isn't
   counted toward CLS. */
#Img_carousel {
  visibility: hidden;
}
```

### `js/tours.js`

The existing `sliderPro({...})` call (`tours.js:2-15`) gains an `init`
callback:

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
     autoplay: false,
     init: function () {
       $('#Img_carousel').css('visibility', 'visible');
     }
   });
```

No other change to this call — every existing option is preserved
verbatim.

## Testing

- Puppeteer, against a live tour page: confirm `#Img_carousel` has
  `visibility: hidden` in its computed style immediately after
  `domcontentloaded`, and `visibility: visible` after a short bounded
  wait (the plugin's `init` event should fire quickly — well under a
  second on a normal connection; flag if it does not).
- Puppeteer: confirm no visible "flash" or collapse when the carousel
  becomes visible — its bounding box at the moment of reveal should
  already match its bounding box after full settle (screenshot
  comparison or bounding-rect comparison immediately before/after the
  `visibility` flip).
- Fresh Lighthouse mobile audit on `discover-santiago-city-tour.php`:
  CLS should drop from the 0.164 baseline to near-zero, comparable to
  what the gallery footer CLS fix achieved (0.42 → 0.004).
- Spot-check at least one other tour page (the fix applies via shared
  files to all 5: `cruise-transfer.php`,
  `discover-santiago-city-tour.php`,
  `maipo-valley-wine-tour-santiago.php`,
  `portillo-inca-lagoon-andes-mountains-vineyard.php`,
  `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`) —
  confirm the carousel still renders and functions (slides advance,
  thumbnails clickable) after the fix.

## Out of scope

- The smaller (~8%), lower-confidence shift suspected to be a web-font
  swap reflow — explicitly deferred per direction, to be investigated
  separately.
- Any change to the prior fix's `aspect-ratio`/`min-height` reservation
  values — confirmed correct as-is (they match the plugin's final
  settled dimensions); this fix only addresses the transition itself.
- Any change to `jquery.sliderPro.js` itself (a vendored third-party
  plugin) — the fix works entirely through the plugin's existing public
  configuration API (`init` callback) and CSS, no vendored-file edits
  needed.
