# PayPal Stuck-Webhook-Event Reprocessing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Recover `paypal_webhook_events` rows that never reached `status='handled'` (a transient signature-verification hiccup, or an exception during processing), by giving them another chance via a standalone reprocessing function — without ever touching the live `paypal_webhook.php`, and built test-first using the payment test infrastructure already in place.

**Architecture:** One shared function (`reprocess_paypal_stuck_events`) in a new file, with its own duplicated copy of `paypal_webhook.php`'s event-type-switch logic (helper functions renamed with a `_reprocess_paypal_` prefix to avoid any global-function-name collision if both files were ever loaded in the same request). Both external dependencies (OAuth token fetch, signature verification) are injectable, following the same pattern that made `reconcile_getnet_pending()` testable. A thin cron script and a thin admin page both call the shared function.

**Tech Stack:** Plain PHP + mysqli, PHPUnit (via the `.phar` already set up in `tests/tools/`), no build step.

## Global Constraints

- Full rationale: `docs/superpowers/specs/2026-08-06-paypal-reprocessing-design.md`. Read it first.
- **`paypal_webhook.php` is never modified by this plan.** Its logic is duplicated (not shared) into a new file, per the user's explicit choice.
- Every duplicated helper function gets a `_reprocess_paypal_` prefix (`_reprocess_paypal_find_reference_by_order_id`, `_reprocess_paypal_try_prepare`, `_reprocess_paypal_try_update_amounts`, `_reprocess_paypal_apply_event`, `_reprocess_paypal_real_token_fetcher`, `_reprocess_paypal_real_signature_verifier`) so there is no possibility of a PHP "cannot redeclare function" fatal error if `paypal_webhook.php` and this new file were ever `require`d in the same process (they currently never are, but the prefix makes that guarantee unconditional rather than incidental).
- Function signature: `reprocess_paypal_stuck_events(mysqli $conn, array $paypalConfig, int $limit = 50, ?callable $tokenFetcher = null, ?callable $signatureVerifier = null): array`, returning `['checked' => int, 'reprocessed' => int, 'failed' => int, 'details' => array]`.
- Eligibility query (exact; refined during the final review pass to add a
  retry-backoff condition — see
  `docs/superpowers/specs/2026-08-06-paypal-reprocessing-design.md`):
  ```sql
  SELECT id, event_id, event_type, status, verified, payload, headers
  FROM paypal_webhook_events
  WHERE status NOT IN ('handled', 'mailed')
    AND received_at <= NOW() - INTERVAL 5 MINUTE
    AND received_at >= NOW() - INTERVAL 30 DAY
    AND NOT (verified = 'FAILURE' AND received_at < NOW() - INTERVAL 1 DAY)
  ORDER BY received_at ASC
  LIMIT ?
  ```
- The 5 event-type branches (`CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.PENDING`, `PAYMENT.CAPTURE.DENIED`/`DECLINED`, `PAYMENT.CAPTURE.REFUNDED`) and their exact SQL — including the two downgrade guards on `PENDING` and `DENIED`/`DECLINED` — must match `paypal_webhook.php`'s current logic verbatim (re-verify against the live file at implementation time; it has not changed this session).
- This plan is built test-first: Task 1 writes and confirms-failing tests before Task 2 writes the implementation.
- Test database, PHPUnit, and the `bootstrap.php` test-DB safety check are already in place from the prior plan — no new test infrastructure is created here.

---

### Task 1: Write failing tests for `reprocess_paypal_stuck_events()`

**Files:**
- Create: `tests/PaypalReprocessTest.php`

