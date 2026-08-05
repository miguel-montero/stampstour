# Gallery: client-side rendering + smaller thumbnails

## Context

The gallery page (`gallery.php`) already loads photos progressively as the user scrolls — an `IntersectionObserver`-driven reveal in `js/gallery.js` shows an initial batch of 16 photos, then more in batches of 16 as the user nears the bottom, toggling a `gallery-hidden` CSS class on pre-rendered `.gallery-item` elements. This shipped in an earlier session and is confirmed live and working.

Despite that, the site owner reports the gallery is slow on first visit. Direct measurement against the live site confirms real remaining costs the batching didn't address:

- `gallery.php`'s HTML response is ~113KB, because the server renders full `<div><a><img></a><p>...</p></div>` markup for **all 166 photos** up front — the existing batching only hides items via CSS, it doesn't avoid generating or shipping their markup.
- Each grid thumbnail is a 500px-wide WebP at quality 80, ~60KB — so the first revealed batch of 16 alone is roughly 1MB of images, even though the grid only ever displays them at ~220–320px wide (`css/gallery.css`'s `minmax(220px, 1fr)` columns).
- The full-size `large.webp` variant (1600px, ~160KB) is unaffected by any of this — it only loads when a photo is opened in the `lightbox2` viewer, not on page visit, so it's out of scope here.

The gallery's images are produced by three independent code paths that must all move together: `gallery-pipeline/lib/image-processing.js` (used by the local Node pipeline's `publish.js` and `bulk-publish.js`), `admin/gallery-upload.php`'s PHP/GD resize function (the production admin-panel upload path), and the 166 already-published thumbnails themselves, whose original source files are still available at `gallery-pipeline/incoming/_published/`.

## Goals

- Reduce `gallery.php`'s initial response size by moving from "render all 166 photos' markup, hide most" to "render a small JSON payload of photo metadata, build only the revealed batch's DOM client-side" — so the page stays light regardless of how large the gallery grows, not just today's 166.
- Reduce each grid thumbnail from ~60KB (500px, quality 80) to a smaller file by generating at a size closer to actual display width (350px) and a slightly lower quality (72), applied consistently across both upload paths (local pipeline, admin panel) and backfilled onto the 166 already-live thumbnails.
- Preserve every existing behavior exactly: tag filtering, infinite-scroll-style batch reveal, and the `lightbox2` full-size viewer must all keep working identically from the visitor's perspective — this is a performance change, not a UX change.

## Non-goals

- Full functionality without JavaScript. Today, a no-JS visitor sees all 166 photos unfiltered (the hide/reveal mechanism just never activates) — a low-stakes, essentially theoretical fallback, since the rest of the site (nav, hero backgrounds, the lightbox itself) already requires JavaScript. After this change, a no-JS visitor sees zero photos, since nothing server-renders the grid anymore. Accepted trade-off; mitigated with a `<noscript>` message so it reads as intentional rather than broken, not with a working no-JS rendering path.
- True backend pagination (an AJAX endpoint that fetches batches on demand). Considered and explicitly rejected during design: the embedded-JSON approach already cuts the initial payload from ~113KB of HTML to roughly ~25KB of JSON (which compresses further over the wire), without adding a new API endpoint, loading states, or error handling. Worth revisiting only if the gallery grows into the thousands of photos.
- Changing `large.webp` (the lightbox full-size image) generation — it's already only fetched on click, not on page visit, so it isn't part of the problem this design addresses.
- Any change to the tag-filter pills or the "no photos yet" empty state — both stay exactly as they are today (server-rendered, cheap, no reason to move to JS).

## Design

### `gallery.php`: emit JSON instead of markup

The existing PHP logic — reading and merging `gallery-data.json` and `gallery-data-admin.json`, sorting by `dateAdded` descending, computing `$allTags` for the filter pills — is unchanged. What changes is the photo grid itself: instead of a `foreach` loop writing out full markup per photo, the sorted `$photos` array is reduced to only the fields the client needs (`id`, `thumb`, `large`, `tags`, `dateAdded` — dropping `title` and `sourceFile`, which the client never uses) and JSON-encoded into a `<script type="application/json" id="gallery-photos-data">` block. The `.gallery-grid` container itself ships empty in the initial HTML; `js/gallery.js` populates it. A `<noscript>` element inside (or adjacent to) the grid reads "Enable JavaScript to view the gallery."

