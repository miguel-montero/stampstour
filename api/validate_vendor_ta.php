<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../../db_config.php';

if (!$conn) {
    echo json_encode(['ok' => false]);
    exit;
}

$code = isset($_POST['vendor_code']) ? trim($_POST['vendor_code']) : '';
if ($code === '' || !ctype_digit($code)) {
    echo json_encode(['ok' => false]);
    exit;
}

$id = (int)$code;
$stmt = $conn->prepare("SELECT id_vendedor, nombre_vendedor, canal_venta FROM vendedores WHERE id_vendedor = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['ok' => false]);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($id_vendedor, $nombre_vendedor, $canal_venta);
if ($stmt->fetch()) {
    if ($canal_venta === 'TA') {
        echo json_encode([
            'ok'              => true,
            'id_vendedor'     => (int)$id_vendedor,
            'nombre_vendedor' => $nombre_vendedor
        ]);
    } else {
        // Vendor found but not Travel Agent channel
        echo json_encode(['ok' => false]);
    }
} else {
    // Vendor code not found
    echo json_encode(['ok' => false]);
}
$stmt->close();
