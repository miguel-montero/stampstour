# Maipo lab redesign: verification report

- Date: 2026-10-14
- Branch: `feature/maipo-lab-redesign`
- Commit range: `75526b72..ad66eb58`, plus this report's commit. Task 9 started at `12440ace`.
- Page: `maipo-valley-wine-tour-santiago.php`, served by a local `php -S 127.0.0.1:8099`.

## Summary

| Area | Result |
|---|---|
| PHPUnit | PASS: 152 tests, 686 assertions, 1 pre-existing deprecation |
| `bin/build-css --check` | PASS (`css/lab.css is up to date`), 20,439 bytes |
| `lab-check.js` | 30/30 PASS, after 1 fix |
| `check.js` | 8/8 PASS |
| `e2e.js` | 3/3 bookings: totals 220 / 258 / 278, `book_tour.php` returns 302 to `shopping.php` |
| Lighthouse perf, median | 55 → 64 (PASS, not below the baseline) |
| Lighthouse CLS | 0.382 → 0.192 (**FAIL**, above 0.1). The cause is shared footer code outside this redesign. See below. |
| Safety incident | **`shopping.php` was loaded 3 times on the local server during one e2e run.** See "Incident". |

## Fixes made in this task

1. **`ad66eb58` fix: lab-btn hover/focus no longer overrides button utilities.** Files: `css/src/lab-base.css`, `css/lab.css`.
   - **Problem:** `:where(.lab) .lab-btn:focus` has specificity (0,2,0), which beat `tw:bg-white/10`. After a mouse click, the coupon "Apply" button stayed primary teal (`rgb(0,111,115)`) until it lost focus.
   - **Fix:** the rule is now `:where(.lab) .lab-btn:where(:hover, :focus-visible)`, at (0,1,0).
   - **Result after the fix:**
     - Apply stays `white/10` after a click and turns `white/20` on hover.
     - Keyboard focus shows the teal-soft outline ring.
     - The primary buttons (Book now, the hero CTA) still darken to `#006f73` on hover.
   - **Verification:** PHPUnit was re-run and all three browser scripts were re-run.

The scratch test scripts were also changed. They are outside the repo and not committed:

- **`check.js`:**
  - Check 3 now clicks the route label after scrolling `#routes` into view.
  - Check 5 now expects `=== 9`.
  - **Both pages now have `page.route(/shopping\.php/, abort)`.** The previous version had no such guard.
- **`lab-check.js`:**
  - Written from the brief.
  - The no-JS context also gets the `shopping.php` abort route.
  - New checks: the hero is downloaded once, adults=12 fits, and the Apply button style after a click.
- **`e2e.js`:** the `book_tour.php` route regex is now anchored to `http://127.0.0.1:8099/`. The unanchored regex also matched the GA4 `form_submit` beacon, whose `form_destination` parameter contains `book_tour.php`. The beacon's 204 then overwrote the recorded status. That is why the first run printed `204`.

## Browser checks (`.superpowers/lab-metrics/lab-check.txt`)

Each check below ran at mobile (390×844) and desktop (1440×900), and passed at both sizes:

- **Hero image downloaded once.** 390 requests only `vineyard-toast-960.webp`; 1440 requests only `vineyard-toast-1280.webp`. Relative `<source>` paths and the root-relative preload resolve to the same URL.
- **No horizontal scroll** (`overflow=0`).
- **Exactly one H1**, with the SEO title.
- **Hero loads the correct size:** 960 WebP at mobile, 1280 WebP at desktop.
- **Sticky bar:**
  - It is visible mid-page and hidden at `#book`.
  - It follows the selected route: "Premium Route $139".
- **The booking-panel route stays in sync** (`premium`).
- **Lightbox:** opens on a 1600 WebP, ArrowRight goes to the next image, and Esc closes it.
- **The old header "Book now" button is absent.**
- **Slider Pro and theia are not requested.**
- **The date picker opens.**
- **adults=12 is not clipped in `.numbers-row`:**
  - Mobile: text 14.7px in an 18px inner width, with `scrollWidth == clientWidth`.
  - Desktop: 55px inner width.
- **The Apply button keeps its secondary style after a click.** This failed before `ad66eb58`: the button was `rgb(0,111,115)` once the mouse left it.
- **No console errors and no failed requests.**

