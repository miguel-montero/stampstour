# Fix Montserrat font-swap CLS with a metric-matched fallback font

## Context

Following the homepage tour-card lazy-load fix, a broader mobile CLS check across page types found the site's real, deterministic (100% reproducible on cold/first visits) remaining CLS contributor: the Montserrat webfont's `font-display: swap` causes a measurable text reflow when it finishes downloading and replaces the fallback (`Arial`).

Measured via cold, independent browser contexts (genuine first-visit simulation, not cache-warmed repeats) at a 390×844 mobile viewport, throttled to 1.6Mbps/150ms latency:

| Page | CLS | Dominant shift source |
|---|---|---|
| Homepage | 0.0635 | `tour_container`, `hero-subtitle` |
| Tour page | 0.0310 | `parallax-content-2`, `LI` |
| Content page | 0.0070 | `btn_1` |

All three are consistent and repeatable across multiple cold runs (confirmed 4/4 on homepage). All are within the 0.1 "Good" CLS threshold individually, but the homepage uses over half the budget on this single cause.

Precisely timed on the homepage: `montserrat-v31-latin-variable.woff2` finishes downloading at 2,846ms; the layout shift fires at 2,868ms — 22ms later. Icon fonts (`bootstrap-icons`, `fontello`, `icon_set_1`) were checked and ruled out as a meaningful contributor in the same investigation — their glyph-swap shifts are negligible (0.0000-0.0007) because icon containers reserve their box via fixed CSS dimensions (`width: 1em`) independent of font metrics, unlike body text, which reflows because Arial's and Montserrat's character widths differ.

This is expected behavior for `font-display: swap` (already used deliberately here to avoid a flash-of-invisible-text) — the fallback (Arial) is available instantly (already installed locally), while Montserrat is a real network download (measured ~2.5s under throttle) that always arrives later and always triggers a swap once it does, regardless of how long that takes. Nothing about *when* Montserrat finishes changes with this fix; what changes is whether that swap causes a layout shift.

## Goals

- Eliminate (or reduce to near-zero) the swap-caused CLS on all three page types, without changing `font-display: swap`'s core behavior (visitors still see text immediately, never a blank/invisible-text period) and without ever failing to show the actual Montserrat font on a page view (unlike `font-display: optional`, which was considered and explicitly rejected — see Non-goals).
- Apply the fix everywhere Montserrat is used as the body font (all three critical CSS variants: home, tour, content), since the measurement confirmed this is a sitewide issue, not a page-specific one.

## Non-goals

- Icon fonts (`bootstrap-icons`, `fontello`, `icon_set_1`) — confirmed negligible contributors to CLS in the same investigation, not touched by this fix. (`fontello`/`icon_set_1` do lack an explicit `font-display` value, defaulting to `auto`, which is a minor inconsistency worth a future tidy-up, but out of scope here since it's not a CLS problem.)
- Switching Montserrat to `font-display: optional` or `font-display: block` — considered and rejected. `optional` would eliminate the swap-caused shift entirely, but at the cost of some first-time slow-connection visitors never seeing Montserrat at all during that page view (the browser commits to the fallback permanently once its ~100ms decision window passes without the font being ready). Given this site's own throttled-mobile testing shows Montserrat taking ~2.5s to arrive — well past that decision window — first-time mobile visitors on a slow connection would routinely never see the brand's actual typeface. The metric-matched-fallback approach in this spec achieves the same CLS elimination without that trade-off.
- The homepage's other, larger, already-identified LCP bottleneck (the ~24-request page-load pile-up found during the tour-card lazy-load plan's final review) — a separate, distinct investigation.
- Any change to `fonts/fonts.css` itself (the file declaring the real `@font-face` for Montserrat) — it already correctly uses `font-display: swap`; this fix only adds a new, additional fallback `@font-face` alongside it.

## Design

### Computing the metric-override values

Rather than hand-deriving the `size-adjust`/`ascent-override`/`descent-override`/`line-gap-override` formula (early attempts at this during brainstorming produced two different, both-wrong results from manual calculation), the values below were generated using `fontaine` — the actual library Nuxt uses in production for this exact technique — against the `capsize` font-metrics database, run directly against this repo's real font file:

```bash
mkdir -p /tmp/font-metrics-calc && cd /tmp/font-metrics-calc
npm init -y >/dev/null 2>&1
npm install fontaine @capsizecss/metrics --silent
node -e "
const { generateFontFace } = require('fontaine');
const montserrat = require('@capsizecss/metrics/montserrat');
const arial = require('@capsizecss/metrics/arial');
console.log(generateFontFace(montserrat, { name: 'Montserrat-fallback', font: 'Arial', metrics: arial }));
"
```

