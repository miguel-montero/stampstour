# Content pages critical CSS: extend render-blocking-CSS elimination to gallery + 4 other pages

## Context

A live Lighthouse audit of `gallery.php` flagged `render-blocking-insight` with an estimated 3,630ms of avoidable render-blocking time, spread across the shared stylesheets (`bootstrap.min.css`, `vendors.css`, `style.css`, `bootstrap-icons.min.css`, `lightbox2.css`, `custom.css`, `fonts.css`) that load via plain, blocking `<link>` tags on this page.

This exact problem was already solved on this site, twice: first for the homepage (`docs/superpowers/specs/2026-08-01-homepage-critical-css-design.md`), then extended to the 5 tour pages (`docs/superpowers/specs/2026-08-02-tour-pages-critical-css-design.md`). Both used the same mechanism: a critical-CSS block covering the above-the-fold content is inlined via a `$critical_css_file` PHP variable, and `includes/head.php` (shared by every page) uses its presence to switch the same 5-6 shared stylesheets from blocking `<link>` tags to a non-blocking preload/onload-swap pattern automatically. Setting `$critical_css_file` is the *only* change a page needs — `includes/head.php` itself requires no modification, since the gating logic is already generic.

The tour-pages spec's own Non-goals explicitly named this exact follow-up: *"Any other page template (blog, contact, privacy, etc.) — not part of what was asked for this round."* Confirmed directly: none of `gallery.php`, `refunds-cancellations.php`, `contact-us.php`, `privacy.php`, or `blog.php` currently set `$critical_css_file`, so all 5 still take the blocking-`<link>` fallback branch in `includes/head.php` — exactly the gap this spec closes.

`shopping.php` and `return.php` are deliberately excluded from this round, for the same reasons the tour-pages spec already gave for `shopping.php`: both have their own separate `<head>` markup rather than using `includes/head.php`, and both are live booking/checkout-flow pages where a mistake carries more real-world cost than on a content page. Applying this pattern to them is real, valid follow-up work, but a separate one — this spec sticks to the 5 pages that already share `includes/head.php`'s normal structure.

One concrete, already-encountered risk to carry forward: during the homepage's first pass, the `critical` extraction tool silently stripped private-use-area icon-font glyph escapes, causing a real, reproducible icon pop-in bug that shipped before a follow-up fix caught it (commit `8fe41041`, per the tour-pages spec). It was only caught because verification explicitly checked icon rendering, not just page layout — a purely visual "does it look right" pass had already missed it once.

## Goals

- Eliminate render-blocking CSS on the above-the-fold critical rendering path (shared header/nav + the `#hero_2` hero) for all 5 content pages: `gallery.php`, `refunds-cancellations.php`, `contact-us.php`, `privacy.php`, `blog.php`.
- No visual regression on any of the 5 pages, at any viewport — including icon-glyph rendering specifically, the exact failure mode this exact technique has already caused once on this site.
- JS-disabled visitors still see a fully, correctly styled page, via the existing `<noscript>` fallback already built into the mechanism.
- Confirm the homepage, the 5 tour pages, and `shopping.php`/`return.php` are all completely unaffected by this change.

## Non-goals

- `shopping.php` and `return.php`. Own separate `<head>` markup, live booking/checkout flow, deliberately deferred — matching the precedent already set for `shopping.php` in the tour-pages spec, applied here to both pages that share that same risk profile.
- Any change to `includes/head.php` itself. The existing `$critical_css_file`-gated mechanism is already generic — this spec only adds one new critical-CSS file and wires 5 pages to reference it.
- Reducing the byte size of the shared stylesheets themselves. This only changes *when* they load, not how large they are — that's the separate, already-scoped icon-font-subsetting piece of work (a different spec).
- A persistent build pipeline or automatic regeneration when source markup changes. Same static-snapshot maintenance model as the homepage and tour-pages work: `critical` runs once, locally, and its output is committed as plain CSS text.
- Deferring any other below-the-fold stylesheet specific to these 5 pages (unlike the tour pages' `timeline.css`, no equivalent page-specific blocking stylesheet was found on the content pages during exploration — if one turns up during implementation, it's an easy bonus, not a requirement of this spec).

