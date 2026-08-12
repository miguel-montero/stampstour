# Subset bootstrap-icons.woff2 and remove a redundant CDN load to fix mobile LCP

## Context

Following up on "lcp and SI what can we do to improve those": a real Lighthouse audit against production (`https://stampstour.com/`, mobile form factor, Lighthouse's default DevTools throttling — 1.6Mbps down / 150ms RTT / 4x CPU slowdown) found:

- **LCP: 5.3s** ("Poor" — threshold for Poor is >4.0s)
- **Speed Index: 5.3s** (borderline "Needs Improvement"/"Poor" — threshold for Good is ≤3.4s, Poor is >5.8s)
- CLS: 0.001 (confirmed excellent — this session's earlier CLS fixes hold up)
- TBT: 30ms, FCP: 1.7s (both fine — this is not a CPU/JS-execution problem)

Lighthouse's own LCP breakdown for the homepage's hero image (`img/Tours/portada-mobile-hero.webp`, correctly marked `fetchpriority="high"`, discoverable in the initial HTML, not lazy-loaded — all three of Lighthouse's LCP-discovery checks pass):

| Subpart | Duration | % of LCP |
|---|---|---|
| Time to first byte | 1,170ms | 22% |
| Resource load delay | 93ms | 2% |
| **Resource load duration** | **3,932ms** | **74%** |
| Element render delay | 69ms | 1% |

The hero image itself is only 84KB — at the throttle profile's ~1.6Mbps, that should take roughly 0.4-0.5s, not 3.9s. Pulling the full network-requests waterfall from the same Lighthouse run explains why: **~20 other requests fire within the same ~15ms window** as the hero image request, all competing for the same throttled connection. Two of them carry `VeryHigh` scheduling priority — outranking the hero image's own `High` priority:

- `montserrat-v31-latin-variable.woff2` (35KB) — reasonable; the main body/heading font, legitimately urgent.
- **`bootstrap-icons.woff2` (127.7KB transfer size)** — the *entire* Bootstrap Icons webfont (~2,000 icons), despite only 7 distinct icon classes being used anywhere on the site (confirmed via `grep -rhoE 'class="[^"]*\bbi-[a-z0-9-]+[^"]*"'` across every `.php`/`.html` file): `bi-check-circle`, `bi-download`, `bi-facebook`, `bi-instagram`, `bi-printer`, `bi-whatsapp`, `bi-x-circle`.

The `VeryHigh` priority comes from `bootstrap-icons.min.css`'s `@font-face` declaring `font-display:block` — Chrome eagerly prioritizes fonts that can block text rendering, to minimize the invisible-text window. That's a reasonable browser heuristic; the problem is what's *behind* it: a 127.7KB file competing for bandwidth with the LCP image when the actual payload needed is a handful of glyphs.

**Separately**, `contact-us.php` loads Bootstrap Icons *twice*: once self-hosted via `includes/head.php` (which it already includes) and again from a third-party CDN (`cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css`) via a leftover `<link>` in its own `<head>` — for the exact same single icon it uses (`bi-whatsapp`). This is a second, independent, fully unsubsetted font fetch, plus an extra cross-origin DNS/TLS handshake, on top of the self-hosted one.

## Goals

- Reduce `bootstrap-icons.woff2`'s size to just what's actually used (7 glyphs), following the exact same `fonttools`-subsetting technique already proven twice this session for `fontello` and `icon_set_1` (each achieved ~95%+ size reductions with zero regressions).
- Eliminate the redundant third-party CDN load on `contact-us.php`.
- Reduce bandwidth contention during the LCP image's critical download window on mobile, directly improving LCP and (since it's driven by the same network congestion) Speed Index.

## Non-goals

- **TTFB (1.17s, 22% of LCP)** — server response time on HostGator shared cPanel hosting. Not an asset-level fix; a separate, larger-lift investigation (infrastructure/hosting change) if pursued later.
- **Changing `font-display` from `block` to `swap`** — considered and rejected. Once subsetted to ~1-2KB, the font fetch completes almost instantly regardless of priority, so it stops being a meaningful bandwidth competitor without needing a behavior change. Touching `font-display` also risks reopening a CLS question (FOIT-vs-FOUT tradeoffs) that's out of scope for an LCP-focused fix — this session's icon-font CLS work (the `icon_set_1-fallback`/`fontello-fallback` metric-matched faces) already covers the CLS angle for the *other* two icon fonts; extending that same fallback-face technique to `bootstrap-icons` was not found necessary here since no CLS was measured or attributed to it.
- **`detalle_reservas.php`** — an internal, auth-gated admin page (`require_once __DIR__ . '/admin/_auth.php'`) that does not include `includes/head.php` at all, and loads its entire Bootstrap 5 stack (both CSS grid *and* icons) standalone from the CDN with no self-hosted alternative to fall back to. Different page category (not part of public SEO/CWV scoring), and removing its CDN link would break its icons entirely since nothing else on that page provides `bootstrap-icons`. Left untouched.
- Any other icon font (`fontello`, `icon_set_1`) — already subsetted and fallback-protected earlier this session; not touched here.

## Design

### 1. Subset `bootstrap-icons.woff2`

Computed and verified directly against this repo's real font file (not assumed):

```bash
fonttools subset css/bs-icon-font/fonts/bootstrap-icons.woff2 \
  --output-file=css/bs-icon-font/fonts/bootstrap-icons-subset.woff2 \
  --unicodes=f26b,f30a,f344,f437,f501,f618,f623 \
  --flavor=woff2 \
  --layout-features='' \
  --no-hinting \
  --desubroutinize
```

Result: **130,396 bytes → 1,196 bytes (99.08% reduction)** — even more dramatic than `fontello`'s or `icon_set_1`'s reductions, since Bootstrap Icons' source font is much larger to begin with (~2,000 glyphs vs. the ~36-glyph `fontello` subset and ~150-ish-glyph `icon_set_1` source).

Verified via `fontTools.ttLib`'s `getBestCmap()`: the subset font's cmap contains **exactly** the 7 requested codepoints (`0xf26b, 0xf30a, 0xf344, 0xf437, 0xf501, 0xf618, 0xf623`) — no more, no less. This rules out the cmap-segment-boundary leak bug found and fixed during the `icon_set_1` subsetting work earlier this session (where subsetting to one codepoint could pull in an unrequested adjacent one); no such leak occurred here, confirmed by direct inspection, not assumed clean by default.

Verified visually: all 7 glyphs (check-circle, download, facebook, instagram, printer, whatsapp, x-circle) render correctly as their real icon shapes (not tofu/placeholder boxes) when loaded via a real `@font-face` in a real browser, screenshotted and visually confirmed.

The original `bootstrap-icons.woff2` (and `bootstrap-icons.woff`) are left untouched as regeneration-source references, matching the established convention from the `fontello`/`icon_set_1` subsetting work.

### 2. Update the `@font-face` declaration

`css/bs-icon-font/bootstrap-icons.min.css` currently has:

```css
@font-face{font-display:block;font-family:bootstrap-icons;src:url("fonts/bootstrap-icons.woff2?dd67030699838ea613ee6dbda90effa6") format("woff2"),url("fonts/bootstrap-icons.woff?dd67030699838ea613ee6dbda90effa6") format("woff")}
```

New:

```css
@font-face{font-display:block;font-family:bootstrap-icons;src:url("fonts/bootstrap-icons-subset.woff2") format("woff2")}
```

Two changes from the original, both matching the already-shipped `fontello`/`icon_set_1` precedent exactly:
- **Points to the new subset file.**
- **Drops the `.woff` fallback format entirely** (woff2-only `src`) — modern browser support for woff2 is ~97%+, and the site's other two icon fonts already made this same simplification.
- **Drops the cache-busting query string** (`?dd67030699838ea613ee6dbda90effa6`) — the new filename (`-subset` suffix) already serves as its own cache-buster; a different URL is automatically treated as new content by any cache, no query string needed (again matching `fontello-subset.woff2`/`icon_set_1-subset.woff2`, which carry no query strings).
- `font-display:block` is **unchanged** — see Non-goals for why this isn't being touched.

**File footprint: this is the *only* file requiring a change.** Unlike the `fontello`/`icon_set_1` fix (which required updating 6 files, because those fonts' `@font-face` declarations were duplicated into every critical-CSS variant), `bootstrap-icons`'s `@font-face` is declared in exactly one place — `bootstrap-icons.min.css` itself, loaded via its own dedicated `<link>` in `includes/head.php`. The critical CSS files (`includes/critical/home.css`, `content.css`, `tour.css`) only carry the *selector* rule (`.bi::before,[class*=" bi-"]::before,[class^=bi-]::before{font-family:bootstrap-icons!important;...}`), not the `@font-face`/`src` itself — confirmed via direct grep, no `@font-face` for `bootstrap-icons` exists anywhere else in the codebase.

### 3. Remove the redundant CDN load on `contact-us.php`

`contact-us.php` currently has, in its own `<head>` (line 17):

```html
<!-- Bootstrap Icons for WhatsApp icon -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
```

Delete this line entirely. `contact-us.php` already includes `includes/head.php` (confirmed), which already loads the (now-subsetted) self-hosted `bootstrap-icons.min.css` — the CDN link is pure redundancy for the exact same single icon (`bi-whatsapp`) already covered.

## Testing

- **Local**: confirm the subset file's cmap contains exactly the 7 codepoints (done above, ahead of writing this spec — will be re-verified as part of implementation). Confirm `bootstrap-icons.min.css` parses (brace balance). Confirm no other file references the old `bootstrap-icons.woff2`/`.woff` filenames that would need updating (already confirmed via grep — only `bootstrap-icons.min.css` declares the `@font-face`).
- **Visual/functional**: load `contact-us.php` (`bi-whatsapp`) and a page using the self-hosted font's other glyphs (`bi-facebook`/`bi-instagram` appear in the shared header/footer on every page; `bi-check-circle`/`bi-x-circle`/`bi-printer`/`bi-download` appear only on `detalle_reservas.php`, which is out of scope and uses the CDN version, not the self-hosted one this fix touches — no re-check needed there) in a real browser — confirm all glyphs actually covered by this fix render as their correct shapes, not tofu/missing glyphs.
- **Production LCP/Speed Index re-measurement (the actual regression test)**: re-run the same real Lighthouse audit (mobile, default throttling) against the homepage after deploying. Expect the `bootstrap-icons.woff2` entry to disappear from the `VeryHigh`-priority bandwidth competitors in the network-requests waterfall (replaced by a sub-2KB fetch that completes near-instantly), and expect LCP's `resourceLoadDuration` subpart to drop meaningfully — a real measurement is required, not an assumption that removing one 127KB competitor alone guarantees a specific LCP number, since ~19 other concurrent requests remain and TTFB (1.17s, unaffected by this fix) still bounds the floor.
- **contact-us.php-specific**: confirm via the browser's network panel (or a Puppeteer network-request listener) that the CDN request to `cdn.jsdelivr.net` no longer fires on page load.

## Risks

- **Low overall risk** — this is the third time this exact technique (subset an icon webfont to its actually-used glyphs, verify cmap exactness, verify visual rendering, swap the `@font-face` source) has been applied in this session, with zero regressions in the prior two applications.
- **Single-file footprint reduces risk further** compared to the `fontello`/`icon_set_1` fix, which had to stay in sync across 6 files (and did briefly drift out of sync once, caught by that fix's own final review) — there's no analogous multi-file drift risk here since `bootstrap-icons`'s `@font-face` lives in exactly one place.
- **LCP improvement is not fully guaranteed to be dramatic** — this fix removes one specific, real, measured contributor (127.7KB at inflated priority) from a ~20-request pile-up, but doesn't address every contributor in that pile-up (e.g., `gtag.js` at 185.6KB, or the sheer request *count* itself). The Testing section's real re-measurement is what actually validates the improvement, not the theoretical removal of one competitor alone. If LCP remains elevated after this fix, the remaining ~1.17s TTFB and the broader request-pile-up pattern are the next places to look (both explicitly out of scope here).
