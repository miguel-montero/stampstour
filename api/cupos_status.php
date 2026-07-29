<?php
// /api/cupos_status.php
declare(strict_types=1);
header('Content-Type: application/json');

require __DIR__ . '/../../db_config.php';

$nombre = isset($_GET['nombre']) ? trim((string)$_GET['nombre']) : '';
$expId  = isset($_GET['exp_id']) ? (int)$_GET['exp_id'] : 0;

$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d', strtotime('+6 months'));

if ($nombre !== '') {
    $stmt = $conn->prepare("
        SELECT id_experiencia
        FROM experiencias
        WHERE LOWER(nombre) = LOWER(?) OR LOWER(COALESCE(nombre_publico,'')) = LOWER(?)
        LIMIT 1
    ");
    $stmt->bind_param('ss', $nombre, $nombre);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $expId = (int)$row['id_experiencia'];
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Experience not found for nombre', 'nombre' => $nombre]);
        exit;
    }
}

if ($expId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing experience identifier (nombre or exp_id)']);
    exit;
}

$stmt = $conn->prepare("
  SELECT fecha, capacidad
  FROM cupos
  WHERE id_experiencia = ?
    AND fecha BETWEEN ? AND ?
  ORDER BY fecha ASC
");
$stmt->bind_param('iss', $expId, $from, $to);
$stmt->execute();
$res = $stmt->get_result();

$unavailable = [];
while ($row = $res->fetch_assoc()) {
  if ((int)$row['capacidad'] <= 0) $unavailable[] = $row['fecha'];
}

echo json_encode([
  'exp_id'      => $expId,
  'from'        => $from,
  'to'          => $to,
  'unavailable' => $unavailable
]);
