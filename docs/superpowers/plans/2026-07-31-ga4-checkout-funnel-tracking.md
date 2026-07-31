# GA4 Checkout Funnel Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `begin_checkout`, `add_payment_info`, and a deduplicated `purchase` event to `shopping.php` and `return.php` — the two real checkout-flow pages, which currently have zero Google Analytics code at all.

**Architecture:** Both files build their own `<head>` (neither uses the shared `includes/head.php`), so each needs the Consent Mode v2 + `gtag.js` snippet added directly, verbatim from `includes/head.php`. Events fire via inline `<script>` blocks using PHP-interpolated values already in scope on each page — no new queries, no new database columns.

**Tech Stack:** PHP (no framework), `gtag.js` (measurement ID `G-GWM59ECSLZ`), vanilla JS (no jQuery dependency for the tracking code itself, though PayPal's SDK callback is already JS).

## Global Constraints

- Both files must load the Consent Mode v2 block **before** `gtag.js`, matching `includes/head.php`'s existing pattern exactly — this is required for the site's GDPR-strict consent handling to work correctly.
- No new database queries or columns. Use only PHP variables already in scope at the point each event fires.
- `purchase` on `return.php` must fire only when `$status === 'APPROVED'`, and must not fire twice for the same `reference_id` within a browser session (refresh/back-button guard via `sessionStorage`).
- No cookie-consent banner is added to either page — consent state is expected to already be set (granted or denied) from earlier in the funnel, matching existing site behavior.
- `confirmation.php` and `success.php` are not touched — confirmed dead/unreached code, out of scope.
- After every edit, run `php -l <file>` and confirm `No syntax errors detected`.
- Working directory for all commands: `/Users/miguelmontero/Documents/superpowers/STAMP`.

---

### Task 1: Add Consent Mode v2 + gtag.js to shopping.php and return.php

**Files:**
- Modify: `shopping.php:120`
- Modify: `return.php:210`

**Interfaces:**
- Produces: a working `gtag()` function on both pages, available to all later tasks in this plan. Any `gtag('event', ...)` call added in Tasks 2-3 depends on this being present first.

- [ ] **Step 1: Edit `shopping.php`**

Current (line 120):
```php
<head>
    <meta charset="utf-8">
```

Change to (insert the Consent Mode v2 + gtag.js block, copied verbatim from `includes/head.php:9-42`, immediately after `<head>`):
```php
<head>
<!-- Google Consent Mode v2: default-deny until the visitor chooses via the
     cookie banner (includes/cookie-banner.php). Must run before gtag.js. -->
<script>
 window.dataLayer = window.dataLayer || [];
 function gtag(){dataLayer.push(arguments);}

 gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'analytics_storage': 'denied',
  'wait_for_update': 500
 });

 (function () {
  try {
   if (localStorage.getItem('stamp_cookie_consent') === 'granted') {
    gtag('consent', 'update', {
     'ad_storage': 'granted',
     'ad_user_data': 'granted',
     'ad_personalization': 'granted',
     'analytics_storage': 'granted'
    });
   }
  } catch (e) { /* localStorage unavailable: default-deny stands */ }
 })();
</script>
<!-- Google tag (gtag.js) -->
<link rel="preconnect" href="https://www.googletagmanager.com">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-GWM59ECSLZ"></script>
<script>
 gtag('js', new Date());
 gtag('config', 'G-GWM59ECSLZ');
</script>
    <meta charset="utf-8">
```

- [ ] **Step 2: Edit `return.php`**

Current (line 210):
```php
<head>
  <meta charset="utf-8">
```

Change to (same block, adjusted indentation to match this file's 2-space style):
```php
<head>
<!-- Google Consent Mode v2: default-deny until the visitor chooses via the
     cookie banner (includes/cookie-banner.php). Must run before gtag.js. -->
<script>
 window.dataLayer = window.dataLayer || [];
 function gtag(){dataLayer.push(arguments);}

 gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'analytics_storage': 'denied',
  'wait_for_update': 500
 });

 (function () {
  try {
   if (localStorage.getItem('stamp_cookie_consent') === 'granted') {
    gtag('consent', 'update', {
     'ad_storage': 'granted',
     'ad_user_data': 'granted',
     'ad_personalization': 'granted',
     'analytics_storage': 'granted'
    });
   }
  } catch (e) { /* localStorage unavailable: default-deny stands */ }
 })();
</script>
<!-- Google tag (gtag.js) -->
<link rel="preconnect" href="https://www.googletagmanager.com">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-GWM59ECSLZ"></script>
<script>
 gtag('js', new Date());
 gtag('config', 'G-GWM59ECSLZ');
</script>
  <meta charset="utf-8">
```

- [ ] **Step 3: Lint both files**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l shopping.php
php -l return.php
```

Expected: `No syntax errors detected in ...` for both.

- [ ] **Step 4: Verify**

```bash
grep -c "gtag('config', 'G-GWM59ECSLZ')" shopping.php return.php
```

Expected: `1` for each file.

- [ ] **Step 5: Commit**

```bash
git add shopping.php return.php
git commit -m "Add gtag.js + Consent Mode v2 to shopping.php and return.php

Both pages build their own <head> instead of using the shared
includes/head.php, so neither had any Analytics code at all -
confirmed by grepping for 'gtag' across both files (zero matches).
Copied the exact Consent Mode v2 + gtag.js block from includes/head.php
so behavior is identical to every other page on the site."
```

---

### Task 2: Add begin_checkout and add_payment_info events to shopping.php

**Files:**
- Modify: `shopping.php` (body opening, the Getnet form, and the PayPal `onApprove` callback)

**Interfaces:**
- Consumes: `gtag()` from Task 1.
- Produces: nothing consumed by later tasks — Task 3's `purchase` event on `return.php` uses the same `reference_id` value conceptually but does not depend on code from this task.

- [ ] **Step 1: Add `begin_checkout` and the Getnet `add_payment_info` listener right after `<body>`**

Current:
```php
</head>
<body>

    <div id="preloader">
```

Change to:
```php
</head>
<body>
<script>
(function () {
  if (typeof gtag !== 'function') return;
  var reference = <?= json_encode($reference) ?>;
  var value = <?= json_encode($originalTotal) ?>;
  var itemName = <?= json_encode($activity) ?>;
  var qty = <?= (int)$adults + (int)$children + (int)$infants ?>;

  gtag('event', 'begin_checkout', {
    transaction_id: reference,
    value: value,
    currency: 'USD',
    items: [{ item_name: itemName, quantity: qty }]
  });

  document.addEventListener('DOMContentLoaded', function () {
    var getnetForm = document.getElementById('getnet-form');
    if (getnetForm) {
      getnetForm.addEventListener('submit', function () {
        gtag('event', 'add_payment_info', {
          transaction_id: reference,
          value: value,
          currency: 'USD',
          payment_type: 'getnet'
        });
      });
    }
  });
})();
</script>

    <div id="preloader">
```

`json_encode()` on PHP strings/numbers produces valid JS literals directly (handles quoting/escaping), so no separate `htmlspecialchars` call is needed for these values when used inside a `<script>` block this way.

- [ ] **Step 2: Give the Getnet form an id so the listener above can find it**

Current:
```php
                              <form method="POST">
                                <button type="submit" name="pay_with_getnet" class="btn text-start w-100 p-3 border-0 rounded-0" style="background-color:#fff;">
```

Change to:
```php
                              <form method="POST" id="getnet-form">
                                <button type="submit" name="pay_with_getnet" class="btn text-start w-100 p-3 border-0 rounded-0" style="background-color:#fff;">
```

- [ ] **Step 3: Add `add_payment_info` to the PayPal `onApprove` callback**

Current:
```js
      onApprove: function(data){
        document.getElementById('paypal-status').textContent = 'Capturing payment...';
        return postJson('api/paypal/capture-order.php', { orderID: data.orderID, reference_id: ref })
          .then(res => {
            if (res && res.ok && res.status === 'COMPLETED') {
              document.getElementById('paypal-status').textContent = 'Payment approved ✔';
              window.location.href = 'return.php?provider=paypal&reference=' + encodeURIComponent(ref);
            } else {
              throw new Error('Capture not completed: ' + JSON.stringify(res));
            }
          })
          .catch(err => { show(err.message); throw err; });
      },
```

Change to (add the `gtag` call right after the "Payment approved" status update, before the redirect):
```js
      onApprove: function(data){
        document.getElementById('paypal-status').textContent = 'Capturing payment...';
        return postJson('api/paypal/capture-order.php', { orderID: data.orderID, reference_id: ref })
          .then(res => {
            if (res && res.ok && res.status === 'COMPLETED') {
              document.getElementById('paypal-status').textContent = 'Payment approved ✔';
              if (typeof gtag === 'function') {
                gtag('event', 'add_payment_info', {
                  transaction_id: ref,
                  value: <?= json_encode($originalTotal) ?>,
                  currency: 'USD',
                  payment_type: 'paypal'
                });
              }
              window.location.href = 'return.php?provider=paypal&reference=' + encodeURIComponent(ref);
            } else {
              throw new Error('Capture not completed: ' + JSON.stringify(res));
            }
          })
          .catch(err => { show(err.message); throw err; });
      },
```

Note: this file already interpolates PHP inside this same `<script>` block elsewhere (`CLIENT_ID`, `CURRENCY` constants used a few lines above `initPayPal`), so a `<?= json_encode(...) ?>` here follows the file's existing pattern.

- [ ] **Step 4: Lint**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l shopping.php
```

Expected: `No syntax errors detected in shopping.php`.

- [ ] **Step 5: Verify**

```bash
grep -c "begin_checkout\|add_payment_info" shopping.php
grep -n 'id="getnet-form"' shopping.php
```

Expected: first command shows 3 occurrences (1 `begin_checkout` + 2 `add_payment_info`, one per payment method); second shows exactly 1 match.

- [ ] **Step 6: Commit**

```bash
git add shopping.php
git commit -m "Add begin_checkout and add_payment_info events to shopping.php

begin_checkout fires once on page load using the reservation data
already loaded. add_payment_info fires from two different trigger
points since PayPal and Getnet work differently: PayPal's onApprove
JS callback, and a submit listener on the Getnet form (a plain POST
that immediately redirects server-side, so there's no other point
where client-side JS could still run)."
```

---

### Task 3: Add deduplicated purchase event to return.php

**Files:**
- Modify: `return.php` (body opening)

**Interfaces:**
- Consumes: `gtag()` from Task 1, and `$status`/`$reserva` (already resolved earlier in `return.php`, unchanged by this plan).

- [ ] **Step 1: Add the purchase event right after `<body>`**

Current:
```php
</head>
<body>
  <!-- Header (igual al original) -->

  <!-- Hero -->
```

Change to:
```php
</head>
<body>
<?php if ($status === 'APPROVED'): ?>
<script>
(function () {
  if (typeof gtag !== 'function') return;
  var ref = <?= json_encode($reserva['reference_id']) ?>;
  var key = 'ga_purchase_tracked_' + ref;
  var already;
  try { already = sessionStorage.getItem(key); } catch (e) { already = null; }
  if (already !== '1') {
    gtag('event', 'purchase', {
      transaction_id: ref,
      value: <?= json_encode((float)$reserva['total_venta']) ?>,
      currency: 'USD',
      items: [{ item_name: <?= json_encode($reserva['actividad']) ?> }]
    });
    try { sessionStorage.setItem(key, '1'); } catch (e) {}
  }
})();
</script>
<?php endif; ?>
  <!-- Header (igual al original) -->

  <!-- Hero -->
```

The `try`/`catch` around `sessionStorage` calls matches the same defensive pattern already used for `localStorage` in the Consent Mode block (Task 1) — some browsers/privacy modes can throw on storage access.

- [ ] **Step 2: Lint**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l return.php
```

Expected: `No syntax errors detected in return.php`.

- [ ] **Step 3: Verify the event is gated correctly**

```bash
grep -n "status === 'APPROVED'" return.php | head -3
grep -c "ga_purchase_tracked_" return.php
```

Expected: the `if ($status === 'APPROVED')` gate from this step shows up alongside the pre-existing status-display gate further down; the dedup key appears twice (get + set).

- [ ] **Step 4: Commit**

```bash
git add return.php
git commit -m "Add deduplicated purchase event to return.php

Fires only when status resolves to APPROVED (the page's own DB-backed
resolution, not just the raw query param - see the file's existing
'Forzar el estado mostrado según lo que diga la BD' comment). Guarded
with sessionStorage so a page refresh or back-button revisit doesn't
double-count the same booking as two purchases."
```

---

### Task 4: Local verification

**Files:** None (verification only).

- [ ] **Step 1: Confirm a real reservation exists to test against**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
mysql -u stampst1_user -pD4t stampst1_stamptour -e "SELECT reference_id, estado, total_venta FROM reservas ORDER BY id_reserva DESC LIMIT 3;"
```

Note one `reference_id` where `estado` is `realizado` (maps to `APPROVED` in `return.php`) — use it in Step 3 below. If none exist, note a `pendiente` one instead and expect `purchase` NOT to fire in Step 3 (still a valid test of the gate).

- [ ] **Step 2: Start the local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8000 > /tmp/php-server.log 2>&1 &
sleep 1
```

- [ ] **Step 3: Confirm return.php renders and the purchase script appears only for APPROVED reservations**

```bash
curl -s "http://localhost:8000/return.php?reference=<reference_id from Step 1>" -o /tmp/return-test.html
grep -c "gtag('event', 'purchase'" /tmp/return-test.html
grep -o "estado real BD\|APPROVED\|PENDING\|REFUNDED" /tmp/return-test.html | head -1
```

Expected: if the reservation's `estado` is `realizado`, the purchase script appears (count = 1). If `pendiente` or `refund`, count = 0 — confirming the gate works.

- [ ] **Step 4: Confirm shopping.php still renders with the new script present**

```bash
mysql -u stampst1_user -pD4t stampst1_stamptour -e "SELECT reference_id FROM reservas WHERE estado='pendiente' LIMIT 1;"
```

Use that `reference_id` (a checkout in progress, not yet paid, is the realistic state for testing `shopping.php`):

```bash
curl -s "http://localhost:8000/shopping.php?reference_id=<reference_id>" -o /tmp/shopping-test.html
grep -c "begin_checkout" /tmp/shopping-test.html
grep -o 'id="getnet-form"' /tmp/shopping-test.html
```

Expected: `begin_checkout` appears once, `id="getnet-form"` is present. (PayPal's `add_payment_info` and the Getnet submit listener can't be verified via `curl` since they only fire on real browser interaction — code-level correctness was already confirmed via `php -l` and the Task 2/3 diffs; no further check needed here.)

- [ ] **Step 5: Stop the local server**

```bash
pkill -f "php -S localhost:8000"
```

- [ ] **Step 6: No commit needed** (verification-only task, nothing to add).
