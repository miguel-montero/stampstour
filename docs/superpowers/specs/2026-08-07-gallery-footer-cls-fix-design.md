# Gallery Footer CLS Fix — Design

## Problem

A post-deploy Lighthouse mobile audit of `gallery.php` (run after the
2026-08-06 content-pages critical-CSS work shipped) found a Cumulative
Layout Shift score of 0.42, almost entirely attributed to `<footer>`
shifting by ~678px. Root cause: `.gallery-grid` (`gallery.php`) starts as
an empty `<div>`, and `js/gallery.js` synchronously appends the entire
first batch (16 items, `BATCH_SIZE`) to it on `DOMContentLoaded`. The grid
jumps from 0 height to its full rendered height right after initial paint,
pushing everything below it — including the footer — down by that amount.
This is a regression from the earlier gallery-performance work that moved
photo-grid rendering from server-side HTML to a client-side JSON payload +
JS-built DOM (done to shrink initial HTML size); reserving space for the
grid itself was never addressed.

Individual tiles already reserve their own space via
`aspect-ratio: 4 / 3` on `.gallery-item-link` (`css/gallery.css:33`) — the
missing piece is space for the grid container as a whole, before JS has
run.

## Fix

Server-render the first `BATCH_SIZE` (16) photo tiles directly in
`gallery.php`'s initial HTML, using the same markup shape
`buildItem()` in `js/gallery.js` currently builds client-side. Real
content occupies real space from first paint, so there is nothing for the
browser to shift once JS runs.

### `gallery.php`

- `$photosForJs` (built at `gallery.php:70-83`) stays unchanged and
  unsliced — it's still the full photo list, still needed by JS for
  tag-filtering and to know what remains to append on scroll.
- Where `<div class="gallery-grid"></div>` is currently emitted
  (`gallery.php:85`), loop over `array_slice($photosForJs, 0, 16)` and
  render each tile server-side inside the div:
  - `.gallery-item` wrapper with `data-tags` (pipe-joined tag list, for
    parity with what JS sets on client-built items)
  - `.gallery-item-link` (`href="/{large}"`, `data-lightbox="gallery"`,
    `data-title="Upload date: {dateLabel}"` when `dateLabel` is non-empty)
    wrapping an `<img src="/{thumb}" loading="lazy" alt="Stamps Tour
    gallery photo">`
  - `.gallery-item-date` caption (`Upload date: {dateLabel}`) when
    `dateLabel` is non-empty
  - All dynamic values pass through `htmlspecialchars(..., ENT_QUOTES,
    'UTF-8')`, matching the escaping already used elsewhere in this file
- `loading="lazy"` is kept on these server-rendered images too, even
  though they're now present at initial paint — this matches the current
  JS-built behavior exactly (JS sets `img.loading = 'lazy'`
  unconditionally for every tile, first batch included) and avoids
  accidentally turning 16 images eager, which could regress LCP. Not a
  behavior change, just a change in *where* the HTML comes from.
- The batch size (16) is hardcoded in PHP, matching the existing hardcoded
  `var BATCH_SIZE = 16;` in `js/gallery.js`. No shared config layer
  between PHP and JS — consistent with how the codebase already handles
  this constant (it isn't shared today either).
- `<noscript><p class="gallery-noscript">Enable JavaScript to view the
  gallery.</p></noscript>` stays exactly as-is. (Its accuracy has shifted
  slightly — no-JS visitors will now see the first 16 real photos — but
  the copy is being left unchanged per explicit direction.)

### `js/gallery.js`

- Replace the unconditional `resetAndRenderFirstBatch();` call at the
  bottom of the `DOMContentLoaded` handler with a check: if
  `grid.querySelectorAll('.gallery-item').length > 0` (i.e., the grid
  already has server-rendered children), set `revealedCount` to that
  count and arm the sentinel (`sentinel.style.display = currentMatching().length
  > revealedCount ? 'block' : 'none'`) without rebuilding anything. If the
  grid is empty (shouldn't happen in normal operation since `gallery.php`
  only reaches this markup when `$photos` is non-empty, but keeps the code
  correct for that edge case), fall back to calling
  `appendNextBatch()` directly.
- `resetAndRenderFirstBatch()` itself is unchanged and is still used by
  the filter-pill click handler (`gallery.php`'s tag pills wipe
  `.gallery-grid` and rebuild via JS when clicked) — that path is a
  user-triggered interaction after the page has already settled, not part
  of Lighthouse's initial-load CLS measurement, so it doesn't need the
  same treatment.
- `IntersectionObserver`-driven `appendNextBatch()` for photos 17+ is
  unchanged — it still appends via JS as the user scrolls.

## Testing

- Fresh Lighthouse mobile audit on `gallery.php`: CLS should collapse from
  0.42 to near-zero, comparable to the 0.019 already measured on
  `refunds-cancellations.php`.
- Puppeteer checks:
  - First 16 tiles present in the raw server HTML (no JS execution) with
    correct `href`/`data-lightbox`/`data-title`/`img src` values.
  - With JS enabled, `revealedCount` starts at 16 (not re-rendered/
    duplicated), and scrolling triggers `appendNextBatch()` for items
    17+ with no gap or duplicate tiles.
  - Clicking a filter pill still correctly wipes and rebuilds the grid
    via the unchanged `resetAndRenderFirstBatch()` path.
  - Lightbox (`data-lightbox="gallery"`) opens correctly on server-
    rendered tiles, same as on JS-built ones.
  - No-JS: first 16 photos visible; `<noscript>` message still present
    beneath them, unchanged copy.

## Out of scope

- Any change to the `<noscript>` copy (explicitly left as-is).
- Any change to `appendNextBatch()`'s scroll-triggered CLS behavior
  (occurs below the fold during user-initiated scrolling; not part of
  Lighthouse's initial-load CLS score).
- The unrelated `contact-us.php` Cloudflare 409 bot-challenge finding from
  the same audit — a Cloudflare-level configuration issue, not a code
  bug, and not part of this fix.
- The residual ~150ms render-blocking savings from `lightbox2.css` /
  `gallery.css` on `gallery.php` (small, pre-existing, not part of this
  plan's scope either).