JavaScript-disabled context (`?option=classic-lunch`):

- PASS: only the 2 classic-lunch blocks are visible.
- PASS: the gallery tiles link to the 1600px image.

Result: `ALL PASS`.

## Regression checks (`.superpowers/lab-metrics/check.txt`)

- PASS 1: premium is selected in both selectors, only the premium blocks are shown, and Undurraga is shown.
- PASS 2: classic-lunch is selected in both selectors, the URL is `?option=classic-lunch`, there is no reload, and the total is 258.
- PASS 3: classic with 2 adults and 1 child totals 270, and the itinerary radio is synced.
- PASS 4: a request goes to `api/cupos_status.php?nombre=Maipo`.
- PASS 4b: `get_prices.php` is not requested on the Maipo page.
- PASS 5: after applying coupon `10off.STAMP`, all **9** `[data-booking-lock]` radios are disabled and the new total of 243 is shown.
- PASS 6: the Valparaíso page still requests `get_prices.php` and computes a total. Its 404 on `slider-pro.min.css` is a pre-existing problem on that page.
- PASS: no console errors on the Maipo page.

Result: `ALL PASS`.

## End-to-end bookings (`.superpowers/lab-metrics/e2e.txt`)

| Route | Displayed total (2 adults) | `activity_name` | `book_tour.php` |
|---|---|---|---|
| classic | 220 | MAIPO_CLASSIC | 302 → `shopping.php?reference_id=STAMP_…` |
| classic-lunch | 258 | MAIPO_CLASSIC_LUNCH | 302 → `shopping.php?reference_id=STAMP_…` |
| premium | 278 | Maipo | 302 → `shopping.php?reference_id=STAMP_…` |

`shopping.php navigated: null`. This is expected: the script captures the 302 and fulfills the navigation itself, so the browser never follows the redirect. The server log confirms that `shopping.php` received no request during the final run.

## Incident: `shopping.php` was loaded locally 3 times

**What happened.** To get the "shopping.php navigated: <aborted URL>" output that the brief expects, one e2e run fulfilled the intercepted `book_tour.php` request with the real 302 response (`route.fulfill({response})`). Chromium followed that redirect **without passing it back through `page.route`**. So the `shopping.php` abort rule did not fire.

The local server log shows three `GET /shopping.php?reference_id=…` with status 200:

- `STAMP_d7ad73797f68d`
- `STAMP_b68de8d99f46e`
- `STAMP_4c61bea15a642`

**Possible impact.**

- **A possible call to the live Getnet gateway.** `shopping.php` calls `callGetnet(...)` server-side when it loads. With the local `../db_config.php` and `helpers.php` settings (base `https://checkout.getnet.cl`), that call may have reached the live gateway.
- **No stored session ID.** None of the three reservation rows got a `process_id`, so no gateway session ID was saved locally. The gateway response itself was not captured.
- **Data affected.** Only the e2e test bookings were involved: amounts 220, 258 and 278, with `e2e-*@example.test` emails. No payment was made.

**Owner action.** Check the Getnet merchant dashboard for unpaid sessions with those three references. They should expire unpaid.

**Fix.**

- `e2e.js` was reverted to fulfilling with a stub page. The script now carries a comment never to fulfill with the real 302.
- Every later run was checked in the server log: no further `shopping.php` requests.

## Local DB cleanup (`.superpowers/lab-metrics/db-cleanup.txt`)

The e2e email is stored in `titulares.email`, not in `reservas`; `reservas` has no `email` column. So the brief's `DELETE FROM reservas WHERE email LIKE …` cannot run. Rows were deleted through the `id_titular` join with the same safe filter `e2e-%@example.test`. The DB was `localhost` (local socket).

```
before: reservas=10 titulares=10 private_booking=0 all_reservas=1281
deleted reservas=10
deleted titulares=10
after:  reservas=0 titulares=0 all_reservas=1271
```

The 10 rows came from four runs:

| Run | Rows |
|---|---|
| First e2e run (status mis-recorded as 204) | 3 |
| Debug run | 1 |
| Incident run | 3 |
| Final run | 3 |

Before testing there were 0 e2e rows and 1271 rows in total. After cleanup the counts are back to those numbers.

