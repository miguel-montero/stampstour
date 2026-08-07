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

    /*
     * --- Drift-guard constants for testReprocessSqlMatchesLiveFileForSharedStatements() ---
     * Exact copies of every SQL statement that includes/reprocess_paypal_events.php
     * duplicates from paypal_webhook.php. CHECKOUT.ORDER.APPROVED is handled
     * separately below (LIVE_ORDER_APPROVED_SQL / REPROCESS_ORDER_APPROVED_SQL)
     * because, per Fix 1, that one statement is now deliberately different
     * between the two files.
     */
    private const LIVE_FIND_REFERENCE_SQL = "SELECT reference_id FROM reservas WHERE order_id=? LIMIT 1";

    private const LIVE_UPDATE_AMOUNTS_SQL = "UPDATE reservas SET monto_pagado=IFNULL(monto_pagado, ?), moneda=IFNULL(moneda, ?) WHERE TRIM(reference_id)=TRIM(?) LIMIT 1";

    private const LIVE_CAPTURE_COMPLETED_PRIMARY_SQL = "
          UPDATE reservas
             SET estado='realizado',
                 capture_id=?,
                 order_id=IFNULL(order_id, ?)
           WHERE TRIM(reference_id)=TRIM(?)
           LIMIT 1
        ";

    private const LIVE_CAPTURE_COMPLETED_FALLBACK_SQL = "
            UPDATE reservas
               SET estado='realizado',
                   capture_id=?,
                   order_id=IFNULL(order_id, ?)
             WHERE order_id=?
             LIMIT 1
          ";

    private const LIVE_PENDING_GUARD_SQL = "UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1";

    private const LIVE_DENIED_GUARD_SQL = "UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'fallido' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1";

    private const LIVE_REFUND_SET_SQL = "UPDATE reservas SET estado='refund' WHERE TRIM(reference_id)=TRIM(?) LIMIT 1";

    private const LIVE_REFUND_FIELDS_SQL = "UPDATE reservas SET refund_id = IFNULL(refund_id, ?), refund_monto = IFNULL(refund_monto, ?), moneda = IFNULL(moneda, ?) WHERE TRIM(reference_id)=TRIM(?) LIMIT 1";

    /** paypal_webhook.php's CHECKOUT.ORDER.APPROVED guard - deliberately does NOT protect 'refund'. */
    private const LIVE_ORDER_APPROVED_SQL = "
          UPDATE reservas
             SET order_id = IFNULL(order_id, ?),
                 estado   = IF(estado='realizado', estado, 'pendiente')
           WHERE TRIM(reference_id)=TRIM(?) LIMIT 1
        ";

    /** includes/reprocess_paypal_events.php's CHECKOUT.ORDER.APPROVED guard - deliberately DOES protect 'refund' (Fix 1). */
    private const REPROCESS_ORDER_APPROVED_SQL = "
                    UPDATE reservas
                       SET order_id = IFNULL(order_id, ?),
                           estado   = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END
                     WHERE TRIM(reference_id)=TRIM(?) LIMIT 1
                ";

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

    /**
     * Fix 1 (final review): unlike the PENDING/DENIED guards, the
     * CHECKOUT.ORDER.APPROVED guard historically only protected 'realizado'.
     * Because this script can replay a stuck ORDER.APPROVED event up to 30
     * days later, a reservation refunded in the meantime must not be
     * silently reverted to 'pendiente'.
     */
    public function testCheckoutOrderApprovedGuardProtectsRefund(): void
    {
        $this->insertReserva('TEST_PR_N', 'refund');
        $id = $this->insertEvent('evt_n', 'CHECKOUT.ORDER.APPROVED', [
            'resource' => ['id' => 'ORDER_N', 'purchase_units' => [['custom_id' => 'TEST_PR_N']]],
        ], 'stored', 'SUCCESS');

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50);

        $this->assertSame(1, $result['reprocessed']);
        $this->assertSame(
            'refund',
            $this->currentEstado('TEST_PR_N'),
            'A replayed ORDER.APPROVED event up to 30 days late must not revert an already-refunded reservation back to pendiente.'
        );
        $this->assertSame('handled', $this->eventStatus($id)['status']);
    }

    /**
     * Fix 2 (final review): a row whose most recent verification attempt
     * failed and whose received_at is more than 24 hours old must be
     * excluded from selection entirely (retry backoff), to avoid retrying a
     * permanently-broken row for the full 30-day window.
     */
    public function testOldFailedVerificationRowIsNotRetried(): void
    {
        $this->insertReserva('TEST_PR_O', 'pendiente');
        $this->insertEvent('evt_o', 'PAYMENT.CAPTURE.COMPLETED', [
            'resource' => ['id' => 'CAP_O', 'custom_id' => 'TEST_PR_O'],
        ], 'stored', 'FAILURE', '-2 days');

        $tripwire = function (...$args): bool {
            throw new RuntimeException('should never be called - row failed verification more than 24h ago and must not be retried');
        };
        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50, null, $tripwire);

        $this->assertSame(0, $result['checked'], 'A row that failed verification more than 24h ago must be excluded from selection entirely (retry backoff).');
        $this->assertSame('pendiente', $this->currentEstado('TEST_PR_O'));
    }

    /**
     * Fix 2 (final review): a row that failed verification less than 24
     * hours ago must still be retried - the backoff only kicks in after a
     * full day, giving several retry attempts for a possibly-transient
     * failure.
     */
    public function testRecentFailedVerificationRowIsStillRetried(): void
    {
        $this->insertReserva('TEST_PR_P', 'pendiente');
        $id = $this->insertEvent('evt_p', 'PAYMENT.CAPTURE.COMPLETED', [
            'resource' => ['id' => 'CAP_P', 'custom_id' => 'TEST_PR_P'],
        ], 'stored', 'FAILURE', '-2 hours');

        $fakeToken = fn(...$args) => 'fake_token';
        $fakeVerifier = fn(...$args) => true;

        $result = reprocess_paypal_stuck_events($this->conn, $this->fakePaypalConfig, 50, $fakeToken, $fakeVerifier);

        $this->assertSame(1, $result['checked'], 'A row that failed verification less than 24h ago must still be eligible for retry.');
        $this->assertSame(1, $result['reprocessed']);
        $this->assertSame('realizado', $this->currentEstado('TEST_PR_P'));
        $status = $this->eventStatus($id);
        $this->assertSame('handled', $status['status']);
        $this->assertSame('SUCCESS', $status['verified']);
    }

    /**
     * Fix 3 (final review): PaypalGuardTest's testSqlMatchesLiveFile() only
     * checks that paypal_webhook.php still contains 2 guard strings - it
     * never checks that includes/reprocess_paypal_events.php's copy still
     * matches paypal_webhook.php. This test compares both files directly
     * for every SQL statement that is supposed to be an exact duplicate
     * between them, so a future edit to either file's SQL, without
     * updating the other, fails a test instead of silently drifting.
     *
     * CHECKOUT.ORDER.APPROVED is excluded from the must-match set: per
     * Fix 1, the reprocessing copy is intentionally MORE protective than
     * the live webhook's version, so it is asserted to *differ* instead.
     */
    public function testReprocessSqlMatchesLiveFileForSharedStatements(): void
    {
        $liveSource = file_get_contents(__DIR__ . '/../paypal_webhook.php');
        $reprocessSource = file_get_contents(__DIR__ . '/../includes/reprocess_paypal_events.php');
        $this->assertNotFalse($liveSource);
        $this->assertNotFalse($reprocessSource);

        $norm = fn(string $s): string => trim(preg_replace('/\s+/', ' ', $s));

        $mustMatch = [
            'find-reference-by-order-id lookup' => self::LIVE_FIND_REFERENCE_SQL,
            'monto_pagado/moneda update' => self::LIVE_UPDATE_AMOUNTS_SQL,
            'CAPTURE.COMPLETED primary update' => self::LIVE_CAPTURE_COMPLETED_PRIMARY_SQL,
            'CAPTURE.COMPLETED order_id fallback update' => self::LIVE_CAPTURE_COMPLETED_FALLBACK_SQL,
            'PAYMENT.CAPTURE.PENDING guard' => self::LIVE_PENDING_GUARD_SQL,
            'PAYMENT.CAPTURE.DENIED/DECLINED guard' => self::LIVE_DENIED_GUARD_SQL,
            'PAYMENT.CAPTURE.REFUNDED estado update' => self::LIVE_REFUND_SET_SQL,
            'PAYMENT.CAPTURE.REFUNDED refund_id/refund_monto update' => self::LIVE_REFUND_FIELDS_SQL,
        ];

        foreach ($mustMatch as $label => $sql) {
            $needle = $norm($sql);
            $this->assertStringContainsString(
                $needle,
                $norm($liveSource),
                "paypal_webhook.php no longer contains the expected $label SQL - update this test's constant to match, then re-check includes/reprocess_paypal_events.php."
            );
            $this->assertStringContainsString(
                $needle,
                $norm($reprocessSource),
                "includes/reprocess_paypal_events.php's $label SQL has drifted from paypal_webhook.php's - these two files' copies of this statement must stay identical."
            );
        }

        // CHECKOUT.ORDER.APPROVED: the one deliberate exception (Fix 1).
        $this->assertStringContainsString(
            $norm(self::LIVE_ORDER_APPROVED_SQL),
            $norm($liveSource),
            "paypal_webhook.php's CHECKOUT.ORDER.APPROVED SQL has changed - update this test's LIVE_ORDER_APPROVED_SQL constant."
        );
        $this->assertStringContainsString(
            $norm(self::REPROCESS_ORDER_APPROVED_SQL),
            $norm($reprocessSource),
            "includes/reprocess_paypal_events.php's CHECKOUT.ORDER.APPROVED SQL has changed - update this test's REPROCESS_ORDER_APPROVED_SQL constant."
        );
        $this->assertStringNotContainsString(
            'refund',
            self::LIVE_ORDER_APPROVED_SQL,
            "paypal_webhook.php's CHECKOUT.ORDER.APPROVED guard must not reference 'refund' - see docs/superpowers/specs/2026-08-06-paypal-reprocessing-design.md for why only the reprocessing copy needs this guard."
        );
        $this->assertStringContainsString(
            'refund',
            self::REPROCESS_ORDER_APPROVED_SQL,
            "includes/reprocess_paypal_events.php's CHECKOUT.ORDER.APPROVED guard should reference 'refund' - this is the deliberate Fix 1 divergence from paypal_webhook.php."
        );
    }
}
