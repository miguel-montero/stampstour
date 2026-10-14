<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MaipoOptionsMigrationTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->query("DELETE FROM experiencias WHERE nombre IN ('Maipo', 'MAIPO_CLASSIC', 'MAIPO_CLASSIC_LUNCH')");
    }

    private function seedOriginalMaipoRow(): int
    {
        $this->conn->query("INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante, precio_concierge, TAR)
                            VALUES ('Maipo', 'Maipo Valley Wine Tour with 4 vineyards from Santiago', 121.00, 50.00, 0.00, 149.00, 99.00)");
        return (int)$this->conn->insert_id;
    }

    private function rows(): array
    {
        $res = $this->conn->query("SELECT * FROM experiencias WHERE nombre IN ('Maipo', 'MAIPO_CLASSIC', 'MAIPO_CLASSIC_LUNCH')");
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $out[$r['nombre']] = $r;
        }
        return $out;
    }

    public function testCreatesAndUpdatesTheThreeRows(): void
    {
        $maipoId = $this->seedOriginalMaipoRow();
        migrate_maipo_options($this->conn);
        $rows = $this->rows();

        $this->assertCount(3, $rows);
        $this->assertSame($maipoId, (int)$rows['Maipo']['id_experiencia'], 'premium keeps the existing id');
        $this->assertSame('139.00', $rows['Maipo']['precio_adulto']);
        $this->assertSame('Maipo Valley Wine Tour - Premium Route with Undurraga', $rows['Maipo']['nombre_publico']);
        $this->assertSame('110.00', $rows['MAIPO_CLASSIC']['precio_adulto']);
        $this->assertSame('Maipo Valley Wine Tour - Classic Route', $rows['MAIPO_CLASSIC']['nombre_publico']);
        $this->assertSame('129.00', $rows['MAIPO_CLASSIC_LUNCH']['precio_adulto']);
        $this->assertSame('Classic Maipo Valley Tastings & Lunch', $rows['MAIPO_CLASSIC_LUNCH']['nombre_publico']);

        foreach ($rows as $r) {
            $this->assertSame('50.00', $r['precio_nino']);
            $this->assertSame('0.00', $r['precio_infante']);
            $this->assertSame('149.00', $r['precio_concierge'], 'concierge price copied from Maipo');
            $this->assertSame('99.00', $r['TAR'], 'TA rate copied from Maipo');
        }
    }

    public function testSecondRunIsANoOp(): void
    {
        $this->seedOriginalMaipoRow();
        migrate_maipo_options($this->conn);
        $before = $this->rows();

        $log = migrate_maipo_options($this->conn);

        $this->assertSame($before, $this->rows());
        foreach ($log as $line) {
            $this->assertStringStartsWith('[skip]', $line);
        }
    }

    public function testFailsWithoutBaseMaipoRow(): void
    {
        $this->expectException(RuntimeException::class);
        migrate_maipo_options($this->conn);
    }
}