**Interfaces:**
- Consumes: the test database and PHPUnit setup (already in place).
- Produces: a test file that FAILS at this point (the function it tests doesn't exist yet) — Task 2 makes it pass.

- [ ] **Step 1: Write `tests/PaypalReprocessTest.php`**

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PaypalReprocessTest extends TestCase
{
    private mysqli $conn;

    private array $fakePaypalConfig = [
        'mode' => 'sandbox',
        'sandbox' => ['client_id' => 'fake_id', 'client_secret' => 'fake_secret', 'webhook_id' => 'fake_webhook_id'],
    ];

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->conn->query("DELETE FROM reservas");
        $this->conn->query("DELETE FROM paypal_webhook_events");
    }

    protected function tearDown(): void
    {
        $this->conn->query("DELETE FROM reservas");
        $this->conn->query("DELETE FROM paypal_webhook_events");
    }

    private function insertReserva(string $reference, string $estado, ?string $orderId = null): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO reservas (reference_id, order_id, fecha_reserva, fecha_actividad, id_titular, estado)
            VALUES (?, ?, CURDATE(), CURDATE(), 1, ?)
        ");
        $stmt->bind_param('sss', $reference, $orderId, $estado);
        $stmt->execute();
        $stmt->close();
    }

    private function currentEstado(string $reference): string
    {
        $stmt = $this->conn->prepare("SELECT estado FROM reservas WHERE reference_id = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->bind_result($estado);
        $stmt->fetch();
        $stmt->close();
        return $estado;
    }

    private function insertEvent(
        string $eventId,
        string $eventType,
        array $payload,
        string $status,
        ?string $verified,
        string $receivedAgo = '-10 minutes'
    ): int {
        $headers = json_encode([
            'PAYPAL-TRANSMISSION-ID' => 'txn_' . $eventId,
            'PAYPAL-TRANSMISSION-TIME' => date('c'),
            'PAYPAL-CERT-URL' => 'https://api.paypal.com/cert',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-TRANSMISSION-SIG' => 'fake_sig',
        ], JSON_UNESCAPED_SLASHES);
        $receivedAt = date('Y-m-d H:i:s', strtotime($receivedAgo));
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $stmt = $this->conn->prepare("
            INSERT INTO paypal_webhook_events (event_id, event_type, status, verified, received_at, payload, headers)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssssss', $eventId, $eventType, $status, $verified, $receivedAt, $payloadJson, $headers);
        $stmt->execute();
        $id = (int)$this->conn->insert_id;
        $stmt->close();
        return $id;
    }

    private function eventStatus(int $id): array
    {
        $stmt = $this->conn->prepare("SELECT status, verified FROM paypal_webhook_events WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($status, $verified);
        $stmt->fetch();
        $stmt->close();
        return ['status' => $status, 'verified' => $verified];
    }

    public function testAlreadyVerifiedEventSkipsReVerificationAndCompletesCapture(): void
    {
        $this->insertReserva('TEST_PR_A', 'pendiente');
        $id = $this->insertEvent('evt_a', 'PAYMENT.CAPTURE.COMPLETED', [
            'resource' => ['id' => 'CAP_A', 'custom_id' => 'TEST_PR_A', 'amount' => ['value' => '79.00', 'currency_code' => 'USD']],
        ], 'stored', 'SUCCESS');

        $verifierCalled = false;
        $fakeVerifier = function (...$args) use (&$verifierCalled): bool {
            $verifierCalled = true;
            return true;
        };

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50, null, $fakeVerifier);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['reprocessed']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse($verifierCalled, 'Signature verifier should never be called for an already-verified event.');
        $this->assertSame('realizado', $this->currentEstado('TEST_PR_A'));
        $this->assertSame('handled', $this->eventStatus($id)['status']);
    }

    public function testQueuedEventReVerifiesAndSucceeds(): void
    {
        $this->insertReserva('TEST_PR_B', 'pendiente');
        $id = $this->insertEvent('evt_b', 'PAYMENT.CAPTURE.COMPLETED', [
            'resource' => ['id' => 'CAP_B', 'custom_id' => 'TEST_PR_B', 'amount' => ['value' => '79.00', 'currency_code' => 'USD']],
        ], 'queued', null);

        $fakeToken = fn(...$args) => 'fake_token';
        $fakeVerifier = fn(...$args) => true;

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50, $fakeToken, $fakeVerifier);

        $this->assertSame(1, $result['reprocessed']);
        $this->assertSame('realizado', $this->currentEstado('TEST_PR_B'));
        $status = $this->eventStatus($id);
        $this->assertSame('handled', $status['status']);
        $this->assertSame('SUCCESS', $status['verified']);
    }

    public function testQueuedEventReVerificationFailsAgainMakesNoWrite(): void
    {
        $this->insertReserva('TEST_PR_C', 'pendiente');
        $id = $this->insertEvent('evt_c', 'PAYMENT.CAPTURE.COMPLETED', [
            'resource' => ['id' => 'CAP_C', 'custom_id' => 'TEST_PR_C'],
        ], 'queued', null);

        $fakeToken = fn(...$args) => 'fake_token';
        $fakeVerifier = fn(...$args) => false;

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50, $fakeToken, $fakeVerifier);

        $this->assertSame(0, $result['reprocessed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('pendiente', $this->currentEstado('TEST_PR_C'));
        $status = $this->eventStatus($id);
        $this->assertSame('queued', $status['status']);
        $this->assertSame('FAILURE', $status['verified']);
    }

    public function testMalformedPayloadDoesNotCrashBatch(): void
    {
        $this->insertReserva('TEST_PR_D', 'pendiente');
        $badId = $this->insertEvent('evt_bad', 'PAYMENT.CAPTURE.COMPLETED', ['resource' => ['id' => 'CAP_BAD']], 'stored', 'SUCCESS');
        // Corrupt the stored payload directly to simulate a malformed row.
        $stmt = $this->conn->prepare("UPDATE paypal_webhook_events SET payload = 'not-json' WHERE id = ?");
        $stmt->bind_param('i', $badId);
        $stmt->execute();
        $stmt->close();

        $goodId = $this->insertEvent('evt_good', 'PAYMENT.CAPTURE.COMPLETED', [
            'resource' => ['id' => 'CAP_GOOD', 'custom_id' => 'TEST_PR_D'],
        ], 'stored', 'SUCCESS');

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50, null, fn(...$a) => true);

        $this->assertSame(2, $result['checked']);
        $this->assertSame(1, $result['reprocessed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('realizado', $this->currentEstado('TEST_PR_D'));
        $this->assertSame('stored', $this->eventStatus($badId)['status']);
        $this->assertSame('handled', $this->eventStatus($goodId)['status']);
    }

    public function testFiveMinuteAgeGuardExcludesRecentEvents(): void
    {
        $this->insertReserva('TEST_PR_E', 'pendiente');
        $this->insertEvent('evt_recent', 'PAYMENT.CAPTURE.COMPLETED', [
            'resource' => ['id' => 'CAP_E', 'custom_id' => 'TEST_PR_E'],
        ], 'stored', 'SUCCESS', '-1 minute');

        $tripwire = function (...$args): bool {
            throw new RuntimeException('should never be called - event is too recent');
        };
        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50, null, $tripwire);

        $this->assertSame(0, $result['checked']);
    }

    public function testThirtyDayWindowExcludesOldEvents(): void
    {
        $this->insertReserva('TEST_PR_F', 'pendiente');
        $this->insertEvent('evt_old', 'PAYMENT.CAPTURE.COMPLETED', [
            'resource' => ['id' => 'CAP_F', 'custom_id' => 'TEST_PR_F'],
        ], 'stored', 'SUCCESS', '-31 days');

        $tripwire = function (...$args): bool {
            throw new RuntimeException('should never be called - event is outside the 30-day window');
        };
        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50, null, $tripwire);

        $this->assertSame(0, $result['checked']);
    }

    public function testHandledEventsAreNeverSelected(): void
    {
        $this->insertReserva('TEST_PR_G', 'realizado');
        $this->insertEvent('evt_handled', 'PAYMENT.CAPTURE.COMPLETED', [
            'resource' => ['id' => 'CAP_G', 'custom_id' => 'TEST_PR_G'],
        ], 'handled', 'SUCCESS');

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50);
        $this->assertSame(0, $result['checked']);
    }

    public function testCheckoutOrderApprovedSetsOrderIdAndPendingWithoutDowngradingRealizado(): void
    {
        $this->insertReserva('TEST_PR_H', 'realizado');
        $id = $this->insertEvent('evt_h', 'CHECKOUT.ORDER.APPROVED', [
            'resource' => ['id' => 'ORDER_H', 'purchase_units' => [['custom_id' => 'TEST_PR_H']]],
        ], 'stored', 'SUCCESS');

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50);

        $this->assertSame(1, $result['reprocessed']);
        $this->assertSame('realizado', $this->currentEstado('TEST_PR_H'), 'A late ORDER.APPROVED must not downgrade an already-realizado reservation.');
        $this->assertSame('handled', $this->eventStatus($id)['status']);
    }

    public function testPaymentCapturePendingGuardProtectsRealizado(): void
    {
        $this->insertReserva('TEST_PR_I', 'realizado');
        $this->insertEvent('evt_i', 'PAYMENT.CAPTURE.PENDING', [
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER_I']], 'custom_id' => 'TEST_PR_I'],
        ], 'stored', 'SUCCESS');

        reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50);

        $this->assertSame('realizado', $this->currentEstado('TEST_PR_I'), 'A stuck, reprocessed PENDING event must not downgrade an already-realizado reservation.');
    }

    public function testPaymentCaptureDeniedGuardProtectsRealizado(): void
    {
        $this->insertReserva('TEST_PR_J', 'realizado');
        $this->insertEvent('evt_j', 'PAYMENT.CAPTURE.DENIED', [
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER_J']], 'custom_id' => 'TEST_PR_J'],
        ], 'stored', 'SUCCESS');

        reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50);

        $this->assertSame('realizado', $this->currentEstado('TEST_PR_J'), 'A stuck, reprocessed DENIED event must not downgrade an already-realizado reservation.');
    }

    public function testPaymentCaptureDeniedSetsFallidoOnPendienteReservation(): void
    {
        $this->insertReserva('TEST_PR_K', 'pendiente');
        $this->insertEvent('evt_k', 'PAYMENT.CAPTURE.DECLINED', [
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER_K']], 'custom_id' => 'TEST_PR_K'],
        ], 'stored', 'SUCCESS');

        reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50);

        $this->assertSame('fallido', $this->currentEstado('TEST_PR_K'));
    }

    public function testPaymentCaptureRefundedSetsRefundAndRefundId(): void
    {
        $this->insertReserva('TEST_PR_L', 'realizado');
        $this->insertEvent('evt_l', 'PAYMENT.CAPTURE.REFUNDED', [
            'resource' => [
                'id' => 'REFUND_L',
                'custom_id' => 'TEST_PR_L',
                'amount' => ['value' => '79.00', 'currency_code' => 'USD'],
            ],
        ], 'stored', 'SUCCESS');

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50);

        $this->assertSame(1, $result['reprocessed']);
        $this->assertSame('refund', $this->currentEstado('TEST_PR_L'));
    }

    public function testMultipleEligibleRowsAllProcessInOneBatch(): void
    {
        $this->insertReserva('TEST_PR_M1', 'pendiente');
        $this->insertReserva('TEST_PR_M2', 'pendiente');
        $this->insertEvent('evt_m1', 'PAYMENT.CAPTURE.COMPLETED', ['resource' => ['id' => 'CAP_M1', 'custom_id' => 'TEST_PR_M1']], 'stored', 'SUCCESS');
        $this->insertEvent('evt_m2', 'PAYMENT.CAPTURE.COMPLETED', ['resource' => ['id' => 'CAP_M2', 'custom_id' => 'TEST_PR_M2']], 'stored', 'SUCCESS');

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50);

        $this->assertSame(2, $result['checked']);
        $this->assertSame(2, $result['reprocessed']);
        $this->assertSame('realizado', $this->currentEstado('TEST_PR_M1'));
        $this->assertSame('realizado', $this->currentEstado('TEST_PR_M2'));
    }
}
```

- [ ] **Step 2: Confirm the test file is syntactically valid but fails because the function under test doesn't exist yet**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l tests/PaypalReprocessTest.php
php tests/tools/phpunit.phar -c phpunit.xml tests/PaypalReprocessTest.php
```