### `js/gallery.js`: build DOM from JSON, batch by batch

On `DOMContentLoaded`, the script parses the JSON block into an array and keeps it in memory — this replaces `document.querySelectorAll('.gallery-item')` as the source of truth (there are no `.gallery-item` elements in the initial DOM to query anymore). A `renderBatch(items)` function creates the same DOM structure `gallery.php` used to render server-side (`div.gallery-item` → `a.gallery-item-link[data-lightbox="gallery"]` → `img[loading="lazy"]`, plus `p.gallery-item-date` when `dateAdded` is present) and appends it to `.gallery-grid`.

The reveal mechanism keeps its current shape: a sentinel element after the grid, observed via `IntersectionObserver` with the same `rootMargin: '200px'`, triggering `renderBatch` for the next 16 items from the *filtered* array each time it intersects. Switching tag filters clears `.gallery-grid`'s contents entirely and re-renders from the first batch of the newly-filtered set, same as today's reset-and-reveal behavior — just via DOM construction instead of visibility toggling. `lightbox2` requires no code changes: it binds via delegated click handling on `<body>` (confirmed in its source), so anchors created after page load are automatically clickable without re-initialization.

### Thumbnail generation: 500px/q80 → 350px/q72

Three call sites move together:

1. `gallery-pipeline/lib/image-processing.js`'s `generateVariants` — the thumb `sharp().resize({width: 500, ...})` becomes `width: 350`; `.webp({quality: 80})` becomes `quality: 72`. (The `large` variant's 1600px/quality-80 settings are untouched, per Non-goals.)
2. `admin/gallery-upload.php`'s `stamp_gallery_generate_variant` calls — the thumb call's `maxWidth` argument changes from `500` to `350`; the WebP-encoding branch's quality argument changes from `80` to `72`.
3. **Backfill**: a new one-time script, `gallery-pipeline/regenerate-thumbnails.js` (reuses `lib/image-processing.js`, not meant to be run repeatedly), re-generates `-thumb.webp` for all 166 already-published photos from their original files in `gallery-pipeline/incoming/_published/`, overwriting the existing thumbnail files in place at the new size/quality. Filenames are unchanged (same `id`-based naming), so no `gallery-data.json` edits are needed — this is a pure image-file replacement. Photos with no matching original file (shouldn't happen given every publish path preserves the source in `_published/`, but defensively) are skipped with a logged warning rather than failing the whole batch.

## Verification

1. Local `php -S` server + Puppeteer (same pattern as prior gallery verification): confirm the initial HTML response no longer contains 166 sets of photo markup (grid starts empty, JSON block present), confirm `.gallery-item` element count in the live DOM matches only the revealed batch count at a given scroll position (not 166), confirm scrolling still progressively reveals more, confirm tag filtering still isolates the right subset, confirm the lightbox still opens with the correct image and caption.
2. Confirm no-JS behavior manually (Puppeteer with JS disabled, or browser dev tools): grid area shows the `<noscript>` message, no console errors.
3. Regenerate the 166 thumbnails, spot-check several visually (resized image still looks correct, not corrupted) and confirm new file sizes are meaningfully smaller than the ~60KB baseline.
4. Compare total transferred bytes for a first visit (HTML + first-batch images) before and after, both locally (via browser dev tools network panel) and against the live site post-deploy.

## Risks

- **No-JS visitors regress from "unfiltered gallery" to "empty gallery."** Accepted per Non-goals — real-world impact is expected to be negligible given the rest of the site's existing JS dependency, but this is a genuine behavior change worth the site owner knowing about explicitly (not just buried in a commit message).
- **Thumbnail backfill touches all 166 live image files.** If the regeneration script has a bug, it could corrupt or mis-size existing live thumbnails across the whole gallery at once. Mitigated by spot-checking output visually before considering the backfill done, and by the source originals in `incoming/_published/` remaining untouched (a failed regeneration is always re-runnable from the same source files).
- **Reduced thumbnail quality (80→72) is a visual judgment call, not a hard threshold.** If it reads as noticeably worse at actual display size once live, quality can be tuned back up without re-architecting anything else in this design.
