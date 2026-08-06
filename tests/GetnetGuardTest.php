<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GetnetGuardTest extends TestCase
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

    /**
     * The exact guarded UPDATE from getnet_notify.php - copied here
     * deliberately (not extracted into a shared function, per this
     * project's constraint against refactoring the live webhook files).
     * testSqlMatchesLiveFile() below guards against this copy drifting
     * from the real file.
     */
    private function runGuardedUpdate(string $nuevoEstado, string $reference, string $processId): int
    {
        $stmt = $this->conn->prepare("
            UPDATE reservas
            SET estado = CASE
                           WHEN estado IN ('realizado', 'refund') AND ? <> 'refund' THEN estado
                           ELSE ?
                         END,
                updated_at = NOW()
            WHERE reference_id = ?
              AND process_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ssss', $nuevoEstado, $nuevoEstado, $reference, $processId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    private function insertReserva(string $reference, string $processId, string $estado): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO reservas (reference_id, process_id, fecha_reserva, fecha_actividad, id_titular, estado)
            VALUES (?, ?, CURDATE(), CURDATE(), 1, ?)
        ");
        $stmt->bind_param('sis', $reference, $processId, $estado);
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

    public function testRealizadoStaysRealizadoWhenIncomingIsPendiente(): void
    {
        $this->insertReserva('TEST_A', '111', 'realizado');
        $this->runGuardedUpdate('pendiente', 'TEST_A', '111');
        $this->assertSame('realizado', $this->currentEstado('TEST_A'));
    }

    public function testRealizadoStaysRealizadoWhenIncomingIsFallido(): void
    {
        $this->insertReserva('TEST_B', '112', 'realizado');
        $this->runGuardedUpdate('fallido', 'TEST_B', '112');
        $this->assertSame('realizado', $this->currentEstado('TEST_B'));
    }

    public function testRefundStaysRefundWhenIncomingIsRealizado(): void
    {
        $this->insertReserva('TEST_C', '113', 'refund');
        $this->runGuardedUpdate('realizado', 'TEST_C', '113');
        $this->assertSame('refund', $this->currentEstado('TEST_C'));
    }

    public function testRealizadoCanBecomeRefund(): void
    {
        $this->insertReserva('TEST_D', '114', 'realizado');
        $this->runGuardedUpdate('refund', 'TEST_D', '114');
        $this->assertSame('refund', $this->currentEstado('TEST_D'));
    }

    public function testPendienteBecomesRealizadoOnApproval(): void
    {
        $this->insertReserva('TEST_E', '115', 'pendiente');
        $this->runGuardedUpdate('realizado', 'TEST_E', '115');
        $this->assertSame('realizado', $this->currentEstado('TEST_E'));
    }

    public function testMismatchedProcessIdMatchesNoRows(): void
    {
        $this->insertReserva('TEST_F', '116', 'pendiente');
        $affected = $this->runGuardedUpdate('realizado', 'TEST_F', '999');
        $this->assertSame(0, $affected);
        $this->assertSame('pendiente', $this->currentEstado('TEST_F'));
    }

    /**
     * Drift guard: if a future edit changes getnet_notify.php's guarded
     * SQL without updating this test's copy above, this fails loudly
     * instead of the test silently testing stale SQL forever.
     */
    public function testSqlMatchesLiveFile(): void
    {
        $source = file_get_contents(__DIR__ . '/../getnet_notify.php');
        $this->assertNotFalse($source);
        $this->assertStringContainsString(
            "WHEN estado IN ('realizado', 'refund') AND ? <> 'refund' THEN estado",
            $source,
            "getnet_notify.php's guard SQL has changed - update this test's copy in runGuardedUpdate() to match, then update this assertion."
        );
    }
}
