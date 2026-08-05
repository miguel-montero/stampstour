<?php
// getnet_notify.php
declare(strict_types=1);

@mkdir(__DIR__ . '/../logs', 0775, true);
ini_set('log_errors','1');
ini_set('error_log', __DIR__ . '/../logs/getnet_webhook.log');

require __DIR__ . '/../db_config.php';
require __DIR__ . '/helpers.php'; // Debe definir GETNET_SECRET_KEY, GETNET_BASE_URL y generateAuth()

header('Content-Type: application/json; charset=utf-8');

// Helper para terminar SIEMPRE con 200
function ok($msg = 'OK'){
  http_response_code(200);
  echo json_encode(['ok'=>true,'msg'=>$msg], JSON_UNESCAPED_SLASHES);
  exit;
}

// 1) Leer entrada
$raw = file_get_contents('php://input');
if ($raw === false) $raw = '';
$data = json_decode($raw, true);

// 2) Validaciones suaves (si algo falla, log + 200)
if (!is_array($data)) {
  error_log("Webhook JSON inválido: " . substr($raw, 0, 2000));
  ok('no-json'); // 200
}

$hasFields = isset($data['requestId'], $data['reference'], $data['signature'])
          && isset($data['status']['status'], $data['status']['date']);

if (!$hasFields) {
  error_log("Webhook faltan campos: " . json_encode($data, JSON_UNESCAPED_SLASHES));
  ok('missing-fields'); // 200
}

// 3) Extraer datos
$requestId = (string)$data['requestId'];
$reference = (string)$data['reference'];
$signature = (string)$data['signature'];
$status    = (string)$data['status']['status']; // APPROVED | REJECTED | PENDING | ...
$date      = (string)$data['status']['date'];

// 3.1) Calcular firma esperada
$calc  = sha1($requestId . $status . $date . GETNET_SECRET_KEY);
$valid = hash_equals($calc, $signature) ? 1 : 0;

// 3.2) (NEW) Registrar SIEMPRE el evento crudo (idempotente) antes de confirmar
try {
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $rawShort = substr($raw, 0, 65535); // por compatibilidad si JSON largo
  $stmt = $conn->prepare("
    INSERT IGNORE INTO getnet_webhook_events
      (received_at, source_ip, request_id, reference, status_text, status_date, signature, calc_signature, signature_valid, http_response, raw_payload)
    VALUES
      (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 200, ?)
  ");
  // tipos: s s s s s s i s => "sssssssis" (9 parámetros)
  $stmt->bind_param("sssssssis", $ip, $requestId, $reference, $status, $date, $signature, $calc, $valid, $rawShort);
  $stmt->execute();
  $stmt->close();
} catch (Throwable $e) {
  error_log("Webhook insert event fail: " . $e->getMessage());
}

// 3.3) Si la firma es inválida, no tocar reservas
if (!$valid) {
  error_log("Firma inválida. ref=$reference req=$requestId status=$status");
  ok('bad-signature'); // 200
}

// 4) Confirmación activa (con timeout y manejo de errores)
$auth = generateAuth();
$base = rtrim(GETNET_BASE_URL, '/'); // sandbox/prod desde config.php
$url  = $base . "/api/session/" . rawurlencode($requestId);
$payload = json_encode(['auth' => $auth], JSON_UNESCAPED_SLASHES);

$opts = [
  'http' => [
    'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
    'method'  => 'POST',
    'content' => $payload,
    'timeout' => 5, // segundos
    'ignore_errors' => true,
  ]
];
$ctx  = stream_context_create($opts);
$resp = @file_get_contents($url, false, $ctx);

if ($resp === false) {
  error_log("Error consultando session: $url payload=$payload");
  // No cortar 200 al webhook; quedará evento registrado
  ok('confirm-failed');
}

$respData = json_decode($resp, true);
$confirmedStatus = $respData['status']['status'] ?? null;
if (!$confirmedStatus) {
  error_log("Respuesta sin status en confirmación: " . substr($resp,0,2000));
  ok('confirm-no-status');
}

// 5) Actualización en BD (mapear estados)
try {
  $normalizedStatus = strtoupper(trim((string)$confirmedStatus));

  $statusMap = [
    'APPROVED' => 'realizado',
    'PENDING'  => 'pendiente',
    'REJECTED' => 'fallido',
    'FAILED'   => 'fallido',
    'EXPIRED'  => 'fallido',
    'REFUNDED' => 'refund',
  ];

  if (!isset($statusMap[$normalizedStatus])) {
    error_log("Unknown Getnet status. ref=$reference req=$requestId status=$normalizedStatus");
    ok('unknown-status');
  }

  $nuevoEstado = $statusMap[$normalizedStatus];

  // Update mínimo: exige que el webhook corresponda al process_id vigente.
  // Guard: nunca permitir que un webhook (posiblemente tardío, de una sesión
  // abandonada) baje una reserva ya 'realizado' a otro estado, salvo un
  // 'refund' genuino. Ver docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
  $stmt = $conn->prepare("
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
  $stmt->bind_param("ssss", $nuevoEstado, $nuevoEstado, $reference, $requestId);
  if (!$stmt->execute()) {
    error_log("DB error: " . $stmt->error . " ref=$reference status=$confirmedStatus");
  }
  $stmt->close();

} catch (Throwable $e) {
  error_log("DB exception: " . $e->getMessage());
  // No fallar el webhook
}

// 5.1) (NEW) Actualizar el evento con el resultado confirmado
try {
  $stmt = $conn->prepare("
    UPDATE getnet_webhook_events
    SET confirmed_status = ?
    WHERE request_id = ? AND status_text = ? AND status_date = ? AND signature = ?
    LIMIT 1
  ");
  $stmt->bind_param("sssss", $confirmedStatus, $requestId, $status, $date, $signature);
  $stmt->execute();
  $stmt->close();
} catch (Throwable $e) {
  error_log("Webhook update event confirm fail: " . $e->getMessage());
}

// 6) Log de auditoría
error_log("OK webhook ref=$reference req=$requestId status=$status -> confirmed=$confirmedStatus");

// 7) Siempre 200
ok('processed');
