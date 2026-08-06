<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

// One-off diagnostic: queries PayPal's live Capture API directly for a
// given capture_id, to independently confirm a payment's real status
// (not just re-reading our own stored webhook data). Admin-only. Read-only
// - never mutates anything, on our side or PayPal's.
header('Content-Type: application/json; charset=utf-8');

$captureId = trim((string)($_GET['capture_id'] ?? ''));
if ($captureId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing ?capture_id=']);
    exit;
}

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

try {
    $token = paypal_capture_check_token($env, $base);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'OAuth failed', 'detail' => $e->getMessage()]);
    exit;
}

$ch = curl_init("$base/v2/payments/captures/" . rawurlencode($captureId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode((string)$resp, true);

echo json_encode([
    'ok' => true,
    'capture_id' => $captureId,
    'http_code' => $code,
    'status' => $data['status'] ?? null,
    'amount' => $data['amount'] ?? null,
    'create_time' => $data['create_time'] ?? null,
    'update_time' => $data['update_time'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
