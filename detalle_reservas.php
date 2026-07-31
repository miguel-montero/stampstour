<?php
// detalle_reservas.php
require_once __DIR__ . '/admin/_auth.php';
?>
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- (Optional) Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<?php
require_once __DIR__ . '/../db_config.php';

if (!isset($_GET['date'])) {
    http_response_code(400);
    echo "Parámetro fecha faltante";
    exit;
}

$fecha = $_GET['date'];
$estado = 'realizado';

$sql = "
  SELECT
    e.nombre AS experiencia,
    CONCAT(t.nombre, ' ', t.apellido) AS titular,
    r.adultos AS adultos,
    r.ninos AS ninos,
    r.infantes AS infantes,
    h.nombre_hotel AS hotel,
    h.direccion AS direccion,
    h.comuna AS comuna,
    t.email AS email,
    t.telefono AS telefono,
    r.reference_id AS codigo_externo,
    r.airport_pickup  AS pickup,
    r.airport_dropoff AS dropoff
  FROM reservas r
  JOIN experiencias e ON r.id_experiencia = e.id_experiencia
  JOIN titulares    t ON r.id_titular     = t.id_titular
  LEFT JOIN hoteles h ON r.id_hotel       = h.id_hotel
  WHERE r.estado = ?
    AND DATE(r.fecha_actividad) = ?
  ORDER BY e.nombre, titular
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $estado, $fecha);
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Reservas del <?= htmlspecialchars($fecha) ?></h1>
    <span class="badge text-bg-primary"><?= number_format($res->num_rows) ?> registro(s)</span>
  </div>

  <?php if ($res->num_rows === 0): ?>
    <div class="alert alert-warning" role="alert">
      No hay reservas para <?= htmlspecialchars($fecha) ?>.
    </div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Experiencia</th>
                <th>Titular</th>
                <th class="text-center">Ad.</th>
                <th class="text-center">Niños</th>
                <th class="text-center">Inf.</th>
                <th>Hotel / Dirección</th>
                <th class="text-nowrap">Email</th>
                <th class="text-nowrap">Teléfono</th>
                <th class="text-center">Pick-up Aerop.</th>
                <th class="text-center">Drop-off Aerop.</th>
                <th class="text-nowrap">Código Ext.</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $res->fetch_assoc()): ?>
                <?php
                  $hotelName = $row['hotel'] ?? '';
                  $addrFull  = trim(
                    (($row['direccion'] ?? '') ? $row['direccion'] : '') .
                    ((($row['direccion'] ?? '') && ($row['comuna'] ?? '')) ? ', ' : '') .
                    (($row['comuna'] ?? '') ? $row['comuna'] : '')
                  );
                ?>
                <tr>
                  <td><?= htmlspecialchars($row['experiencia']) ?></td>
                  <td><?= htmlspecialchars($row['titular']) ?></td>
                  <td class="text-center"><?= (int)$row['adultos'] ?></td>
                  <td class="text-center"><?= (int)$row['ninos'] ?></td>
                  <td class="text-center"><?= (int)$row['infantes'] ?></td>
                  <td>
                    <?php if ($hotelName): ?>
                      <div><?= htmlspecialchars($hotelName) ?></div>
                    <?php else: ?>
                      <div class="text-muted fst-italic">—</div>
                    <?php endif; ?>
                    <div class="small text-muted"><?= $addrFull ? htmlspecialchars($addrFull) : '—' ?></div>
                  </td>
                  <td class="text-nowrap">
                    <a href="mailto:<?= htmlspecialchars($row['email']) ?>">
                      <?= htmlspecialchars($row['email']) ?>
                    </a>
                  </td>
                  <td class="text-nowrap">
                    <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $row['telefono'])) ?>">
                      <?= htmlspecialchars($row['telefono']) ?>
                    </a>
                  </td>
                  <td class="text-center">
                    <?php if ((int)$row['pickup'] === 1): ?>
                      <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i> Sí</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary"><i class="bi bi-x-circle me-1"></i> No</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php if ((int)$row['dropoff'] === 1): ?>
                      <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i> Sí</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary"><i class="bi bi-x-circle me-1"></i> No</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-nowrap">
                    <code class="small"><?= htmlspecialchars($row['codigo_externo']) ?></code>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-end gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
          <i class="bi bi-printer me-1"></i> Imprimir
        </button>
        <button class="btn btn-outline-primary btn-sm" onclick="exportCSV()">
          <i class="bi bi-download me-1"></i> Exportar CSV
        </button>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
function exportCSV() {
  const table = document.querySelector('table');
  if (!table) return;
  const rows = [];
  table.querySelectorAll('tr').forEach(tr => {
    const cols = Array.from(tr.querySelectorAll('th,td'))
      .map(td => `"${td.innerText.replace(/"/g, '""')}"`);
    rows.push(cols.join(','));
  });
  const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'reservas_<?= preg_replace("/[^0-9\-]/", "", $fecha) ?>.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
</script>

<?php
$stmt->close();
$conn->close();
