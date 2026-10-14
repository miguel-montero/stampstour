# Maipo Valley Tour Options (Classic / Classic + Lunch / Premium) — Design

**Date:** 2026-10-13
**Page:** `maipo-valley-wine-tour-santiago.php`
**Status:** Approved in conversation; awaiting written-spec review

## Goal

The Maipo Valley wine tour is now sold in three shared variants (matching the
Tripadvisor/Viator listing). The website page must let the traveler pick one,
show the matching itinerary, charge the matching price, and record the chosen
variant on the reservation — without changing the payment flow
(`shopping.php` → Getnet / PayPal / MercadoPago) and without breaking the
admin booking tools that share `submit.php`.

The work also fixes a price-tampering hole for this page: today `submit.php`
stores the browser-posted `total_price` as `reservas.total_venta`, and
`shopping.php:63` charges that amount.

Visual redesign / new CSS framework is explicitly **out of scope** (deferred);
the page keeps its current Bootstrap/CityTours styling.

## The three options

| Slug (`?option=`) | DB `nombre` | Public name (`nombre_publico`) | Adult | Child (<12) | Infant (<2) |
|---|---|---|---|---|---|
| `classic` (default) | `MAIPO_CLASSIC` (new row) | Maipo Valley Wine Tour - Classic Route | 110 | 50 | 0 |
| `classic-lunch` | `MAIPO_CLASSIC_LUNCH` (new row) | Classic Maipo Valley Tastings & Lunch | 129 | 50 | 0 |
| `premium` | `Maipo` (existing id 2, repurposed) | Maipo Valley Wine Tour - Premium Route with Undurraga | 139 (was 121) | 50 | 0 |

Itineraries (all ~10 h, hotel pickup ~07:00–07:30, return ~17:30–18:30):

1. Campo La Quirinca — family farm, wine + pisco tasting, alpacas/farm animals (~1 h 30)
2. Viña Santa Ema — tasting of three wines incl. a blend (~1 h)
3. Viña TerraMater — lunch stop at Zinfandel restaurant (~1 h–1 h 30).
   Lunch **at own cost** for `classic` and `premium`; **included** for `classic-lunch`.
4. Final stop:
   - `classic`, `classic-lunch`: **Viña Ballek** — boutique winery by the Maipo river, relaxed tasting (~1 h)
   - `premium`: **Viña Undurraga** — extended guided tour of gardens, vineyards, production,
     barrel cellar, pre-Columbian exhibit; four premium wines (~1 h 30)

The private variant stays out of the selector (sold separately; FAQ points to contact).

### Naming constraint (admin reports)

`admin/check.php`, `admin/closing.php`, `admin/consolidate-day.php` and
`admin/consolidate-month.php` categorize website bookings by substring-matching
`nombre_publico` (fallback `nombre`). Therefore:

- The lunch row's public name **must** contain `Tastings & Lunch` (and
  `classic maipo valley`) so it lands in `WINE TOUR MAIPO VALLEY TASTINGS & LUNCH`.
- Classic and Premium public names **must not** contain `tasting`, `lunch`,
  `almuerzo`, `classic maipo valley`, `pvt` or `private`, so they land in
  `WINE TOUR MAIPO VALLEY`.

Consequence: admin reports do **not** distinguish Classic from Premium (both
"WINE TOUR MAIPO VALLEY"). The reservation row still records the exact option
via `id_experiencia`, and `nombre_publico` is visible in reservation detail views.
A separate ops category for Premium is a possible follow-up.

Renaming the existing `Maipo` row's `nombre_publico` also relabels historical
website bookings in live-joined views. Those bookings were sold with the
Undurraga itinerary, so "Premium Route with Undurraga" is accurate for them.

## Components

### 1. Migration — `migrate_2026_10_13_maipo_options.php` (new, one-time)

Follows the `migrate_2026_07_29.php` pattern: requires `admin/_auth.php`,
idempotent, prints `[ok]`/`[skip]` per step, deleted from the server after use.