Expected: `php -l` passes. The PHPUnit run FAILS — specifically with a `Call to undefined function reprocess_paypal_stuck_events()` (or equivalent fatal/error) on every test, since `includes/reprocess_paypal_events.php` doesn't exist yet and nothing defines this function. This is the correct, expected state for this task — confirms the tests are actually exercising the not-yet-built function, not silently passing for the wrong reason.

- [ ] **Step 3: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add tests/PaypalReprocessTest.php
git commit -m "Add failing tests for PayPal stuck-event reprocessing (TDD, pre-implementation)"
```

---

### Task 2: Implement `reprocess_paypal_stuck_events()` to make the tests pass

**Files:**
- Create: `includes/reprocess_paypal_events.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: Task 1's test file (already written, currently failing).
- Produces: `reprocess_paypal_stuck_events(mysqli $conn, array $paypalConfig, int $limit = 50, ?callable $tokenFetcher = null, ?callable $signatureVerifier = null): array` — consumed by Task 3 (cron) and Task 4 (admin page).

- [ ] **Step 1: Write `includes/reprocess_paypal_events.php`**

```php
<?php
// includes/reprocess_paypal_events.php
// Recovers paypal_webhook_events rows that never reached status='handled' -
// a transient signature-verification failure, or an exception during
// processing. Duplicates paypal_webhook.php's event-handling logic
// (deliberately - paypal_webhook.php is never modified by this file). See
// docs/superpowers/specs/2026-08-06-paypal-reprocessing-design.md
declare(strict_types=1);

function _reprocess_paypal_find_reference_by_order_id(mysqli $conn, string $orderId): ?string
{
    $stmt = $conn->prepare("SELECT reference_id FROM reservas WHERE order_id=? LIMIT 1");
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->bind_result($ref);
    $got = $stmt->fetch();
    $stmt->close();
    return $got ? $ref : null;
}

function _reprocess_paypal_try_prepare(mysqli $conn, string $sql)
{
    return @mysqli_prepare($conn, $sql) ?: null;
}

function _reprocess_paypal_try_update_amounts(mysqli $conn, string $referenceId, ?string $amountVal, ?string $currency): void
{
    if ($amountVal === null && $currency === null) {
        return;
    }
    if ($stmt = _reprocess_paypal_try_prepare($conn, "UPDATE reservas SET monto_pagado=IFNULL(monto_pagado, ?), moneda=IFNULL(moneda, ?) WHERE TRIM(reference_id)=TRIM(?) LIMIT 1")) {
        mysqli_stmt_bind_param($stmt, 'sss', $amountVal, $currency, $referenceId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Applies one decoded PayPal event to reservas, using the exact same
 * event-type logic as paypal_webhook.php (duplicated, not shared).
 * Throws on unexpected DB errors - caller decides what "failed" means.
 */
function _reprocess_paypal_apply_event(mysqli $conn, string $eventType, array $event): void
{
    switch ($eventType) {
        case 'CHECKOUT.ORDER.APPROVED': {
            $r = $event['resource'] ?? [];
            $orderId = $r['id'] ?? null;
            $pu = $r['purchase_units'][0] ?? [];
            $referenceId = $pu['custom_id'] ?? ($pu['invoice_id'] ?? null);
            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }

            if ($referenceId && $orderId) {
                $stmt = $conn->prepare("
                    UPDATE reservas
                       SET order_id = IFNULL(order_id, ?),
                           estado   = IF(estado='realizado', estado, 'pendiente')
                     WHERE TRIM(reference_id)=TRIM(?) LIMIT 1
                ");
                $stmt->bind_param('ss', $orderId, $referenceId);
                $stmt->execute();
                $stmt->close();
            }
            break;
        }

        case 'PAYMENT.CAPTURE.COMPLETED': {
            $r = $event['resource'] ?? [];
            $captureId = $r['id'] ?? null;
            $orderId = $r['supplementary_data']['related_ids']['order_id'] ?? null;
            $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
            $amountVal = $r['amount']['value'] ?? null;
            $currency = $r['amount']['currency_code'] ?? null;

            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }

            if ($captureId && $referenceId) {
                $stmt = $conn->prepare("
                    UPDATE reservas
                       SET estado='realizado',
                           capture_id=?,
                           order_id=IFNULL(order_id, ?)
                     WHERE TRIM(reference_id)=TRIM(?)
                     LIMIT 1
                ");
                $stmt->bind_param('sss', $captureId, $orderId, $referenceId);
                $stmt->execute();
                $rows = $stmt->affected_rows;
                $stmt->close();

                if ($rows === 0 && $orderId) {
                    if ($stmt = $conn->prepare("
                        UPDATE reservas
                           SET estado='realizado',
                               capture_id=?,
                               order_id=IFNULL(order_id, ?)
                         WHERE order_id=?
                         LIMIT 1
                    ")) {
                        $stmt->bind_param('sss', $captureId, $orderId, $orderId);
                        $stmt->execute();
                        $rows = $stmt->affected_rows;
                        $stmt->close();
                    }
                }

                error_log("reprocess_paypal CAPTURE.UPDATE rows={$rows} ref={$referenceId} order={$orderId} capture={$captureId}");
                _reprocess_paypal_try_update_amounts($conn, $referenceId, $amountVal, $currency);
            }
            break;
        }

        case 'PAYMENT.CAPTURE.PENDING': {
            $r = $event['resource'] ?? [];
            $orderId = $r['supplementary_data']['related_ids']['order_id'] ?? null;
            $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }
            if ($referenceId) {
                // Same downgrade guard as paypal_webhook.php - see
                // docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
                $stmt = $conn->prepare("UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
                $stmt->bind_param('s', $referenceId);
                $stmt->execute();
                $stmt->close();
            }
            break;
        }

        case 'PAYMENT.CAPTURE.DENIED':
        case 'PAYMENT.CAPTURE.DECLINED': {
            $r = $event['resource'] ?? [];
            $orderId = $r['supplementary_data']['related_ids']['order_id'] ?? null;
            $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }
            if ($referenceId) {
                // Same downgrade guard as paypal_webhook.php.
                $stmt = $conn->prepare("UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'fallido' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
                $stmt->bind_param('s', $referenceId);
                $stmt->execute();
                $stmt->close();
            }
            break;
        }

        case 'PAYMENT.CAPTURE.REFUNDED': {
            $r = $event['resource'] ?? [];
            $orderId = $r['supplementary_data']['related_ids']['order_id'] ?? null;
            $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
            $refundId = $r['id'] ?? null;
            $amountVal = $r['amount']['value'] ?? null;
            $currency = $r['amount']['currency_code'] ?? null;

            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }
            if ($referenceId) {
                $stmt = $conn->prepare("UPDATE reservas SET estado='refund' WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
                $stmt->bind_param('s', $referenceId);
                $stmt->execute();
                $stmt->close();

                if ($stmt = _reprocess_paypal_try_prepare($conn, "UPDATE reservas SET refund_id = IFNULL(refund_id, ?), refund_monto = IFNULL(refund_monto, ?), moneda = IFNULL(moneda, ?) WHERE TRIM(reference_id)=TRIM(?) LIMIT 1")) {
                    $stmt->bind_param('ssss', $refundId, $amountVal, $currency, $referenceId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            break;
        }

        default:
            // no-op; still marked handled by the caller
            break;
    }
}

function _reprocess_paypal_real_token_fetcher(array $paypalConfig): string
{
    $mode = ($paypalConfig['mode'] === 'live') ? 'live' : 'sandbox';
    $env = $paypalConfig[$mode];
    $base = ($mode === 'live') ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

    $ch = curl_init("$base/v1/oauth2/token");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $env['client_id'] . ':' . $env['client_secret'],
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        throw new RuntimeException('OAuth curl: ' . curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("OAuth HTTP $code");
    }
    $json = json_decode($res, true);
    if (empty($json['access_token'])) {
        throw new RuntimeException('No access token');
    }
    return $json['access_token'];
}

function _reprocess_paypal_real_signature_verifier(array $paypalConfig, string $token, array $headers, array $event): bool
{
    $mode = ($paypalConfig['mode'] === 'live') ? 'live' : 'sandbox';
    $env = $paypalConfig[$mode];
    $base = ($mode === 'live') ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

    $H = array_change_key_case($headers, CASE_UPPER);
    $verifyBody = [
        'transmission_id'   => $H['PAYPAL-TRANSMISSION-ID'] ?? '',
        'transmission_time' => $H['PAYPAL-TRANSMISSION-TIME'] ?? '',
        'cert_url'          => $H['PAYPAL-CERT-URL'] ?? '',
        'auth_algo'         => $H['PAYPAL-AUTH-ALGO'] ?? '',
        'transmission_sig'  => $H['PAYPAL-TRANSMISSION-SIG'] ?? '',
        'webhook_id'        => $env['webhook_id'],
        'webhook_event'     => $event,
    ];
    $ch = curl_init("$base/v1/notifications/verify-webhook-signature");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_POSTFIELDS => json_encode($verifyBody, JSON_UNESCAPED_SLASHES),
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        return false;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300) && (json_decode($resp, true)['verification_status'] ?? '') === 'SUCCESS';
}

/**
 * Reprocesses up to $limit paypal_webhook_events rows stuck below
 * status='handled'. Never bypasses signature verification - a row already
 * marked verified='SUCCESS' skips re-verification; anything else is
 * re-verified against PayPal's real endpoint (or the injected fake in
 * tests) before any reservas write is attempted.
 *
 * @param callable $tokenFetcher Defaults to a real PayPal OAuth token
 *                  fetch. Injectable for testing.
 * @param callable $signatureVerifier Defaults to a real call to PayPal's
 *                  verify-webhook-signature endpoint. Injectable for testing.
 * @return array{checked:int, reprocessed:int, failed:int, details:array<int,array{event_id:string,event_type:string,result:string}>}
 */
function reprocess_paypal_stuck_events(
    mysqli $conn,
    array $paypalConfig,
    int $limit = 50,
    ?callable $tokenFetcher = null,
    ?callable $signatureVerifier = null
): array {
    @mkdir(__DIR__ . '/../../logs', 0775, true);
    ini_set('error_log', __DIR__ . '/../../logs/paypal_reprocess.log');

    $tokenFetcher = $tokenFetcher ?? '_reprocess_paypal_real_token_fetcher';
    $signatureVerifier = $signatureVerifier ?? '_reprocess_paypal_real_signature_verifier';

    $checked = 0;
    $reprocessed = 0;
    $failed = 0;
    $details = [];

    $stmt = $conn->prepare("
        SELECT id, event_id, event_type, status, verified, payload, headers
        FROM paypal_webhook_events
        WHERE status NOT IN ('handled', 'mailed')
          AND received_at <= NOW() - INTERVAL 5 MINUTE
          AND received_at >= NOW() - INTERVAL 30 DAY
        ORDER BY received_at ASC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($rows) === $limit) {
        error_log("reprocess_paypal: batch full (limit=$limit) - eligible events may exceed batch size, some may not have been checked this run");
    }

    foreach ($rows as $row) {
        $checked++;
        $id = (int)$row['id'];
        $eventId = (string)$row['event_id'];
        $eventType = (string)$row['event_type'];
        $event = json_decode((string)$row['payload'], true);
        $headers = json_decode((string)$row['headers'], true);

        if (!is_array($event) || !is_array($headers)) {
            $failed++;
            error_log("reprocess_paypal: unparseable stored payload/headers for event_id=$eventId id=$id");
            continue;
        }

        if ($row['verified'] !== 'SUCCESS') {
            try {
                $token = $tokenFetcher($paypalConfig);
            } catch (Throwable $e) {
                $failed++;
                error_log("reprocess_paypal: token fetch failed for event_id=$eventId: " . $e->getMessage());
                continue;
            }

            try {
                $verifiedOk = (bool)$signatureVerifier($paypalConfig, $token, $headers, $event);
            } catch (Throwable $e) {
                $verifiedOk = false;
                error_log("reprocess_paypal: signature verification threw for event_id=$eventId: " . $e->getMessage());
            }

            $verStatus = $verifiedOk ? 'SUCCESS' : 'FAILURE';
            $upd = $conn->prepare("UPDATE paypal_webhook_events SET verified = ? WHERE id = ? LIMIT 1");
            $upd->bind_param('si', $verStatus, $id);
            $upd->execute();
            $upd->close();

            if (!$verifiedOk) {
                $failed++;
                error_log("reprocess_paypal: signature re-verification failed for event_id=$eventId, leaving queued");
                continue;
            }
        }

        try {
            _reprocess_paypal_apply_event($conn, $eventType, $event);
            $upd = $conn->prepare("UPDATE paypal_webhook_events SET status='handled', handled_at=NOW() WHERE id=? LIMIT 1");
            $upd->bind_param('i', $id);
            $upd->execute();
            $upd->close();
            $reprocessed++;
            $details[] = ['event_id' => $eventId, 'event_type' => $eventType, 'result' => 'handled'];
            error_log("reprocess_paypal: reprocessed event_id=$eventId type=$eventType");
        } catch (Throwable $e) {
            $failed++;
            error_log("reprocess_paypal: processing threw for event_id=$eventId type=$eventType: " . $e->getMessage());
        }
    }

    return ['checked' => $checked, 'reprocessed' => $reprocessed, 'failed' => $failed, 'details' => $details];
}
```

