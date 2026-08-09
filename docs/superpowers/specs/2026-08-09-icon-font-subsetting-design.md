# Subset the fontello and icon_set_1 icon fonts

## Context

A Lighthouse "avoid oversized web fonts" finding flagged the site's icon fonts. Investigation confirmed a real, dramatic waste:

| Font | Declared glyphs | Actually used sitewide | Current format | Current size |
|---|---|---|---|---|
| `fontello` | 1,929 | **36** | WOFF (no WOFF2 in the `@font-face` `src` list at all) | 320,576 bytes (313KB) |
| `icon_set_1` | 100 | **18** | WOFF (no WOFF2) | 41,852 bytes (41KB) |

The "no WOFF2" gap compounds the problem: with no `format("woff2")` source listed, every modern browser falls back to the much larger, less-compressed WOFF file.

Actual usage was determined precisely, not estimated: every `class="..."` attribute across all `.php` files was scanned, filtered to tokens matching `icon-*`/`icon_set_1_icon-*`, and cross-referenced against each font's own declared `.icon-X:before{content:...}` selectors in `css/vendors.css` to exclude false positives (e.g. `icon-114x114-precomposed` from unrelated Apple touch-icon `<link>` tags matched a naive grep but isn't a font glyph at all).

**A static-markup-only scan isn't sufficient, and this was caught before it shipped, not after.** `js/functions.js` (lines 88 and 95) dynamically toggles `.toggleClass('icon-minus icon-plus')` on an accordion's chevron indicator — neither class appears anywhere in static PHP markup at all (confirmed via a repo-wide grep restricted to first-party code, excluding vendored plugin bundles which can't reference this site's own icon classes). A JS-only usage scan (`grep` for `toggleClass`/`addClass`/`classList.add` combined with `icon-`/`icon_set_1_icon-` across first-party `.js` files and inline `<script>` blocks in `.php` files) found these 2 additional glyphs.