## Design

### 1. Generate one shared critical CSS file across all 5 content pages

Run the `critical` npm package (same scratch, local-only usage as the prior two rounds — not a project dependency) against all 5 pages, served locally via `php -S`, at the same two viewports already used for the homepage and tour pages: 390×844 (mobile) and 1470×900 (desktop). That's 10 extraction runs, merged into one file covering the union of what's needed across all 5. Saved to `includes/critical/content.css`.

These 5 pages share the same header/nav and the `#hero_2` hero structure (just fixed in the prior LCP-focused spec — a real `<img fetchpriority="high">`, the `.hero-bg-img` CSS rule, and the `.opacity-mask`/`.intro_title h1` overlay), so a single shared file is the right approach here too, matching the reasoning already validated for the tour pages' shared `tour.css`. The pages aren't byte-identical below the very top (different `<h1>` text, `shopping.php`-style extra classes don't apply here since none of these 5 hide their hero), but the above-the-fold structure overlaps enough that a union file is safe — an unused rule sitting inertly in a shared critical-CSS block costs a little extra inline weight, not correctness, same conclusion the tour-pages spec already reached.

### 2. Wire up each of the 5 pages

Each page gets one new line in its existing top-of-file PHP block, before the closing `?>` — the exact same pattern `index.php` and the 5 tour pages already use:

```php
$critical_css_file = __DIR__ . '/includes/critical/content.css';
```

No other change to these files' `<head>` markup is needed.

## Verification

1. Generate the critical CSS, then visually verify on **all 5 pages**, not just one — local `php -S` server, headless Chrome via CDP device-metrics override (not `--window-size` below 500px, which silently clamps), screenshot immediately on load and again after full load, at both 390px and 1470px widths. The above-the-fold region (header + hero) must look identical in both captures on every page.
2. **Explicit icon-glyph check** on all 5 pages: confirm the header's phone/social icons render correctly in the immediately-on-load screenshot, not just after the deferred stylesheets swap in. This is the specific, already-reproduced failure mode from the homepage work — check it directly, don't infer it from layout looking right.
3. Confirm the hero image (`.hero-bg-img`, from the prior spec) still renders correctly under this new critical-CSS setup specifically — its own CSS rule lives in `custom.css`, one of the stylesheets whose loading timing this spec changes, so this is a real interaction point between the two pieces of work, not a redundant check.
4. Confirm the site remains fully usable with JavaScript disabled (the `<noscript>` fallback path) on at least one of the 5 pages.
5. `php -l` on all 5 modified PHP files.
6. Confirm no other page's rendered output changed — spot-check the homepage, one tour page, and `shopping.php`/`return.php` to confirm they're completely unaffected (all three fall through paths this spec doesn't touch).
7. Once deployed and Cloudflare's cache purged, re-run the Lighthouse mobile audit already used to diagnose this against `gallery.php`, and compare the `render-blocking-insight` finding and overall performance score against the most recent documented baseline (42/100, LCP 11.3s, ~3,630ms estimated render-blocking savings) — the real test of whether this achieved its goal.

## Risks

- **Critical CSS could miss a rule**, causing a brief flash when the deferred stylesheets swap in. Same risk class as both prior rounds, mitigated the same way: generating from real rendering across all 5 pages (not hand-picked selectors) and verifying each of the 5 individually, not just one.
- **The icon-font glyph-stripping bug could recur** — it's already happened once. Verification step 2 exists specifically because a "looks right" screenshot pass didn't catch it last time.
- **Interaction with the hero-image fix.** This is the first time the critical-CSS mechanism has been applied to pages using the newer `.hero-bg-img` real-`<img>` hero pattern (the tour pages use the older `.tour-banner`/`.tour-banner-bg` pattern, built before this spec existed). Verification step 3 exists specifically to catch any interaction between the two, rather than assuming the tour-pages precedent automatically covers it.
- **Static snapshot goes stale**, same as both prior rounds — if the shared header or hero markup changes meaningfully later, the inlined critical CSS won't automatically reflect it. Mitigated the same way: a regeneration note at the `$critical_css_file` definition sites, consistent with the existing convention.
