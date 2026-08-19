<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/../../paypal_config.php';
require __DIR__ . '/../../db_config.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function moneyFormat($amount): string
{
    return number_format((float)$amount, 2, '.', '');
}

function cleanAmount($value): float
{
    $value = trim((string)$value);
    $value = str_replace(',', '.', $value);
    $value = preg_replace('/[^0-9.]/', '', $value);

    if ($value === '' || !is_numeric($value)) {
        return 0.0;
    }

    return round((float)$value, 2);
}

function getPayPalAccessToken(string $base, string $clientId, string $secret): string
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($base, '/') . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $clientId . ':' . $secret,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US',
        ],
        CURLOPT_TIMEOUT => 45,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("PayPal token request failed: {$curlErr}");
    }

    $data = json_decode($response, true);

    if ($httpCode >= 400 || !isset($data['access_token'])) {
        throw new RuntimeException("PayPal token error ({$httpCode}): {$response}");
    }

    return $data['access_token'];
}

function refundCapture(string $base, string $token, string $captureId, string $amount, string $referenceId, string $refundType): array
{
    $note = ($refundType === 'partial')
        ? "Partial refund for {$referenceId}"
        : "Refund for {$referenceId}";

    $payload = [
        'amount' => [
            'value' => $amount,
            'currency_code' => 'USD',
        ],
        'note_to_payer' => $note,
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($base, '/') . '/v2/payments/captures/' . rawurlencode($captureId) . '/refund',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT => 45,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("PayPal refund request failed: {$curlErr}");
    }

    $data = json_decode($response, true);

    if ($httpCode >= 400 || !is_array($data)) {
        throw new RuntimeException("PayPal refund error ({$httpCode}): {$response}");
    }

    return $data;
}

function findReservationByReference(PDO $pdo, string $referenceId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            r.id_reserva, r.reference_id, r.order_id, r.capture_id,
            r.total_venta, r.estado, r.fecha_reserva, r.fecha_actividad,
            r.adultos, r.ninos, r.infantes,
            CONCAT(t.nombre, ' ', t.apellido) AS nombre_titular,
            t.email, t.telefono,
            e.nombre_publico AS experiencia,
            COALESCE(h.nombre_hotel, r.hotel_manual) AS hotel
        FROM reservas r
        JOIN titulares t ON r.id_titular = t.id_titular
        LEFT JOIN experiencias e ON r.id_experiencia = e.id_experiencia
        LEFT JOIN hoteles h ON r.id_hotel = h.id_hotel
        WHERE r.reference_id = :reference_id
        LIMIT 1
    ");
    $stmt->execute(['reference_id' => $referenceId]);
    $reserva = $stmt->fetch();

    return $reserva ?: null;
}

function alreadyRefundedTotal(PDO $pdo, string $captureId): float
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS refunded_total
        FROM refunds
        WHERE capture_id = :capture_id
          AND status IN ('COMPLETED', 'PENDING')
    ");
    $stmt->execute(['capture_id' => $captureId]);
    $row = $stmt->fetch();

    return round((float)($row['refunded_total'] ?? 0), 2);
}

$pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$step = 'lookup';
$errorMessage = '';
$success = false;
$result = null;
$reservation = null;
$remainingAmount = 0.0;
$alreadyRefunded = 0.0;

$referenceId = trim($_POST['reference_id'] ?? ($_GET['ref'] ?? ''));
$referenceId = preg_replace('/\s+/', '', $referenceId);
$refundType = trim($_POST['refund_type'] ?? 'full');
$partialAmountInput = trim($_POST['partial_amount'] ?? '');

$action = $_POST['action'] ?? ($referenceId !== '' ? 'lookup' : '');

