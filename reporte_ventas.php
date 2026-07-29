<?php
// reportes_ventas.php
require_once __DIR__ . '/../db_config.php';

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-t');

$CUSTOM_EXPR = "e.nombre_publico = 'Custom Invoice'";
$JOYCE_ONLY  = "v.id_vendedor = 11";
$NOT_JOYCE   = "(v.id_vendedor IS NULL OR v.id_vendedor <> 11)";

$whereBase   = "r.fecha_reserva BETWEEN '{$start_date}' AND '{$end_date}' AND r.estado = 'realizado'";

$SELECT_COMMON = "
    e.id_experiencia,
    e.nombre_publico AS experiencia,
    r.reference_id,
    r.fecha_reserva,
    r.fecha_actividad,
    CONCAT(t.nombre, ' ', t.apellido) AS titular,
    r.adultos,
    r.ninos,
    r.infantes,
    (r.adultos + r.ninos + r.infantes) AS total_pasajeros,
    CASE WHEN IFNULL(r.airport_pickup,0)  = 1 THEN 'Sí' ELSE 'No' END AS airport_pickup,
    CASE WHEN IFNULL(r.airport_dropoff,0) = 1 THEN 'Sí' ELSE 'No' END AS airport_dropoff,
    v.nombre_vendedor,
    r.total_venta
";
$FROM_JOINS = "
FROM reservas r
LEFT JOIN experiencias e ON e.id_experiencia = r.id_experiencia
LEFT JOIN titulares   t ON t.id_titular     = r.id_titular
LEFT JOIN vendedores  v ON v.id_vendedor    = r.id_vendedor
";
$ORDER_BY = " ORDER BY e.id_experiencia, r.fecha_reserva, r.id_reserva";

/** ---- Tabla 1: TODO EXCEPTO Custom Invoice (EXCLUYE Joyce pero MANTIENE NULL) ---- */
$query_no_custom = "
SELECT $SELECT_COMMON
$FROM_JOINS
WHERE $whereBase AND NOT ($CUSTOM_EXPR) AND $NOT_JOYCE
$ORDER_BY";
$result_no_custom = $conn->query($query_no_custom);

$queryTotal_no_custom = "
SELECT
  SUM(r.total_venta) AS total_venta,
  SUM(r.adultos)     AS total_adultos,
  SUM(r.ninos)       AS total_ninos,
  SUM(r.infantes)    AS total_infantes
$FROM_JOINS
WHERE $whereBase AND NOT ($CUSTOM_EXPR) AND $NOT_JOYCE";
$totalRow_no_custom = $conn->query($queryTotal_no_custom)->fetch_assoc();

/** ---- Tabla 2: SOLO Custom Invoice (EXCLUYE Joyce pero MANTIENE NULL) ---- */
$query_custom = "
SELECT $SELECT_COMMON
$FROM_JOINS
WHERE $whereBase AND ($CUSTOM_EXPR) AND $NOT_JOYCE
$ORDER_BY";
$result_custom = $conn->query($query_custom);

$queryTotal_custom = "
SELECT
  SUM(r.total_venta) AS total_venta,
  SUM(r.adultos)     AS total_adultos,
  SUM(r.ninos)       AS total_ninos,
  SUM(r.infantes)    AS total_infantes
$FROM_JOINS
WHERE $whereBase AND ($CUSTOM_EXPR) AND $NOT_JOYCE";
$totalRow_custom = $conn->query($queryTotal_custom)->fetch_assoc();

/** ---- Tabla 3: Vendedores externos (Joyce = ID 11) ---- */
$query_joyce = "
SELECT $SELECT_COMMON
$FROM_JOINS
WHERE $whereBase AND ($JOYCE_ONLY)
$ORDER_BY";
$result_joyce = $conn->query($query_joyce);

$queryTotal_joyce = "
SELECT
  SUM(r.total_venta) AS total_venta,
  SUM(r.adultos)     AS total_adultos,
  SUM(r.ninos)       AS total_ninos,
  SUM(r.infantes)    AS total_infantes
$FROM_JOINS
WHERE $whereBase AND ($JOYCE_ONLY)";
$totalRow_joyce = $conn->query($queryTotal_joyce)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Reporte de Ventas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    .card { box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,.05); }
    th, td { vertical-align: middle; }
    .table thead th { white-space: nowrap; }
  </style>
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
  <div class="container">
    <span class="navbar-brand fw-semibold">Reporte de Ventas</span>
    <span class="badge text-bg-primary">Bootstrap</span>
  </div>
