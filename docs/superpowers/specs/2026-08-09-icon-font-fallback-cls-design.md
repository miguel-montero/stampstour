# Fix icon-font FOIT/FOUT CLS with metric-matched fallback faces

## Context

User reported "desktop cls seems to be an issue" and "lcp too." A real desktop-viewport investigation (Puppeteer, 1920×1080, Lighthouse-equivalent desktop throttling — 10Mbps/40ms latency, 4x CPU slowdown — against production, cold browser context per run) had never been run this session; every prior CWV measurement used only a 390×844 mobile viewport.

Results:

| Page | CLS | LCP |
|---|---|---|
| Homepage | 0.0003 | 1224-1232ms |
| `discover-santiago-city-tour.php` | 0.0037-0.0221 | 1744-1804ms |
| `contact-us.php` | 0.1879 (both runs) | 928-1044ms |

LCP is not a problem anywhere measured — all three pages land well inside the 2.5s "Good" threshold. CLS is fine on the homepage and tour page. `contact-us.php` has a real, 100%-reproducible "Needs Improvement"-tier shift (0.1879, threshold for Good is ≤0.1).

Root-caused via layout-shift source attribution: one shift event at 670ms moves five elements simultaneously — the `.box_style_4` support-contact box (`col-lg-6 col-md-8`) and the footer's `row`/`col-md-3`/`col-md-2 footer-sep` columns beneath it. `.box_style_4 i { font-size: 52px }` (`css/style.css` / `includes/critical/content.css`) renders `<i class="icon_set_1_icon-89">`. The `icon_set_1` `@font-face` (confirmed via direct inspection of `css/vendors-core.css`) has no `font-display` value, defaulting to `auto` (FOIT, then swap once the font loads). The glyph classes' line-height is set in `em` (`[class*=icon_set_1_]:before{...line-height:1em...}`), so the line-box height at 52px is derived from whichever font is currently active. When `icon_set_1` finishes loading and its own hhea/OS2 metrics replace the browser's fallback-font metrics, the box's height changes — and because there's nothing else in that short page below the box except the footer, the shift is unusually large and visible.

`.box_style_4` is also used on `return.php`, `refunds-cancellations.php`, `privacy.php`, and `shopping.php` — same component, same font, same missing metrics — so this is very likely not contact-page-specific, just where it happened to surface during measurement.

## Goals

- Eliminate the icon-font FOIT/FOUT layout shift sitewide, for both icon fonts in active use (`icon_set_1`, `fontello`), at any font-size any component uses them at — not just the one 52px usage that happened to produce a measurable shift.
- Use the same metric-matched-fallback-face technique already shipped for Montserrat (`docs/superpowers/specs/2026-08-09-montserrat-font-swap-cls-design.md`), for consistency and because it's already proven to work in this codebase.
- Keep `font-display: auto`'s actual behavior otherwise unchanged — no visual/timing trade-off, unlike `font-display: optional` (which was rejected for the same reason it was rejected for Montserrat: on a slow first load, the icon could permanently fail to render for that pageview once the browser's decision window passes).

## Non-goals

- `icon_set_2` — a third icon font in use on 5 tour pages (`.eot`/`.woff`/`.ttf`/`.svg`, no woff2, never subsetted). No CLS was measured against it in this investigation; pulling it into this fix isn't warranted by evidence. A future audit could check it, but that's a separate thread.
- Re-measuring or changing anything about the Montserrat text-font fallback already shipped — that fix is unrelated and unaffected by this one.
- Investigating LCP further — measured as fine (928–1804ms) on every page checked. If the user has a different signal indicating an LCP problem (e.g. a specific page, or field data from Search Console/PSI), that's a new, separate investigation.
- Subsetting or otherwise modifying the icon font files themselves — this fix only adds new `@font-face` fallback declarations alongside the existing ones.

## Design

### Computing the metric-override values

Rather than using a generic font-metrics database (no public database entry exists for these custom Fontello-generated icon fonts), the values were computed directly from this repo's own font files via `fontTools`, reading the `hhea` and `OS/2` tables of the original (pre-subset) `.ttf` sources — `css/fontello/font/icon_set_1.ttf` and `css/fontello/font/fontello.ttf`:

```python
from fontTools.ttLib import TTFont
f = TTFont('css/fontello/font/icon_set_1.ttf')  # and fontello.ttf
hhea, head = f['hhea'], f['head']
ascent_pct = hhea.ascent / head.unitsPerEm * 100
descent_pct = abs(hhea.descent) / head.unitsPerEm * 100
linegap_pct = hhea.lineGap / head.unitsPerEm * 100
```

Both fonts (built by the same Fontello tool, same default settings) produced identical raw metrics — `unitsPerEm: 1000`, `hhea.ascent: 850`, `hhea.descent: -150`, `hhea.lineGap: 90` — and `OS/2.sTypoAscender/Descender/LineGap` match `hhea` exactly for both, so there's no browser-dependent ambiguity about which metrics table gets used at render time. Result, used for both fallback faces:

