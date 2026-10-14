<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookTourHandlerTest extends TestCase
{
    private mysqli $conn;
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->today = new DateTimeImmutable('2026-10-13 23:30:00', new DateTimeZone('America/Santiago'));
        $this->cleanup();
        $this->conn->query("INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante)
                            VALUES ('MAIPO_CLASSIC', 'Maipo Valley Wine Tour - Classic Route', 110.00, 50.00, 0.00),
                                   ('Maipo', 'Maipo Valley Wine Tour - Premium Route with Undurraga', 139.00, 50.00, 0.00),
                                   ('Valparaiso', 'Valparaiso', 79.00, 40.00, 0.00)");
        // MAIPO_CLASSIC_LUNCH deliberately absent: simulates the page deployed before the migration
        $this->conn->query("INSERT INTO cupones (nombre, id_vendedor, porcentaje) VALUES ('TEST10', 1, 10)");
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
        $this->conn->query("DELETE FROM experiencias WHERE nombre IN ('MAIPO_CLASSIC', 'MAIPO_CLASSIC_LUNCH', 'Maipo', 'Valparaiso')");
        $this->conn->query("DELETE FROM cupones WHERE nombre LIKE 'TEST%'");
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
            'activity_name'     => 'MAIPO_CLASSIC',
            'coupon_code'       => '',
            'subtotal'          => '1',
            'total_price'       => '1',
        ], $overrides);
    }

    private function reserva(string $ref): array
    {
        $stmt = $this->conn->prepare('SELECT r.*, e.nombre AS exp FROM reservas r JOIN experiencias e ON e.id_experiencia = r.id_experiencia WHERE r.reference_id = ?');
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function reservasCount(): int
    {
        return (int)$this->conn->query('SELECT COUNT(*) c FROM reservas')->fetch_assoc()['c'];
    }

    public function testPostedTotalIsIgnoredAndServerTotalStored(): void
    {
        $r = book_tour_handle($this->conn, $this->post(), $this->today);
        $this->assertTrue($r['ok']);
        $this->assertSame(270.0, $r['total']);
        $row = $this->reserva($r['reference_id']);
        $this->assertSame('270.00', $row['total_venta']);
        $this->assertSame('270.00', $row['subtotal']);
        $this->assertSame('MAIPO_CLASSIC', $row['exp']);
    }

    public function testPremiumIsBookedAgainstTheMaipoRow(): void
    {
        $r = book_tour_handle($this->conn, $this->post(['activity_name' => 'Maipo', 'children' => '0']), $this->today);
        $this->assertTrue($r['ok']);
        $this->assertSame('278.00', $this->reserva($r['reference_id'])['total_venta']);
    }

    public function testCouponAppliedServerSide(): void
    {
        $r = book_tour_handle($this->conn, $this->post(['coupon_code' => 'test10']), $this->today);
        $row = $this->reserva($r['reference_id']);
        $this->assertSame('270.00', $row['subtotal']);
        $this->assertSame('243.00', $row['total_venta']);
    }

    public function testAdminOnlyFieldsAreIgnored(): void
    {
        $r = book_tour_handle($this->conn, $this->post([
            'codigo_vendedor' => 'TEST_VENDOR',
            'is_private'      => '1',
            'airport_pick_up' => 'Yes',
        ]), $this->today);
        $row = $this->reserva($r['reference_id']);
        $this->assertNull($row['id_vendedor']);
        $this->assertSame(0, (int)$row['is_private']);
        $this->assertSame(0, (int)$row['airport_pickup']);
    }

    public function testTodayInSantiagoIsBookable(): void
    {
        $r = book_tour_handle($this->conn, $this->post(['date_booking' => '10-13-2026']), $this->today);
        $this->assertTrue($r['ok']);
    }

    #[DataProvider('rejectedPosts')]
    public function testRejectedWithoutInserting(array $overrides): void
    {
        $r = book_tour_handle($this->conn, $this->post($overrides), $this->today);
        $this->assertFalse($r['ok']);
        $this->assertNotSame('', $r['error']);
        $this->assertSame(0, $this->reservasCount());
    }

    public static function rejectedPosts(): array
    {
        return [
            'not allow-listed tour'      => [['activity_name' => 'Valparaiso']],
            'allow-listed but no DB row' => [['activity_name' => 'MAIPO_CLASSIC_LUNCH']],
            'no adults'                  => [['adults' => '0', 'children' => '2']],
            'more than 15 travelers'     => [['adults' => '10', 'children' => '6']],
            'non-numeric count'          => [['adults' => 'abc']],
            'negative count'             => [['children' => '-1']],
            'past date'                  => [['date_booking' => '10-12-2026']],
            'impossible date'            => [['date_booking' => '13-45-2026']],
            'wrong date format'          => [['date_booking' => '2026-11-20']],
            'missing email'              => [['email_booking' => '']],
            'invalid email'              => [['email_booking' => 'not-an-email']],
            'missing phone'              => [['phone_booking' => '  ']],
        ];
    }
}
