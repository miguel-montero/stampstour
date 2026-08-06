# Getnet payment reconciliation

## Context

Confirmed this session: `getnet_notify.php`'s webhook can silently fail to arrive (Getnet approved a payment — verified live via their own `/api/session/{id}` API, real authorization code and receipt — but zero rows in `getnet_webhook_events` for that reference, meaning Getnet's notification never reached us at all). The reservation stayed `pendiente` indefinitely until the customer complained. This is a different failure mode than the downgrade bug just fixed (`docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md`): that fix stops an *incorrect* webhook from overwriting a *correct* status; this feature recovers from a webhook that never shows up at all.

The fix: periodically re-check `pendiente` reservations directly against Getnet's own API (the same `getSessionInfo()` helper `return.php` already uses for read-only lookups), and correct any that Getnet actually approved.

## Goals

- Catch reservations where Getnet approved payment but the webhook never arrived, and correct them automatically.
- Run both automatically (hourly cron) and on-demand (admin button), sharing one implementation.
- Never write anything unsafe — reuse the downgrade-protected UPDATE pattern from the guard fix, so a race with a real webhook arriving mid-check can't cause harm.
- Leave a record of what was corrected.

## Non-goals

- PayPal reconciliation — same idea, deliberately sequenced as separate follow-up work after this one, since PayPal's webhook already has stronger delivery guarantees (signature verification + idempotency table) and doesn't yet have a confirmed incident behind it.
- Checking reservations without a `process_id` — there's no Getnet session to check them against.
- Checking `fallido`/`refund` reservations for a status change — scope is strictly "recover a `pendiente` reservation Getnet actually approved," matching the one confirmed failure mode.
- A persistent database log/history table — a log file is enough for this scope (see Design).

## Design

### 1. Shared reconciliation function — `includes/reconcile_getnet.php`

```php
function reconcile_getnet_pending(mysqli $conn, int $limit = 50): array
```

- Selects eligible reservations:
  ```sql
  SELECT id_reserva, reference_id, process_id
  FROM reservas
  WHERE estado = 'pendiente'
    AND process_id IS NOT NULL
    AND fecha_reserva >= NOW() - INTERVAL 1 DAY
  ORDER BY id_reserva ASC
  LIMIT ?
  ```
  (`fecha_reserva` is set to `NOW()` at INSERT time in `submit.php` — it's this table's de facto "created at" column; there is no separate `created_at`.)
- For each row, calls `getSessionInfo((int)$process_id)` (existing helper in `helpers.php`, already used read-only by `return.php` and `getnetcheck.php`) and maps the result through the same `$statusMap` used in `getnet_notify.php` (`APPROVED→realizado`, `PENDING→pendiente`, `REJECTED/FAILED/EXPIRED→fallido`, `REFUNDED→refund`).
- Only writes when the mapped status is `realizado` or `refund` (an actual resolution) — a mapped `pendiente` or `fallido` doesn't need reconciling (still genuinely pending, or already correctly failed) and is skipped.
- Writes via the same guarded pattern as `getnet_notify.php`, for the same reason (defense against a real webhook resolving the row between this function's `SELECT` and `UPDATE`):
  ```sql
  UPDATE reservas
  SET estado = CASE
                 WHEN estado IN ('realizado', 'refund') AND ? <> 'refund' THEN estado
                 ELSE ?
               END,
      updated_at = NOW()
  WHERE reference_id = ? AND process_id = ?
  LIMIT 1
  ```
- Logs every correction (not every check) to `logs/getnet_reconcile.log` via `error_log()` with `ini_set('error_log', ...)`, matching `getnet_notify.php`'s own logging convention: reference, old status, new status, timestamp.
- Returns `['checked' => int, 'corrected' => int, 'corrections' => [['reference' => ..., 'from' => 'pendiente', 'to' => 'realizado'], ...]]` so both callers can display a summary.

### 2. Cron entry point — `includes/cron_reconcile_getnet.php`

Same shape as the existing `includes/cron_send_confirmations.php`: a `sys_get_temp_dir()` lock file with `flock(LOCK_EX | LOCK_NB)` to prevent overlapping runs, requires `db_config.php` + `reconcile_getnet.php`, calls `reconcile_getnet_pending($conn, 50)`, logs the summary.

The user adds the actual crontab entry via cPanel (not part of this codebase) — same as the existing confirmation-email cron — running hourly, e.g. `0 * * * * /usr/local/bin/php /home/.../includes/cron_reconcile_getnet.php`.

### 3. Admin page — `admin/getnet-reconcile.php`

- `require __DIR__ . '/_auth.php';` (real auth this time — unlike the now-deleted `success.php`).
- Includes `admin/_nav.php` with `$active = 'getnet-reconcile'`.
- A "Run Check Now" button (POST, no GET-triggered side effects) that calls `reconcile_getnet_pending($conn, 50)` and displays the result: how many reservations were checked, how many corrected, and a table of any corrections (reference, old → new status).
- Add to `admin/_nav.php`'s `$toolsLinks` array:
  ```php
  'getnet-reconcile' => ['label' => 'Getnet Reconciliation', 'href' => '/admin/getnet-reconcile.php'],
  ```

## Verification

1. `php -l` on all 3 new/modified files.
2. A local logic test (same style as the downgrade-guard fix's transition-matrix script — no live DB, no live HTTP) confirming the query's WHERE clause and the guarded UPDATE's CASE logic behave correctly for the relevant cases.
3. Local test against a `php -S` server + a real (but harmless) test reservation: create one via `booking_manual.php` (same technique used earlier this session), manually set it `pendiente` with a real-but-irrelevant `process_id` pointing at a known Getnet sandbox/test session if available, or verify the code path structurally if a live Getnet call isn't safely testable locally — do not call the live Getnet API with fabricated process_ids that could collide with a real transaction.
4. Confirm the admin button requires login (redirects to `/login.php` when not authenticated) and appears correctly in the Admin Tools dropdown.
5. Deploy, then trigger the admin button once on production and confirm it runs without error against real (if any) eligible reservations, and correctly reports zero corrections if there's nothing to fix at that moment.
6. Give the user the exact crontab line to add via cPanel — this plan's code can't add it, only the user's hosting panel can.

## Risks

- **A live API call per eligible reservation, every run.** At 50/run with a 1-day/`process_id`-required window, volume should be small in practice, but if it's ever not, add a per-run cap and rely on the next run to catch the remainder rather than raising the limit unboundedly.
- **Reusing the guarded UPDATE is the safety-critical part of this feature**, same as the original guard fix — verification must confirm it, not just assume the copy-paste is correct.
