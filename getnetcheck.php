<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// config.php vive un nivel arriba
require_once __DIR__ . '/../config.php';

function b64(string $raw): string { return base64_encode($raw); }

function makeAuth(string $login, string $secretKey): array {
  $seed = (new DateTime('now', new DateTimeZone('America/Santiago')))->format('c');
  $nonceRaw = random_bytes(16);
  $nonce = b64($nonceRaw);
  $tranKey = b64(hash('sha256', $nonceRaw . $seed . $secretKey, true));

  return [
    'login'   => $login,
    'seed'    => $seed,
    'nonce'   => $nonce,
    'tranKey' => $tranKey
  ];
}

function httpPostJson(string $url, array $payload, int $timeout = 20): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => $timeout,
  ]);

  $raw = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return [
    'ok' => ($err === '' && $code >= 200 && $code < 300),
    'http_code' => $code,
    'curl_error' => $err ?: null,
    'raw' => $raw,
    'json' => json_decode($raw ?? '', true),
  ];
}

// ✅ Caso específico
$processId = '1487683';

$auth = makeAuth(GETNET_LOGIN, GETNET_SECRET_KEY);
$url = rtrim(GETNET_BASE_URL, '/') . '/api/session/' . $processId;

// Getnet recibe auth por POST al endpoint session
$res = httpPostJson($url, ['auth' => $auth]);

if (!$res['ok']) {
  http_response_code(502);
  echo json_encode([
    'ok' => false,
    'error' => 'No se pudo consultar Getnet',
    'process_id' => $processId,
    'http_code' => $res['http_code'],
    'curl_error' => $res['curl_error'],
    'getnet_raw' => $res['raw'],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  exit;
}

$session = $res['json'] ?? [];

$status = $session['status']['status'] ?? null;
$message = $session['status']['message'] ?? null;

$payment0 = $session['payment'][0] ?? null;
$paymentStatus = $payment0['status']['status'] ?? null;
$authorization = $payment0['authorization'] ?? null;
$receipt = $payment0['receipt'] ?? null;

// Map simple a tu lógica
$norm = strtoupper((string)($paymentStatus ?? $status ?? ''));
$mapped = 'pendiente';
if ($norm === 'APPROVED') $mapped = 'realizado';
elseif (in_array($norm, ['REJECTED','FAILED','EXPIRED'], true)) $mapped = 'fallido';
elseif ($norm === 'REFUNDED') $mapped = 'refund';

echo json_encode([
  'ok' => true,
  'process_id' => $processId,
  'url' => $url,
  'getnet' => [
    'status' => $status,
    'message' => $message,
    'payment_status' => $paymentStatus,
    'authorization' => $authorization,
    'receipt' => $receipt,
  ],
  'mapped_estado' => $mapped,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
