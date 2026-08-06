<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

// Diagnostic: queries PayPal's live Capture API directly, to independently
// confirm payments' real status (not just re-reading our own stored
// webhook data). Admin-only. Read-only - never mutates anything, on our
// side or PayPal's.
//
// Two modes:
//   ?capture_id=XYZ  -> single capture, raw JSON (original behavior)
//   (no params)      -> batch: finds every reservas row recently touched
//                       by reprocessing (estado IN realizado/refund,
//                       updated recently) and checks each one against
//                       PayPal live, rendered as an HTML comparison table.

require __DIR__ . '/../../db_config.php';

$paypalConfig = require __DIR__ . '/../../paypal_config.php';
$mode = ($paypalConfig['mode'] === 'live') ? 'live' : 'sandbox';
$env = $paypalConfig[$mode];
$base = ($mode === 'live') ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

function paypal_capture_check_token(array $env, string $base): string
{
    $ch = curl_init("$base/v1/oauth2/token");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $env['client_id'] . ':' . $env['client_secret'],
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        throw new RuntimeException('OAuth curl: ' . curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("OAuth HTTP $code");
    }
    $json = json_decode($res, true);
    if (empty($json['access_token'])) {
        throw new RuntimeException('No access token');
    }
    return $json['access_token'];
}

function paypal_capture_check_fetch(string $base, string $token, string $captureId): array
{
    $ch = curl_init("$base/v2/payments/captures/" . rawurlencode($captureId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$resp, true);
    return [
        'http_code' => $code,
        'status' => $data['status'] ?? null,
        'amount' => $data['amount']['value'] ?? null,
        'currency' => $data['amount']['currency_code'] ?? null,
        'create_time' => $data['create_time'] ?? null,
    ];
}

// ---- Single-capture JSON mode (unchanged from original) ----
if (!empty($_GET['capture_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $captureId = trim((string)$_GET['capture_id']);

    try {
        $token = paypal_capture_check_token($env, $base);
    } catch (Throwable $e) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'OAuth failed', 'detail' => $e->getMessage()]);
        exit;
    }

    $result = paypal_capture_check_fetch($base, $token, $captureId);
    echo json_encode(array_merge(['ok' => true, 'capture_id' => $captureId], $result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Batch HTML mode ----
$stmt = $conn->prepare("
    SELECT reference_id, estado, capture_id, order_id, total_venta, moneda, fecha_reserva, fecha_actividad, updated_at
    FROM reservas
    WHERE capture_id IS NOT NULL
      AND estado IN ('realizado', 'refund')
      AND updated_at >= NOW() - INTERVAL 1 DAY
    ORDER BY updated_at DESC
");
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$results = [];
$tokenError = null;
if (!empty($rows)) {
    try {
        $token = paypal_capture_check_token($env, $base);
        foreach ($rows as $row) {
            $check = paypal_capture_check_fetch($base, $token, (string)$row['capture_id']);
            $ourAmount = number_format((float)$row['total_venta'], 2, '.', '');
            $paypalAmount = $check['amount'] !== null ? number_format((float)$check['amount'], 2, '.', '') : null;
            $statusMatches = ($row['estado'] === 'realizado' && $check['status'] === 'COMPLETED')
                           || ($row['estado'] === 'refund' && $check['status'] === 'REFUNDED');
            $amountMatches = ($paypalAmount !== null && $ourAmount === $paypalAmount);
            $tourDatePassed = strtotime((string)$row['fecha_actividad']) < strtotime('today');
            $results[] = [
                'reference_id' => $row['reference_id'],
                'our_estado' => $row['estado'],
                'our_amount' => $ourAmount,
                'moneda' => $row['moneda'],
                'fecha_reserva' => $row['fecha_reserva'],
                'fecha_actividad' => $row['fecha_actividad'],
                'tour_date_passed' => $tourDatePassed,
                'capture_id' => $row['capture_id'],
                'paypal_status' => $check['status'],
                'paypal_amount' => $paypalAmount,
                'paypal_create_time' => $check['create_time'] ?? null,
                'http_code' => $check['http_code'],
                'status_matches' => $statusMatches,
                'amount_matches' => $amountMatches,
            ];
        }
    } catch (Throwable $e) {
        $tokenError = $e->getMessage();
    }
}

$mismatchCount = count(array_filter($results, fn($r) => !$r['status_matches'] || !$r['amount_matches']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PayPal Capture Check</title>
  <link href="/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav(''); ?>
<div class="container">
  <h1 class="h4 mb-3">PayPal Capture Check</h1>
  <p class="text-muted">
    Independently checks every reservation updated to <code>realizado</code>/<code>refund</code>
    in the last 24 hours directly against PayPal's live Capture API - confirming our
    stored status actually matches what PayPal itself reports, not just re-reading our own data.
  </p>

  <?php if ($tokenError !== null): ?>
    <div class="alert alert-danger">Could not reach PayPal: <?= htmlspecialchars($tokenError, ENT_QUOTES, 'UTF-8') ?></div>
  <?php elseif (empty($results)): ?>
    <p class="text-muted">No reservations updated to realizado/refund with a capture_id in the last 24 hours.</p>
  <?php else: ?>
    <?php $pastTourCount = count(array_filter($results, fn($r) => $r['tour_date_passed'])); ?>
    <p>
      <strong>Checked:</strong> <?= count($results) ?> &nbsp;
      <strong>Mismatches:</strong>
      <span class="<?= $mismatchCount > 0 ? 'text-danger fw-bold' : 'text-success' ?>"><?= $mismatchCount ?></span> &nbsp;
      <strong>Tour date already passed:</strong>
      <span class="<?= $pastTourCount > 0 ? 'text-warning fw-bold' : '' ?>"><?= $pastTourCount ?></span>
    </p>
    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Purchased</th>
            <th>Tour Date</th>
            <th>Our Status</th>
            <th>Our Amount</th>
            <th>PayPal Status</th>
            <th>PayPal Amount</th>
            <th>PayPal Paid At</th>
            <th>Match</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $r): ?>
            <tr class="<?= (!$r['status_matches'] || !$r['amount_matches']) ? 'table-danger' : ($r['tour_date_passed'] ? 'table-warning' : '') ?>">
              <td><?= htmlspecialchars($r['reference_id'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($r['fecha_reserva'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($r['fecha_actividad'], ENT_QUOTES, 'UTF-8') ?><?= $r['tour_date_passed'] ? ' ⚠️ past' : '' ?></td>
              <td><?= htmlspecialchars($r['our_estado'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($r['our_amount'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string)$r['moneda'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$r['paypal_status'], ENT_QUOTES, 'UTF-8') ?> (HTTP <?= (int)$r['http_code'] ?>)</td>
              <td><?= htmlspecialchars((string)$r['paypal_amount'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($r['paypal_create_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= ($r['status_matches'] && $r['amount_matches']) ? '✅' : '❌' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
