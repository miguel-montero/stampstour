<?php
header('Content-Type: application/json; charset=utf-8');

// Incluye tu configuración de base de datos (mysqli) sin modificarla
require_once __DIR__ . '/../db_config.php';

// Obtenemos el código externo desde GET
$codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
if ($codigo === '') {
    echo json_encode([]);
    exit;
}

// La columna `codigo_externo` está definida en tu tabla `reservas` :contentReference[oaicite:0]{index=0}
$sql = "
  SELECT 
    r.id_reserva,
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
  WHERE r.reference_id = ?
    AND r.estado = 'realizado'
  LIMIT 1
";

if (! $stmt = $conn->prepare($sql)) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit;
}

$stmt->bind_param('s', $codigo);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc() ?: [];

echo json_encode($data);
