# Responsive tour card images on the homepage

## Context

A PageSpeed Insights mobile audit against production found the homepage's LCP metric (10.0s, reported) wildly inconsistent with the real observed network trace (every one of the page's 35 requests, including the LCP hero image itself, finishes downloading by 1.58s). Investigating the raw `network-requests` data explained the gap: the five homepage tour-grid card images (`img/Tours/{Valpo,Maipo,Andes,Stgo,Cruise}/portada.webp`) all start downloading at essentially the same moment as the hero image (~750ms), racing it for bandwidth. Under Lighthouse's simulated-throttled-mobile-connection model (used to compute the reported metric, as opposed to the real fast trace used elsewhere), that competing ~740KB of card images plausibly pushes the simulated hero-image completion time — and therefore the reported LCP — much later than the real, fast trace shows.

These five images are `loading="lazy"`, but the homepage's tour grid sits close enough to the top of the page (right after the hero and a short intro paragraph) that they're within the browser's lazy-load trigger distance on a typical mobile viewport, so they load essentially immediately regardless of the `lazy` attribute.

Before designing a fix, real render widths were measured directly (Puppeteer + CDP device-metrics override, not `--window-size`) rather than assumed from Bootstrap class names — an earlier assumption this session (that mobile renders these at ~180px, a 2-column grid) was wrong. The actual layout is single-column below Bootstrap's `md` breakpoint (768px; only `col-lg-6 col-md-6` classes are set on these cards, no smaller-breakpoint override), so real render widths run from 342px (320px viewport) up to 730px (1470px+ viewport):

| Viewport | Real card render width |
|---|---|
| 320px | 342px |
| 375px | 396px |
| 414px | 446px |
| 480px | 518px |
| 576px | 587px |
| 650px+ (2-column kicks in at 768px) | 384-727px |

Current native image widths (720–1440px depending on the tour) are therefore not dramatically oversized across the board — they're reasonably matched to 2x-retina rendering at the wider single-column widths (576-650px) and only meaningfully oversized (roughly 2-2.5x) for the narrowest phones (≤480px).

## Goals

- Reduce the byte weight these five images contribute during the homepage's initial load window on narrow mobile viewports, without any visible quality loss at their actual real-world render size.
- Directly test whether this measurably improves the reported mobile LCP metric, since that's the concrete problem motivating this work — not just a general "smaller is better" pass.

## Non-goals

- Rewriting the tour grid's layout or breakpoints. The current single-column-then-two-column behavior is unchanged; this only changes which image file loads at which width.
- Touching any image other than these five homepage card images (not the tour pages' own banners, not the blog, not lightbox gallery images) — those are separately scoped or already addressed.
- A large multi-tier responsive-image system (e.g. 4-5 size variants per image with a build pipeline). Confirmed with the project owner: two variants per image (one new smaller "mobile" file, one existing "full" file) is enough to capture the real win identified here.
- Guessing exact pixel targets in this design. The actual resized files will be generated and visually verified during planning, the same way the critical-CSS content and the Andes/Valparaíso banner resizes were handled earlier this session — this spec fixes the *mechanism*, not the exact numbers.

## Design

### New image variant

For each of the 5 tour card images, generate one additional "mobile" WebP variant sized to comfortably cover 2x-retina rendering at the narrowest real widths (roughly 320-480px viewport, i.e. up to ~600px source width) — exact target width to be confirmed empirically during planning against the measured render-width table above, then verified visually before committing (same process as the Cruise/Andes/Valparaíso resizes earlier this session: resize, convert to PNG for visual inspection, confirm no quality loss at actual display size, only then replace/add files). Store alongside the existing file, e.g. `img/Tours/Maipo/portada-mobile.webp`.

The existing full-size WebP and JPEG files are unchanged and continue serving as the "full" variant for wider renders (~576px and up), where they're already reasonably matched to real display need.

### Markup change

Each card currently uses:
```html
<picture>
  <source srcset="img/Tours/Maipo/portada.webp" type="image/webp">
  <img src="img/Tours/Maipo/portada.jpg" width="800" height="533" class="img-fluid" alt="Maipo Wine Tour" loading="lazy">
</picture>
```

Add `srcset`/`sizes` to the existing `<source>` (the fallback `<img>` is untouched — JPEG-fallback browsers without `<picture>`/`srcset` support are a vanishingly small, already-accepted edge case elsewhere on this site):

```html
<picture>
  <source srcset="img/Tours/Maipo/portada-mobile.webp 600w, img/Tours/Maipo/portada.webp 720w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
  <img src="img/Tours/Maipo/portada.jpg" width="800" height="533" class="img-fluid" alt="Maipo Wine Tour" loading="lazy">
</picture>
```

The `sizes` attribute reflects the real measured layout (single column, effectively full container width, below 768px; roughly half-width from 768px up) so the browser's own srcset-selection algorithm — which already accounts for device pixel ratio — picks correctly without any JS.

`loading="lazy"` stays as-is; this spec doesn't change *when* these images load, only *which file* loads once the browser decides to fetch one. (Whether these five images should be deferred further, e.g. via a stricter lazy-load hint, is a separate question not addressed here — the immediate, well-understood win is file size, not load timing.)

## Verification

1. Generate each mobile-variant file, convert to PNG, and visually inspect at its actual real-world render size before committing — same process as every image resize this session.
2. Local `php -S` server, confirm via the browser's actual selected `currentSrc` (not just presence of the `srcset` attribute) that narrow viewports genuinely load the new smaller file and wider viewports still load the existing full file — a `sizes` attribute mismatch could silently cause the browser to always pick the larger file, which would need to be caught here, not assumed correct from markup alone.
3. Confirm no visual regression on the homepage tour grid at the standard breakpoint set used throughout this session.
4. Once deployed (and Cloudflare cache purged, per the caching issue discovered earlier this session), re-run PageSpeed Insights (mobile) against the homepage and compare LCP specifically against the most recent baseline (10.0s) — this is the real test of whether the bandwidth-contention theory was correct. If LCP doesn't move meaningfully, that's a real, useful negative result worth recording, not a reason to consider the spec's own goals unmet (the byte-savings goal is separate from, and doesn't depend on, the LCP theory being fully correct).

## Risks

- **The core LCP-improvement hypothesis (bandwidth contention in Lighthouse's simulated throttling model) is a plausible, evidence-grounded theory, not a confirmed root cause.** The verification step explicitly treats "LCP doesn't improve" as a valid, informative outcome rather than a failure of this spec — the byte-weight reduction is worth doing on its own merits regardless.
- **`sizes` attribute correctness is easy to get subtly wrong** (a common source of responsive-image bugs is a `sizes` value that doesn't match the real CSS layout, causing the browser to under- or over-select). Verification step 2 exists specifically to catch this via real `currentSrc` inspection, not just visual "looks fine" checks.
