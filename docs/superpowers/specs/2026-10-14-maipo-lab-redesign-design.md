# Maipo Page Visual Redesign ("Lab") — Design

**Date:** 2026-10-14
**Page:** `maipo-valley-wine-tour-santiago.php`
**Status:** Approved in conversation (visual direction, layout, approach, mockup); awaiting written-spec review
**Builds on:** `2026-10-13-maipo-tour-options-design.md` (options, server-side pricing — unchanged here)

## Goal

Turn the Maipo tour page into a modern landing page for the wine tour, using the
new photo set, while keeping every piece of booking functionality exactly as it
works today. The page is the **lab** for a reusable styling system (Tailwind v4 +
shadcn-style PHP components) that the other tour pages will adopt later.

Success criteria:
- The page matches the approved mockup (`.superpowers/brainstorm/…/mockup-full-v3.html`):
  direction **C-B** (neutral charcoal, teal buttons, coral accents) and layout **A**
  (full-width landing flow + sticky bar).
- Booking behaves identically: option selector, itinerary switching, `?option=`
  URLs, price totals, coupon lock, availability calendar, `book_tour.php` →
  `shopping.php`. The existing headless check passes against the new markup.
- No visual change to the site header, footer, cookie banner or any other page.
- SEO signals preserved or improved; Lighthouse performance on the page does not
  regress versus today.

## Decisions (from brainstorming)

| Topic | Decision |
|---|---|
| Scope | Tour body only — everything between the existing site header and footer |
| Visual direction | C-B: dark neutral charcoal base, site teal `#008489` for buttons/selection, site coral `#e04f67` for prices/highlights |
| Layout | A: landing-page flow, booking form near the end, sticky "From $X · Check availability" bar on all viewports |
| Tech | Tailwind v4 standalone CLI, compiled CSS committed; reusable PHP components in `includes/ui/` |
| Award | Official Tripadvisor Travelers' Choice "Best of the Best" 2026 badge (existing assets in `img/badges/`) in the hero and the booking panel, with the line "Top 1% of experiences on Tripadvisor & Viator" |
| "Most popular" tag | Not shown (no booking data to back it) |
| Heading | "A day in Chilean wine country" (no "sorted") |

## Design tokens

| Token | Value | Use |
|---|---|---|
| `bg` | `#1c1f21` | page body background |
| `card` | `#262a2d` | cards, inputs |
| `line` | `rgba(255,255,255,.09)` | borders, dividers |
| `text` | `#e9ecee` | body text |
| `muted` | `#a8b0b5` | secondary text |
| `teal` | `#008489` | primary buttons, selected option, timeline |
| `teal-soft` | `#5fd0d4` | icons/checkmarks on dark |
| `coral` | `#e04f67` | prices, eyebrows, highlights, "not included" marks |
| font | Montserrat (already used by the site), system-ui fallback | all text |
| radius | 8px controls, 12px cards, 16px booking panel, full pills | |

Contrast: body text and muted text on `bg`/`card` meet WCAG AA (≥ 4.5:1); teal
button text is white on `#008489` (4.5:1+). Coral is used for large/bold text
only (prices, eyebrows) — verified in testing.

## Architecture

### Tailwind build (`bin/build-css`)
- Tailwind v4 standalone CLI (pinned version), downloaded by `bin/build-css` into
  `bin/.tailwind/` (git-ignored) on first run for the host OS/arch.
- Source `css/src/lab.css`:
  - `@import "tailwindcss/theme.css" layer(theme) prefix(tw);`
    `@import "tailwindcss/utilities.css" layer(utilities) prefix(tw);`
    — **no preflight**, and every utility is prefixed `tw:` so Tailwind classes
    can't collide with Bootstrap/CityTours class names (`container`, `hidden`,
    `table`, `btn`…) in either direction.
  - `@theme` block with the tokens above.
  - `@layer components` with a small `.lab` base: font, colors, heading/link/list
    resets **scoped under `.lab`**, so CityTours' global `h2 {color:#2a2a2a}` etc.
    don't leak in.
  - `@source` pointing at `maipo-valley-wine-tour-santiago.php` and `includes/ui/**`.
- Output `css/lab.css` (minified, committed — HostGator has no Node). Loaded on
  the Maipo page only, with `?v=` cache-busting.
- `bin/build-css --check` exits non-zero if `css/lab.css` is stale vs. sources
  (used in the test step so a forgotten build is caught).

### Components (`includes/ui/`)
One PHP function per component, pure render (escape every value; no DB access),
each with a PHPUnit render test. Data comes in as arrays.

