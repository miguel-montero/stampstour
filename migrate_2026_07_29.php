<?php
// One-time migration: adds the columns paypal_webhook.php needs on `reservas`,
// and creates the missing `blog_posts` table. Safe to run more than once —
// each step checks whether it's already done before touching the schema.
//
// Visit this file once in a browser while logged into /login.php, confirm
// the output says [ok] or [skip] for every line, then DELETE THIS FILE from
// the server. It can run schema changes, so it shouldn't stay deployed.

require __DIR__ . '/admin/_auth.php';
require __DIR__ . '/../db_config.php';

header('Content-Type: text/plain; charset=utf-8');

function columnExists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function tableExists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

echo "Stamps Tour migration -- " . date('Y-m-d H:i:s') . "\n\n";

$columns = [
    'monto_pagado' => "ALTER TABLE `reservas` ADD COLUMN `monto_pagado` DECIMAL(10,2) DEFAULT NULL AFTER `total_venta`",
    'moneda'       => "ALTER TABLE `reservas` ADD COLUMN `moneda` VARCHAR(3) DEFAULT NULL AFTER `monto_pagado`",
    'refund_id'    => "ALTER TABLE `reservas` ADD COLUMN `refund_id` VARCHAR(50) DEFAULT NULL AFTER `moneda`",
    'refund_monto' => "ALTER TABLE `reservas` ADD COLUMN `refund_monto` DECIMAL(10,2) DEFAULT NULL AFTER `refund_id`",
];

foreach ($columns as $col => $sql) {
    if (columnExists($conn, 'reservas', $col)) {
        echo "[skip] reservas.$col already exists\n";
        continue;
    }
    echo $conn->query($sql)
        ? "[ok]   added reservas.$col\n"
        : "[FAIL] reservas.$col: " . $conn->error . "\n";
}

echo "\n";

if (tableExists($conn, 'blog_posts')) {
    echo "[skip] blog_posts table already exists\n";
} else {
    $sql = "CREATE TABLE `blog_posts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `slug` VARCHAR(255) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `excerpt` TEXT DEFAULT NULL,
        `content` LONGTEXT NOT NULL,
        `featured_image` VARCHAR(255) DEFAULT NULL,
        `meta_title` VARCHAR(255) DEFAULT NULL,
        `meta_description` VARCHAR(500) DEFAULT NULL,
        `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
        `published_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `slug` (`slug`),
        KEY `status_published_at` (`status`, `published_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    echo $conn->query($sql)
        ? "[ok]   created blog_posts table\n"
        : "[FAIL] blog_posts: " . $conn->error . "\n";
}

echo "\nDone. Delete this file from the server now.\n";
