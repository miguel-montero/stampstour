<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/admin/_auth.php';
require_once __DIR__ . '/../paypal_config.php';
require_once __DIR__ . '/../db_config.php';

$referenceId = trim($_GET['ref'] ?? '');

if ($referenceId === '') {
    http_response_code(400);
    exit("Missing ref. Example: ?ref=STAMP_ac7d8a8d6423c\n");
}

if (stripos($referenceId, 'STAMP_') !== 0) {
    http_response_code(400);
    exit("Invalid ref format.\n");
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
    http_response_code(500);
    exit("DB connection failed: " . $e->getMessage() . "\n");
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
    http_response_code(404);
    exit("Reservation not found for ref: {$referenceId}\n");
}

$idReserva  = (int)$reserva['id_reserva'];
$orderId    = (string)($reserva['order_id'] ?? '');
$captureId  = trim((string)($reserva['capture_id'] ?? ''));
$totalVenta = number_format((float)$reserva['total_venta'], 2, '.', '');
$estado     = (string)$reserva['estado'];

if ($captureId === '') {
    http_response_code(422);
    exit("Reservation found, but capture_id is empty.\n");
}

// Prevent double refund
$stmt = $pdo->prepare("
    SELECT id_refund, refund_id, status, amount, created_at
    FROM refunds
    WHERE capture_id = :capture_id
    ORDER BY id_refund DESC
    LIMIT 1
");
$stmt->execute(['capture_id' => $captureId]);
$existingRefund = $stmt->fetch();

if ($existingRefund) {
    http_response_code(409);
    exit(
        "This capture already has a refund record.\n" .
        "Capture ID: {$captureId}\n" .
        "Refund ID: {$existingRefund['refund_id']}\n" .
        "Status: {$existingRefund['status']}\n"
    );
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

function refundCapture(string $base, string $token, string $captureId, string $amount, string $referenceId): array
{
    $payload = [
        'amount' => [
            'value' => $amount,
            'currency_code' => 'USD',
        ],
        'note_to_payer' => "Refund for {$referenceId}",
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

try {
    if (!isset($PP_CLIENT_ID, $PP_SECRET, $PP_BASE)) {
        throw new RuntimeException('paypal_config.php is missing $PP_CLIENT_ID, $PP_SECRET, or $PP_BASE');
    }

    $token = getPayPalAccessToken($PP_BASE, $PP_CLIENT_ID, $PP_SECRET);
    $refund = refundCapture($PP_BASE, $token, $captureId, $totalVenta, $referenceId);

    $refundId     = (string)($refund['id'] ?? '');
    $refundStatus = (string)($refund['status'] ?? 'UNKNOWN');
    $refundAmount = (string)($refund['amount']['value'] ?? $totalVenta);
    $currency     = (string)($refund['amount']['currency_code'] ?? 'USD');

    if ($refundId === '') {
        throw new RuntimeException("Refund response missing refund ID.");
    }

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
        'note'       => "Refund triggered by reference_id {$referenceId}",
    ]);

    $stmt = $pdo->prepare("
        UPDATE reservas
        SET estado = 'refund'
        WHERE id_reserva = :id_reserva
        LIMIT 1
    ");
    $stmt->execute(['id_reserva' => $idReserva]);

    $pdo->commit();

    echo "Refund completed successfully.\n";
    echo "Reference ID: {$referenceId}\n";
    echo "Reservation ID: {$idReserva}\n";
    echo "Order ID: {$orderId}\n";
    echo "Capture ID: {$captureId}\n";
    echo "Refund ID: {$refundId}\n";
    echo "Amount: {$refundAmount} {$currency}\n";
    echo "Status: {$refundStatus}\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    exit("Refund failed: " . $e->getMessage() . "\n");
}