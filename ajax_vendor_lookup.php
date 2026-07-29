<?php
// /admin/ajax_vendor_lookup.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../db_config.php'; // Debe definir $conn (mysqli)

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo json_encode(['ok'=>false, 'msg'=>'ID de vendedor inválido']); 
  exit;
}

// Tu tabla 'vendedores' tiene id_vendedor y nombre_vendedor (según tu dump). 
// No hay 'codigo' ni 'activo'; consultamos por id. :contentReference[oaicite:0]{index=0}
$sql = "SELECT id_vendedor, nombre_vendedor FROM vendedores WHERE id_vendedor = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo json_encode(['ok'=>false, 'msg'=>'Error SQL (prepare)']); 
  exit;
}

$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
  echo json_encode(['ok'=>false, 'msg'=>'Error SQL (execute)']); 
  exit;
}

// Usamos bind_result para evitar dependencia de mysqlnd/get_result()
$stmt->bind_result($rid, $rnombre);
if ($stmt->fetch()) {
  echo json_encode([
    'ok' => true,
    'id_vendedor' => (int)$rid,
    'nombre' => (string)$rnombre
  ]);
} else {
  echo json_encode(['ok'=>false, 'msg'=>'Vendedor no encontrado']);
}
$stmt->close();
