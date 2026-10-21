<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';
require __DIR__ . '/../helpers.php';

/**
 * One-reservation Getnet diagnosis tool.
 *
 * admin/getnet-reconcile.php only ever checks 'pendiente' reservations with
 * a process_id created in the last 24 hours (see includes/reconcile_getnet.php) -
 * anything older silently falls outside that batch job forever. This page
 * looks up a single reservation by its STAMP reference code, queries Getnet
 * live regardless of age, and explains exactly why the batch tool would or
 * wouldn't have caught it. If Getnet says the payment actually resolved but
 * our estado still says 'pendiente', it offers a manual, password-gated
 * correction using the same guarded UPDATE the batch tool uses.
 */

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function moneyFormat($amount): string
{
    return number_format((float)$amount, 2, '.', '');
}

$statusMap = [
    'APPROVED' => 'realizado',
    'PENDING'  => 'pendiente',
    'REJECTED' => 'fallido',
    'FAILED'   => 'fallido',
    'EXPIRED'  => 'fallido',
    'REFUNDED' => 'refund',
];

$referenceId = trim($_POST['reference_id'] ?? ($_GET['ref'] ?? ''));
$referenceId = preg_replace('/\s+/', '', $referenceId);

$step = 'lookup';
$errorMessage = '';
$reservation = null;
$sessionError = '';
$normStatus = '';
$mappedEstado = '';
$batchEligible = true;
$batchReasons = [];
$mismatch = false;
$applyResult = '';
$applyError = '';

$action = $_POST['action'] ?? ($referenceId !== '' ? 'lookup' : '');

