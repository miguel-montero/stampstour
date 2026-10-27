<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaBookingTest extends TestCase
{
    private mysqli $conn;
    private DateTimeImmutable $today;
    private array $agent;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->today = new DateTimeImmutable('2026-10-27 12:00:00', new DateTimeZone('UTC'));
        $this->cleanup();
        $this->conn->query("INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, TAR, precio_nino, precio_infante) VALUES
            ('Valparaiso', 'Valparaiso', 79.00, 59.00, 40.00, 0.00),
            ('Andes', 'Andes', 79.00, NULL, 40.00, 0.00),
            ('Santiago', 'Santiago', 50.00, 0.00, 25.00, 0.00),
            ('Maipo', 'Maipo', 139.00, 119.00, 50.00, 0.00),
            ('MAIPO_CLASSIC', 'Maipo Classic', 110.00, 90.00, 50.00, 0.00)");
        $r = ta_apply($this->conn, 'TEST_agent', 'agent@example.test', '', $this->today);
        $this->assertTrue($r['ok']);
        $this->agent = ta_approve($this->conn, $r['id'], $this->today);
        $this->assertNotNull($this->agent);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->query("DELETE FROM reservas WHERE id_vendedor IN (SELECT id_vendedor FROM vendedores WHERE nombre_vendedor LIKE 'TEST\\_%')");
        $this->conn->query("DELETE FROM titulares WHERE email LIKE '%@example.test'");
        $this->conn->query("DELETE FROM experiencias WHERE nombre IN ('Valparaiso', 'Andes', 'Santiago', 'Maipo', 'MAIPO_CLASSIC')");
        $this->conn->query("DELETE FROM vendedores WHERE nombre_vendedor LIKE 'TEST\\_%'");
        $this->conn->query("DELETE FROM ta_code_attempts WHERE ip LIKE 'TEST%'");
    }

    private function post(array $o = []): array
    {
        return array_merge([
            'ta_code' => $this->agent['ta_code'],
            'activity_name' => 'Valparaiso',
            'customer_first_name' => 'Ana',
            'customer_last_name' => 'Traveler',
            'customer_whatsapp' => '+56 9 1234-5678',
            'date_booking' => '11-20-2026',
            'adults' => '2',
            'children' => '1',
            'infants' => '0',
        ], $o);
    }

    private function handle(array $post): array
    {
        return ta_booking_handle($this->conn, $post, 'TEST_1', $this->today);
    }

    private function reservaCount(): int
    {
        return (int)$this->conn->query('SELECT COUNT(*) FROM reservas r JOIN vendedores v ON v.id_vendedor = r.id_vendedor WHERE v.nombre_vendedor LIKE \'TEST\\_%\'')->fetch_row()[0];
    }

    private function row(string $ref): array
    {
        $stmt = $this->conn->prepare('SELECT r.*, t.nombre, t.apellido, t.telefono, t.email FROM reservas r JOIN titulares t ON t.id_titular = r.id_titular WHERE r.reference_id = ?');
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function test_prices_use_tar_for_adults_and_fallback_when_missing(): void
    {
        $p = ta_tour_prices($this->conn);
        $this->assertSame(59.0, $p['Valparaiso']['adult']);
        $this->assertSame(79.0, $p['Andes']['adult']);
        $this->assertSame(50.0, $p['Santiago']['adult']);
        $this->assertSame(40.0, $p['Valparaiso']['child']);
        $this->assertSame(0.0, $p['Valparaiso']['infant']);
        $this->assertArrayNotHasKey('MAIPO_CLASSIC', $p);
    }

    public function test_successful_booking_stores_server_total_agent_email_and_vendor(): void
    {
        $r = $this->handle($this->post());
        $this->assertTrue($r['ok'], (string)($r['error'] ?? ''));
        $row = $this->row($r['reference_id']);
        $this->assertSame('158.00', $row['total_venta']);
        $this->assertSame('158.00', $row['subtotal']);
        $this->assertSame((int)$this->agent['id_vendedor'], (int)$row['id_vendedor']);
        $this->assertSame('agent@example.test', $row['email']);
        $this->assertSame('Ana', $row['nombre']);
        $this->assertSame('Traveler', $row['apellido']);
        $this->assertSame('+56 9 1234-5678', $row['telefono']);
    }

    public function test_browser_supplied_price_vendor_email_and_coupon_are_ignored(): void
    {
        $r = $this->handle($this->post([
            'total_price' => '1', 'subtotal' => '1', 'coupon_code' => 'TEST10',
            'email_booking' => 'evil@example.test', 'codigo_vendedor' => '1',
        ]));
        $this->assertTrue($r['ok'], (string)($r['error'] ?? ''));
        $row = $this->row($r['reference_id']);
        $this->assertSame('158.00', $row['total_venta']);
        $this->assertSame('158.00', $row['subtotal']);
        $this->assertSame((int)$this->agent['id_vendedor'], (int)$row['id_vendedor']);
        $this->assertSame('agent@example.test', $row['email']);
        $this->assertNull($row['id_cupon']);
    }

    public function test_inactive_agent_cannot_book(): void
    {
        ta_set_active($this->conn, (int)$this->agent['id_vendedor'], false);
        $r = $this->handle($this->post());
        $this->assertFalse($r['ok']);
        $this->assertSame('Your agent code is not valid or is no longer active.', $r['error']);
        $this->assertSame(0, $this->reservaCount());
    }

    public function test_invalid_code_records_failure_and_blocks_after_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $r = $this->handle($this->post(['ta_code' => 'abcd']));
            $this->assertSame('Your agent code is not valid or is no longer active.', $r['error']);
        }
        $r = $this->handle($this->post());
        $this->assertFalse($r['ok']);
        $this->assertSame('Too many attempts. Please wait 15 minutes and try again.', $r['error']);
        $this->assertSame(0, $this->reservaCount());
    }

    public function test_empty_children_and_infants_count_as_zero(): void
    {
        $r = $this->handle($this->post(['children' => '', 'infants' => '']));
        $this->assertTrue($r['ok'], (string)($r['error'] ?? ''));
        $r = $this->handle($this->post(['adults' => '']));
        $this->assertFalse($r['ok']);
    }

    public function test_rejects_date_more_than_two_years_out(): void
    {
        $r = $this->handle($this->post(['date_booking' => '10-28-2028']));
        $this->assertFalse($r['ok']);
        $this->assertSame('Please choose a tour date within the next two years.', $r['error']);
        $r = $this->handle($this->post(['date_booking' => '10-27-2028']));
        $this->assertTrue($r['ok'], (string)($r['error'] ?? ''));
    }

    public function test_rejects_tour_outside_the_four(): void
    {
        $r = $this->handle($this->post(['activity_name' => 'MAIPO_CLASSIC']));
        $this->assertFalse($r['ok']);
        $this->assertSame(0, $this->reservaCount());
    }

    public function test_rejects_past_date(): void
    {
        $this->assertFalse($this->handle($this->post(['date_booking' => '10-26-2026']))['ok']);
        $this->assertFalse($this->handle($this->post(['date_booking' => '2026-11-20']))['ok']);
        $this->assertTrue($this->handle($this->post(['date_booking' => '10-27-2026']))['ok']);
    }

    public function test_requires_customer_name_and_whatsapp(): void
    {
        $this->assertFalse($this->handle($this->post(['customer_first_name' => ' ']))['ok']);
        $this->assertFalse($this->handle($this->post(['customer_last_name' => '']))['ok']);
        $this->assertFalse($this->handle($this->post(['customer_whatsapp' => '']))['ok']);
        $this->assertFalse($this->handle($this->post(['customer_whatsapp' => '123']))['ok']);
        $this->assertFalse($this->handle($this->post(['customer_whatsapp' => 'call me maybe 12345678']))['ok']);
        $this->assertSame(0, $this->reservaCount());
    }

    public function test_rejects_zero_adults_and_over_15_travelers(): void
    {
        $this->assertFalse($this->handle($this->post(['adults' => '0']))['ok']);
        $this->assertFalse($this->handle($this->post(['adults' => '10', 'children' => '5', 'infants' => '1']))['ok']);
        $this->assertTrue($this->handle($this->post(['adults' => '10', 'children' => '5', 'infants' => '0']))['ok']);
    }

    public function test_non_string_post_values_are_rejected(): void
    {
        $this->assertFalse($this->handle($this->post(['adults' => ['2']]))['ok']);
    }
}
