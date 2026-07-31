# GA4 Checkout Funnel Tracking

## Problem

`stampstour.com` has Google Analytics (`gtag.js`, measurement ID `G-GWM59ECSLZ`) wired up site-wide via `includes/head.php`, with Consent Mode v2 correctly gating it — but two of the most important pages in the site don't use that include at all, and build their own separate `<head>`:

- **`shopping.php`** (checkout — hotel/date/participant selection, choice of PayPal or Getnet) — zero Analytics code.
- **`return.php`** (the actual, current thank-you page for *both* payment gateways — confirmed by tracing `helpers.php`'s and `shopping.php`'s `returnUrl` config, both point here) — zero Analytics code.

Two other files that look like confirmation pages are not part of the live flow and are out of scope: `confirmation.php` is dead code (hardcoded `root`/empty-password credentials to a nonexistent `stamptour` database, tables that don't match the real schema, and nothing in the codebase links to it). `success.php` also isn't reached by the current PayPal/Getnet redirect flow.

Net effect: today there is **no way to know whether anyone completes a booking** — the single most important business metric — because the page where a completed sale is confirmed has no tracking on it at all.

## Scope

Add three GA4 events across the two real checkout-flow pages, using data already available on each page (no new database columns, no new queries beyond what's already fetched):

- `begin_checkout` on `shopping.php`
- `add_payment_info` on `shopping.php` (two trigger points — PayPal and Getnet work differently)
- `purchase` on `return.php`, gated to `$status === 'APPROVED'`, deduplicated against page refresh

Explicitly out of scope for this pass: the two `generate_lead` events (newsletter/contact form) from the earlier measurement plan — their actual form markup hasn't been located yet (footer.php only has a `mailto:` link, `contact-us.php` has no `<form>` tag); adding a cookie-consent banner to `shopping.php`/`return.php` themselves (consent is expected to already be granted/denied from an earlier page in the funnel, matching how the rest of the site works); `confirmation.php`/`success.php` (dead code, not touched).

## Changes

### 1. Add Analytics + Consent Mode v2 to both pages

Neither page currently loads `gtag.js`. Copy the exact snippet already used in `includes/head.php` (consent default-deny block → `gtag.js` loader → `gtag('config', 'G-GWM59ECSLZ')`) into the `<head>` of both `shopping.php` and `return.php`, verbatim, so behavior is identical to every other page on the site — a visitor who already granted consent earlier in their session (localStorage `stamp_cookie_consent`) will have it correctly recognized here too.

### 2. `shopping.php` — `begin_checkout`

Fires once, in an inline `<script>`, right after the reservation data loads successfully (the page already has `$activity`, `$originalTotal`, `$adults`/`$children`/`$infants`, `$reference` in scope by that point — no new queries needed):

```js
gtag('event', 'begin_checkout', {
  transaction_id: '<?= htmlspecialchars($reference, ENT_QUOTES, "UTF-8") ?>',
  value: <?= json_encode($originalTotal) ?>,
  currency: 'USD',
  items: [{ item_name: '<?= addslashes($activity) ?>', quantity: <?= (int)$adults + (int)$children + (int)$infants ?> }]
});
```

### 3. `shopping.php` — `add_payment_info`

Two trigger points, because the two payment methods work fundamentally differently:

- **PayPal**: fires inside the existing `onApprove` callback in the PayPal Buttons JS, right after PayPal confirms approval and before the server-side capture call. `payment_type: 'paypal'`.
- **Getnet**: the Getnet button is a plain form POST (`<form method="POST"><button name="pay_with_getnet">`) that causes an immediate PHP-side redirect to Getnet's hosted payment page — no HTML/JS ever renders in between, so this can't fire server-side. Instead, attach a `submit` listener to that form that fires the event client-side right as the form submits (gtag's default transport is beacon-based, so it reliably sends before the navigation completes). `payment_type: 'getnet'`.

Both use the same `transaction_id`/`value`/`currency` as `begin_checkout`.

### 4. `return.php` — `purchase`

Fires only when `$status === 'APPROVED'` (the page already resolves this from the DB's `estado` column, which is the authoritative source — see `return.php`'s own comment: "Forzar el estado mostrado según lo que diga la BD"), using the reservation data the page already fetches: `$reserva['reference_id']` as `transaction_id`, `$reserva['total_venta']` as `value`, `$reserva['actividad']` as the item name.

**Deduplication:** the page already stores `$_SESSION['last_status'][$reference]` for its own UI purposes, but nothing currently stops a page refresh (or back-button revisit) from re-rendering the same "approved" state and firing `purchase` again, double-counting revenue. Guard the event with `sessionStorage`, keyed by the reference:

```js
var ref = '<?= htmlspecialchars($reserva["reference_id"], ENT_QUOTES, "UTF-8") ?>';
var key = 'ga_purchase_tracked_' + ref;
if (sessionStorage.getItem(key) !== '1') {
  gtag('event', 'purchase', {
    transaction_id: ref,
    value: <?= json_encode((float)$reserva['total_venta']) ?>,
    currency: 'USD',
    items: [{ item_name: '<?= addslashes($reserva["actividad"]) ?>' }]
  });
  sessionStorage.setItem(key, '1');
}
```

This only guards against duplicate fires within the same browser session — acceptable for this pass, consistent with standard practice for client-side purchase tracking without adding a server-side "already tracked" flag to the `reservas` table.

## Testing

- Visual/functional check on the local server + local DB already set up this session: load `shopping.php` for an existing test reservation, confirm `begin_checkout` fires once (browser devtools network tab or GA4 DebugView), confirm the Getnet form submit fires `add_payment_info` before navigating away.
- Confirm `return.php` fires `purchase` exactly once on first load with `status=APPROVED`, and does NOT fire again on a manual refresh of the same URL (dedup check).
- Confirm no event fires when `$status !== 'APPROVED'` on `return.php` (pending/failed payments must not count as purchases).
- `php -l` on both edited files.
- Confirm `shopping.php` and `return.php` still render and function identically for non-Analytics behavior (existing forms, redirects, AJAX hotel-update endpoint) — this change only adds tracking, must not touch any existing logic.
