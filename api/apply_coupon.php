<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['code'], $data['total'])) {
    echo json_encode(['valid'=>false,'message'=>'Datos incompletos']);
    exit;
}
$code  = $data['code'];
$total = (float)$data['total'];

// Conexión a base de datos usando configuración central
require_once __DIR__ . '/../../db_config.php';  // carga $conn desde root/db_config.php :contentReference[oaicite:0]{index=0}
$mysqli = $conn;

// Comprobación de error de conexión (opcional, db_config.php ya hace die en error)
if ($mysqli->connect_error) {
    echo json_encode(['valid'=>false,'message'=>'Error de conexión: '.$mysqli->connect_error]);
    exit;
}

// Consulta insensible a mayúsculas
$stmt = $mysqli->prepare("
    SELECT porcentaje, id_vendedor
    FROM cupones
    WHERE LOWER(nombre) = LOWER(?)
");
if (!$stmt) {
    echo json_encode(['valid'=>false,'message'=>'Error prepare: '.$mysqli->error]);
    exit;
}
$stmt->bind_param('s', $code);
$stmt->execute();
$stmt->bind_result($porcentaje, $id_vendedor);

if ($stmt->fetch()) {
    $stmt->close();
    $discounted = round($total * (1 - $porcentaje/100), 2);
    echo json_encode([
        'valid'           => true,
        'porcentaje'      => $porcentaje,
        'discountedTotal' => $discounted,
        'id_vendedor'     => $id_vendedor
    ]);
} else {
    echo json_encode(['valid'=>false,'message'=>'Cupón inválido o no existe']);
}
?>
