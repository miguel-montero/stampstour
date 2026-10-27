<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../includes/travel_agents.php';

if (!$conn) {
    echo json_encode(['ok' => false]);
    exit;
}

$code = isset($_POST['vendor_code']) ? trim($_POST['vendor_code']) : '';
if ($code === '' || !ctype_digit($code)) {
    echo json_encode(['ok' => false]);
    exit;
}

$vendor = ta_legacy_vendor_lookup($conn, (int)$code);
if ($vendor) {
    echo json_encode([
        'ok'              => true,
        'id_vendedor'     => $vendor['id_vendedor'],
        'nombre_vendedor' => $vendor['nombre_vendedor']
    ]);
} else {
    echo json_encode(['ok' => false]);
}
