<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PaypalGuardTest extends TestCase
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

    /** Exact copy of paypal_webhook.php:321's PAYMENT.CAPTURE.PENDING guard. */
    private function runPendingGuard(string $reference): int
    {
        $stmt = $this->conn->prepare("UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /** Exact copy of paypal_webhook.php:338's PAYMENT.CAPTURE.DENIED/DECLINED guard. */
    private function runDeniedGuard(string $reference): int
    {
        $stmt = $this->conn->prepare("UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'fallido' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    private function insertReserva(string $reference, string $estado): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO reservas (reference_id, fecha_reserva, fecha_actividad, id_titular, estado)
            VALUES (?, CURDATE(), CURDATE(), 1, ?)
        ");
        $stmt->bind_param('ss', $reference, $estado);
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

    public function testPendingGuardProtectsRealizado(): void
    {
        $this->insertReserva('TEST_PP_A', 'realizado');
        $this->runPendingGuard('TEST_PP_A');
        $this->assertSame('realizado', $this->currentEstado('TEST_PP_A'));
    }

    public function testPendingGuardProtectsRefund(): void
    {
        $this->insertReserva('TEST_PP_B', 'refund');
        $this->runPendingGuard('TEST_PP_B');
        $this->assertSame('refund', $this->currentEstado('TEST_PP_B'));
    }

    public function testPendingGuardAllowsPendienteToStayPendiente(): void
    {
        $this->insertReserva('TEST_PP_C', 'pendiente');
        $this->runPendingGuard('TEST_PP_C');
        $this->assertSame('pendiente', $this->currentEstado('TEST_PP_C'));
    }

    public function testDeniedGuardProtectsRealizado(): void
    {
        $this->insertReserva('TEST_PP_D', 'realizado');
        $this->runDeniedGuard('TEST_PP_D');
        $this->assertSame('realizado', $this->currentEstado('TEST_PP_D'));
    }

    public function testDeniedGuardAllowsPendienteToBecomeFallido(): void
    {
        $this->insertReserva('TEST_PP_E', 'pendiente');
        $this->runDeniedGuard('TEST_PP_E');
        $this->assertSame('fallido', $this->currentEstado('TEST_PP_E'));
    }

    /** Drift guard for both statements. */
    public function testSqlMatchesLiveFile(): void
    {
        $source = file_get_contents(__DIR__ . '/../paypal_webhook.php');
        $this->assertNotFalse($source);
        $this->assertStringContainsString(
            "UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END",
            $source,
            "paypal_webhook.php's PENDING guard SQL has changed - update this test's copy in runPendingGuard() to match, then update this assertion."
        );
        $this->assertStringContainsString(
            "UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'fallido' END",
            $source,
            "paypal_webhook.php's DENIED/DECLINED guard SQL has changed - update this test's copy in runDeniedGuard() to match, then update this assertion."
        );
    }
}
