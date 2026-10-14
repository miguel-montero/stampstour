# Maipo tour options - verification report (2026-10-13)

Branch `feature/maipo-tour-options`, LOCAL dev DB (`stampst1_stamptour` on localhost), PHP built-in server `127.0.0.1:8099`. Production was not touched. All DB queries ran through PHP with `require "../db_config.php"` (no `mysql -p`). `shopping.php` was never followed to the payment gateway (see Step 2 note).

Legend: PASS / FAIL / MANUAL / UNVERIFIED.

## Step 1 - Full test suite: PASS

`php tests/tools/phpunit.phar`

```
PHPUnit 11.4.4 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.5.10
...............................................................  63 / 112 ( 56%)
.................................................               112 / 112 (100%)
OK, but there were issues!
Tests: 112, Assertions: 389, PHPUnit Deprecations: 1.
```

0 failures, 0 errors (61 baseline + 51 new; 1 PHPUnit deprecation notice only).

## Step 2 - Book each option from the page: PASS (with notes)

Headless Chromium (Playwright 1.47.2) drove the real page: `?option=<slug>`, click the option label, 2 adults, name/phone, `e2e-<slug>@example.test`, date 12-01-2026, click "Book now" -> js/tours.js builds the hidden form -> `book_tour.php`.

Browser output (page total, activity_name sent by the JS, date):

```
classic        displayed total 220  activity MAIPO_CLASSIC        date 12-01-2026
classic-lunch  displayed total 258  activity MAIPO_CLASSIC_LUNCH  date 12-01-2026
premium        displayed total 278  activity Maipo                date 12-01-2026
```

DB rows created by the browser bookings (`reservas` joined to `experiencias` and `titulares`):

```
{"reference_id":"STAMP_ec59e86026ebe","nombre":"MAIPO_CLASSIC","adultos":"2","ninos":"0","subtotal":"220.00","total_venta":"220.00","airport_pickup":"0","is_private":"0","id_vendedor":null,"email":"e2e-classic@example.test","fecha_actividad":"2026-12-01"}
{"reference_id":"STAMP_dc9e68193cfc3","nombre":"MAIPO_CLASSIC_LUNCH","adultos":"2","ninos":"0","subtotal":"258.00","total_venta":"258.00","airport_pickup":"0","is_private":"0","id_vendedor":null,"email":"e2e-classic-lunch@example.test","fecha_actividad":"2026-12-01"}
{"reference_id":"STAMP_b2e2f6ceb59f7","nombre":"Maipo","adultos":"2","ninos":"0","subtotal":"278.00","total_venta":"278.00","airport_pickup":"0","is_private":"0","id_vendedor":null,"email":"e2e-premium@example.test","fecha_actividad":"2026-12-01"}
```

The amount `shopping.php` charges is `reservas.total_venta` (220.00 / 258.00 / 278.00 above); it was verified in the DB instead of rendering the payment page.

302 + Location assertion: Playwright could not reliably expose the 302 from the browser path (its intercepted fetch reported status 204 and no Location header), so the 302 and `Location` were asserted with curl POSTs to `book_tour.php` using the same field set the JS sends (`name_booking, last_name_booking, email_booking, phone_booking, date_booking, adults, children, infants, airport_pick_up, activity_name, coupon_code, subtotal, total_price`):

```
MAIPO_CLASSIC:       302 http://127.0.0.1:8099/shopping.php?reference_id=STAMP_c3f86afd94d9b
MAIPO_CLASSIC_LUNCH: 302 http://127.0.0.1:8099/shopping.php?reference_id=STAMP_17a8593336076
Maipo:               302 http://127.0.0.1:8099/shopping.php?reference_id=STAMP_46d4508347dee
```

Each stored 220.00 / 258.00 / 278.00 with `airport_pickup=0`, `is_private=0`, `id_vendedor` NULL (same query shape as the rows above).