## Lighthouse (12.6.0, mobile default, simulated throttling, local server)

| Run | Perf | LCP (ms) | CLS | TBT (ms) |
|---|---|---|---|---|
| before-1 | 57 | 10666 | 0.254 | 42 |
| before-2 | 55 | 9079 | 0.382 | 9 |
| before-3 | 55 | 8854 | 0.382 | 14 |
| **before median** | **55** | **9079** | **0.382** | **14** |
| after-1 | 64 | 8714 | 0.191 | 24 |
| after-2 | 64 | 8706 | 0.192 | 24 |
| after-3 | 64 | 8707 | 0.192 | 22 |
| **after median** | **64** | **8707** | **0.192** | **24** |
| diagnostic: after, with the footer TripAdvisor SVG blocked | 74 | 8712 | 0.000 | 33 |

**Perf: PASS.** The median rose from 55 to 64.

**CLS: FAIL** (0.192 > 0.1). This failure was already present before the redesign: the baseline was 0.254–0.382. The cause was investigated:

1. Lighthouse attributes every shift to `#cookie-consent-banner`, with the cause "Media element lacking an explicit size".
2. A throttled mobile trace shows how it happens. Mid-load, the layout viewport grows to 1648px wide because one `<a>` in the shared footer is 3354px wide. That `<a>` holds the TripAdvisor widget logo: `<img src="…Tripadvisor_lockup_horizontal…svg">` in `includes/footer.php:34`, which has no `width` or `height`. The SVG loads before the widget's own CSS sizes it.
3. The banner is fixed with `left:0;right:0`, so it stretches to 1648px wide and loses height (137px → 62px). When the CSS arrives, the banner snaps back to normal size. That snap is the layout shift.
4. When the run blocks only that SVG, CLS falls to **0.000** (diagnostic row above).

The fix belongs in `includes/footer.php`: give the TripAdvisor `<img>` explicit `width`/`height`, or `max-width:100%; height:auto` in the critical CSS. That file is shared by every page and lies outside the page/lab scope; the plan also requires the footer to stay unchanged. So it was **not changed here**, and the decision is left to the owner. The same fix should also improve CLS on every other page that uses the footer.

**LCP.** LCP improved slightly (median 9079 → 8707 ms), but it is still dominated by about 7.8 s of simulated "render delay". The observed, unthrottled LCP is 171 ms. In the simulation, `lab.css` (20 KB) is the only render-blocking stylesheet, with an estimated saving of 0 ms. The full-screen `#preloader` overlay, which `js/functions.js` fades out after the load event, is worth investigating next. It was kept from the old page.

## Screenshots (`.superpowers/lab-metrics/shots/`)

| File | What it shows |
|---|---|
| `mobile.png` | Full page at 390×844 |
| `desktop.png` | Full page at 1440×900 |
| `mobile-booking-adults12.png` | Booking panel at 390 with 12 adults (fits) |
| `desktop-booking-adults12.png` | Booking panel at 1440 with 12 adults (fits) |
| `mobile-coupon-apply-clicked.png` | Apply button after a click, after the fix |
| `desktop-coupon-apply-clicked.png` | Apply button after a click, after the fix |

Visual review against the approved mockup:

- The header is readable over the hero.
- Every section has the charcoal background.
- The route cards show coral prices and a teal border on the selected card.
- The mosaic has 7 tiles and no holes.
- The timeline dots line up.
- The booking panel shows two columns on desktop and one on mobile, and the +/- counters fit.
- The accordions are closed.
- The footer is unchanged.
- No white Bootstrap backgrounds or dark-on-dark text leak in.

Minor cosmetic note, not fixed: in the booking panel, the "Route" segmented control sits only about 10px above the "Name" label, which is tighter than the spacing between the other fields. (The full-page screenshot shows the fixed cookie banner partway down the page. That comes from full-page capture and does not affect the layout.)

## Manual checks left for the owner after deploy

- [ ] Getnet dashboard: look for the 3 unpaid local test sessions (see "Incident").
- [ ] Rich Results test on the live URL (FAQ schema).
- [ ] One real booking reaching the payment page with the right amount.
- [ ] Visual check on a real phone.
- [ ] Purge the Cloudflare cache.
- [ ] Optional: fix the footer TripAdvisor `<img>` size (site-wide CLS).