function loadReservationByReference(mysqli $conn, string $referenceId): ?array
{
    $stmt = $conn->prepare("
        SELECT id_reserva, reference_id, order_id, process_id, capture_id,
               estado, fecha_reserva, fecha_actividad, total_venta, updated_at
        FROM reservas
        WHERE reference_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $referenceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

if ($action === 'lookup' || $action === 'apply') {
    if ($referenceId === '') {
        $errorMessage = 'Missing STAMP reference code.';
    } elseif (stripos($referenceId, 'STAMP_') !== 0) {
        $errorMessage = 'Invalid ref format. Example: STAMP_ac7d8a8d6423c';
    } else {
        $reservation = loadReservationByReference($conn, $referenceId);

        if (!$reservation) {
            $errorMessage = "Reservation not found for ref: {$referenceId}";
        } else {
            $step = 'result';

            if ($reservation['estado'] !== 'pendiente') {
                $batchEligible = false;
                $batchReasons[] = "estado is '{$reservation['estado']}', not 'pendiente'";
            }
            if (empty($reservation['process_id'])) {
                $batchEligible = false;
                $batchReasons[] = 'process_id is empty - no Getnet session on record for this reservation';
            }
            $fechaReserva = $reservation['fecha_reserva'] ?? null;
            if ($fechaReserva !== null && $fechaReserva !== '') {
                $ageSeconds = time() - strtotime((string)$fechaReserva);
                if ($ageSeconds > 86400) {
                    $batchEligible = false;
                    $batchReasons[] = "fecha_reserva ({$fechaReserva}) is older than 1 day - outside the batch tool's lookback window";
                }
            }

            if (!empty($reservation['process_id'])) {
                try {
                    $session = getSessionInfo((int)$reservation['process_id']);

                    if (isset($session['ok']) && $session['ok'] === false) {
                        $sessionError = 'Getnet query failed: ' . json_encode($session, JSON_UNESCAPED_SLASHES);
                    } else {
                        $status = $session['status']['status'] ?? null;
                        $payment0 = $session['payment'][0] ?? null;
                        $paymentStatus = $payment0['status']['status'] ?? null;
                        $normStatus = strtoupper((string)($paymentStatus ?? $status ?? ''));
                        $mappedEstado = $statusMap[$normStatus] ?? '';

                        if ($mappedEstado !== '' && $mappedEstado !== $reservation['estado']) {
                            $mismatch = true;
                        }
                    }
                } catch (Throwable $e) {
                    $sessionError = 'Getnet session lookup threw: ' . $e->getMessage();
                }
            }
        }
    }
}

if ($action === 'apply' && $step === 'result' && $reservation !== null && isset($_POST['admin_password'])) {
    try {
        $adminPassword = (string)$_POST['admin_password'];
        if ($adminPassword === '') {
            throw new RuntimeException('Admin password is required to confirm this correction.');
        }

        $stmt = $conn->prepare('SELECT password FROM users WHERE username = ?');
        $stmt->bind_param('s', $_SESSION['username']);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$userRow || !password_verify($adminPassword, $userRow['password'])) {
            throw new RuntimeException('Incorrect admin password. Nothing was changed.');
        }

        if (!$mismatch || $mappedEstado === '') {
            throw new RuntimeException('Nothing to apply - no confirmed Getnet mismatch for this reference.');
        }
        if ($mappedEstado !== 'realizado' && $mappedEstado !== 'refund') {
            throw new RuntimeException('Refusing to apply - mapped status is not a terminal resolved state.');
        }

        $processId = (string)$reservation['process_id'];

        // Same guarded UPDATE as includes/reconcile_getnet.php - defends
        // against downgrading a row a real webhook already resolved.
        $upd = $conn->prepare("
            UPDATE reservas
            SET estado = CASE
                           WHEN estado IN ('realizado', 'refund') AND ? <> 'refund' THEN estado
                           ELSE ?
                         END,
                updated_at = NOW()
            WHERE reference_id = ?
              AND process_id = ?
            LIMIT 1
        ");
        $upd->bind_param('ssss', $mappedEstado, $mappedEstado, $referenceId, $processId);
        $upd->execute();
        $rowsAffected = $upd->affected_rows;
        $upd->close();

        if ($rowsAffected > 0) {
            error_log("getnet-diagnose: manually corrected ref={$referenceId} -> {$mappedEstado} (process_id={$processId}, admin={$_SESSION['username']})");
            $applyResult = "Corrected: estado updated to '{$mappedEstado}'.";
            $reservation['estado'] = $mappedEstado;
            $mismatch = false;
        } else {
            $applyResult = 'No rows updated - reference_id/process_id may have changed since lookup. Re-check and try again.';
        }
    } catch (Throwable $e) {
        $applyError = $e->getMessage();
    }
}

$active = 'getnet-diagnose';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Getnet Diagnose</title>
  <link href="/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('getnet-diagnose'); ?>
<div class="container pb-5">
  <h1 class="h4 mb-3">Getnet Diagnose</h1>
  <p class="text-muted">
    Look up one reservation by its STAMP reference code and check it live
    against Getnet, even if it's older than the 1-day window the automatic
    "Getnet Reconciliation" batch tool checks. Use this when a passenger
    shows you a receipt but the reservation still shows <code>pendiente</code>.
  </p>

  <?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger" style="white-space: pre-line;"><?= h($errorMessage) ?></div>
  <?php endif; ?>

  <form method="post" class="row g-2 align-items-end mb-4">
    <input type="hidden" name="action" value="lookup">
    <div class="col-auto">
      <label for="reference_id" class="form-label">STAMP reference code</label>
      <input
        type="text"
        id="reference_id"
        name="reference_id"
        class="form-control"
        placeholder="STAMP_ac7d8a8d6423c"
        value="<?= h($referenceId) ?>"
        required
      >
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary">Look up</button>
    </div>
  </form>

  <?php if ($step === 'result' && $reservation !== null): ?>

    <h2 class="h6">Reservation (database)</h2>
    <table class="table table-bordered table-sm w-auto mb-4">
      <tbody>
        <tr><th>Reference</th><td><?= h($reservation['reference_id']) ?></td></tr>
        <tr><th>Order ID</th><td><?= h($reservation['order_id']) ?></td></tr>
        <tr><th>Process ID (Getnet session)</th><td><?= h($reservation['process_id'] ?: '(empty)') ?></td></tr>
        <tr><th>Capture ID</th><td><?= h($reservation['capture_id']) ?></td></tr>
        <tr><th>Estado (current)</th><td><strong><?= h($reservation['estado']) ?></strong></td></tr>
        <tr><th>Total venta</th><td>USD <?= h(moneyFormat($reservation['total_venta'])) ?></td></tr>
        <tr><th>Fecha reserva</th><td><?= h($reservation['fecha_reserva']) ?></td></tr>
        <tr><th>Fecha actividad</th><td><?= h($reservation['fecha_actividad']) ?></td></tr>
        <tr><th>Last updated</th><td><?= h($reservation['updated_at']) ?></td></tr>
      </tbody>
    </table>

    <h2 class="h6">Would the automatic batch tool catch this?</h2>
    <?php if ($batchEligible): ?>
      <div class="alert alert-success">
        Yes - this reservation meets the batch tool's criteria (pendiente, has a process_id, created within the last day).
        If it's still stuck, the batch run may not have been triggered recently, or Getnet's API may be failing for it.
      </div>
    <?php else: ?>
      <div class="alert alert-warning">
        <strong>No</strong> - the automatic "Getnet Reconciliation" tool would not check this reservation, because:
        <ul class="mb-0">
          <?php foreach ($batchReasons as $reason): ?>
            <li><?= h($reason) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <h2 class="h6">Live Getnet session status</h2>
    <?php if (empty($reservation['process_id'])): ?>
      <div class="alert alert-secondary">No process_id on this reservation - nothing to check against Getnet directly.</div>
    <?php elseif ($sessionError !== ''): ?>
      <div class="alert alert-danger"><?= h($sessionError) ?></div>
    <?php else: ?>
      <table class="table table-bordered table-sm w-auto mb-3">
        <tbody>
          <tr><th>Getnet status (raw)</th><td><?= h($normStatus ?: '(empty/unrecognized)') ?></td></tr>
          <tr><th>Maps to estado</th><td><?= h($mappedEstado ?: '(no mapping - nothing safe to conclude)') ?></td></tr>
        </tbody>
      </table>

      <?php if ($mismatch && $applyResult === ''): ?>
        <div class="alert alert-danger">
          <strong>Mismatch:</strong> Getnet reports this payment resolved to
          <strong><?= h($mappedEstado) ?></strong>, but the reservation's estado is still
          <strong><?= h($reservation['estado']) ?></strong>.
        </div>

        <?php if ($mappedEstado === 'realizado' || $mappedEstado === 'refund'): ?>
          <?php if ($applyError !== ''): ?>
            <div class="alert alert-danger" style="white-space: pre-line;"><?= h($applyError) ?></div>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('Apply correction: set estado to &quot;<?= h($mappedEstado) ?>&quot; for <?= h($referenceId) ?>?');">
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="reference_id" value="<?= h($referenceId) ?>">
            <div class="mb-3">
              <label for="admin_password" class="form-label">Your admin password</label>
              <input
                type="password"
                id="admin_password"
                name="admin_password"
                class="form-control w-auto"
                autocomplete="current-password"
                required
              >
            </div>
            <button type="submit" class="btn btn-danger">Apply correction (estado &rarr; <?= h($mappedEstado) ?>)</button>
          </form>
        <?php endif; ?>
      <?php elseif ($applyResult !== ''): ?>
        <div class="alert alert-success"><?= h($applyResult) ?></div>
      <?php else: ?>
        <div class="alert alert-secondary">No mismatch - Getnet and the database agree.</div>
      <?php endif; ?>
    <?php endif; ?>

  <?php endif; ?>
</div>
</body>
</html>
