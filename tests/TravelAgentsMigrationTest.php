<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TravelAgentsMigrationTest extends TestCase
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
        $this->conn->query("DELETE FROM vendedores WHERE nombre_vendedor = 'TEST_LEGACY'");
    }

    private function rowCount(string $sql): int
    {
        return $this->conn->query($sql)->num_rows;
    }

    private function dropTravelAgentSchema(): void
    {
        foreach (['ta_code', 'ta_status', 'ta_applied_at', 'ta_approved_at'] as $col) {
            if ($this->rowCount("SHOW COLUMNS FROM vendedores LIKE '$col'") === 1) {
                $this->conn->query("ALTER TABLE vendedores DROP COLUMN $col");
            }
        }
        $this->conn->query('DROP TABLE IF EXISTS ta_code_attempts');
    }

    public function test_migration_is_idempotent_and_leaves_existing_rows_alone(): void
    {
        $this->dropTravelAgentSchema();
        $this->conn->query("INSERT INTO vendedores (nombre_vendedor, canal_venta) VALUES ('TEST_LEGACY', 'TA')");

        $first = migrate_travel_agents($this->conn);
        $second = migrate_travel_agents($this->conn);

        $this->assertNotEmpty($first);
        foreach ($first as $line) {
            $this->assertStringStartsWith('[ok]', $line);
        }
        $this->assertNotEmpty($second);
        foreach ($second as $line) {
            $this->assertStringStartsWith('[skip]', $line);
        }

        $this->assertSame(1, $this->rowCount("SHOW COLUMNS FROM vendedores LIKE 'ta_code'"));
        $this->assertSame(1, $this->rowCount("SHOW COLUMNS FROM vendedores LIKE 'ta_status'"));
        $this->assertSame(1, $this->rowCount("SHOW COLUMNS FROM vendedores LIKE 'ta_applied_at'"));
        $this->assertSame(1, $this->rowCount("SHOW COLUMNS FROM vendedores LIKE 'ta_approved_at'"));
        $this->assertSame(1, $this->rowCount("SHOW TABLES LIKE 'ta_code_attempts'"));
        $this->assertSame(1, $this->rowCount("SHOW INDEX FROM vendedores WHERE Key_name = 'uniq_ta_code' AND Non_unique = 0"));

        $row = $this->conn->query("SELECT ta_code, ta_status FROM vendedores WHERE nombre_vendedor = 'TEST_LEGACY'")->fetch_assoc();
        $this->assertNull($row['ta_code']);
        $this->assertNull($row['ta_status']);
    }
}