- [ ] **Step 2: Add this file to the test bootstrap**

In `tests/bootstrap.php`, add after the existing `require_once __DIR__ . '/../includes/reconcile_getnet.php';` line:

```php
require_once __DIR__ . '/../includes/reprocess_paypal_events.php';
```

- [ ] **Step 3: Run the tests and confirm they now pass**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l includes/reprocess_paypal_events.php
php tests/tools/phpunit.phar -c phpunit.xml tests/PaypalReprocessTest.php
```

Expected: `php -l` clean. All 13 tests in `PaypalReprocessTest.php` pass.

- [ ] **Step 4: Run the full suite to confirm no regressions**

```bash
php tests/tools/phpunit.phar -c phpunit.xml
```

Expected: all tests across all 4 files pass (24 from before + 13 new = 37 tests).

- [ ] **Step 5: Confirm `paypal_webhook.php` is untouched**

```bash
git diff paypal_webhook.php
```

Expected: empty output.

- [ ] **Step 6: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add includes/reprocess_paypal_events.php tests/bootstrap.php
git commit -m "Implement reprocess_paypal_stuck_events (makes Task 1's tests pass)"
```

---

### Task 3: Cron entry point

**Files:**
- Create: `includes/cron_reprocess_paypal.php`

**Interfaces:**
- Consumes: `reprocess_paypal_stuck_events()` from Task 2.
- Produces: nothing for later tasks.

