<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/admin/_auth.php';
require_once __DIR__ . '/../paypal_config.php';
require_once __DIR__ . '/../db_config.php';

$success = false;
$errorMessage = '';
$result = null;
$customerMessage = '';

$referenceId = trim($_POST['reference_id'] ?? ($_GET['ref'] ?? ''));
$refundType = trim($_POST['refund_type'] ?? 'full');
$partialAmountInput = trim($_POST['partial_amount'] ?? '');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $referenceId = trim($_POST['reference_id'] ?? '');
        $referenceId = preg_replace('/\s+/', '', $referenceId);

        if ($referenceId === '') {
            throw new RuntimeException("Missing STAMP reference code.");
        }

        if (stripos($referenceId, 'STAMP_') !== 0) {
            throw new RuntimeException("Invalid ref format. Example: STAMP_ac7d8a8d6423c");
        }

        $refundType = trim($_POST['refund_type'] ?? '');

        if ($refundType !== 'full' && $refundType !== 'partial') {
            throw new RuntimeException("Invalid refund type.");
        }

        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (Throwable $e) {
            throw new RuntimeException("DB connection failed: " . $e->getMessage());
        }

        $stmt = $pdo->prepare("
            SELECT id_reserva, reference_id, order_id, capture_id, total_venta, estado
            FROM reservas
            WHERE reference_id = :reference_id
            LIMIT 1
        ");
        $stmt->execute(['reference_id' => $referenceId]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            throw new RuntimeException("Reservation not found for ref: {$referenceId}");
        }

        $idReserva  = (int)$reserva['id_reserva'];
        $orderId    = (string)($reserva['order_id'] ?? '');
        $captureId  = trim((string)($reserva['capture_id'] ?? ''));
        $totalVentaNumber = round((float)$reserva['total_venta'], 2);
        $totalVenta = moneyFormat($totalVentaNumber);
        $estado     = (string)$reserva['estado'];

        if ($captureId === '') {
            throw new RuntimeException("Reservation found, but capture_id is empty.");
        }

        if ($totalVentaNumber <= 0) {
            throw new RuntimeException("Reservation total_venta is invalid.");
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS refunded_total
            FROM refunds
            WHERE capture_id = :capture_id
              AND status IN ('COMPLETED', 'PENDING')
        ");
        $stmt->execute(['capture_id' => $captureId]);
        $refundSummary = $stmt->fetch();

        $alreadyRefunded = round((float)($refundSummary['refunded_total'] ?? 0), 2);
        $remainingAmount = round($totalVentaNumber - $alreadyRefunded, 2);

        if ($remainingAmount <= 0) {
            throw new RuntimeException(
                "This capture already appears to be fully refunded.\n" .
                "Capture ID: {$captureId}\n" .
                "Total sale: USD {$totalVenta}\n" .
                "Already refunded: USD " . moneyFormat($alreadyRefunded)
            );
        }

        if ($refundType === 'partial') {
            $refundAmountNumber = cleanAmount($_POST['partial_amount'] ?? '');

            if ($refundAmountNumber <= 0) {
                throw new RuntimeException("Partial refund amount must be greater than 0.");
            }

            if ($refundAmountNumber > $remainingAmount) {
                throw new RuntimeException(
                    "Partial refund cannot be greater than the remaining amount.\n" .
                    "Remaining amount: USD " . moneyFormat($remainingAmount)
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

        $refund = refundCapture(
            $PP_BASE,
            $token,
            $captureId,
            $refundAmountToSend,
            $referenceId,
            $refundType
        );

        $refundId     = (string)($refund['id'] ?? '');
        $refundStatus = (string)($refund['status'] ?? 'UNKNOWN');
        $refundAmount = (string)($refund['amount']['value'] ?? $refundAmountToSend);
        $currency     = (string)($refund['amount']['currency_code'] ?? 'USD');

        if ($refundId === '') {
            throw new RuntimeException("Refund response missing refund ID.");
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
            'note'       => ucfirst($refundType) . " refund triggered by reference_id {$referenceId}",
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

        $customerMessage =
            "Hello,\n\n" .
            "We have processed the refund for your booking with Stamp's Tour.\n\n" .
            "Booking code: {$referenceId}\n" .
            "Refund amount: {$currency} " . moneyFormat((float)$refundAmount) . "\n" .
            "PayPal refund transaction code: {$refundId}\n\n" .
            "You can use this code to check the transaction directly with PayPal. " .
            "Please note that depending on your bank or card issuer, it may take a few business days for the funds to appear in your account.\n\n" .
            "Kind regards,\n" .
            "Stamp's Tour";

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PayPal Refund by Reference</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            color: #222;
            margin: 0;
            padding: 30px;
        }

        .box {
            max-width: 760px;
            margin: 0 auto 20px auto;
            background: #fff;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        h1, h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 14px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        textarea {
            min-height: 190px;
        }

        .radio-line {
            margin-top: 10px;
        }

        .radio-line label {
            display: inline-block;
            font-weight: normal;
            margin-right: 20px;
            margin-top: 0;
        }

        button {
            margin-top: 18px;
            padding: 11px 16px;
            background: #111;
            color: #fff;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .error {
            background: #ffecec;
            border: 1px solid #ffb5b5;
            color: #900;
            padding: 12px;
            border-radius: 6px;
            white-space: pre-wrap;
            margin-bottom: 15px;
        }

        .success {
            background: #e9ffe9;
            border: 1px solid #a6dfa6;
            color: #145214;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th, td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        th {
            width: 240px;
        }

        .hidden {
            display: none;
        }

        .note {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
    </style>
</head>

<body>

<div class="box">
    <h1>PayPal Refund by Reference</h1>

    <?php if ($errorMessage !== ''): ?>
        <div class="error"><?= h($errorMessage) ?></div>
    <?php endif; ?>

    <?php if ($success && is_array($result)): ?>
        <div class="success">Refund completed successfully.</div>

        <table>
            <tr>
                <th>Reference ID</th>
                <td><?= h($result['reference_id']) ?></td>
            </tr>
            <tr>
                <th>Reservation ID</th>
                <td><?= h($result['id_reserva']) ?></td>
            </tr>
            <tr>
                <th>Order ID</th>
                <td><?= h($result['order_id']) ?></td>
            </tr>
            <tr>
                <th>Capture ID</th>
                <td><?= h($result['capture_id']) ?></td>
            </tr>
            <tr>
                <th>Refund ID</th>
                <td><?= h($result['refund_id']) ?></td>
            </tr>
            <tr>
                <th>Refund type</th>
                <td><?= h($result['refund_type']) ?></td>
            </tr>
            <tr>
                <th>Refund amount</th>
                <td><?= h($result['refund_amount']) ?> <?= h($result['currency']) ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><?= h($result['status']) ?></td>
            </tr>
            <tr>
                <th>Total sale</th>
                <td><?= h($result['total_venta']) ?> <?= h($result['currency']) ?></td>
            </tr>
            <tr>
                <th>Already refunded before</th>
                <td><?= h($result['already_refunded_before']) ?> <?= h($result['currency']) ?></td>
            </tr>
            <tr>
                <th>New refunded total</th>
                <td><?= h($result['new_refunded_total']) ?> <?= h($result['currency']) ?></td>
            </tr>
            <tr>
                <th>Remaining after refund</th>
                <td><?= h($result['remaining_after_refund']) ?> <?= h($result['currency']) ?></td>
            </tr>
            <tr>
                <th>Reservation status</th>
                <td><?= h($result['estado']) ?></td>
            </tr>
        </table>
    <?php endif; ?>

    <form method="post" onsubmit="return confirmRefund();">
        <label for="reference_id">STAMP code</label>
        <input
            type="text"
            id="reference_id"
            name="reference_id"
            placeholder="STAMP_ac7d8a8d6423c"
            value="<?= h($referenceId) ?>"
            required
        >

        <div class="note">
            You can also use ?ref=STAMP_xxxxx to prefill this field.
        </div>

        <label>Refund type</label>

        <div class="radio-line">
            <label>
                <input
                    type="radio"
                    name="refund_type"
                    value="full"
                    <?= $refundType === 'full' ? 'checked' : '' ?>
                    onclick="togglePartialAmount();"
                >
                Full / remaining refund
            </label>

            <label>
                <input
                    type="radio"
                    name="refund_type"
                    value="partial"
                    <?= $refundType === 'partial' ? 'checked' : '' ?>
                    onclick="togglePartialAmount();"
                >
                Partial refund
            </label>
        </div>

        <div id="partial_amount_box">
            <label for="partial_amount">Partial amount in USD</label>
            <input
                type="number"
                step="0.01"
                min="0.01"
                id="partial_amount"
                name="partial_amount"
                placeholder="Example: 50.00"
                value="<?= h($partialAmountInput) ?>"
            >
        </div>

        <button type="submit">Process refund</button>
    </form>
</div>

<?php if ($success && $customerMessage !== ''): ?>
    <div class="box">
        <h2>Message to send to customer</h2>
        <textarea id="customer_message" readonly><?= h($customerMessage) ?></textarea>
        <button type="button" onclick="copyCustomerMessage();">Copy message</button>
    </div>
<?php endif; ?>

<script>
function togglePartialAmount() {
    var selected = document.querySelector('input[name="refund_type"]:checked');
    var box = document.getElementById('partial_amount_box');
    var input = document.getElementById('partial_amount');

    if (selected && selected.value === 'partial') {
        box.classList.remove('hidden');
        input.required = true;
    } else {
        box.classList.add('hidden');
        input.required = false;
        input.value = '';
    }
}

function confirmRefund() {
    var ref = document.getElementById('reference_id').value;
    var selected = document.querySelector('input[name="refund_type"]:checked');
    var amount = document.getElementById('partial_amount').value;

    if (!selected) {
        alert('Please select refund type.');
        return false;
    }

    var message = 'Confirm PayPal refund for ' + ref + '?';

    if (selected.value === 'partial') {
        message += '\n\nPartial refund: USD ' + amount;
    } else {
        message += '\n\nFull / remaining refund.';
    }

    return confirm(message);
}

function copyCustomerMessage() {
    var textarea = document.getElementById('customer_message');
    textarea.select();
    document.execCommand('copy');
    alert('Customer message copied.');
}

togglePartialAmount();
</script>

</body>
</html>
