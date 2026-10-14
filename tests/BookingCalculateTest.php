<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BookingCalculateTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->cleanup();
        $this->conn->query("INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante)
                            VALUES ('TEST_CLASSIC', 'Test Classic', 110.00, 50.00, 0.00),
                                   ('TEST_PREMIUM', 'Test Premium', 139.00, 50.00, 5.00)");
        $this->conn->query("INSERT INTO cupones (nombre, id_vendedor, porcentaje)
                            VALUES ('TEST10', 1, 10), ('TEST33', 1, 33), ('TEST15', 1, 15)");
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->query("DELETE FROM experiencias WHERE nombre LIKE 'TEST\\_%' ESCAPE '\\\\'");
        $this->conn->query("DELETE FROM cupones WHERE nombre LIKE 'TEST%'");
    }

    public function testFetchTourPricesReturnsRequestedRowsOnly(): void
    {
        $prices = fetch_tour_prices($this->conn, ['TEST_CLASSIC', 'TEST_PREMIUM', 'TEST_MISSING']);
        $this->assertSame(['adult' => 110.0, 'child' => 50.0, 'infant' => 0.0], $prices['TEST_CLASSIC']);
        $this->assertSame(['adult' => 139.0, 'child' => 50.0, 'infant' => 5.0], $prices['TEST_PREMIUM']);
        $this->assertArrayNotHasKey('TEST_MISSING', $prices);
    }

    public function testFetchTourPricesWithEmptyListReturnsEmpty(): void
    {
        $this->assertSame([], fetch_tour_prices($this->conn, []));
    }

    public function testTotalForAdultsChildrenInfants(): void
    {
        $r = booking_calculate_total($this->conn, 'TEST_PREMIUM', 2, 1, 1, '');
        $this->assertSame(['subtotal' => 333.0, 'total' => 333.0], $r); // 2*139 + 50 + 5
    }

    public function testCouponIsAppliedCaseInsensitively(): void
    {
        $r = booking_calculate_total($this->conn, 'TEST_CLASSIC', 2, 1, 0, 'test10');
        $this->assertSame(['subtotal' => 270.0, 'total' => 243.0], $r);
    }

    public function testCouponRoundingMatchesApplyCouponEndpoint(): void
    {
        // api/apply_coupon.php: round(total * (1 - pct/100), 2)
        $this->assertSame(73.7, booking_calculate_total($this->conn, 'TEST_CLASSIC', 1, 0, 0, 'TEST33')['total']);
        $this->assertSame(93.5, booking_calculate_total($this->conn, 'TEST_CLASSIC', 1, 0, 0, 'TEST15')['total']);
    }

    public function testUnknownCouponMeansNoDiscount(): void
    {
        $r = booking_calculate_total($this->conn, 'TEST_CLASSIC', 1, 0, 0, 'NOPE');
        $this->assertSame(['subtotal' => 110.0, 'total' => 110.0], $r);
    }

    public function testUnknownExperienceThrows(): void
    {
        $this->expectException(BookingUnknownExperienceException::class);
        booking_calculate_total($this->conn, 'TEST_MISSING', 1, 0, 0, '');
    }

    public function testNegativeCountsThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        booking_calculate_total($this->conn, 'TEST_CLASSIC', 1, -1, 0, '');
    }
}
