# Tour pages critical CSS: extend render-blocking-CSS elimination beyond the homepage

## Context

The homepage's render-blocking CSS was eliminated earlier this session (`docs/superpowers/specs/2026-08-01-homepage-critical-css-design.md`): a critical-CSS block covering the header/nav + hero is inlined, and the 5 shared stylesheets (`fonts.css`, `bootstrap.min.css`, `style.css`, `vendors.css`, `custom.css`, plus `bootstrap-icons.min.css`) load via a non-blocking preload/onload-swap pattern instead of plain blocking `<link>` tags. That work was deliberately scoped to `index.php` only — proving the mechanism on the highest-traffic page first — with an explicit note in the spec that the other ~9 page templates were follow-up work.

That mechanism is already built to be reusable: `includes/head.php` (shared by every page) gates both the inlined critical-CSS block and the non-blocking stylesheet pattern behind a single PHP variable, `$critical_css_file`. `index.php` sets it to `includes/critical/home.css` before including `head.php`; every other page currently leaves it unset, so they still fall into the blocking `<link>` fallback branch — same behavior as before the homepage work shipped. Adding critical CSS for a new page is therefore just: generate the CSS file, and add one line (`$critical_css_file = __DIR__ . '/includes/critical/<name>.css';`) to that page. No changes to `includes/head.php` are needed.

One real gotcha surfaced during the homepage work, worth carrying forward: the `critical` extraction tool silently stripped private-use-area icon-font glyph escapes on its first pass, causing a real, reproducible icon pop-in bug in production that needed a follow-up fix (commit `8fe41041`). This wasn't caught by layout-only visual comparison — it required specifically checking that icon glyphs render correctly, not just that the page layout looks right.

