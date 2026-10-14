<?php
// includes/migrations/maipo_options_2026_10_13.php
// Data-only migration for the Maipo tour options: repurposes the existing
// 'Maipo' row as Premium and adds MAIPO_CLASSIC / MAIPO_CLASSIC_LUNCH.
// Idempotent. Run via migrate_2026_10_13_maipo_options.php (web, logged in)
// or the CLI one-liner in the implementation plan.

require_once __DIR__ . '/../tours/maipo_options.php';

const MAIPO_MIGRATION_PRICES = [
    'classic'       => ['adult' => 110.00, 'child' => 50.00, 'infant' => 0.00],
    'classic-lunch' => ['adult' => 129.00, 'child' => 50.00, 'infant' => 0.00],
    'premium'       => ['adult' => 139.00, 'child' => 50.00, 'infant' => 0.00],
];

function migrate_maipo_options(mysqli $conn): array
{
    $base = $conn->query("SELECT precio_concierge, TAR FROM experiencias WHERE nombre = 'Maipo' LIMIT 1")->fetch_assoc();
    if (!$base) {
        throw new RuntimeException("Base 'Maipo' row not found in experiencias - nothing migrated.");
    }
    $concierge = (float)$base['precio_concierge'];
    $tar = $base['TAR'] !== null ? (float)$base['TAR'] : null;

    $log = [];
    foreach (maipo_options() as $slug => $opt) {
        $price = MAIPO_MIGRATION_PRICES[$slug];
        $name = $opt['exp_name'];
        $public = $opt['public_name'];

        $stmt = $conn->prepare('SELECT nombre_publico, precio_adulto, precio_nino, precio_infante FROM experiencias WHERE nombre = ? LIMIT 1');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $same = $row['nombre_publico'] === $public
                && (float)$row['precio_adulto'] === $price['adult']
                && (float)$row['precio_nino'] === $price['child']
                && (float)$row['precio_infante'] === $price['infant'];
            if ($same) {
                $log[] = "[skip] $name already up to date";
                continue;
            }
            $stmt = $conn->prepare('UPDATE experiencias SET nombre_publico = ?, precio_adulto = ?, precio_nino = ?, precio_infante = ? WHERE nombre = ?');
            $stmt->bind_param('sddds', $public, $price['adult'], $price['child'], $price['infant'], $name);
            $stmt->execute();
            $stmt->close();
            $log[] = "[ok] updated $name: '$public' adult {$price['adult']}";
            continue;
        }

        $stmt = $conn->prepare('INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante, precio_concierge, TAR) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssddddd', $name, $public, $price['adult'], $price['child'], $price['infant'], $concierge, $tar);
        $stmt->execute();
        $stmt->close();
        $log[] = "[ok] inserted $name: '$public' adult {$price['adult']}";
    }

    return $log;
}
