# Prevent payment webhooks from downgrading an already-paid reservation

## Context

Two real, confirmed incidents this session:

1. **`STAMP_2e764578829b5`**: Getnet approved the payment (confirmed live via Getnet's own `/api/session/{id}` API — status `APPROVED`, real authorization code `NKPXC7`, receipt `621713225995`), but `getnet_notify.php`'s webhook was never delivered (zero rows in `getnet_webhook_events` for this reference), so `reservas.estado` was never updated from `pendiente`. A missed-notification problem — the reconciliation-cron work planned next addresses this class of bug.
2. **`STAMP_65145de891f95`**: The customer tried Getnet first, abandoned that session, then paid successfully via PayPal. PayPal's webhook correctly set `estado = 'realizado'`, triggering the confirmation email (`email_sent_at` 2026-08-04 12:54:06). A day later (`updated_at` 2026-08-05 17:54:09), a late-arriving Getnet webhook for the earlier *abandoned* session reported a non-approved status, and `getnet_notify.php` overwrote `estado` back to `pendiente` — a genuinely paid reservation was silently marked unpaid.

Incident 2 is the one this spec fixes. Root cause: `getnet_notify.php`'s DB update is unconditional —

```php
UPDATE reservas SET estado = ?, updated_at = NOW()
WHERE reference_id = ? AND process_id = ?
```

— it never checks the reservation's current status before overwriting it. Any Getnet webhook, including a stale one from an abandoned/expired session, can downgrade an already-`realizado` reservation. `paypal_webhook.php` has the identical pattern in its `PAYMENT.CAPTURE.PENDING` and `PAYMENT.CAPTURE.DENIED`/`DECLINED` handlers — only `CHECKOUT.ORDER.APPROVED` currently protects against this (`estado = IF(estado='realizado', estado, 'pendiente')`).

This is a live, ongoing risk: any customer who tries one payment method, abandons it, then successfully pays another way is exposed to this today, on both providers.

## Goals

- No webhook from either provider can ever move a reservation's `estado` backward from `realizado` to `pendiente` or `fallido`.
- Legitimate transitions still work: `pendiente`/`fallido` → `realizado` (a real approval) and `realizado` → `refund` (a genuine refund) must both continue to work exactly as today.
- Apply the same guard consistently to both `getnet_notify.php` and `paypal_webhook.php`, since both have the same class of bug.

## Non-goals

- The reconciliation-cron feature (recovering from *missed* webhooks, incident 1's class of bug) — separate, already-planned next work, Getnet first then PayPal.
- Any change to `CHECKOUT.ORDER.APPROVED`'s handler — it already guards correctly.
- Retroactively fixing other reservations that may have hit this bug historically — the user is correcting known cases manually.

## Design

Same guard shape in both files: only let `estado` move away from `realizado` when the new status is `refund`; otherwise, once a reservation is `realizado`, non-refund updates are no-ops on `estado` (they still update whatever else they update, e.g. `updated_at`, `capture_id`).

**`getnet_notify.php`** (replaces the current unconditional UPDATE):

```php
$stmt = $conn->prepare("
  UPDATE reservas
  SET estado = CASE
                 WHEN estado = 'realizado' AND ? <> 'refund' THEN estado
                 ELSE ?
               END,
      updated_at = NOW()
  WHERE reference_id = ?
    AND process_id = ?
  LIMIT 1
");
$stmt->bind_param("ssss", $nuevoEstado, $nuevoEstado, $reference, $requestId);
```

**`paypal_webhook.php`**, `PAYMENT.CAPTURE.PENDING` (currently unconditional `estado='pendiente'`):

```php
$stmt = $conn->prepare("
  UPDATE reservas
  SET estado = CASE WHEN estado = 'realizado' THEN estado ELSE 'pendiente' END
  WHERE TRIM(reference_id) = TRIM(?)
  LIMIT 1
");
```

**`paypal_webhook.php`**, `PAYMENT.CAPTURE.DENIED`/`DECLINED` (currently unconditional `estado='fallido'`): same shape, `ELSE 'fallido'`.

`PAYMENT.CAPTURE.REFUNDED` is unchanged in both files — a refund is the one legitimate case that's allowed to move a reservation away from `realizado`, and it already sets an unconditional `'refund'`, which is correct as-is.

## Verification

No automated test framework exists in this repo (confirmed earlier this session). Verification is:

1. Static correctness check of the SQL `CASE` logic against every real transition this reservations table can be in: `pendiente`→`realizado` (normal approval, must work), `pendiente`→`fallido` (normal rejection, must work), `realizado`→`pendiente` (the bug, must now be blocked), `realizado`→`fallido` (same bug shape, must now be blocked), `realizado`→`refund` (legitimate, must still work), `fallido`→`realizado` (a retried/second payment attempt succeeding, must work).
2. A local PHP script simulating the exact `CASE`/bind-param logic against an in-memory stand-in (no live DB needed, since the logic is pure and testable in isolation) confirming all 6 transitions above produce the expected result.
3. `php -l` on both modified files.
4. Manual review of the resulting diff against both files' surrounding code style.

## Risks

- **Getting the guard's scope wrong** (e.g., accidentally also blocking `pendiente`→`fallido`) would silently break normal rejection handling. Verification step 1/2's full transition matrix exists specifically to catch this.
- **This fixes the symptom's blast radius, not incident 1's class of bug** (missed webhooks) — that's intentionally deferred to the reconciliation-cron work, not because it's less important, but because it's a different mechanism (detection + backfill vs. prevention).