**Scope of this spec:** the 5 tour pages only (`portillo-inca-lagoon-andes-mountains-vineyard.php` / Andes, `maipo-valley-wine-tour-santiago.php` / Maipo, `discover-santiago-city-tour.php` / Santiago, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php` / Valparaíso, `cruise-transfer.php` / Cruise). `shopping.php` is deliberately excluded (see Non-goals) — it has its own separate `<head>` rather than using `includes/head.php`, it's the live checkout flow, and there's currently no way to get a real PageSpeed Insights baseline for it (it redirects without an active reservation session), all of which make it meaningfully higher-risk than the tour pages. It's scoped as a separate follow-up once this pattern has been proven a second time.

All 5 tour pages share the same `.tour-banner` / `.tour-banner-bg` above-the-fold structure (confirmed by inspecting all 5 files directly), a byproduct of the tour-banner-parallax-removal work done earlier this session. They're not byte-identical — Maipo and Valparaíso have an extra `.badge_tripadvisor_circle` element in the banner that Andes/Santiago/Cruise don't, and Santiago's `.badge_save` is commented out — but the structural overlap is large enough that one shared critical-CSS file, generated as the union across all 5 pages, is a reasonable approach (see Design). Unlike a missing rule (which causes visible pop-in, the real risk demonstrated by the homepage bug), an unused rule sitting inertly in a shared file is harmless — it costs a little extra inline-CSS weight, not correctness.

A second, smaller opportunity surfaced while inspecting these pages: all 5 also load `css/timeline.css` as a plain blocking `<link>` right after the `head.php` include, styling a `.cbp_tmtimeline` itinerary widget that appears well below the fold on every page (confirmed by its usage location in the markup, e.g. line ~228 of the Andes page, versus the banner at line ~29). It's identical across all 5 pages and isn't part of any page's critical rendering path, so it's a low-risk candidate for the same non-blocking treatment.

**On PSI data:** today's PageSpeed Insights API quota is exhausted (hit the daily limit from this session's earlier checks), so this spec proceeds without a fresh, tour-page-specific `render-blocking-insight` measurement. The homepage's own measured figure (2,850ms of recoverable FCP time from these same 5 stylesheets) is the best available estimate for what's blocking on tour pages too, since it's literally the same shared files loaded the same way — but this is an inference, not a fresh direct measurement, and should be confirmed via a real PSI re-check once the quota resets or the user runs one manually.

## Goals

- Eliminate render-blocking CSS on all 5 tour pages' critical rendering path (header/nav + `.tour-banner`), matching what already shipped for the homepage.
- Also defer `css/timeline.css` to non-blocking loading on all 5 pages, since it's identical across them and confirmed below the fold.
- No visual regression on any of the 5 pages, at any viewport, including icon rendering specifically (the exact failure mode the homepage work already hit once).
- JS-disabled visitors still see a fully, correctly styled page (via the existing `<noscript>` fallback pattern).

## Non-goals

- `shopping.php`. Deferred to a separate follow-up per the reasoning in Context — different head structure, live checkout flow, no real PSI baseline available yet.
- Any other page template (blog, contact, privacy, etc.) — not part of what was asked for this round.
- Changes to `includes/head.php` itself. The existing `$critical_css_file`-gated mechanism is already generic; this spec only adds a new critical-CSS file and wires 5 pages to use it, the same way `index.php` already does.
- A persistent build pipeline. Same as the homepage spec: `critical` runs once, locally, producing a static CSS string committed as plain text.
- Reducing the byte size of the shared stylesheets themselves, or of `timeline.css` — this only changes *when* they load.
- Automatic regeneration when source markup changes — static snapshot, same maintenance model as the homepage's file.

## Design

### 1. Generate one shared critical CSS file across all 5 tour pages

Run the `critical` npm package against all 5 tour pages, served locally via `php -S`, at the same two viewports used for the homepage (390×844 mobile, 1470×900 desktop) — 10 extraction runs total (5 pages × 2 viewports), merged into one CSS file covering the union of what's needed across all of them. This is a local, throwaway step (`npx critical`, scratch directory, not committed as a dependency) — only its output CSS text matters, saved to `includes/critical/tour.css`.

Because the 5 pages aren't byte-identical above the fold (the Tripadvisor badge on 2 of them, the commented-out save badge on Santiago), the merged file will contain the union of all their rules — including rules that go unused on pages that don't render that particular element. That's expected and safe, not a bug to fix.

The icon-font gotcha from the homepage work applies here too, more so: the shared header/nav (with its phone/social icons) appears on every one of these 5 pages, so any glyph-stripping in the extraction needs to be caught before shipping, not after. Verification (below) explicitly checks this.

### 2. Wire up each tour page

Each of the 5 tour PHP files gets one new line in its existing top-of-file PHP block, before the closing `?>` — the exact same pattern `index.php` already uses:

```php
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
```

No other change to these files' `<head>` markup is needed — `includes/head.php`'s existing `$critical_css_file` check handles both the inlined critical-CSS block and the switch to non-blocking stylesheet loading automatically, for all 5 pages, once this line is present.

### 3. Defer `timeline.css` to non-blocking loading

Each of the 5 tour pages currently has, immediately after its `include __DIR__ . '/includes/head.php';` line:

```html
<link href="css/timeline.css" rel="stylesheet"/>
```

Replace with the same preload/onload-swap + noscript pattern already established for the 5 shared stylesheets:

```html
<link rel="preload" href="css/timeline.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
```

This is independent of the `$critical_css_file` mechanism (it's not part of `includes/head.php`, and `timeline.css` isn't part of the critical-CSS content) — it applies unconditionally to all 5 pages, since deferring a below-the-fold stylesheet is safe regardless of whether critical CSS covers it.

## Verification

1. Generate the critical CSS, then visually verify on **all 5 pages**, not just one — for each: local `php -S` server, headless Chrome via CDP device-metrics override (not `--window-size` below 500px, which silently clamps), screenshot immediately on load and again after full load, at both 390px and 1470px widths. The above-the-fold region (header + tour banner) must look identical in both captures on every page — that's the signal the shared critical CSS actually covers what each page needs.
2. **Explicit icon-glyph check** on all 5 pages: confirm the header's phone/social icons and any other icon-font glyphs render correctly in the immediately-on-load screenshot, not just after the full stylesheet swaps in. This is the specific failure mode the homepage work already hit once (commit `8fe41041`) — check it directly rather than assuming layout-correctness implies icon-correctness.
3. Confirm the site remains fully usable with JavaScript disabled (the `<noscript>` fallback path) on at least one tour page.
4. Confirm `timeline.css`'s deferred loading doesn't cause a visible flash of the itinerary section — scroll-to and screenshot that section immediately on load vs. after full load, on at least one page.
5. `php -l` on all 5 modified PHP files, tag/div balance checks.
6. Confirm no other page's rendered output changed — spot-check the homepage and `shopping.php` to confirm they're unaffected (homepage should be completely untouched by this change; shopping.php falls through the same unset-`$critical_css_file` blocking path it already uses today).
7. Once deployed (and Cloudflare cache purged), re-run PageSpeed Insights (mobile + desktop) against at least one tour page (Maipo has an existing baseline from earlier today: mobile score 71, LCP 4.4s, CLS 0.127) and compare FCP specifically — not a blocking step for this spec, but the actual measure of whether this achieved the expected win. If the PSI API quota is still exhausted when this step is reached, this can be done manually via pagespeed.web.dev, or deferred to a later session.

## Risks

- **Critical CSS could miss a rule**, causing a brief flash when the full stylesheets swap in — same risk class as the homepage work, but with more surface area (5 pages' worth of above-the-fold markup instead of 1) increasing the chance something page-specific gets missed in the union. Mitigated by generating from real rendering across all 5 pages (not hand-picked) and by verifying all 5 individually, not just one, in Verification step 1.
- **The icon-font stripping bug could recur.** It already happened once on the homepage's first pass. Verification step 2 exists specifically because "looks right" screenshots alone didn't catch it last time.
- **The shared-file approach could turn out to be wrong** if verification finds a page-specific visual difference that can't be reconciled by simply adding more rules to the union (e.g., if two pages need visually conflicting values for the same selector, which isn't expected given they use the same CSS classes, but hasn't been ruled out). If that happens, the fallback is per-page critical CSS files instead of one shared one — a larger but mechanically identical change, to be raised during planning/implementation if discovered rather than assumed away here.
- **No fresh PSI baseline for tour-page render-blocking time specifically** — today's quota is exhausted, so the expected-savings estimate is inferred from the homepage's own measurement rather than independently confirmed for these pages. Verification step 7 is the real test, whenever it can be run.
- **Static snapshot goes stale**, same as the homepage's file — if the shared header or any tour banner's markup changes meaningfully later, the inlined critical CSS won't automatically reflect it. Mitigated the same way: a clear regeneration comment at the `$critical_css_file` definition sites.