- `UPDATE experiencias SET precio_adulto = 139.00, nombre_publico = '<premium name>' WHERE nombre = 'Maipo'`
- `INSERT` `MAIPO_CLASSIC` and `MAIPO_CLASSIC_LUNCH` if absent, copying
  `precio_concierge` (149.00) and `TAR` (99.00) from the `Maipo` row;
  `sale_adulto` / `sale_nino` NULL.

No schema change. `id_experiencia` is AUTO_INCREMENT, so new rows omit it.
Existence is checked by `nombre`, making the script safe to re-run.

### 2. Option catalogue — `includes/tours/maipo_options.php` (new)

Returns a PHP array keyed by slug (`classic`, `classic-lunch`, `premium`), each with:
`exp_name` (DB `nombre`), `label`, `tagline`, `lunch_included` (bool),
`stops` (list of `{place, title, duration, icon, text}`), `included` (list).
Holds content only — **no prices**. Plus `maipo_default_option() = 'classic'`.

Content lives in code (not DB) for now: it changes rarely, is versioned in git,
and the array shape ports directly to a future DB table or CMS.

### 3. Server pricing + booking — `includes/booking.php` (new)

- `booking_insert_reservation(mysqli $conn, array $post, float $subtotal, float $total): string`
  — the titular insert, experience lookup, coupon/vendor lookup, `codigo_vendedor`
  override, hotel resolution, `reference_id` generation and `reservas` insert,
  **moved verbatim from `submit.php`**. Returns `reference_id`.
- `booking_calculate_total(mysqli $conn, string $expName, int $adults, int $children, int $infants, string $coupon): array`
  — returns `['subtotal' => …, 'total' => …]` from `experiencias` prices and
  `cupones.porcentaje` (case-insensitive, same as `api/apply_coupon.php`),
  rounded to 2 decimals. Throws on unknown experience or negative counts.

`submit.php` becomes a thin wrapper: reads posted subtotal/total exactly as
today and calls `booking_insert_reservation`. **Behavior unchanged** for
`booking_manual.php`, `admin/private-booking.php`, `admin/preferentials.php`
and the 4 other public pages.

### 4. Public booking endpoint — `book_tour.php` (new)

- Allow-list of experience names: `MAIPO_CLASSIC`, `MAIPO_CLASSIC_LUNCH`, `Maipo`.
  Anything else → HTTP 400.
- Validates: adults ≥ 1 (adults + children ≥ 1), counts 0–15 each, date parses
  and is not in the past, required contact fields present.
- Computes subtotal/total via `booking_calculate_total`; **ignores** posted
  `subtotal` / `total_price`.
- Calls `booking_insert_reservation`, sets `$_SESSION['reference_id']`, redirects
  to `shopping.php?reference_id=…` (identical to `submit.php`).
- Errors render a short, styled message with a link back to the page
  (no `display_errors`).

### 5. Page — `maipo-valley-wine-tour-santiago.php`

- Load `maipo_options.php`; resolve `$_GET['option']` against the slugs
  (unknown/missing → `classic`). Fetch prices for the three `nombre`s in one query.
- **Selector** (radio-card group, `name="tour_option"`) at the top of the booking
  box, and a mirrored selector above the itinerary; both stay in sync. Each card:
  label, price "from $X", one-line key difference.
- **Itineraries:** all three rendered server-side as `.cbp_tmtimeline` lists
  (existing timeline CSS), wrapped in `<div data-option="…">`; non-selected ones
  get `hidden`. Existing "See more" collapse behaviour is kept.
- **Content:** rewrite description so Ballek vs Undurraga are clearly alternatives;
  add a Classic / + Lunch / Premium comparison table; hero shows "from $110";
  H1/title no longer imply Undurraga for every option; FAQ gains option questions
  (difference between options, same-day Ballek+Undurraga = no, lunch per option,
  can I bring my own lunch). The pending uncommitted FAQ pickup-text edit
  (Airport area removed) is preserved.