- [ ] **Step 1: Write `includes/cron_reprocess_paypal.php`**

```php
<?php
// includes/cron_reprocess_paypal.php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$LOCK = sys_get_temp_dir() . '/cron_reprocess_paypal.lock';
$fh = fopen($LOCK, 'c');
if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) { exit; } // avoid overlap

require __DIR__ . '/../../db_config.php'; // defines $conn (mysqli)
require __DIR__ . '/reprocess_paypal_events.php';

$paypalConfig = require __DIR__ . '/../../paypal_config.php';

$result = reprocess_paypal_stuck_events($conn, $paypalConfig, 50);

error_log(sprintf(
    "cron_reprocess_paypal: checked=%d reprocessed=%d failed=%d",
    $result['checked'],
    $result['reprocessed'],
    $result['failed']
));
```

- [ ] **Step 2: Syntax check**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l includes/cron_reprocess_paypal.php
```

Expected: no syntax errors.

- [ ] **Step 3: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add includes/cron_reprocess_paypal.php
git commit -m "Add cron entry point for PayPal stuck-event reprocessing"
```

---

### Task 4: Admin page and nav entry

**Files:**
- Create: `admin/paypal-reprocess.php`
- Modify: `admin/_nav.php`

**Interfaces:**
- Consumes: `reprocess_paypal_stuck_events()` from Task 2, `admin/_auth.php` (existing), `admin/_nav.php` (existing, modified here).
- Produces: nothing for later tasks — last task before verification/deploy.

