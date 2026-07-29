<?php
// reserva_dia.php
require_once __DIR__ . '/../db_config.php';

// 1) Recogemos parámetros
$day   = isset($_GET['day'])   ? intval($_GET['day'])   : date('j');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year  = isset($_GET['year'])  ? intval($_GET['year'])  : date('Y');
$fecha = sprintf('%04d-%02d-%02d', $year, $month, $day);

// 2) Consulta totales por actividad
$estado = 'realizado';
$sql = "
  SELECT
    e.nombre                             AS actividad,
    SUM(r.adultos + r.ninos + r.infantes) AS total_pasajeros
  FROM reservas r
  JOIN experiencias e ON r.id_experiencia = e.id_experiencia
  WHERE r.estado = ?
    AND DATE(r.fecha_actividad) = ?
  GROUP BY e.nombre
  ORDER BY total_pasajeros DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $estado, $fecha);
$stmt->execute();
$res = $stmt->get_result();
$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = $row;
}
$stmt->close();
$conn->close();

// 3) Respuesta JSON para AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

// 4) Si no es AJAX, mostrar interfaz completa
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pasajeros por Actividad</title>
  <style>
    body { font-family: sans-serif; padding:2rem; }
    form { margin-bottom:1.5rem; }
    select, button { font-size:1rem; padding:.3rem; margin-right:.5rem; }
    table { border-collapse: collapse; width:100%; max-width:600px; margin-bottom:1rem; }
    th, td { border:1px solid #ccc; padding:.5rem; text-align:left; }
    th { background:#f0f0f0; }
    .modal-overlay { position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);display:none;align-items:center;justify-content:center; }
    .modal { background:#fff;padding:1rem;border-radius:5px;max-width:90%;max-height:80%;overflow:auto;position:relative;box-shadow:0 2px 10px rgba(0,0,0,0.2); }
    .modal .close { position:absolute;top:.5rem;right:.5rem;background:none;border:none;font-size:1.5rem;cursor:pointer; }
    .reserva-badge { display:block;margin:2px 0;padding:2px 4px;font-size:.75rem;border-radius:4px;background:#007bff;color:#fff; }
  </style>
</head>
<body>
  <h1>Pasajeros por Actividad: <?= htmlspecialchars($fecha) ?></h1>
  <!-- Podrías incluir tu formulario aquí si lo deseas -->
  <?php if ($results): ?>
    <table>
      <thead><tr><th>Actividad</th><th>Total Pasajeros</th></tr></thead>
      <tbody>
        <?php foreach ($results as $r): ?>
          <tr><td><?= htmlspecialchars($r['actividad']) ?></td><td><?= intval($r['total_pasajeros']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No hay reservas para <?= htmlspecialchars($fecha) ?>.</p>
  <?php endif; ?>

  <!-- Tu modal y detalle_reservas.php podrían integrarse aquí si lo deseas -->
</body>
</html>