if ($action === 'lookup' || $action === 'confirm') {
    try {
        if ($referenceId === '') {
            throw new RuntimeException('Missing STAMP reference code.');
        }
        if (stripos($referenceId, 'STAMP_') !== 0) {
            throw new RuntimeException('Invalid ref format. Example: STAMP_ac7d8a8d6423c');
        }

        $reservation = findReservationByReference($pdo, $referenceId);
        if (!$reservation) {
            throw new RuntimeException("Reservation not found for ref: {$referenceId}");
        }

        $captureId = trim((string)($reservation['capture_id'] ?? ''));
        if ($captureId === '') {
            throw new RuntimeException('Reservation found, but capture_id is empty.');
        }

        $totalVentaNumber = round((float)$reservation['total_venta'], 2);
        if ($totalVentaNumber <= 0) {
            throw new RuntimeException('Reservation total_venta is invalid.');
        }

        $alreadyRefunded = alreadyRefundedTotal($pdo, $captureId);
        $remainingAmount = round($totalVentaNumber - $alreadyRefunded, 2);

        if ($remainingAmount <= 0) {
            throw new RuntimeException(
                "This capture already appears to be fully refunded.\n" .
                "Capture ID: {$captureId}\n" .
                "Total sale: USD " . moneyFormat($totalVentaNumber) . "\n" .
                'Already refunded: USD ' . moneyFormat($alreadyRefunded)
            );
        }

        $step = 'confirm';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        $step = 'lookup';
        $reservation = null;
    }
}