</nav>

<div class="container py-4">
  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Desde</label>
          <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">Filtrar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla 1: Todas excepto Custom Invoice (excluye Joyce, incluye NULL) -->
  <div class="card mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <span>Ventas WEB - Tours regulares</span>
      <span class="badge text-bg-light">Rango: <?= htmlspecialchars($start_date) ?> a <?= htmlspecialchars($end_date) ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th>Experiencia</th>
              <th>Referencia</th>
              <th>Reserva</th>
              <th>Actividad</th>
              <th>Titular</th>
              <th class="text-end">Adultos</th>
              <th class="text-end">Niños</th>
              <th class="text-end">Infantes</th>
              <th class="text-end">Total Pax</th>
              <th>Pickup</th>
              <th>Dropoff</th>
              <th class="text-end">Total Venta</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($row = $result_no_custom->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['experiencia']) ?></td>
              <td><span class="text-monospace"><?= htmlspecialchars($row['reference_id']) ?></span></td>
              <td><?= htmlspecialchars($row['fecha_reserva']) ?></td>
              <td><?= htmlspecialchars($row['fecha_actividad']) ?></td>
              <td><?= htmlspecialchars($row['titular']) ?></td>
              <td class="text-end"><?= (int)$row['adultos'] ?></td>
              <td class="text-end"><?= (int)$row['ninos'] ?></td>
              <td class="text-end"><?= (int)$row['infantes'] ?></td>
              <td class="text-end"><?= (int)$row['total_pasajeros'] ?></td>
              <td>
                <?= ($row['airport_pickup'] === 'Sí')
                    ? '<span class="badge text-bg-success">Sí</span>'
                    : '<span class="badge text-bg-secondary">No</span>' ?>
              </td>
              <td>
                <?= ($row['airport_dropoff'] === 'Sí')
                    ? '<span class="badge text-bg-success">Sí</span>'
                    : '<span class="badge text-bg-secondary">No</span>' ?>
              </td>
              <td class="text-end fw-semibold">$<?= number_format((float)$row['total_venta'],0,',','.') ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
          <tfoot class="table-secondary fw-bold">
            <tr>
              <td colspan="5" class="text-end">TOTAL</td>
              <td class="text-end"><?= (int)$totalRow_no_custom['total_adultos'] ?></td>
              <td class="text-end"><?= (int)$totalRow_no_custom['total_ninos'] ?></td>
              <td class="text-end"><?= (int)$totalRow_no_custom['total_infantes'] ?></td>
              <td class="text-end">
                <?= (int)($totalRow_no_custom['total_adultos'] + $totalRow_no_custom['total_ninos'] + $totalRow_no_custom['total_infantes']) ?>
              </td>
              <td colspan="2"></td>
              <td class="text-end">$<?= number_format((float)$totalRow_no_custom['total_venta'],0,',','.') ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Tabla 2: Solo Custom Invoice (excluye Joyce, incluye NULL) -->
  <div class="card mb-4">
    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
      <span>Tours Privados y tickets de otras actividades</span>
      <span class="badge text-bg-light">Rango: <?= htmlspecialchars($start_date) ?> a <?= htmlspecialchars($end_date) ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th>Experiencia</th>
              <th>Referencia</th>
              <th>Reserva</th>
              <th>Actividad</th>
              <th>Titular</th>
              <th class="text-end">Adultos</th>
              <th class="text-end">Niños</th>
              <th class="text-end">Infantes</th>
              <th class="text-end">Total Pax</th>
              <th>Pickup</th>
              <th>Dropoff</th>
              <th>Vendedor</th>
              <th class="text-end">Total Venta</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($row = $result_custom->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['experiencia']) ?></td>
              <td><span class="text-monospace"><?= htmlspecialchars($row['reference_id']) ?></span></td>
              <td><?= htmlspecialchars($row['fecha_reserva']) ?></td>
              <td><?= htmlspecialchars($row['fecha_actividad']) ?></td>
              <td><?= htmlspecialchars($row['titular']) ?></td>
              <td class="text-end"><?= (int)$row['adultos'] ?></td>
              <td class="text-end"><?= (int)$row['ninos'] ?></td>
              <td class="text-end"><?= (int)$row['infantes'] ?></td>
              <td class="text-end"><?= (int)$row['total_pasajeros'] ?></td>
              <td>
                <?= ($row['airport_pickup'] === 'Sí')
                    ? '<span class="badge text-bg-success">Sí</span>'
                    : '<span class="badge text-bg-secondary">No</span>' ?>
              </td>
              <td>
                <?= ($row['airport_dropoff'] === 'Sí')
                    ? '<span class="badge text-bg-success">Sí</span>'
                    : '<span class="badge text-bg-secondary">No</span>' ?>
              </td>
              <td><?= htmlspecialchars($row['nombre_vendedor']) ?></td>
              <td class="text-end fw-semibold">$<?= number_format((float)$row['total_venta'],0,',','.') ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
          <tfoot class="table-secondary fw-bold">
            <tr>
              <td colspan="5" class="text-end">TOTAL</td>
              <td class="text-end"><?= (int)$totalRow_custom['total_adultos'] ?></td>
              <td class="text-end"><?= (int)$totalRow_custom['total_ninos'] ?></td>
              <td class="text-end"><?= (int)$totalRow_custom['total_infantes'] ?></td>
              <td class="text-end">
                <?= (int)($totalRow_custom['total_adultos'] + $totalRow_custom['total_ninos'] + $totalRow_custom['total_infantes']) ?>
              </td>
              <td colspan="3"></td>
              <td class="text-end">$<?= number_format((float)$totalRow_custom['total_venta'],0,',','.') ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Tabla 3: Vendedores externos (Joyce = ID 11) -->
  <div class="card mb-4">
    <div class="card-header bg-warning d-flex justify-content-between align-items-center">
      <span>Vendedores externos</span>
      <span class="badge text-bg-dark">Rango: <?= htmlspecialchars($start_date) ?> a <?= htmlspecialchars($end_date) ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th>Experiencia</th>
              <th>Referencia</th>
              <th>Reserva</th>
              <th>Actividad</th>
              <th>Titular</th>
              <th class="text-end">Adultos</th>
              <th class="text-end">Niños</th>
              <th class="text-end">Infantes</th>
              <th class="text-end">Total Pax</th>
              <th>Pickup</th>
              <th>Dropoff</th>
              <th>Vendedor</th>
              <th class="text-end">Total Venta</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($row = $result_joyce->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['experiencia']) ?></td>
              <td><span class="text-monospace"><?= htmlspecialchars($row['reference_id']) ?></span></td>
              <td><?= htmlspecialchars($row['fecha_reserva']) ?></td>
              <td><?= htmlspecialchars($row['fecha_actividad']) ?></td>
              <td><?= htmlspecialchars($row['titular']) ?></td>
              <td class="text-end"><?= (int)$row['adultos'] ?></td>
              <td class="text-end"><?= (int)$row['ninos'] ?></td>
              <td class="text-end"><?= (int)$row['infantes'] ?></td>
              <td class="text-end"><?= (int)$row['total_pasajeros'] ?></td>
              <td>
                <?= ($row['airport_pickup'] === 'Sí')
                    ? '<span class="badge text-bg-success">Sí</span>'
                    : '<span class="badge text-bg-secondary">No</span>' ?>
              </td>
              <td>
                <?= ($row['airport_dropoff'] === 'Sí')
                    ? '<span class="badge text-bg-success">Sí</span>'
                    : '<span class="badge text-bg-secondary">No</span>' ?>
              </td>
              <td><?= htmlspecialchars($row['nombre_vendedor']) ?></td>
              <td class="text-end fw-semibold">$<?= number_format((float)$row['total_venta'],0,',','.') ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
          <tfoot class="table-secondary fw-bold">
            <tr>
              <td colspan="5" class="text-end">TOTAL</td>
              <td class="text-end"><?= (int)$totalRow_joyce['total_adultos'] ?></td>
              <td class="text-end"><?= (int)$totalRow_joyce['total_ninos'] ?></td>
              <td class="text-end"><?= (int)$totalRow_joyce['total_infantes'] ?></td>
              <td class="text-end">
                <?= (int)($totalRow_joyce['total_adultos'] + $totalRow_joyce['total_ninos'] + $totalRow_joyce['total_infantes']) ?>
              </td>
              <td colspan="3"></td>
              <td class="text-end">$<?= number_format((float)$totalRow_joyce['total_venta'],0,',','.') ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

</div>
</body>
</html>
