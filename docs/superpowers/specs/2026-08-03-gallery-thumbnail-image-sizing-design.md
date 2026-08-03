# Right-size the tour-page photo gallery thumbnails

## Context

Each of the 5 tour pages has a photo-gallery slider (jQuery `sliderPro` plugin) with two visual components sharing the same underlying image files: a large "slide" view (the image shown when browsing the gallery) and a strip of small thumbnails below it (used to jump between slides). Both currently load the exact same `{n}_medium.webp` file per image — there's no dedicated thumbnail-sized asset at all.

Measured directly (Puppeteer + CDP, matching this session's established measure-don't-guess practice) against Maipo's gallery at 375px, 768px, and 1470px viewport widths:

| Element | 375px | 768px | 1470px |
|---|---|---|---|
| `.sp-thumbnail` (thumbnail) | 117×78 | 117×78 | 117×78 |
| `.sp-image` (slide) | 351×234 | 696×464 | 856×570 |

The thumbnail renders at a **fixed size regardless of viewport width** (the plugin sets `height: 100%` on a fixed-height container, `width: auto`, so each thumbnail's width follows its own image's native aspect ratio, but the height — and therefore the overall scale — never changes). The slide view, by contrast, genuinely scales with viewport and is already reasonably matched to the existing `_medium.webp` file's 1000×600 native size (not wildly oversized at any tested width) — that file stays as-is for slide-view purposes.

The waste is entirely on the thumbnail side: every thumbnail loads a 1000×600px file (50-220KB depending on the image) to display at 117×78px — roughly 4-8x oversized in each dimension for its real, fixed display need.

This isn't a small handful of images. Counting the actual gallery loops in each tour page:

| Tour | Gallery images | Total `_medium.webp` weight (all images, that tour) |
|---|---|---|
| Andes | 39 | 9.7 MB |
| Valparaíso | 45 | 4.3 MB |
| Cruise | 21 | 2.3 MB |
| Maipo | 9 | 1.1 MB |
| Santiago | 8 | 0.9 MB |

(All images load `loading="lazy"`, so not every thumbnail necessarily downloads on every visit — but the gallery sits high enough on the page to be "borderline in-viewport" per earlier investigation this session, and a visitor who scrolls through the gallery will trigger most or all of them. PageSpeed Insights' own "image delivery" diagnostic — 380-523 KiB — reflects only what its single-scroll-position crawl observes loading, not this full potential total.)

**Aside, confirmed but out of scope:** Andes' gallery has a pre-existing, unrelated bug — the thumbnail loop generates 39 thumbnails but the slide loop only generates 38 slides (confirmed: `img/Tours/Andes/39_medium.webp` exists on disk, but there's no 39th `.sp-slide` element for it to open into). This spec doesn't touch the loop counts or slide/thumbnail index mapping, only the thumbnail image `src` — noted here so it isn't confused with anything this change does.

## Goals

- Reduce the byte weight of the gallery thumbnail strip across all 5 tour pages, without any visible quality loss at the thumbnails' actual, fixed real-world display size.
- No change to the slide (full-size) view — it already loads reasonably-matched files.

## Non-goals

- Fixing the Andes 39-vs-38 slide/thumbnail count mismatch — real, pre-existing, unrelated to image sizing.
- A responsive `srcset`/`sizes` setup for thumbnails. Unlike the homepage tour cards (which genuinely resize across viewports), these thumbnails render at one fixed size everywhere — a single appropriately-sized file covers every viewport, no responsive-image mechanism needed.
- Changing anything about the slide view's image loading.
- Touching the main `_medium.webp`/`_medium.jpg` files themselves — those stay exactly as they are, used for slide view and (for the cover image) other existing references. This spec adds new, separate thumbnail-specific files alongside them.

## Design

For each gallery image across all 5 tours (~119 images total: 39 Andes, 45 Valparaíso, 21 Cruise, 9 Maipo, 8 Santiago — plus each tour's "cover" thumbnail, e.g. `portada.webp`, which uses the same `.sp-thumbnail` markup and has the same fixed-size real need), generate one new, small thumbnail-specific WebP variant, resized proportionally from the existing `_medium.webp` file (preserving each image's own native aspect ratio, since thumbnail width varies slightly per image while height stays fixed).

Target size: comfortably covers the real 78px display height at 2x-retina (156px), with margin — exact target height (and resulting per-image width) to be confirmed empirically during planning, generated and visually spot-checked across a sample from each tour before committing to the full batch, matching this session's established practice of confirming real generated output rather than guessing exact pixel targets in the design phase.

Store alongside the existing files with a clear naming convention, e.g. `img/Tours/Maipo/1_thumb.webp` (paralleling the existing `_medium` suffix convention).

Markup change: on each tour page, `.sp-thumbnail`'s `src` attribute changes from the shared `_medium.webp` path to the new `_thumb.webp` path. The `data-*` attributes on the corresponding `.sp-slide`/`.sp-image` elements (which drive the full-size slide view) are completely untouched — they keep pointing at `_medium.webp` as today.

```html
<!-- current -->
<img class="sp-thumbnail" src="img/Tours/Maipo/1_medium.webp" alt="Maipo thumbnail 1" loading="lazy">

<!-- proposed -->
<img class="sp-thumbnail" src="img/Tours/Maipo/1_thumb.webp" alt="Maipo thumbnail 1" loading="lazy">
```

The `<?php for (...) ?>` loop structure that generates these tags is unchanged — only the file path referenced inside it changes (from `{i}_medium.webp` to `{i}_thumb.webp`), plus the same change for each tour's separate "cover" thumbnail line.

## Verification

1. Generate the new thumbnail variants, convert a sample to PNG, and visually inspect at real display size (117×78, matching the measured value) before committing to the full batch — same process as every image resize this session.
2. Local `php -S` server: confirm via direct DOM inspection (not just markup) that thumbnails render at their expected fixed size with the new files, on at least one tour page, at multiple viewport widths (confirming the "fixed regardless of viewport" finding still holds and nothing regressed).
3. Confirm the slide (full-size) view is completely unaffected — same file, same behavior, on at least one tour page.
4. Confirm total byte-weight reduction by comparing old vs. new file sizes in aggregate, per tour.
5. Visual regression check: the gallery thumbnail strip should look identical (same images, no visible quality loss, no layout shift) before and after, on all 5 tour pages.
6. Once deployed and cache-purged, this is a real, direct byte-weight reduction independent of any LCP/CLS hypothesis — no PSI recheck is strictly required to confirm the win, though one can be run as a sanity check on the "image delivery" diagnostic specifically.

## Risks

- **Low risk overall** — this follows an already-proven pattern (the homepage tour-card mobile-variant work) and, unlike that work, doesn't need to reason about responsive `sizes` correctness at all, since thumbnail display size doesn't vary by viewport. The main risk is purely mechanical: generating ~119+ files correctly and wiring up the right path per image, which Verification steps 1-2 exist to catch.
- **Volume.** ~119+ new files is more moving parts than prior single-digit-count image work this session. Batch-generation via script (not one-by-one manual resizing) is expected, with visual spot-checking rather than inspecting every single file individually — worth being explicit about during planning.