**Amendment (found by the plan's final whole-branch review, after this spec was already implemented):** a third JS-referenced fontello glyph exists — `icon-spin4` (`U+e801`), used 6 times in `assets/validate.js` as a loading-spinner icon — that this spec's scan missed because it only checked `js/functions.js`, not every `.js` file in the tree. It is NOT included in the shipped subset. Independently confirmed harmless: `validate.js` is dead code, referenced by no page currently in production (no page loads it, and the form IDs it targets — `#submit-contact`, `#submit-review`, `#submit-newsletter_2` — exist in no live markup). If `validate.js` is ever revived, its spinner will render blank until `U+e801` is added to the fontello subset and regenerated. The final, confirmed, precise codepoint lists actually shipped (36 fontello, 18 icon_set_1):

- **fontello** (36 codepoints, Private-Use-Area, e.g. `U+e872` for `icon-phone`): `U+ea35, U+ed5a, U+ee4e, U+e87a, U+e816, U+e815, U+e931, U+e9ec, U+eea6, U+e839, U+e83f, U+e932, U+e996, U+e82f, U+e8dd, U+eaf4, U+e90e, U+ed75, U+e81a, U+e872, U+e814, U+eb76, U+eb20, U+ed80, U+e8fa, U+ed84, U+e8f6, U+e99c, U+eba1, U+e899, U+e80f, U+ea9c, U+e827, U+eb9a, U+e821, U+e825` (the last two are `icon-plus`/`icon-minus`, the JS-only accordion glyphs)
- **icon_set_1** (18 codepoints, reused low-ASCII slots, e.g. `U+79` for `icon_set_1_icon-89`): `U+2f, U+30, U+22, U+36, U+39, U+3c, U+3d, U+42, U+45, U+24, U+4c, U+5c, U+67, U+6d, U+6e, U+73, U+77, U+79` (no JS-only additions found for this font)

Both fonts are declared in the same 5 files, 14 occurrences each (confirmed identical to the pattern this session already handled for the Montserrat font-swap fix): `css/vendors.css`, `css/vendors-core.css`, `includes/critical/home.css`, `includes/critical/tour.css`, and `includes/critical/content.css` (which alone has 10 duplicated occurrences of its own critical-CSS chunk structure).

The subsetting approach and exact numbers were verified for real, not assumed: `fonttools subset` (Python, installed and tested in this environment) was run against the actual `fontello.ttf`/`icon_set_1.ttf` files with these exact codepoint lists (including the 2 JS-only glyphs), producing genuinely working WOFF2 files — **7,608 bytes** (fontello, a 97.6% / 42.1x reduction) and **10,680 bytes** (icon_set_1, a 74.5% / 3.9x reduction) — both rendered and visually confirmed correct via a real headless-browser test covering a sample of glyphs from each font (phone, up-caret, down-caret, user, wine-glass from fontello; two icon_set_1 glyphs), with clean, sharp icon shapes and no missing-glyph placeholder boxes.

## Goals

- Cut both icon fonts down to exactly the glyphs actually used sitewide, with a genuine WOFF2 source (browsers currently fall back to the larger WOFF since no WOFF2 is offered at all).
- Apply the same subset files across all 5 declaring files / 28 total `@font-face` occurrences (14 fontello + 14 icon_set_1), so every page gets the benefit consistently.
- Keep every existing icon-using CSS selector (`.icon-phone:before`, `.icon_set_1_icon-89:before`, etc.) working unchanged — this is purely an asset-swap at the `@font-face` `src` level, not a markup or selector-naming change.
- Leave the original, full-glyph-set font files on disk, untouched, as the source of truth for regenerating an updated subset later if a new icon is ever needed (a documented, deliberate step, not silent breakage — per explicit decision during brainstorming).

## Non-goals

- Any other font on the site (Montserrat, Gochi Hand, Bootstrap Icons, the theme's other unused icon-font families like `icon_set_2`, `Glyphter`, `ElegantIcons`, `Pe-icon-7-stroke` found alongside fontello/icon_set_1 in `css/new_icons/` and `css/icon_restaurant/` — these weren't confirmed as part of this Lighthouse finding and weren't scoped in brainstorming; a natural, separate follow-up if a similar audit flags them).
- Legacy format support (EOT/TTF/SVG) — dropped entirely in the new `@font-face` declarations. This site already relies on modern CSS (`aspect-ratio`, container queries elsewhere in the codebase) with no indication of legacy-browser support requirements, so WOFF2-only is safe and standard 2026 practice; this drop is itself part of the size win, not a separate risk.
- Automating future icon additions (e.g. a build step that auto-detects new icon usage and regenerates the subset) — YAGNI given the site's slow, manual-deploy-driven pace; a documented manual command is sufficient.
- The `sp-force-reveal` non-compositable animation and the sitewide `transition: all` pattern also found in the same Lighthouse audit — explicitly deferred to a separate, second brainstorming round per the user's own request to sequence this work.

## Design

### 1. Generate the two subset WOFF2 files

Using `fonttools` (installable via `pip install fonttools` in a venv; already verified working in this environment):

```bash
fonttools subset css/fontello/font/fontello.ttf \
  --unicodes="U+ea35,U+ed5a,U+ee4e,U+e87a,U+e816,U+e815,U+e931,U+e9ec,U+eea6,U+e839,U+e83f,U+e932,U+e996,U+e82f,U+e8dd,U+eaf4,U+e90e,U+ed75,U+e81a,U+e872,U+e814,U+eb76,U+eb20,U+ed80,U+e8fa,U+ed84,U+e8f6,U+e99c,U+eba1,U+e899,U+e80f,U+ea9c,U+e827,U+eb9a,U+e821,U+e825" \
  --output-file=css/fontello/font/fontello-subset.woff2 \
  --flavor=woff2 --name-IDs='*' --layout-features='*'

fonttools subset css/fontello/font/icon_set_1.ttf \
  --unicodes="U+2f,U+30,U+22,U+36,U+39,U+3c,U+3d,U+42,U+45,U+24,U+4c,U+5c,U+67,U+6d,U+6e,U+73,U+77,U+79" \
  --output-file=css/fontello/font/icon_set_1-subset.woff2 \
  --flavor=woff2 --name-IDs='*' --layout-features='*'
```

Both new files live alongside the originals in `css/fontello/font/` (not a new directory) — same location convention, `-subset` suffix distinguishes them. The originals (`fontello.eot`/`.woff`/`.ttf`/`.svg`, `icon_set_1.eot`/`.woff`/`.ttf`/`.svg`) are NOT deleted — they remain the regeneration source (see Testing/Risks).

### 2. Update all 28 `@font-face` occurrences

Current pattern (repeated per-file, cache-busted query string varies slightly is actually identical `?32974303`/`?55361665` across all occurrences of each font):

```css
@font-face{font-family:fontello;src:url(/css/fontello/font/fontello.eot?32974303);src:url(/css/fontello/font/fontello.eot?32974303#iefix) format("embedded-opentype"),url(/css/fontello/font/fontello.woff?32974303) format("woff"),url(/css/fontello/font/fontello.ttf?32974303) format("truetype"),url(/css/fontello/font/fontello.svg?32974303#fontello) format("svg");font-weight:400;font-style:normal}
```

New (all legacy sources dropped, single WOFF2 source, same `font-family` name so no selector changes needed anywhere):

```css
@font-face{font-family:fontello;src:url(/css/fontello/font/fontello-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

(Path is root-relative `/css/fontello/font/...` in the critical CSS files and `css/vendors-core.css`/`css/vendors.css` per each file's own existing convention — matches whatever relative/absolute form that specific occurrence already uses; do not change path style, only the filename/extension/format list.)

Same transformation for `icon_set_1`:

```css
@font-face{font-family:icon_set_1;src:url(/css/fontello/font/icon_set_1-subset.woff2) format("woff2");font-weight:400;font-style:normal}
```

`content.css`'s 10 duplicated occurrences require the same scripted-replacement approach already established and proven in this session's Montserrat font-swap plan (exact-string replace with an assertion on the expected occurrence count, not manual per-occurrence editing).

## Testing

- **Visual verification of every actually-used glyph**, not just a sample — render each of the 36 fontello + 18 icon_set_1 glyphs (both via their real CSS classes, e.g. `.icon-phone`, `.icon_set_1_icon-89`, on an actual page or a local test harness) and confirm each displays as the correct icon shape, not a missing-glyph box. The brainstorming-phase spot-check (5 fontello + 2 icon_set_1 glyphs) already proved the subsetting mechanism works; this is the full, exhaustive version before shipping.
- **Specifically exercise the JS-only accordion toggle** (`icon-plus`/`icon-minus`, via `.accordion_styled`'s `hidden.bs.collapse`/`shown.bs.collapse` handlers in `js/functions.js`) — click through an actual accordion on a live page and confirm the chevron icon swaps correctly in both directions, since this is the one glyph pair that isn't visible from static markup alone.
- **File-size confirmation**: verify the new files on disk are close to the brainstorming-phase measurements (~7.6KB fontello, ~10.7KB icon_set_1) — a significant deviation would indicate the codepoint list or subset command changed unexpectedly.
- **No 404s / console errors**: load a representative page from each of the 3 critical-CSS variants (home, tour, content) and confirm no failed font requests, no `@font-face` parse warnings.
- **Production verification**: given this session's established lesson about Cloudflare's edge cache masking CSS/JS fixes, explicitly verify via `curl` that the deployed `@font-face` declarations reflect the new subset files (not a stale cached copy) before considering this done.

## Risks

- **A future icon addition will silently render as a missing/blank glyph if someone adds a new `.icon-X`/`.icon_set_1_icon-X` class usage (in markup or in JS) without regenerating the subset.** This is the deliberate, accepted trade-off from the brainstorming decision (subset to today's exact usage, not a speculative buffer) — mitigated by keeping the original full-glyph files on disk specifically so a future regeneration is a known, documented, one-command operation (the exact `fonttools subset` commands in this spec, with an updated `--unicodes` list) rather than a from-scratch investigation. Whoever adds a new icon usage in the future must remember this step — not enforced by tooling, since automating it was explicitly ruled out as YAGNI during brainstorming.
- **Blast radius**: 28 `@font-face` occurrences across 5 files, all shared/global stylesheets loaded on every page. Mitigated by the exhaustive glyph-by-glyph visual verification in Testing, and by the fact that the change is purely additive-safe at the CSS level (selectors and class names are completely unchanged — only the underlying font file swaps).
