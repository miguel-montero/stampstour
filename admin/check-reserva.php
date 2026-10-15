<?php
// Read-only diagnostic: shows how reporte_ventas.php classifies a booking.
// Temporary page - safe to delete once private-tour grouping is confirmed.
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$input = trim((string)($_GET['refs'] ?? ''));
$refs = array_values(array_unique(array_filter(preg_split('/[\s,]+/', $input) ?: [])));
$refs = array_slice($refs, 0, 20);

$rows = [];
if ($refs && $conn) {
    $placeholders = implode(',', array_fill(0, count($refs), '?'));
    $stmt = $conn->prepare("
        SELECT r.id_reserva, r.reference_id, r.estado, r.fecha_reserva, r.fecha_actividad,
               r.is_private, r.id_vendedor, v.nombre_vendedor, r.total_venta,
               r.id_experiencia, e.nombre, e.nombre_publico,
               CONCAT(t.nombre, ' ', t.apellido) AS titular
          FROM reservas r
          LEFT JOIN experiencias e ON e.id_experiencia = r.id_experiencia
          LEFT JOIN titulares   t ON t.id_titular     = r.id_titular
          LEFT JOIN vendedores  v ON v.id_vendedor    = r.id_vendedor
         WHERE r.reference_id IN ($placeholders)
    ");
    $stmt->bind_param(str_repeat('s', count($refs)), ...$refs);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[$row['reference_id']] = $row;
    }
    $stmt->close();
}

// Same rules as reporte_ventas.php
function report_table(array $r): string
{
    if ($r['estado'] !== 'realizado') {
        return 'Not in report (estado is not "realizado")';
    }
    if ((int)$r['id_vendedor'] === 11) {
        return 'Table 3: Vendedores externos';
    }
    if (($r['nombre_publico'] ?? '') === 'Custom Invoice' || (int)$r['is_private'] === 1) {
        return 'Table 2: Tours Privados';
    }
    return 'Table 1: Ventas WEB - Tours regulares';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Check Reservation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav(''); ?>
<div class="container py-3">
  <h2>Check reservation (read-only)</h2>
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-9">
      <input type="text" name="refs" class="form-control" value="<?= h($input) ?>"
             placeholder="STAMP_xxx, STAMP_yyy (comma or space separated)">
    </div>
    <div class="col-md-3"><button class="btn btn-primary w-100">Check</button></div>
  </form>

  <?php if ($refs): ?>
  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle">
      <thead class="table-dark">
        <tr>
          <th>Reference</th><th>Titular</th><th>Estado</th><th>Reserva</th><th>Actividad</th>
          <th>Experiencia (nombre / público)</th><th>is_private</th><th>Vendedor</th>
          <th class="text-end">Total</th><th>Report table</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($refs as $ref): $r = $rows[$ref] ?? null; ?>
        <?php if (!$r): ?>
          <tr><td><?= h($ref) ?></td><td colspan="9" class="text-danger">Not found</td></tr>
        <?php else: ?>
          <tr>
            <td><code><?= h($r['reference_id']) ?></code></td>
            <td><?= h($r['titular']) ?></td>
            <td><?= h($r['estado']) ?></td>
            <td><?= h($r['fecha_reserva']) ?></td>
            <td><?= h($r['fecha_actividad']) ?></td>
            <td><?= h($r['nombre']) ?> / <?= h($r['nombre_publico']) ?> (id <?= h($r['id_experiencia']) ?>)</td>
            <td class="fw-bold"><?= h($r['is_private']) ?></td>
            <td><?= h($r['id_vendedor']) ?> <?= h($r['nombre_vendedor']) ?></td>
            <td class="text-end"><?= h($r['total_venta']) ?></td>
            <td><?= h(report_table($r)) ?></td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