| File / function | Renders |
|---|---|
| `ui_hero(array $d)` | full-bleed `<picture>` hero (responsive srcset, `fetchpriority="high"`, explicit width/height), award badge, H1, lead, fact chips, CTA, "From $X" |
| `ui_section_open(string $id, string $eyebrow, string $title)` / `ui_section_close()` | section wrapper with eyebrow + H2 |
| `ui_benefits(array $items)` | 4 benefit cards (icon, title, text) |
| `ui_option_cards(...)` | the existing option selector (same radios, `name`, `data-tour-options`, `data-booking-lock`, ids) restyled as large cards |
| `ui_gallery(array $photos)` | mosaic grid of `<picture>`s, lazy-loaded; click opens a native `<dialog>` lightbox with prev/next + keyboard + Esc |
| `ui_itinerary(...)` | segmented route switch (mirrors `tour_option_itin` radios) + per-option timelines in `[data-option]` blocks (same contract as today) |
| `ui_checklist(array $yes, array $no)` | included / not included cards (per option, in `[data-option]` blocks) |
| `ui_booking_panel(...)` | wraps the **existing** booking form markup: same `form#booking`, field ids/names, `.numbers-row`, `.date-pick`, `#coupon_code`, `#apply_coupon_valpo`, `#total_price`, `#new_total_container`, `#option_2` (hidden), `#activity_name` |
| `ui_accordion(array $items, string $id)` | `<details>/<summary>` accordion (no JS) — practical info and FAQ |
| `ui_sticky_bar(...)` | bottom bar with current option price + CTA; hidden while `#book` is in view |
| `ui_award(string $variant)` | official badge image + caption (`hero` / `compact`) |

Existing `includes/tours/option_ui.php` functions stay (other code/tests use
them); the new UI components call the same data and keep the same DOM contract
the JS relies on.

### JavaScript
- **No change** to the booking contract: `js/tours.js` and `js/maipo-options.js`
  keep working on the same ids/attributes.
- New `js/lab.js` (small, vanilla, deferred): gallery `<dialog>` lightbox;
  sticky-bar visibility (IntersectionObserver on `#book`) and price text update on
  `maipo:optionchange`; smooth-scroll for "Check availability" CTAs. Versioned URL.
- The segmented itinerary switch is the existing `tour_option_itin` radio group,
  styled — `maipo-options.js` already syncs it.
- `js/tours.js`: guard the Slider Pro init (`if ($.fn.sliderPro && $('#Img_carousel').length)`)
  so the Maipo page can stop loading `jquery.sliderPro.min.js`; other pages
  unaffected (they still load it and have `#Img_carousel`).
- `includes/tour-scripts.php`: optional `$tour_scripts_skip_gallery = true` flag
  to omit Slider Pro CSS/JS and the theia sticky-sidebar on the Maipo page.
  Default unchanged for other pages.
- The old "floating Book now button in the header" in `tours.js` keys off
  `.tour-banner`; on the new page it finds no banner and stays hidden — the sticky
  bar replaces it. (Behavior verified in testing; no code change unless it errors.)

## Page structure (top → bottom)

1. **Site header** — unchanged (`includes/header.php`).
2. **Hero** — photo **`PHOTO-2026-01-03-11-47-39`** (group toasting in the vineyard) as the LCP image; official white award badge + "Travelers' Choice · Best of the Best 2026 — Top 1% of experiences on Tripadvisor & Viator"; H1 "Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries" (unchanged text — keeps SEO); lead "Four wineries, pisco tasting and a lunch stop at a winery restaurant — with hotel pickup and an English-speaking guide."; fact chips (10 hours · Hotel pickup · Max 15 travelers · English guide · Free cancellation 24 h); CTA "Check availability" → `#book`; "From $110 per adult" (server price, unchanged logic).
3. **Why this tour** — eyebrow "Why this tour", H2 "A day in Chilean wine country"; 4 benefits: 4 wineries + pisco · Small group (max 15) · Door-to-door pickup (zones) · Free cancellation (24 h). Facts only from existing page content.
4. **Choose your route** — 3 option cards (label, adult price, tagline). No "Most popular" tag.
5. **Gallery** — 8 new photos, mosaic (desktop 3-col with one tall + one wide; mobile 2-col), lightbox. Captions/alt text describe the scene without implying lunch is included on every option (e.g. "Asado lunch stop at a winery restaurant (included on Classic + Lunch)").
6. **Your day** — segmented switch + timeline; substitution note under the heading.
7. **Included / Not included** — per option.
8. **Book your date** (`id="book"`) — booking panel (two columns desktop, one mobile) with the compact award badge + "Free cancellation up to 24 h before · Secure payment".
9. **Good to know** — accordion: Pickup & return, Additional information, Cancellation policy (existing text, unchanged).
10. **FAQ** — accordion with the existing `$tour_faqs`; FAQPage JSON-LD still emitted (via a schema-only helper; the old Bootstrap accordion markup is not rendered on this page).
11. **Sticky bar** — "Classic Route · $110 · Check availability" (updates with the selected option).
12. **Site footer** — unchanged.