- [ ] **Step 1: Add the nav entry**

In `admin/_nav.php`, the `$toolsLinks` array currently reads (after the Getnet-reconciliation plan already added one entry):

```php
    $toolsLinks = [
        'gallery' => ['label' => 'Gallery Upload', 'href' => '/admin/gallery-upload.php'],
        'getnet-reconcile' => ['label' => 'Getnet Reconciliation', 'href' => '/admin/getnet-reconcile.php'],
    ];
```

Add one more entry:

```php
    $toolsLinks = [
        'gallery' => ['label' => 'Gallery Upload', 'href' => '/admin/gallery-upload.php'],
        'getnet-reconcile' => ['label' => 'Getnet Reconciliation', 'href' => '/admin/getnet-reconcile.php'],
        'paypal-reprocess' => ['label' => 'PayPal Reprocessing', 'href' => '/admin/paypal-reprocess.php'],
    ];
```

(Re-read the actual current file first — confirm the exact existing array contents before editing, since this plan's text may not perfectly reflect any drift since the Getnet plan shipped.)

- [ ] **Step 2: Write `admin/paypal-reprocess.php`**

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../db_config.php';
require __DIR__ . '/../includes/reprocess_paypal_events.php';

set_time_limit(0);

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_check'])) {
    $paypalConfig = require __DIR__ . '/../paypal_config.php';
    $result = reprocess_paypal_stuck_events($conn, $paypalConfig, 50);
}

