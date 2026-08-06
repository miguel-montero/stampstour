# PayPal stuck-webhook-event reprocessing

## Context

Earlier this session, `getnet_notify.php` (a real, confirmed incident) and now the payment layer has real tests protecting it. The analogous gap on PayPal's side is different: `paypal_webhook.php` already has strong delivery guarantees (PayPal retries failed deliveries, we verify signatures, we store every raw event in `paypal_webhook_events` before processing). But nothing ever reprocesses an event that got stuck: `status` starts at `stored`, and only advances to `handled` if the full flow — OAuth token fetch, signature verification, the event-type switch's database writes — completes without error. If signature verification fails (transient OAuth/network issue, not necessarily a forged event), the row is marked `queued` and never retried. If verification succeeds but something throws during processing (a DB hiccup, an unexpected payload shape), the row stays at `stored` forever. `paypal_webhook_events` is referenced nowhere else in the codebase — confirmed via repo-wide grep.

Per your explicit choice: this reprocesses stuck events (not a Getnet-style poll against PayPal's live API), and does it via a **standalone script with its own copy of the event-processing logic** — `paypal_webhook.php` itself is not touched or refactored. Tests are written first this time, using the test infrastructure just built.

## Goals

- Find `paypal_webhook_events` rows that never reached `status='handled'`, and give them another chance to complete, using the exact same event-type handling logic already proven in the live webhook (copied, not shared).
- Never touch `paypal_webhook.php`.
- Never bypass signature verification — a stuck-because-verification-failed event gets re-verified, not trusted blindly. A row already marked `verified='SUCCESS'` skips re-verification (no need to re-call PayPal for something already proven authentic) and goes straight to reprocessing.
- Avoid racing the live webhook — an event PayPal delivered 30 seconds ago and is still being processed by a concurrent live request should not be picked up mid-flight.
- Exposed the same way as the Getnet feature: hourly cron + admin button, both calling one shared function.
- Built test-first: the reprocessing function's two external dependencies (OAuth token fetch, signature verification) are injectable from the start, the same pattern that made `reconcile_getnet_pending()` testable.

## Non-goals

- Touching `paypal_webhook.php` in any way.
- A Getnet-style live API poll against PayPal's Orders/Payments API — not needed here, since we already have the full raw event stored locally; there's nothing to poll for that isn't already sitting in `paypal_webhook_events`.
- Handling the `CHECKOUT.ORDER.APPROVED` guard's `refund`-blindness gap found during the last final review (it protects `realizado` but not `refund` from a late `CHECKOUT.ORDER.APPROVED`) — real, but it's a `paypal_webhook.php` fix, out of scope for a plan that explicitly doesn't touch that file. Worth a separate future ticket.
- Emailing customers directly — already handled automatically: once reprocessing flips a reservation to `estado='realizado'`, the existing `includes/cron_send_confirmations.php` (independent, already running hourly-ish, picks up any `estado='realizado' AND email_sent_at IS NULL`) sends the confirmation without any new code needed here.

## Design

### 1. Shared reprocessing function — `includes/reprocess_paypal_events.php`

```php
function reprocess_paypal_stuck_events(
    mysqli $conn,
    array $paypalConfig,
    int $limit = 50,
    ?callable $tokenFetcher = null,
    ?callable $signatureVerifier = null
): array
```

- `$paypalConfig` is the same shape `paypal_webhook.php` already gets from `require __DIR__ . '/../paypal_config.php'` — passed in rather than required internally, so tests can supply a fake config without needing real PayPal credentials on disk.
- `$tokenFetcher` defaults to a real OAuth token fetch (the same `curl` call `paypal_webhook.php`'s `paypalAccessToken()` makes — duplicated here, not shared). Tests inject a fake closure returning a canned token.
- `$signatureVerifier` defaults to a real call to PayPal's `/v1/notifications/verify-webhook-signature` endpoint (duplicated from `paypal_webhook.php`). Tests inject a fake closure returning `true`/`false` without any network call.

Selects eligible rows:
```sql
SELECT id, event_id, event_type, status, verified, payload, headers
FROM paypal_webhook_events
WHERE status NOT IN ('handled', 'mailed')
  AND received_at <= NOW() - INTERVAL 5 MINUTE
  AND received_at >= NOW() - INTERVAL 30 DAY
ORDER BY received_at ASC
LIMIT ?
```

- **5-minute minimum age** avoids racing a concurrent live webhook request still processing the same event — the live path normally completes in well under a second, so 5 minutes is a large, safe margin, not a tight one.
- **30-day window**: unlike Getnet reconciliation (which polls a live API that may not retain very old sessions), this works entirely from our own stored `payload`/`headers` — there's no external retention concern. 30 days is generous but still bounded, so an ancient, permanently-broken row (bad payload shape from a long-fixed bug, say) doesn't get re-attempted forever.
- `('handled', 'mailed')` are the two terminal statuses in the schema — `mailed` is a vestigial status from the now-disabled webhook-email path (`$ENABLE_WEBHOOK_EMAIL = false` in `paypal_webhook.php`) that's very unlikely to appear on new rows, excluded for completeness.

For each row:
1. Decode `payload` (raw JSON) → `$event`; decode `headers` (JSON) → `$H`.
2. If `verified !== 'SUCCESS'`: fetch a token via `$tokenFetcher`, verify via `$signatureVerifier` using the stored headers/payload against the real PayPal verify endpoint (or the injected fake in tests). Update the row's `verified` column with the result. If verification fails, leave `status` unchanged, log, move to the next row — no processing attempt on an unverified event, full stop.
3. Run the same event-type `switch` logic as `paypal_webhook.php` (all 5 branches: `CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.PENDING`, `PAYMENT.CAPTURE.DENIED`/`DECLINED`, `PAYMENT.CAPTURE.REFUNDED` — copied verbatim, including the existing downgrade guards on the `PENDING`/`DENIED`/`DECLINED` branches), wrapped in try/catch.
4. On success: `UPDATE paypal_webhook_events SET status='handled', handled_at=NOW() WHERE id=?`.
5. On exception: log, leave `status` unchanged, continue to the next row — one bad row never blocks the batch.

Returns `['checked' => int, 'reprocessed' => int, 'failed' => int, 'details' => [...]]`, mirroring the Getnet feature's return shape and admin-page display pattern.

### 2. Cron entry point — `includes/cron_reprocess_paypal.php`

Same lock-file shape as `includes/cron_reconcile_getnet.php`/`includes/cron_send_confirmations.php`. Hourly, same crontab pattern already established (`/usr/local/bin/php -d detect_unicode=0 .../includes/cron_reprocess_paypal.php`).

### 3. Admin page — `admin/paypal-reprocess.php`

Same shape as `admin/getnet-reconcile.php`: `_auth.php` first, POST-gated "Run Check Now" button, `set_time_limit(0)` (learned from the Getnet final review), displays `checked`/`reprocessed`/`failed`. Added to `admin/_nav.php`'s `$toolsLinks` alongside the Getnet entry.

### 4. Tests first

Following the test infrastructure built in the prior plan: `tests/PaypalReprocessTest.php` is written and passing *before* considering the feature done, covering (using injected fake `$tokenFetcher`/`$signatureVerifier`, zero real PayPal API calls):
- A `stored`, already-`verified='SUCCESS'` `PAYMENT.CAPTURE.COMPLETED` event correctly flips the matching reservation to `realizado` and marks the row `handled`, with no re-verification call made (confirm the injected verifier is never invoked when `verified` is already `SUCCESS`).
- A `queued`, `verified=NULL` event: injected verifier returns `true` → reprocesses and succeeds, row becomes `handled`, `verified` becomes `SUCCESS`.
- A `queued` event where the injected verifier returns `false` again → stays `queued`, no reservation write, `failed` count increments.
- An event whose processing throws (e.g., malformed payload) → row status unchanged, `failed` increments, next row still processes (no batch-wide crash).
- The 5-minute age guard: a row received 1 minute ago is never selected, regardless of status.
- The 30-day window: a row received 31 days ago is never selected.
- Each of the 5 event-type branches produces the correct `reservas` write, spot-checked against what `paypal_webhook.php`'s own logic does (same downgrade guards apply on the `PENDING`/`DENIED`/`DECLINED` branches — a `realizado` row must not be downgraded by a stuck, reprocessed event either).
- A deliberate-break verification pass (matching the prior plan's Task 6 pattern): temporarily disable one of the copied guards, confirm the corresponding test fails, restore, confirm it passes again.

## Verification

1. `php -l` on all new files.
2. Full test suite passes, including the new `PaypalReprocessTest.php` — run via the existing `tests/tools/phpunit.phar -c phpunit.xml`, no changes needed to the test harness itself.
3. Deliberately break a copied guard, confirm the test suite catches it, restore.
4. Local behavioral check: confirm the admin page requires login.
5. Deploy, confirm the admin button runs cleanly in production (checked/reprocessed/failed, no errors) — same spot-check pattern as the Getnet feature.
6. Give the user the crontab line, matching the established format.

## Risks

- **Duplicated logic is the deliberate tradeoff** the user chose (Option 2) — a future change to `paypal_webhook.php`'s event handling won't automatically propagate here. Mitigated by: the tests exist specifically to catch drift if anyone forgets, the same way the downgrade-guard tests do for the two webhook files' guard SQL.
- **The 5-minute race-avoidance window is a judgment call**, not derived from a hard guarantee — if the live webhook's processing ever legitimately takes longer than 5 minutes (network issues, PayPal API slowness), a false "stuck" pickup could occur. Low risk in practice (the live path is normally sub-second), and the underlying `reservas` writes are idempotent (same final state regardless of how many times applied), so a rare double-attempt isn't harmful, just wasted work.
