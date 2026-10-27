<?php
// includes/migrations/travel_agents_2026_10_27.php
// Schema migration for the travel agent portal: adds ta_* columns to
// vendedores and creates ta_code_attempts (rate-limit log for code lookups).
// Idempotent; changes no data. Run via migrate_2026_10_27_travel_agents.php
// (web, logged in) or the CLI one-liner in the implementation plan.

function migrate_travel_agents(mysqli $conn): array
{
    $log = [];

    $columns = [
        'ta_code'        => 'CHAR(4) NULL',
        'ta_status'      => "ENUM('pending','active','inactive','rejected') NULL",
        'ta_applied_at'  => 'DATETIME NULL',
        'ta_approved_at' => 'DATETIME NULL',
    ];
    foreach ($columns as $name => $def) {
        $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendedores' AND COLUMN_NAME = ?");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $log[] = "[skip] vendedores.$name already exists";
            continue;
        }
        $conn->query("ALTER TABLE vendedores ADD COLUMN $name $def");
        $log[] = "[ok] added vendedores.$name";
    }

    $idx = $conn->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendedores' AND INDEX_NAME = 'uniq_ta_code'");
    if ($idx->num_rows > 0) {
        $log[] = '[skip] index uniq_ta_code already exists';
    } else {
        $conn->query('ALTER TABLE vendedores ADD UNIQUE KEY uniq_ta_code (ta_code)');
        $log[] = '[ok] added unique index uniq_ta_code';
    }

    $tbl = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ta_code_attempts'");
    if ($tbl->num_rows > 0) {
        $log[] = '[skip] table ta_code_attempts already exists';
    } else {
        $conn->query('CREATE TABLE ta_code_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL,
            KEY idx_ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $log[] = '[ok] created table ta_code_attempts';
    }

    return $log;
}
