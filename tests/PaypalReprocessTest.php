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