Notes (honest disclosure):
- In the first browser run Chromium followed the `book_tour.php` 302 internally, so the page issued `GET /shopping.php?reference_id=...` three times against the local server (a `page.route` abort does not intercept redirect hops). Per `shopping.php` the Getnet call only runs on a POST with `pay_with_getnet`, so only the page render happened; no payment request was made. Those rows were deleted, and the browser run was repeated with `book_tour.php` itself intercepted (`maxRedirects:0`), after which the server log showed no further `GET /shopping.php` requests.
- In that first run the typed date did not stick (rows were stored with today's date): the daterangepicker (autoApply, `minDate: today`) re-wrote the input. In the final run the date was set through the picker API (`setStartDate`) and then stored as `2026-12-01`. Typing straight into `#date_booking` is not reliable under automation; this is a test-driver limitation, not a booking-path failure.
- One curl loop of mine returned `400` for all three because of a shell word-splitting slip (activity name sent as `MAIPO_CLASSIC 220`). The friendly "Booking not completed" page came back, no fatal, no row inserted. Re-run correctly above.

## Step 3 - Tampering check: PASS

```
$ curl ... -X POST /book_tour.php -d 'activity_name=Maipo&...&adults=2&...&subtotal=1&total_price=1&codigo_vendedor=1'
302 http://127.0.0.1:8099/shopping.php?reference_id=STAMP_86965d4f74f9d
```

```
{"reference_id":"STAMP_86965d4f74f9d","total_venta":"278.00","id_vendedor":null,"fecha_reserva":"2026-10-13","fecha_actividad":"2026-12-01"}
```

Posted `total_price=1` and `codigo_vendedor=1` were ignored: `total_venta = 278.00`, `id_vendedor` NULL.

## Step 4 - Admin flows regression (via direct POST to submit.php): PASS

No admin login session was available, so `submit.php` was POSTed directly with the field names copied from `booking_manual.php`, `admin/private-booking.php` and `admin/preferentials.php`. Totals are custom values; responses were not followed.

```
manual (Valparaiso, 123.45, codigo_vendedor=1):  302 http://127.0.0.1:8099/shopping.php?reference_id=STAMP_3a24a72635271
private (custom, is_private=1, 777.00):          302 http://127.0.0.1:8099/shopping.php?reference_id=STAMP_8a21a0ac91d71
preferentials (Maipo, TA 99.00, vendor 2):       302 http://127.0.0.1:8099/shopping.php?reference_id=STAMP_af202d48ab45a
```

```
{"reference_id":"STAMP_3a24a72635271","nombre":"Valparaiso","adultos":"1","subtotal":"123.45","total_venta":"123.45","is_private":"0","id_vendedor":"1","email":"admin-1@example.test"}
{"reference_id":"STAMP_8a21a0ac91d71","nombre":"custom","adultos":"2","subtotal":"777.00","total_venta":"777.00","is_private":"1","id_vendedor":null,"email":"admin-2@example.test"}
{"reference_id":"STAMP_af202d48ab45a","nombre":"Maipo","adultos":"2","subtotal":"99.00","total_venta":"99.00","is_private":"0","id_vendedor":"2","email":"admin-3@example.test"}
```

Each stored `total_venta` equals the posted total; vendor and private flags are honored on the trusted `submit.php` path. Caveat: this exercises the `submit.php` back end, not the three admin forms' own JS.

## Step 5 - Admin report categorization: MANUAL

Needs a login. Owner instructions: log in locally, open `admin/check.php` (or `admin/consolidate-day.php`) for a date with bookings of all three options (for example create three bookings on the Maipo page for the same future date, with `@example.test` emails). Expected: the `MAIPO_CLASSIC_LUNCH` booking is under `WINE TOUR MAIPO VALLEY TASTINGS & LUNCH`; the `MAIPO_CLASSIC` and `Maipo` bookings are under `WINE TOUR MAIPO VALLEY`.

## Step 6 - Cleanup: PASS

Deleted only rows tied to titulares with `email LIKE '%@example.test'`.

```
before: reservas_count=12  titulares_count=12
deleted reservas=12
deleted titulares=12
after:  reservas_count=0   titulares_count=0
```

(An earlier mid-run delete removed the first batch of 3 browser rows, `e2e-%@example.test`, same safe filter.)

## Manual checks still required

- Admin report categories (Step 5 above).
- GA4 `select_item` event on option change: UNVERIFIED headless; check in GA4 DebugView or Tag Assistant.
- A real (staging/production) booking reaching the payment page with the right amount (local run never rendered the gateway step).
- Admin forms themselves (`booking_manual.php`, `admin/private-booking.php`, `admin/preferentials.php`) driven from a logged-in browser; only the `submit.php` endpoint was verified here.

## Deploy order (production)

1. Upload: includes/booking.php, includes/tour_price.php, includes/tour_schema.php,
   includes/book_tour_handler.php, includes/tours/, includes/migrations/,
   submit.php, book_tour.php, migrate_2026_10_13_maipo_options.php.
2. Logged in, visit /migrate_2026_10_13_maipo_options.php -> three [ok] lines.
   Delete the file from the server.
3. Upload: maipo-valley-wine-tour-santiago.php, css/tour-options.css,
   js/maipo-options.js, js/tours.js.
4. Smoke test: /maipo-valley-wine-tour-santiago?option=premium shows Premium;
   one real booking reaches the payment page with the right amount.
5. Rich Results Test on the live URL: Product (AggregateOffer) + FAQPage valid.

## Follow-ups (not in this change)
- Move Valparaíso, Andes, Santiago and cruise pages to book_tour.php, then
  restrict submit.php to logged-in admin use (closes tampering site-wide).
- Separate ops category for Premium vs Classic in admin reports.
- Visual redesign "lab" (Tailwind/shadcn-style kit) + new photos in ../photos/.