if ($action === 'confirm' && $step === 'confirm' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    try {
        $adminPassword = (string)$_POST['admin_password'];

        if ($adminPassword === '') {
            throw new RuntimeException('Admin password is required to confirm this refund.');
        }

        $stmt = $pdo->prepare('SELECT password FROM users WHERE username = :username');
        $stmt->execute(['username' => $_SESSION['username']]);
        $userRow = $stmt->fetch();

        if (!$userRow || !password_verify($adminPassword, $userRow['password'])) {
            throw new RuntimeException('Incorrect admin password. Refund was not processed.');
        }

        // Re-fetch fresh state right before charging, so a stale page can't
        // trigger a refund against data that has since changed.
        $reservation = findReservationByReference($pdo, $referenceId);
        if (!$reservation) {
            throw new RuntimeException("Reservation not found for ref: {$referenceId}");
        }

        $idReserva  = (int)$reservation['id_reserva'];
        $orderId    = (string)($reservation['order_id'] ?? '');
        $captureId  = trim((string)($reservation['capture_id'] ?? ''));
        $totalVentaNumber = round((float)$reservation['total_venta'], 2);
        $totalVenta = moneyFormat($totalVentaNumber);
        $estado     = (string)$reservation['estado'];

        if ($captureId === '') {
            throw new RuntimeException('Reservation found, but capture_id is empty.');
        }

        $alreadyRefunded = alreadyRefundedTotal($pdo, $captureId);
        $remainingAmount = round($totalVentaNumber - $alreadyRefunded, 2);

        if ($remainingAmount <= 0) {
            throw new RuntimeException(
                "This capture already appears to be fully refunded.\n" .
                "Capture ID: {$captureId}\n" .
                "Total sale: USD {$totalVenta}\n" .
                'Already refunded: USD ' . moneyFormat($alreadyRefunded)
            );
        }

        if ($refundType !== 'full' && $refundType !== 'partial') {
            throw new RuntimeException('Invalid refund type.');
        }

        if ($refundType === 'partial') {
            $refundAmountNumber = cleanAmount($partialAmountInput);

            if ($refundAmountNumber <= 0) {
                throw new RuntimeException('Partial refund amount must be greater than 0.');
            }
            if ($refundAmountNumber > $remainingAmount) {
                throw new RuntimeException(
                    "Partial refund cannot be greater than the remaining amount.\n" .
                    'Remaining amount: USD ' . moneyFormat($remainingAmount)
                );
            }
        } else {
            $refundAmountNumber = $remainingAmount;
        }

        $refundAmountToSend = moneyFormat($refundAmountNumber);

        if (!isset($PP_CLIENT_ID, $PP_SECRET, $PP_BASE)) {
            throw new RuntimeException('paypal_config.php is missing $PP_CLIENT_ID, $PP_SECRET, or $PP_BASE');
        }

        $token = getPayPalAccessToken($PP_BASE, $PP_CLIENT_ID, $PP_SECRET);
        $refund = refundCapture($PP_BASE, $token, $captureId, $refundAmountToSend, $referenceId, $refundType);

        $refundId     = (string)($refund['id'] ?? '');
        $refundStatus = (string)($refund['status'] ?? 'UNKNOWN');
        $refundAmount = (string)($refund['amount']['value'] ?? $refundAmountToSend);
        $currency     = (string)($refund['amount']['currency_code'] ?? 'USD');

        if ($refundId === '') {
            throw new RuntimeException('Refund response missing refund ID.');
        }

        $newRefundedTotal = round($alreadyRefunded + (float)$refundAmount, 2);
        $remainingAfterRefund = round(max(0, $totalVentaNumber - $newRefundedTotal), 2);
        $isFullRefundNow = ($remainingAfterRefund <= 0.01);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO refunds (id_reserva, capture_id, refund_id, currency, amount, status, note)
            VALUES (:id_reserva, :capture_id, :refund_id, :currency, :amount, :status, :note)
        ");
        $stmt->execute([
            'id_reserva' => $idReserva,
            'capture_id' => $captureId,
            'refund_id'  => $refundId,
            'currency'   => $currency,
            'amount'     => $refundAmount,
            'status'     => $refundStatus,
            'note'       => ucfirst($refundType) . " refund triggered by reference_id {$referenceId} (admin: {$_SESSION['username']})",
        ]);

        if ($isFullRefundNow) {
            $stmt = $pdo->prepare("
                UPDATE reservas
                SET estado = 'refund'
                WHERE id_reserva = :id_reserva
                LIMIT 1
            ");
            $stmt->execute(['id_reserva' => $idReserva]);
            $newEstado = 'refund';
        } else {
            $newEstado = $estado;
        }

        $pdo->commit();

        $success = true;
        $step = 'done';

        $result = [
            'reference_id' => $referenceId,
            'id_reserva' => $idReserva,
            'order_id' => $orderId,
            'capture_id' => $captureId,
            'refund_id' => $refundId,
            'refund_type' => $refundType,
            'refund_amount' => moneyFormat((float)$refundAmount),
            'currency' => $currency,
            'status' => $refundStatus,
            'total_venta' => $totalVenta,
            'already_refunded_before' => moneyFormat($alreadyRefunded),
            'new_refunded_total' => moneyFormat($newRefundedTotal),
            'remaining_after_refund' => moneyFormat($remainingAfterRefund),
            'estado' => $newEstado,
        ];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
        // Fall back to the confirm screen (not lookup) so the admin doesn't
        // have to retype the reference code after a wrong password/amount.
        $step = ($reservation !== null) ? 'confirm' : 'lookup';
    }
}

$active = 'refund-by-reference';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Refund by Reference</title>
  <link href="/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('refund-by-reference'); ?>
<div class="container pb-5">
  <h1 class="h4 mb-3">Refund by Reference</h1>
  <p class="text-muted">
    Look up a reservation by its STAMP reference code, review who it belongs
    to, then confirm with your admin password to issue a PayPal refund.
  </p>

  <?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger" style="white-space: pre-line;"><?= h($errorMessage) ?></div>
  <?php endif; ?>

  <?php if ($step === 'lookup' || $step === ''): ?>
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
  <?php endif; ?>

  <?php if ($step === 'confirm' && $reservation !== null): ?>
    <table class="table table-bordered table-sm w-auto">
      <tbody>
        <tr><th>Reference</th><td><?= h($reservation['reference_id']) ?></td></tr>
        <tr><th>Customer</th><td><?= h($reservation['nombre_titular']) ?></td></tr>
        <tr><th>Email</th><td><?= h($reservation['email']) ?></td></tr>
        <tr><th>Phone</th><td><?= h($reservation['telefono']) ?></td></tr>
        <tr><th>Tour</th><td><?= h($reservation['experiencia']) ?></td></tr>
        <tr><th>Activity date</th><td><?= h($reservation['fecha_actividad']) ?></td></tr>
        <tr><th>Booking date</th><td><?= h($reservation['fecha_reserva']) ?></td></tr>
        <tr><th>Pax</th><td><?= (int)$reservation['adultos'] ?> adults, <?= (int)$reservation['ninos'] ?> children, <?= (int)$reservation['infantes'] ?> infants</td></tr>
        <tr><th>Hotel</th><td><?= h($reservation['hotel']) ?></td></tr>
        <tr><th>Order / Capture ID</th><td><?= h($reservation['order_id']) ?> / <?= h($reservation['capture_id']) ?></td></tr>
        <tr><th>Reservation status</th><td><?= h($reservation['estado']) ?></td></tr>
        <tr><th>Total sale</th><td>USD <?= h(moneyFormat($reservation['total_venta'])) ?></td></tr>
        <tr><th>Already refunded</th><td>USD <?= h(moneyFormat($alreadyRefunded)) ?></td></tr>
        <tr><th>Remaining refundable</th><td>USD <?= h(moneyFormat($remainingAmount)) ?></td></tr>
      </tbody>
    </table>

    <form method="post" onsubmit="return confirmRefund();" class="mt-3">
      <input type="hidden" name="action" value="confirm">
      <input type="hidden" name="reference_id" value="<?= h($referenceId) ?>">

      <div class="mb-2">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="refund_type" id="refund_full" value="full" <?= $refundType === 'partial' ? '' : 'checked' ?> onclick="togglePartialAmount();">
          <label class="form-check-label" for="refund_full">Full / remaining refund (USD <?= h(moneyFormat($remainingAmount)) ?>)</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="refund_type" id="refund_partial" value="partial" <?= $refundType === 'partial' ? 'checked' : '' ?> onclick="togglePartialAmount();">
          <label class="form-check-label" for="refund_partial">Partial refund</label>
        </div>
      </div>

      <div class="mb-3" id="partial_amount_box">
        <label for="partial_amount" class="form-label">Partial amount in USD</label>
        <input
          type="number"
          step="0.01"
          min="0.01"
          max="<?= h(moneyFormat($remainingAmount)) ?>"
          id="partial_amount"
          name="partial_amount"
          class="form-control w-auto"
          placeholder="Example: 50.00"
          value="<?= h($partialAmountInput) ?>"
        >
      </div>

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

      <button type="submit" class="btn btn-danger">Confirm refund</button>
      <a href="/admin/refund-by-reference.php" class="btn btn-outline-secondary">Cancel</a>
    </form>

    <script>
    function togglePartialAmount() {
      var selected = document.querySelector('input[name="refund_type"]:checked');
      var box = document.getElementById('partial_amount_box');
      var input = document.getElementById('partial_amount');

      if (selected && selected.value === 'partial') {
        box.classList.remove('d-none');
        input.required = true;
      } else {
        box.classList.add('d-none');
        input.required = false;
      }
    }

    function confirmRefund() {
      var ref = document.querySelector('input[name="reference_id"]').value;
      var selected = document.querySelector('input[name="refund_type"]:checked');
      var amount = document.getElementById('partial_amount').value;

      var message = 'Confirm PayPal refund for ' + ref + '?';
      if (selected && selected.value === 'partial') {
        message += '\n\nPartial refund: USD ' + amount;
      } else {
        message += '\n\nFull / remaining refund.';
      }

      return confirm(message);
    }

    togglePartialAmount();
    </script>
  <?php endif; ?>

  <?php if ($step === 'done' && $success && $result !== null): ?>
    <div class="alert alert-success">Refund completed successfully.</div>
    <table class="table table-bordered table-sm w-auto">
      <tbody>
        <tr><th>Reference ID</th><td><?= h($result['reference_id']) ?></td></tr>
        <tr><th>Reservation ID</th><td><?= h($result['id_reserva']) ?></td></tr>
        <tr><th>Order ID</th><td><?= h($result['order_id']) ?></td></tr>
        <tr><th>Capture ID</th><td><?= h($result['capture_id']) ?></td></tr>
        <tr><th>Refund ID</th><td><?= h($result['refund_id']) ?></td></tr>
        <tr><th>Refund type</th><td><?= h(ucfirst($result['refund_type'])) ?></td></tr>
        <tr><th>Refund amount</th><td><?= h($result['refund_amount']) ?> <?= h($result['currency']) ?></td></tr>
        <tr><th>PayPal status</th><td><?= h($result['status']) ?></td></tr>
        <tr><th>Total sale</th><td><?= h($result['total_venta']) ?> <?= h($result['currency']) ?></td></tr>
        <tr><th>Already refunded before</th><td><?= h($result['already_refunded_before']) ?> <?= h($result['currency']) ?></td></tr>
        <tr><th>New refunded total</th><td><?= h($result['new_refunded_total']) ?> <?= h($result['currency']) ?></td></tr>
        <tr><th>Remaining after refund</th><td><?= h($result['remaining_after_refund']) ?> <?= h($result['currency']) ?></td></tr>
        <tr><th>Reservation status</th><td><?= h($result['estado']) ?></td></tr>
      </tbody>
    </table>
    <a href="/admin/refund-by-reference.php" class="btn btn-primary">Look up another reference</a>
  <?php endif; ?>
</div>
</body>
</html>