Output (the values this spec uses):

```css
@font-face {
  font-family: "Montserrat-fallback";
  src: local("Arial");
  size-adjust: 112.8307%;
  ascent-override: 85.7923%;
  descent-override: 22.2457%;
  line-gap-override: 0%;
}
```

If Montserrat is ever changed to a different font in the future, this exact command (swapping the `require('@capsizecss/metrics/montserrat')` line) regenerates the correct values — don't hand-edit these percentages.

### 1. Add the fallback `@font-face` to all three critical CSS files

Add to `includes/critical/home.css`, `includes/critical/tour.css`, and `includes/critical/content.css` (each already inlines its own copy of the real Montserrat `@font-face`, per the existing site convention of duplicating critical-path font declarations per page-type critical file — this fix follows that same existing pattern rather than introducing a new one):

```css
@font-face{font-family:"Montserrat-fallback";src:local("Arial");size-adjust:112.8307%;ascent-override:85.7923%;descent-override:22.2457%;line-gap-override:0%}
```

(minified, single-line, matching each file's existing style — these files are not hand-formatted.)

### 2. Update the `body` font-family stack in all three files

Current (all three critical files, identical):

```css
body{...font-family:Montserrat,Arial,sans-serif;...}
```

New:

```css
body{...font-family:Montserrat,"Montserrat-fallback",Arial,sans-serif;...}
```

The metric-matched fallback sits between Montserrat and plain Arial: while Montserrat is downloading, the browser resolves to `"Montserrat-fallback"` (which renders using the locally-available Arial glyphs, but at Montserrat's own line-box dimensions via the override descriptors) instead of jumping straight to unmatched Arial. Plain `Arial, sans-serif` remains at the end of the stack as the ultimate fallback for the rare case where `local("Arial")` itself can't resolve (e.g. a device without Arial installed at all).

## Testing

- **Local functional/visual**: confirm the page still renders correctly with the new font stack, no console errors, no `@font-face` parse warnings.
- **Production CLS re-measurement (the actual regression test)**: using the same cold-browser-context methodology established in this session's investigation (fresh `context.close()`/re-create per run, not sequential loads in one browser, since a warm font cache silently masks the swap-timing effect) — measure CLS on all three page types (homepage, one tour page, one content page) under the same throttled-mobile conditions (390×844, 1.6Mbps/150ms), at least 3 cold runs per page. Compare against this spec's baseline numbers (0.0635 / 0.0310 / 0.0070). Expect all three to drop to near-zero; a real, verified measurement is required, not an assumption that the metric-matching math alone guarantees a clean result — text is still real content with real character-level width variance, so a *residual* small shift is plausible even with correct override values, and must be measured rather than assumed away.
- **Visual sanity check**: since Arial (scaled per `size-adjust`) is rendering during the swap window, briefly compare screenshots of the fallback-rendered state against the final Montserrat-rendered state to confirm no obviously broken/oversized/undersized text during the interim period — the override descriptors target line-box *height* and average character *width* statistically, not an exact glyph-by-glyph match, so some individual words may still shift slightly even as the overall box stays stable.

## Risks

- **The override values are computed for Montserrat "Regular" (400 weight)** via capsize's database entry, while this site uses a variable font spanning weight 100-900. Font ascent/descent metrics are normally weight-independent within a single font family (a design-level property, not a per-instance one), so this is expected to be a non-issue, but isn't independently re-verified per-weight in this spec — the Testing section's real CLS measurement is what actually validates this in practice, not the theoretical computation alone.
- **A residual, smaller shift may still occur** even with correct metrics, since `size-adjust`/`ascent-override`/`descent-override` match the font's overall line-box dimensions statistically (average character width, ascent/descent), not each individual glyph's exact width — Montserrat and Arial still have different letterforms. The goal is a large reduction (measured baseline → near-zero), not a mathematical guarantee of exactly 0.0000.
- **Sitewide blast radius** — this touches the `body` font-family in all three critical CSS files, affecting every page's text rendering during the font-load window. Mitigated by: the change only affects the *interim* rendering state (before Montserrat loads) or in the rare case Arial resolves via `local()` at all — final, loaded-Montserrat rendering is completely unchanged, and the visual sanity check in Testing explicitly covers this.