```
ascent-override: 85%
descent-override: 15%
line-gap-override: 9%
```

No `size-adjust` is needed (unlike the Montserrat fix). `size-adjust` matters for *text*, where a fallback font's average character width affects word-wrap and reflow. Icon glyphs are single, pre-sized `:before` pseudo-elements with an explicit `width: 1em` — their box width never depends on font metrics, only their box *height* does (via the `1em` line-height), which the override descriptors handle completely on their own.

### 1. Add two fallback `@font-face` rules

Add, wherever the real `icon_set_1` and `fontello` `@font-face` rules already exist:

```css
@font-face{font-family:icon_set_1-fallback;src:local('Arial');ascent-override:85%;descent-override:15%;line-gap-override:9%}
@font-face{font-family:fontello-fallback;src:local('Arial');ascent-override:85%;descent-override:15%;line-gap-override:9%}
```

(minified, single-line, matching each target file's existing style.)

### 2. Extend the two bracket-selector `font-family` rules

Current (all target files, identical pattern):

```css
[class*=" icon-"]:before,[class^=icon-]:before{font-family:fontello;...}
[class*=icon_set_1_]:before,[class^=icon_set_1_]:before{font-family:icon_set_1;...}
```

New — append the matching fallback as the second family in the stack:

```css
[class*=" icon-"]:before,[class^=icon-]:before{font-family:fontello,fontello-fallback;...}
[class*=icon_set_1_]:before,[class^=icon_set_1_]:before{font-family:icon_set_1,icon_set_1-fallback;...}
```

While the real font is loading, the browser resolves to the fallback face — which renders using locally-available Arial glyphs, but at the *real* icon font's line-box dimensions via the override descriptors — instead of an unmatched system default. Once the real font finishes loading, rendering is byte-for-byte what it already is today; nothing about the loaded-state behavior changes.

### File footprint

Both the `@font-face` pair and the bracket-selector pair need updating in every file that currently declares them:

| File | Occurrences |
|---|---|
| `css/vendors.unminified.css` | 1 (regeneration source for `vendors.css` via `clean-css-cli` — must stay in sync per the existing in-file hazard comment, same as the icon-font-subsetting fix) |
| `css/vendors.css` | 1 |
| `css/vendors-core.css` | 1 |
| `includes/critical/home.css` | 1 |
| `includes/critical/tour.css` | 1 |
| `includes/critical/content.css` | 10 (identical duplicated blocks within the one file) |

`vendors.unminified.css` uses quoted, multi-line formatting — different style from the other five files' minified single-line format. Match each file's existing convention; don't reformat surrounding code. For example, its `icon_set_1` fallback face and selector look like:

```css
@font-face {
	font-family: 'icon_set_1-fallback';
	src: local('Arial');
	ascent-override: 85%;
	descent-override: 15%;
	line-gap-override: 9%
}

[class^="icon_set_1_"]:before,
[class*="icon_set_1_"]:before {
	font-family: "icon_set_1", "icon_set_1-fallback";
	...
}
```

(same pattern for `fontello`/`fontello-fallback`, matching that block's own existing quoting).

## Testing

- **Local**: confirm no `@font-face` parse errors, icons still render visually correct (spot-check a few pages in a real browser).
- **Production CLS re-measurement (the actual regression test)**: same real desktop methodology used to find the bug (Puppeteer, 1920×1080, Lighthouse-equivalent throttling, cold `browser.createBrowserContext()` per run) — re-measure `contact-us.php` (confirmed-broken baseline: 0.1879) plus the other four `box_style_4` pages (`return.php`, `refunds-cancellations.php`, `privacy.php`, `shopping.php`) before/after. Expect `contact-us.php` to drop into "Good" range (≤0.1, ideally near-zero); a real measurement is required, not an assumption that the metric math alone guarantees a clean result.
- **Regression check on already-good pages**: re-measure homepage and the tour page (already 0.0003 / 0.0037-0.0221) to confirm this fix doesn't introduce any new shift there.
- **Visual sanity check**: briefly compare an icon rendered via the fallback face against its final real-font rendering — the override descriptors match line-box *height* only, not glyph shape, so the fallback state (which most users will never actually see, since icon fonts are small and typically load fast) may look visually different from the final icon, but must not be a different *size*.

## Risks

- **`content.css`'s 10 duplicated blocks must all be updated identically** — a partial update (some duplicates fixed, others not) would leave the bug present on whichever pages route through the un-fixed copies. The plan's task for this file must cover all 10, not just the first match.
- **`vendors.unminified.css` drift** — this file already has one documented history of silently falling out of sync with the regenerated `vendors.css` (found and fixed during the icon-font-subsetting plan's final review). This spec explicitly includes it in the file footprint to avoid repeating that mistake.
- **Sitewide blast radius, same shape as the Montserrat fix** — every icon glyph sitewide resolves through these bracket-selector rules, so this touches icon rendering everywhere during the font-load window. Mitigated the same way: the change only affects the brief interim state before the real icon font loads; final rendering is unchanged, and the visual sanity check in Testing explicitly covers this.