- **SEO:** canonical stays the bare URL (no `?option`). `render_tour_product_schema`
  extended (backward compatible) to accept an `offers` list → emits
  `AggregateOffer` (lowPrice 110, highPrice 139, offerCount 3) with per-option
  `Offer`s. FAQ schema via existing `render_tour_faq`.
- Form `action` → `book_tour.php`; hidden `activity_name` set from selected option.

### 6. Client JS — `js/maipo-options.js` (new) + small `tours.js` changes

- Page injects `window.MAIPO_OPTIONS = {slug: {exp, adult, child, infant}}` and
  `window.MAIPO_SELECTED`.
- `maipo-options.js`: on change → set `activity_name`, toggle itinerary blocks,
  sync both selectors, update hero/price displays, `history.replaceState` to
  `?option=slug`, trigger total recalculation; push GA4 `select_item`-style event
  if `gtag` exists.
- `tours.js`: if `window.MAIPO_OPTIONS` exists, take prices from it (no
  `get_prices.php` fetch) and read the current option on every recalculation;
  availability calendar keeps querying `cupos` with `EXP_NAME = 'Maipo'` so all
  options share one departure. Submit handler posts to the form's `action`
  instead of hard-coded `submit.php`. Other tour pages unaffected (they don't
  define `MAIPO_OPTIONS`, and their form action is still `submit.php`).
- Coupon flow unchanged in UI; if the option changes after a coupon was applied,
  the coupon is re-applied to the new total (or reset) so the displayed total
  never disagrees with what the server will charge.

## Data flow

```
?option=premium ─► page resolves slug ─► renders selector (premium checked),
                                          all 3 itineraries (premium visible),
                                          prices JSON, schema offers
user changes option ─► maipo-options.js ─► activity_name = exp, itinerary swap,
                                           URL update, total recalculated
Book now ─► POST book_tour.php ─► allow-list check ─► booking_calculate_total (DB)
         ─► booking_insert_reservation ─► shopping.php (charges total_venta) ─► gateway
```

## Error handling

- Unknown `?option` → default option, no error.
- Price lookup fails at render → selector still renders; booking button disabled
  with a "please contact us" note (same as today when total is 0).
- `book_tour.php` rejects: non-allow-listed experience, zero passengers, bad/past
  date, unknown coupon (treated as no coupon — same as `submit.php` today).

## Testing

**PHPUnit** (`tests/BookingCalculateTest.php`, test DB via existing `setup-test-db.sh`):
- each option's total for adult/child/infant mixes
- coupon percentage applied and rounded; unknown coupon = no discount; case-insensitive
- unknown experience throws; negative counts throw
- `book_tour.php` logic: posted `total_price=1` is ignored (stored total = computed)
- `submit.php` regression: posted totals still stored as-is (admin flows)
- categorization: the three `nombre_publico` values map to the intended categories
  in the shared report classifiers (where those are callable)

**Manual, local (`php -S` + test DB):**
- `?option=classic|classic-lunch|premium|bogus` preselects correctly; selector,
  itinerary, hero price and total stay in sync; URL updates on change
- complete a booking per option → `reservas.id_experiencia` and `total_venta`
  correct, `shopping.php` shows the right amount
- tampered POST (`total_price=1`) → stored total is the computed one
- `booking_manual.php` / `admin/private-booking.php` / `admin/preferentials.php`
  still store their custom totals
- view-source: canonical bare URL; all three itineraries present; Rich Results
  test passes for Product + FAQ

## Out of scope / follow-ups

- Visual redesign, Tailwind/shadcn-style component kit (deferred "lab").
- Migrating Valparaíso, Andes, Santiago and cruise pages to `book_tour.php`,
  then restricting `submit.php` to authenticated admin use (closes the
  tampering hole site-wide).
- Separate ops/report category for Premium vs Classic.
- Private option in the selector.
- Spanish/Portuguese versions of the page (see 2026-08-19 spec).
