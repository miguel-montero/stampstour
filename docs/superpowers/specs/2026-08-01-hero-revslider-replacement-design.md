# Homepage hero: replace Revolution Slider with pure CSS

## Context

The homepage (`index.php`) uses Revolution Slider (RevSlider) to display a single static hero image (`img/Tours/portada.webp`) with a slow "zoomout" pan/zoom effect and a dark overlay for text contrast. There is no carousel, no video, and no navigation controls — it's one non-interactive slide.

Earlier this session, RevSlider was trimmed from 9 JS files + 2 stylesheets down to 6 JS files + 1 stylesheet (`docs/superpowers/reports/` — see git history around commit `61af56d0`), after two prior attempts to fully replace it with pure CSS both broke the hero: the first used flat per-breakpoint heights that under-sized the hero on wide screens; the second used uncapped `vw`-based scaling that produced a large visual discontinuity right at a breakpoint boundary, breaking the mobile/tablet view. RevSlider's actual responsive height turned out to be a non-linear, multi-segment curve (flat 257px below 500px width, three different scaling ratios between 500–1240px, flat 600px cap above 1240px) — empirically measured via headless Chrome across ~30 sample widths, not documented anywhere.

A follow-up Core Web Vitals audit (PageSpeed Insights, mobile + desktop, run against production) found:
- Mobile Lighthouse performance: 41/100, LCP 9.9s (poor), CLS 0.42 (poor)
- Desktop: 67/100, LCP 1.7s (good), CLS 0.422 (poor)
- Lighthouse's own LCP-attribution audits (`lcp-breakdown-insight`, `lcp-discovery-insight`) point at a RevSlider-internal element (`div.slot > div.slotslide`) — the "zoomout" transition doesn't just fade the image, it slices it into a JS-generated grid of tiles and animates those in, which is inherently slower and less predictable than painting a plain `<img>`.

This spec covers full removal of RevSlider from the homepage, replacing it with a pure-CSS hero that keeps the same visual appearance (image, dark overlay, centered text, a subtle zoom) across desktop/tablet/mobile — using Bootstrap's own breakpoints, since the theme already loads Bootstrap sitewide, rather than RevSlider's arbitrary non-Bootstrap breakpoints.

This is scoped separately from the render-blocking CSS/critical-path work identified by the same CWV audit (7 render-blocking stylesheets, ~2.85s of recoverable FCP time) — that's a distinct, independent piece of work with its own spec.

## Goals

