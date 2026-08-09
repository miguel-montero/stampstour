# Fix sitewide footer double-nesting and unlabeled icon-only links

## Context

A real `axe-core` accessibility audit run against 3 live production pages (homepage, a tour page, a content page) found the same violations repeating across all three — a strong signal these are shared-component bugs (`includes/header.php`/`includes/footer.php`), not page-specific issues.

**Footer double-nesting (the core "tree not well-formed" bug):** every one of the 13 pages that includes `includes/footer.php` wraps that include in its own `<footer>` tag — and `footer.php` *also* opens its own `<footer>` internally. The rendered HTML on every page is literally:

```html
<footer class="revealed">   <!-- the page's own wrapper -->
    <footer>                 <!-- footer.php's own tag -->
        ...all footer content...
    </footer>
</footer>
```

This trips 3 separate axe violations on every page: `landmark-no-duplicate-contentinfo` (two `contentinfo` landmarks instead of one), `landmark-contentinfo-is-top-level` (the inner one isn't top-level), and `landmark-unique`. This is exactly the kind of structural defect that makes a page's landmark/outline structure ambiguous to a screen reader or an AI system parsing the accessibility tree — genuinely different from a purely visual/cosmetic issue.

The `class="revealed"` some of these wrapper tags carry has zero CSS or JS behavior anywhere in the codebase (confirmed via a full-codebase grep) — it's dead, inert, safe to discard entirely rather than migrate.

**Unlabeled icon-only links:** `link-name` was flagged 7 times on the homepage alone. Three are the header's social icons (`#top_links`, `includes/header.php`) — `<a href="..."><i class="bi bi-instagram"></i></a>` with no text, no `aria-label`. Three more are the footer's own copy of the same 3 social icons (`#social_footer`, `includes/footer.php`). The 7th is the header's "sticky" logo link (`a[href="/"]:nth-child(2)`, the `.logo_sticky` variant shown only after scroll) — its `<img>` does carry `alt="Stamps Tour"`, so the exact mechanism behind axe flagging it wasn't fully chased down (a plausible CSS-visibility interaction with accessible-name computation for scroll-triggered elements), but adding an explicit `aria-label` directly to that link is a one-line, unambiguously-safe fix regardless of the precise cause.

All 6 icon-only links live in the two shared includes, so fixing them once fixes every page site-wide, same as the footer fix.

## Goals

- Every page has exactly one, top-level `<footer>`/`contentinfo` landmark.
- Every link on every page has a discernible accessible name (an icon alone is not one).
- Both fixes apply via the shared includes (`header.php`, `footer.php`) plus the 13 pages' own wrapper markup, so the fix is complete sitewide in one pass, not per-page follow-ups.

## Non-goals

- The other axe findings from the same audit (missing form labels on the tour booking form, heading-order violations, cookie-banner color contrast, the cookie-banner content not being inside a landmark region) — explicitly deferred to a separate, later round, per the user's own request to scope this first pass to the two structural/link-name issues.
- `admin.php` — investigated and confirmed it does NOT include `includes/footer.php` at all; it has its own single, non-duplicated footer block. Not part of this bug, not touched by this plan.
- Any other icon-only links elsewhere on the site outside the header/footer includes (the audit's 3-page sample didn't surface any; a broader sweep is a candidate for a future round if this pattern turns out to recur elsewhere).

## Design

### 1. Remove the redundant footer wrapper (13 pages)

Each of the 13 pages currently wraps its `footer.php` include like one of these two patterns:

```html
<footer class="revealed">
    <?php include __DIR__ . '/includes/footer.php'; ?>
</footer>
```

or (3 content pages, no "revealed" class):

```html
<footer>
  <?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
</footer>
```

New: delete the wrapping `<footer...>`/`</footer>` tags entirely, keeping only the bare include call at its existing indentation:

```html
<?php include __DIR__ . '/includes/footer.php'; ?>
```

(For the 3 pages with a trailing `<!-- Common footer include -->` comment, keep that comment on the same line as the include.) `includes/footer.php`'s own `<footer>...</footer>` (already present, unchanged) becomes the page's sole footer landmark.

Affected pages: `index.php`, `blog-post.php`, `gallery.php`, `shopping.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`, `cruise-transfer.php`, `blog.php`, `maipo-valley-wine-tour-santiago.php`, `discover-santiago-city-tour.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `contact-us.php`, `privacy.php`, `refunds-cancellations.php`.

### 2. Add accessible names to icon-only links

`includes/header.php`, the 3 social icons in `#top_links`:

```html
<li><a href="https://www.instagram.com/stampstour/"><i class="bi bi-instagram"></i></a></li>
<li><a href="https://www.facebook.com/stampstour"><i class="bi bi-facebook"></i></a></li>
<li><a href="https://api.whatsapp.com/send?phone=56923993146"><i class="bi bi-whatsapp"></i></a></li>
```

New — add `aria-label` to each anchor (icons remain purely decorative, so also add `aria-hidden="true"` to each `<i>` to prevent the icon glyph itself from being separately, redundantly announced):

```html
<li><a href="https://www.instagram.com/stampstour/" aria-label="Instagram"><i class="bi bi-instagram" aria-hidden="true"></i></a></li>
<li><a href="https://www.facebook.com/stampstour" aria-label="Facebook"><i class="bi bi-facebook" aria-hidden="true"></i></a></li>
<li><a href="https://api.whatsapp.com/send?phone=56923993146" aria-label="WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a></li>
```

`includes/header.php`, the sticky logo link:

```html
<a href="/">
 <picture>
  <source srcset="/img/logo_sticky.webp" type="image/webp">
  <img alt="Stamps Tour" class="logo_sticky" height="34" width="147" src="/img/logo_sticky.png"/>
 </picture>
</a>
```

New — add a defensive `aria-label` directly on the link:

```html
<a href="/" aria-label="Stamps Tour">
 <picture>
  <source srcset="/img/logo_sticky.webp" type="image/webp">
  <img alt="Stamps Tour" class="logo_sticky" height="34" width="147" src="/img/logo_sticky.png"/>
 </picture>
</a>
```

`includes/footer.php`, the 3 social icons in `#social_footer` (same treatment, note this file's icon order is IG/WhatsApp/Facebook, different from the header's IG/Facebook/WhatsApp — preserve each file's own existing order, don't reconcile them):

```html
<li>
 <a href="https://www.instagram.com/stampstour/" aria-label="Instagram">
  <i class="bi bi-instagram" aria-hidden="true"></i>
 </a>
</li>
<li>
 <a href="https://api.whatsapp.com/send?phone=56923993146" aria-label="WhatsApp">
  <i class="bi bi-whatsapp" aria-hidden="true"></i>
 </a>
</li>
<li>
 <a href="https://www.facebook.com/stampstour" aria-label="Facebook">
  <i class="bi bi-facebook" aria-hidden="true"></i>
 </a>
</li>
```

## Testing

- **Re-run the exact same `axe-core` audit** (via Puppeteer, `axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'best-practice'] } })`) against the same 3 pages used to find these bugs, both locally and in production after deploy. Confirm `landmark-no-duplicate-contentinfo`, `landmark-contentinfo-is-top-level`, `landmark-unique`, and `link-name` all drop to 0 violations. Confirm no NEW violations appear (the wrapper removal touches layout-adjacent markup; a visual check is also warranted, see below).
- **Visual regression check**: screenshot the footer and header on all 3 representative page types before/after — removing the wrapper `<footer>` tag changes the DOM but `<footer>` has no default browser styling of its own (it's not `display:none` or otherwise visually special), so no visual change is expected; confirm this directly rather than assume.
- **Full 13-page sweep**: since the footer fix touches 13 different page files (not a single shared include), explicitly verify via `grep` that exactly 13 occurrences of the old wrapper pattern are gone and exactly 13 pages now render a single, correctly-structured footer landmark — don't rely on the 3-page audit sample alone for this part, since a typo in even one of the 13 files would be easy to miss.
- **Screen-reader-adjacent spot check**: use the accessibility tree inspector (Chrome DevTools' Accessibility pane, or CDP's `Accessibility.getFullAXTree`) on the header/footer social icons to directly confirm each now reports an accessible name (not just that axe stops flagging them — axe and the actual computed AX tree can diverge in edge cases, as already seen with the sticky-logo node in this same investigation).

## Risks

- **13 separate page files touched for the footer fix** — a single typo in any one could leave that page still double-nested or (worse) accidentally remove `footer.php`'s own opening/closing tag instead of the wrapper's. Mitigated by the exact before/after text given per page in this spec, and by the full 13-page grep sweep in Testing.
- **`aria-hidden="true"` on the icon `<i>` tags is new** — checked during brainstorming: zero CSS files in the codebase use `[aria-hidden]` as a styling selector (confirmed via `grep -rln "aria-hidden" css/*.css`, no matches). The only `aria-hidden` usages anywhere in the codebase are vendored Bootstrap JS managing its own modal/dropdown visibility state (`js/bootstrap.bundle.js`) — unrelated, JS-managed, not CSS-selector-driven, and not touching these icon elements. No conflict risk.
- **The sticky-logo `aria-label` fix is defensive, not root-caused** — if the actual mechanism behind axe's flag is something other than what's assumed (e.g., a genuine bug in how the sticky header becomes visible/hidden), the `aria-label` addition fixes the symptom (missing accessible name) without necessarily explaining why the existing `alt` text wasn't sufficient. Acceptable given the fix is safe and effective either way, but worth noting as an open question rather than a fully closed investigation.
