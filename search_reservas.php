<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db_config.php';  // same-dir include

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
if ($term === '') {
    echo json_encode([]);
    exit;
}

$like = "%{$term}%";
$sql = "
  SELECT 
    r.id_reserva,
    r.codigo_externo,                                 -- ← NEW
    CONCAT(t.nombre, ' ', t.apellido) AS nombre_titular,
    e.nombre AS experiencia,
    r.fecha_reserva,
    r.fecha_actividad,
    r.adultos,
    r.ninos,
    r.infantes,
    CASE WHEN r.airport_pickup = 1 THEN 'Sí' ELSE 'No' END AS airport_pickup,
    h.nombre_hotel AS hotel,
    t.email AS correo_electronico,
    t.telefono AS telefono,
    r.total_venta AS total_pagado,
    v.nombre_vendedor AS nombre_vendedor
  FROM reservas r
    JOIN titulares t ON r.id_titular = t.id_titular
    LEFT JOIN experiencias e ON r.id_experiencia = e.id_experiencia
    LEFT JOIN hoteles h       ON r.id_hotel       = h.id_hotel
    LEFT JOIN vendedores v    ON r.id_vendedor   = v.id_vendedor
  WHERE (
      t.nombre LIKE ?
   OR t.apellido LIKE ?
   OR CONCAT(t.nombre, ' ', t.apellido) LIKE ?
  )
    AND r.estado = 'realizado'
  ORDER BY r.fecha_actividad DESC
  LIMIT 20
";

if (! $stmt = $conn->prepare($sql)) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit;
}
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
