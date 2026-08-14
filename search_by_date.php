<?php
require_once __DIR__ . '/admin/_auth.php';
header('Content-Type: application/json; charset=utf-8');

// Incluye tu configuración de base de datos (mysqli) sin modificarla
require_once __DIR__ . '/../db_config.php';

// Obtiene y valida las fechas desde GET
$start = isset($_GET['start']) ? trim($_GET['start']) : '';
$end   = isset($_GET['end'])   ? trim($_GET['end'])   : '';

// Función simple para validar formato YYYY-MM-DD
function is_valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

if (!is_valid_date($start) || !is_valid_date($end)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de fecha inválido. Usa YYYY-MM-DD.']);
    exit;
}

// Consulta reservaciones entre las dos fechas
$sql = "
  SELECT
    r.id_reserva,
    r.codigo_externo,
    CONCAT(t.nombre, ' ', t.apellido) AS nombre_titular,
    e.nombre AS experiencia,
    r.fecha_reserva,
    r.fecha_actividad,
    r.adultos,
    r.ninos,
    r.infantes,
    CASE WHEN r.airport_pickup = 1 THEN 'Sí' ELSE 'No' END AS airport_pickup,
    COALESCE(h.nombre_hotel, r.hotel_manual) AS hotel,
    t.email AS correo_electronico,
    t.telefono AS telefono,
    r.total_venta AS total_pagado,
    v.nombre_vendedor AS nombre_vendedor
  FROM reservas r
    JOIN titulares t ON r.id_titular   = t.id_titular
    LEFT JOIN experiencias e ON r.id_experiencia = e.id_experiencia
    LEFT JOIN hoteles       h ON r.id_hotel       = h.id_hotel
    LEFT JOIN vendedores    v ON r.id_vendedor    = v.id_vendedor
  WHERE r.fecha_reserva BETWEEN ? AND ?
    AND r.estado = 'realizado'
  ORDER BY r.fecha_actividad DESC
";

if (! $stmt = $conn->prepare($sql)) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit;
}

// Vincula parámetros y ejecuta
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Devuelve el arreglo JSON
echo json_encode($data);
