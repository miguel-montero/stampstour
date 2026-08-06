<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GetnetReconciliationTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->conn->query("DELETE FROM reservas");
    }

    protected function tearDown(): void
    {
        $this->conn->query("DELETE FROM reservas");
    }

    private function insertReserva(
        string $reference,
        ?string $processId,
        string $estado,
        string $fechaReserva = 'today'
    ): void {
        $stmt = $this->conn->prepare("
            INSERT INTO reservas (reference_id, process_id, fecha_reserva, fecha_actividad, id_titular, estado)
            VALUES (?, ?, ?, CURDATE(), 1, ?)
        ");
        $fecha = date('Y-m-d', strtotime($fechaReserva));
        $stmt->bind_param('siss', $reference, $processId, $fecha, $estado);
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

    public function testApprovedGetnetSessionCorrectsPendienteToRealizado(): void
    {
        $this->insertReserva('TEST_RC_A', '2001', 'pendiente');
        $fakeLookup = fn(int $id) => ['status' => ['status' => 'APPROVED']];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['corrected']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame('realizado', $this->currentEstado('TEST_RC_A'));
        $this->assertCount(1, $result['corrections']);
        $this->assertSame('TEST_RC_A', $result['corrections'][0]['reference']);
        $this->assertSame('realizado', $result['corrections'][0]['to']);
    }

    public function testStillPendingGetnetSessionMakesNoWrite(): void
    {
        $this->insertReserva('TEST_RC_B', '2002', 'pendiente');
        $fakeLookup = fn(int $id) => ['status' => ['status' => 'PENDING']];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(0, $result['corrected']);
        $this->assertSame('pendiente', $this->currentEstado('TEST_RC_B'));
    }

    public function testRejectedGetnetSessionMakesNoWrite(): void
    {
        // Unlike the PENDING case above, REJECTED maps to 'fallido' - a
        // value genuinely different from the row's starting 'pendiente'.
        // This makes the skip-guard's effect observable: if
        // reconcile_getnet_pending() ever stopped skipping non-realizado/
        // non-refund results, this row's estado would visibly flip to
        // 'fallido' and 'corrected' would visibly increment, unlike the
        // PENDING case where the "corrected" write (if it happened) would
        // be a same-value no-op indistinguishable from no write at all.
        $this->insertReserva('TEST_RC_B2', '2012', 'pendiente');
        $fakeLookup = fn(int $id) => ['status' => ['status' => 'REJECTED']];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(0, $result['corrected']);
        $this->assertSame('pendiente', $this->currentEstado('TEST_RC_B2'));
    }

    public function testFailedGetnetLookupIncrementsFailedNotCorrected(): void
    {
        $this->insertReserva('TEST_RC_C', '2003', 'pendiente');
        $fakeLookup = fn(int $id) => ['ok' => false, 'error' => 'network'];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(0, $result['corrected']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('pendiente', $this->currentEstado('TEST_RC_C'));
    }

    public function testThrowingLookupIncrementsFailedAndDoesNotCrash(): void
    {
        $this->insertReserva('TEST_RC_D', '2004', 'pendiente');
        $fakeLookup = function (int $id): array {
            throw new RuntimeException('simulated network failure');
        };

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('pendiente', $this->currentEstado('TEST_RC_D'));
    }

    public function testRefundedGetnetSessionCorrectsToRefund(): void
    {
        $this->insertReserva('TEST_RC_E', '2005', 'pendiente');
        $fakeLookup = fn(int $id) => ['status' => ['status' => 'REFUNDED']];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['corrected']);
        $this->assertSame('refund', $this->currentEstado('TEST_RC_E'));
    }

    public function testRowWithoutProcessIdIsNeverSelected(): void
    {
        $this->insertReserva('TEST_RC_F', null, 'pendiente');
        $fakeLookup = function (int $id): array {
            throw new RuntimeException('should never be called - row has no process_id');
        };

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(0, $result['checked']);
    }

    public function testRowOutsideOneDayWindowIsNeverSelected(): void
    {
        $this->insertReserva('TEST_RC_G', '2007', 'pendiente', '-2 days');
        $fakeLookup = function (int $id): array {
            throw new RuntimeException('should never be called - row is outside the 1-day window');
        };

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(0, $result['checked']);
    }

    public function testAlreadyRealizadoRowIsNeverSelected(): void
    {
        $this->insertReserva('TEST_RC_H', '2008', 'realizado');
        $fakeLookup = function (int $id): array {
            throw new RuntimeException('should never be called - row is not pendiente');
        };

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(0, $result['checked']);
    }

    public function testPaymentStatusTakesPriorityOverSessionStatus(): void
    {
        $this->insertReserva('TEST_RC_I', '2009', 'pendiente');
        // session-level status says PENDING, but payment[0].status.status says APPROVED -
        // payment status must win, per the proven field-extraction pattern.
        $fakeLookup = fn(int $id) => [
            'status' => ['status' => 'PENDING'],
            'payment' => [['status' => ['status' => 'APPROVED']]],
        ];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame('realizado', $this->currentEstado('TEST_RC_I'));
    }

    public function testDefaultParameterStillCallsRealGetSessionInfo(): void
    {
        // No fixture reservation matches (no eligible rows), so the real
        // getSessionInfo() is never actually invoked - this only proves
        // the function is callable with its default third argument and
        // doesn't fatal, confirming real callers (cron, admin page) that
        // pass no third argument remain unaffected by Task 3's change.
        $result = reconcile_getnet_pending($this->conn, 50);
        $this->assertSame(0, $result['checked']);
    }
}
