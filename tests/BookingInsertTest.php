<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BookingInsertTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->cleanup();
        $this->conn->query("INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante)
                            VALUES ('TEST_TOUR', 'Test Tour', 110.00, 50.00, 0.00)");
        $this->conn->query("INSERT INTO vendedores (nombre_vendedor) VALUES ('TEST_VENDOR')");
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->query("DELETE FROM reservas");
        $this->conn->query("DELETE FROM titulares WHERE email LIKE '%@example.test'");
        $this->conn->query("DELETE FROM experiencias WHERE nombre LIKE 'TEST\\_%' ESCAPE '\\\\'");
        $this->conn->query("DELETE FROM vendedores WHERE nombre_vendedor LIKE 'TEST\\_%' ESCAPE '\\\\'");
    }

    private function post(array $overrides = []): array
    {
        return array_merge([
            'name_booking'      => 'Ana',
            'last_name_booking' => 'Traveler',
            'email_booking'     => 'ana@example.test',
            'phone_booking'     => '+56 9 1234 5678',
            'date_booking'      => '11-20-2026',
            'adults'            => '2',
            'children'          => '1',
            'infants'           => '0',
            'activity_name'     => 'TEST_TOUR',
            'coupon_code'       => '',
        ], $overrides);
    }

    private function reserva(string $ref): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM reservas WHERE reference_id = ?');
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function testStoresGivenTotalsAsIs(): void
    {
        // submit.php's admin/manual/TA callers rely on custom totals being stored unchanged
        $ref = booking_insert_reservation($this->conn, $this->post(), 999.99, 1.00);
        $this->assertMatchesRegularExpression('/^STAMP_[0-9a-f]{13}$/', $ref);
        $row = $this->reserva($ref);
        $this->assertSame('999.99', $row['subtotal']);
        $this->assertSame('1.00', $row['total_venta']);
        $this->assertSame('2026-11-20', $row['fecha_actividad']);
        $this->assertSame(2, (int)$row['adultos']);
        $this->assertSame(1, (int)$row['ninos']);
    }

    public function testCodigoVendedorOverrideStillWorks(): void
    {
        $ref = booking_insert_reservation($this->conn, $this->post(['codigo_vendedor' => 'TEST_VENDOR']), 10.0, 10.0);
        $this->assertNotNull($this->reserva($ref)['id_vendedor']);
    }

    public function testStoresNotasInternasTrimmed(): void
    {
        $ref = booking_insert_reservation($this->conn, $this->post(['notas_internas' => "  Pickup 8:30\nVegetarian lunch  "]), 10.0, 10.0);
        $this->assertSame("Pickup 8:30\nVegetarian lunch", $this->reserva($ref)['notas_internas']);
    }

    public function testEmptyOrMissingNotasInternasStoresNull(): void
    {
        $ref = booking_insert_reservation($this->conn, $this->post(['notas_internas' => '   ']), 10.0, 10.0);
        $this->assertNull($this->reserva($ref)['notas_internas']);
        $ref = booking_insert_reservation($this->conn, $this->post(), 10.0, 10.0);
        $this->assertNull($this->reserva($ref)['notas_internas']);
    }

    public function testUnknownExperienceThrowsAndInsertsNothing(): void
    {
        try {
            booking_insert_reservation($this->conn, $this->post(['activity_name' => 'TEST_MISSING']), 1.0, 1.0);
            $this->fail('Expected BookingUnknownExperienceException');
        } catch (BookingUnknownExperienceException $e) {
            $count = (int)$this->conn->query("SELECT COUNT(*) c FROM titulares WHERE email LIKE '%@example.test'")->fetch_assoc()['c'];
            $this->assertSame(0, $count, 'no orphan titular row');
            $this->assertSame(0, (int)$this->conn->query('SELECT COUNT(*) c FROM reservas')->fetch_assoc()['c']);
        }
    }
}