$active = 'paypal-reprocess';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PayPal Reprocessing</title>
  <link href="/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('paypal-reprocess'); ?>
<div class="container">
  <h1 class="h4 mb-3">PayPal Reprocessing</h1>
  <p class="text-muted">
    Checks <code>paypal_webhook_events</code> rows that never reached
    <code>status='handled'</code> (received more than 5 minutes ago, within
    the last 30 days), re-verifies their signature if needed, and finishes
    processing them - recovering payments PayPal notified us about but that
    we never fully recorded.
  </p>

  <form method="post">
    <button type="submit" name="run_check" value="1" class="btn btn-primary">Run Check Now</button>
  </form>

  <?php if ($result !== null): ?>
    <div class="mt-4">
      <p>
        <strong>Checked:</strong> <?= (int)$result['checked'] ?> &nbsp;
        <strong>Reprocessed:</strong> <?= (int)$result['reprocessed'] ?> &nbsp;
        <strong>Failed:</strong> <?= (int)$result['failed'] ?>
      </p>

      <?php if (!empty($result['details'])): ?>
        <table class="table table-striped table-sm">
          <thead>
            <tr><th>Event ID</th><th>Type</th><th>Result</th></tr>
          </thead>
          <tbody>
            <?php foreach ($result['details'] as $d): ?>
              <tr>
                <td><?= htmlspecialchars($d['event_id'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($d['event_type'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($d['result'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-success">Nothing to reprocess.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
```

(Note: uses local `/css/bootstrap.min.css`, not a CDN — matching the fix applied to the Getnet admin page after its final review, and consistent with every other admin page's convention.)

- [ ] **Step 3: Syntax check**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l admin/paypal-reprocess.php
php -l admin/_nav.php
```

Expected: no syntax errors.

- [ ] **Step 4: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add admin/paypal-reprocess.php admin/_nav.php
git commit -m "Add admin page and Admin Tools entry for PayPal reprocessing"
```

---

### Task 5: Local verification, including a deliberate-break check

**Files:**
- None modified — this task only verifies. If a check fails, fix the affected file in place, re-verify, then re-run the relevant earlier task's syntax checks before re-committing.

**Interfaces:**
- Consumes: the committed state from Tasks 1-4.
- Produces: verification evidence only.

- [ ] **Step 1: Full suite passes**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php tests/tools/phpunit.phar -c phpunit.xml
```

Expected: all tests pass (37 total: 24 from the prior plan + 13 from this one).

- [ ] **Step 2: Confirm the admin page requires login**

```bash
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -L http://localhost:8899/admin/paypal-reprocess.php | grep -c 'name="password"'
pkill -f "php -S localhost:8899"
```

Expected: `1` or more (redirects to the login form).

- [ ] **Step 3: Confirm the nav entry**

```bash
grep -n "paypal-reprocess" admin/_nav.php
```

Expected: the `$toolsLinks` array contains the `'paypal-reprocess'` key.

- [ ] **Step 4: Deliberately break one downgrade guard and confirm a test catches it**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
cp includes/reprocess_paypal_events.php /tmp/reprocess_paypal_events.php.bak
```

Temporarily change the `PAYMENT.CAPTURE.PENDING` branch's guarded SQL in `includes/reprocess_paypal_events.php` from:
```php
"UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1"
```
to an unguarded version:
```php
"UPDATE reservas SET estado = 'pendiente' WHERE TRIM(reference_id)=TRIM(?) LIMIT 1"
```

Then:
```bash
php tests/tools/phpunit.phar -c phpunit.xml tests/PaypalReprocessTest.php
```

Expected: `testPaymentCapturePendingGuardProtectsRealizado` FAILS.

```bash
cp /tmp/reprocess_paypal_events.php.bak includes/reprocess_paypal_events.php
php -l includes/reprocess_paypal_events.php
git diff includes/reprocess_paypal_events.php
php tests/tools/phpunit.phar -c phpunit.xml
```

Expected: `php -l` clean, `git diff` empty (fully restored), full suite passes again (37/37).

- [ ] **Step 5: If any check failed for a real reason (not the deliberate break), fix and re-verify**

Re-run the relevant steps after any fix, and re-run the relevant earlier task's syntax checks before considering the fix complete.

- [ ] **Step 6: Commit (only if Step 5 required a fix)**

```bash
git add -A
git commit -m "Fix issue found during PayPal reprocessing verification"
```

If no fix was needed, skip this step.

---

### Task 6: Deploy and give crontab instructions

**Files:**
- None modified — this task pushes already-committed changes and confirms the live site.

**Interfaces:**
- Consumes: the commits from Tasks 1-5.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push
```

- [ ] **Step 2: Remind the user to deploy and give the crontab line**

State clearly: pushing doesn't deploy — pull on the cPanel server. No Cloudflare purge needed (admin pages aren't edge-cached).

Give the user this crontab line, matching the exact format already confirmed for the Getnet reconciliation cron:
```
0 * * * * /usr/local/bin/php -d detect_unicode=0 /home/stampst1/public_html/includes/cron_reprocess_paypal.php >/dev/null 2>&1
```

- [ ] **Step 3: Once deployed, spot-check production**

Log into the admin panel, navigate to Admin Tools → PayPal Reprocessing, click "Run Check Now," and confirm it returns a result (checked/reprocessed/failed counts) without error — regardless of whether there happen to be any eligible stuck events at that moment. Confirm the page requires login (log out and confirm `admin/paypal-reprocess.php` redirects to `/login.php`).

- [ ] **Step 4: Confirm the cron is registered**

Ask the user to confirm the crontab entry from Step 2 was added successfully via cPanel's Cron Jobs tool.