- Remove RevSlider entirely from `index.php`: all remaining JS files, `rev-slider-files/css/settings.css`, the RevSlider markup, and the init script.
- Keep the same visual appearance at desktop, tablet, and mobile widths: hero image, `rgba(0,0,0,0.35)` dark overlay, centered "Discover Chile" text block (unchanged, already pure CSS), a slow background zoom.
- "Same appearance" means visually equivalent, not a pixel-perfect match to RevSlider's exact non-standard curve — sizing snaps to Bootstrap's breakpoints with heights chosen to look right at each tier, confirmed by screenshot comparison against real RevSlider renders.
- No JS-driven post-load resize (eliminates that class of layout shift entirely, unlike the earlier "trim" which kept RevSlider's own JS resize behavior).

## Non-goals

- Fixing the render-blocking CSS chain (separate spec).
- Fixing mobile LCP beyond what naturally improves from removing RevSlider's JS/DOM-construction overhead (the FCP-blocking CSS chain is the dominant remaining cause and is out of scope here).
- Any carousel, multiple slides, or navigation controls — none exist today and none are being added.
- Touching any page other than `index.php` (RevSlider is confirmed homepage-only via `grep -rl "rev-slider-files\|rev_slider" *.php`).

## Design

### Markup (`index.php`)

Replace the entire RevSlider block — `#rev_slider_66_1_wrapper`, the `<ul><li>` slide, the `tp-caption` overlay div, the `tp-bannertimer` div — with:

```html
<div class="hero-wrap">
  <img
    src="img/Tours/portada.webp"
    width="1883"
    height="1059"
    fetchpriority="high"
    alt="Colorful hillside houses in Valparaíso, Chile"
    class="hero-bg">
  <div class="hero-overlay"></div>
  <div class="hero-content text-center text-white">
    <!-- unchanged -->
  </div>
</div>
```

The `<img>` keeps its existing attributes (`fetchpriority="high"`, explicit `width`/`height`, `alt`) — this is already correct LCP practice and RevSlider never touched these directly. `.hero-content` is untouched; it was already plain CSS, independent of RevSlider.

Remove from `<head>`: the `rev-slider-files/css/settings.css` link.
Remove from before `</body>`: all `rev-slider-files/js/*` script tags and the `revapi66` init script block.

### CSS (`css/custom.css`)

```css
.hero-wrap {
  position: relative;
  overflow: hidden;
  height: 260px;
}
@media (min-width: 576px) {
  .hero-wrap { height: 340px; }
}
@media (min-width: 768px) {
  .hero-wrap { height: 420px; }
}
@media (min-width: 992px) {
  .hero-wrap { height: 520px; }
}
@media (min-width: 1200px) {
  .hero-wrap { height: 600px; }
}

.hero-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center center;
  animation: hero-zoom 20s ease-in-out infinite alternate;
}
.hero-overlay {
  position: absolute;
  inset: 0;
  z-index: 10;
  background-color: rgba(0, 0, 0, 0.35);
}
@keyframes hero-zoom {
  from { transform: scale(1); }
  to   { transform: scale(1.08); }
}
@media (prefers-reduced-motion: reduce) {
  .hero-bg { animation: none; }
}
```

Breakpoints are Bootstrap's own (`sm`/`md`/`lg`/`xl`: 576/768/992/1200px), already used elsewhere in the theme. Heights were chosen by reading RevSlider's real measured height at a representative width within each tier (from the ~30-point empirical sample taken earlier this session), then rounding to a clean number — not derived from a formula. `.hero-content`'s existing z-index (20) already sits above `.hero-overlay` (10), so no change needed there.

### Removed files (references only, files themselves left on disk)

All six remaining `rev-slider-files/js/*` includes and `rev-slider-files/css/settings.css`. The `rev-slider-files/` directory itself is left in place (nothing else references it, matching how `font-awesome.css` and the four already-removed extensions were handled earlier).

## Verification

1. Local `php -S` server, headless Chrome screenshots at Bootstrap breakpoint boundaries **and** at least one width mid-tier for each tier (not just boundaries — a mid-tier check is what would have caught the previous discontinuity bug): e.g. 375, 576, 650, 768, 880, 992, 1100, 1200, 1470, 1920.
2. Compare each screenshot against the corresponding real-RevSlider screenshot already captured this session (`/Users/miguelmontero/.claude/jobs/*/tmp/orig-*.png` and `dump2-*.html` measurements) for visual equivalence — image visible, overlay present, text centered and readable, no obvious height jump between adjacent widths.
3. `php -l` on `index.php`, div-tag balance check.
4. Confirm no remaining references to `rev-slider-files` or `rev_slider` in `index.php` after the edit.
5. Re-run PageSpeed Insights (mobile + desktop) against the deployed change as a follow-up sanity check once live — not a blocking step for this spec, since CrUX field data and full Lighthouse runs need production, but useful to confirm the CLS improvement materializes.

## Risks

- **Residual risk of a sizing mismatch** at some width not explicitly checked. Mitigated by checking more points than either prior attempt did, including mid-tier widths.
- **This does not fully fix mobile LCP** (9.9s) — the dominant cause there is the render-blocking CSS chain, covered by the separate spec. Removing RevSlider should still help (one less blocking stylesheet, no more JS-driven slot-grid construction before the image can be considered "rendered"), but shouldn't be presented as a full fix on its own.