Removed from this page: old banner + Slider Pro gallery + `single_tour_feat` icon row
+ two-column Bootstrap layout + theia sticky sidebar.

## Photos

- Source: `../photos/` — 8 photos (the two marked "no usar" are excluded).
- `bin/make-images` (one-time, local, uses macOS `sips` + `cwebp` if present, else
  `sips` JPEG only) writes `img/Tours/Maipo/2026/<slug>-{480,960,1600,2400}.{webp,jpg}`
  (2400 only for the hero). Slugs: `vineyard-toast` (hero), `asado-grill`,
  `tasting-room`, `pisco-sour`, `asado-skewer`, `empanadas`, `toast-table`,
  `red-wine-friends`.
- Only resized outputs are committed (target: hero ≤ 250 KB at 1600 WebP,
  gallery images ≤ 120 KB at 960 WebP). Originals stay outside the repo.
- Every image: descriptive English alt text; explicit `width`/`height`.
- `$lcp_preload_image` / `$lcp_preload_image_mobile` point at the hero's 1600 /
  960 WebP.

## SEO

- Unchanged: `$page_title`, `$page_description`, canonical, H1 text, Product +
  AggregateOffer + FAQPage JSON-LD, all three itineraries server-rendered.
- Product schema `image` → new hero photo (absolute URL).
- Heading hierarchy: one H1; each section H2; timeline stops H3.
- Image alt text and lazy loading as above; `og:image` set to the new hero.

## Performance

- `css/lab.css` target ≤ 30 KB minified; loaded render-blocking on this page only
  (small, and avoids FOUC on the dark page); `includes/critical/tour.css` still
  inlined for the header.
- Drop Slider Pro CSS/JS + theia on this page.
- Hero `<picture>` with WebP srcset, preloaded, `fetchpriority="high"`.
- Gallery images `loading="lazy"` + `decoding="async"`.
- Lighthouse (mobile) on local `php -S` before vs. after: Performance not lower,
  CLS ≤ 0.1, no new console errors.

## Error handling / edge cases

- Option without DB price: option card disabled with "Contact us" (existing rule);
  sticky bar falls back to "From $X" of the lowest priced option; if none priced,
  bar shows "Check availability" only.
- JS disabled: same as today — page readable, the selected option's itinerary
  visible (others `hidden`), booking requires JS.
- `<dialog>` unsupported (very old browsers): gallery links open the image file
  directly (anchor `href` to the 1600 image).
- `prefers-reduced-motion`: no smooth scroll / transitions.

## Testing

- PHPUnit: one render test per `includes/ui/` component (structure, escaping,
  required ids/attributes, srcset/alt/width/height); suite stays green.
- `bin/build-css --check` passes (compiled CSS up to date).
- Headless Playwright (existing scratch setup) against `php -S`:
  - all Task 9 checks of the previous plan still pass (selected states, hidden
    blocks, `?option=` without reload, totals 258/270, cupos request for Maipo,
    coupon lock, Valparaíso page unchanged);
  - sticky bar shows/hides around `#book` and updates price on option change;
  - gallery dialog opens, navigates, closes with Esc;
  - no console errors; screenshots at 390×844 and 1440×900 saved for review.
- Visual check against the mockup by the owner (screenshots).
- Lighthouse mobile before/after; Rich Results test on the deployed page.

## Out of scope

- Other tour pages (they adopt the lab later, one spec each).
- Header/footer redesign.
- Content rewrites beyond the new hero lead, benefits, eyebrows, captions/alt text.
- Booking/pricing logic, DB, `book_tour.php`.
- Spanish/Portuguese versions.

## Housekeeping

- Add `.superpowers/` to `.gitignore` (brainstorm mockups and SDD workspaces are
  local scratch).
- Add `bin/.tailwind/` to `.gitignore`.
- Deploy: unchanged flow (push → cPanel Update from Remote → Deploy HEAD Commit →
  purge Cloudflare). No migration.
