<?php
// paypal_webhook.php — secure + idempotent; writes order_id, capture_id, estado
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/* ===== EMAIL FROM WEBHOOK DISABLED =====
   We moved emailing to a cron worker. Toggle this to re-enable if needed. */
$ENABLE_WEBHOOK_EMAIL = false;
/* ====================================== */

/* ---- getallheaders fallback (non-Apache SAPIs) ---- */
if (!function_exists('getallheaders')) {
  function getallheaders(): array {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
      if (strpos($name, 'HTTP_') === 0) {
        $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
        $headers[$key] = $value;
      }
    }
    return $headers;
  }
}

/* ---- Read raw body + required headers ---- */
$rawBody = file_get_contents('php://input');
if (!$rawBody) { http_response_code(400); exit('No body'); }

$H = array_change_key_case(getallheaders(), CASE_UPPER);
$needed = ['PAYPAL-TRANSMISSION-ID','PAYPAL-TRANSMISSION-SIG','PAYPAL-TRANSMISSION-TIME','PAYPAL-CERT-URL','PAYPAL-AUTH-ALGO'];
foreach ($needed as $k) { if (empty($H[$k])) { http_response_code(400); exit('Missing header '.$k); } }

/* ---- Load config + DB ---- */
$config = require __DIR__ . '/../paypal_config.php';
$mode   = ($config['mode'] === 'live') ? 'live' : 'sandbox';
$env    = $config[$mode];
$base   = ($mode === 'live') ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

require __DIR__ . '/../db_config.php'; // defines $conn (mysqli)

/* ---- Parse event ---- */
$event = json_decode($rawBody, true);
if (!is_array($event) || empty($event['id']) || empty($event['event_type'])) {
  http_response_code(400); exit('Invalid JSON');
}
$eventId   = $event['id'];
$eventType = $event['event_type'];

/* --- capture/ref retained (no-op when email disabled) --- */
$mailCheckRef = null;
$mailCheckCap = null;

/* ---- Idempotency (race-safe) ---- */
$headersJson = json_encode($H, JSON_UNESCAPED_SLASHES);
if ($stmt = $conn->prepare("INSERT IGNORE INTO paypal_webhook_events (event_id, event_type, payload, headers, status) VALUES (?,?,?,?, 'stored')")) {
  $stmt->bind_param('ssss', $eventId, $eventType, $rawBody, $headersJson);
  $stmt->execute();
  $inserted = ($stmt->affected_rows > 0);
  $stmt->close();

  if (!$inserted) {
    // Already exists: only exit if previously handled; otherwise continue (retry scenario)
    $stmt = $conn->prepare("SELECT status FROM paypal_webhook_events WHERE event_id=? LIMIT 1");
    $stmt->bind_param('s', $eventId);
    $stmt->execute();
    $stmt->bind_result($evtStatus);
    $found = $stmt->fetch();
    $stmt->close();
    if ($found && $evtStatus === 'handled') { http_response_code(200); exit('Already handled'); }
  }
} else {
  // Fallback if table layout differs
  $stmt = $conn->prepare("SELECT id FROM paypal_webhook_events WHERE event_id=? LIMIT 1");
  $stmt->bind_param('s', $eventId);
  $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) { http_response_code(200); exit('Already handled'); }
  $stmt->free_result(); $stmt->close();

  $stmt = $conn->prepare("INSERT INTO paypal_webhook_events (event_id, event_type, payload) VALUES (?,?,?)");
  $stmt->bind_param('sss', $eventId, $eventType, $rawBody);
  $stmt->execute(); $stmt->close();
}

/* ---- OAuth2 token for signature verification ---- */
function paypalAccessToken(array $env, string $base): string {
  $ch = curl_init("$base/v1/oauth2/token");
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $env['client_id'] . ':' . $env['client_secret'],
    CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
    CURLOPT_HTTPHEADER => ['Accept: application/json']
  ]);
  $res = curl_exec($ch);
  if ($res === false) throw new RuntimeException('OAuth curl: '.curl_error($ch));
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code < 200 || $code >= 300) throw new RuntimeException("OAuth HTTP $code");
  $json = json_decode($res, true);
  if (empty($json['access_token'])) throw new RuntimeException('No access token');
  return $json['access_token'];
}
try {
  $accessToken = paypalAccessToken($env, $base);
} catch (Throwable $e) {
  if ($stmt = @mysqli_prepare($conn, "UPDATE paypal_webhook_events SET status='queued' WHERE event_id=? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, 's', $eventId); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
  }
  http_response_code(500); exit('Auth error');
}

/* ---- Verify signature (official endpoint) ---- */
$verifyBody = [
  'transmission_id'   => $H['PAYPAL-TRANSMISSION-ID'],
  'transmission_time' => $H['PAYPAL-TRANSMISSION-TIME'],
  'cert_url'          => $H['PAYPAL-CERT-URL'],
  'auth_algo'         => $H['PAYPAL-AUTH-ALGO'],
  'transmission_sig'  => $H['PAYPAL-TRANSMISSION-SIG'],
  'webhook_id'        => $env['webhook_id'],
  'webhook_event'     => $event,
];
$ch = curl_init("$base/v1/notifications/verify-webhook-signature");
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer '.$accessToken],
  CURLOPT_POSTFIELDS => json_encode($verifyBody, JSON_UNESCAPED_SLASHES)
]);
$resp = curl_exec($ch);
if ($resp === false) { http_response_code(400); exit('Verify curl'); }
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$ok = ($code >= 200 && $code < 300) && (json_decode($resp, true)['verification_status'] ?? '') === 'SUCCESS';

/* ---- Persist verification result if column exists ---- */
if ($stmt = @mysqli_prepare($conn, "UPDATE paypal_webhook_events SET verified=?, status=IF(?='SUCCESS', status, status) WHERE event_id=? LIMIT 1")) {
  $ver = $ok ? 'SUCCESS' : 'FAILURE';
  mysqli_stmt_bind_param($stmt, 'sss', $ver, $ver, $eventId);
  mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
}
if (!$ok) {
  if ($stmt = @mysqli_prepare($conn, "UPDATE paypal_webhook_events SET status='queued' WHERE event_id=? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, 's', $eventId); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
  }
  http_response_code(202); exit('Verification failed; queued');
}

/* ---- Helpers: status updates & reference lookup ---- */
function updateHandled(mysqli $conn, string $eventId): void {
  if ($stmt = @mysqli_prepare($conn, "UPDATE paypal_webhook_events SET status='handled', handled_at=NOW() WHERE event_id=? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, 's', $eventId); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
  }
}
function findReferenceByOrderId(mysqli $conn, string $orderId): ?string {
  $stmt = $conn->prepare("SELECT reference_id FROM reservas WHERE order_id=? LIMIT 1");
  $stmt->bind_param('s', $orderId); $stmt->execute(); $stmt->bind_result($ref);
  $got = $stmt->fetch(); $stmt->close();
  return $got ? $ref : null;
}
function tryPrepare(mysqli $conn, string $sql) { return @mysqli_prepare($conn, $sql) ?: null; }
function tryUpdateAmounts(mysqli $conn, string $referenceId, ?string $amountVal, ?string $currency): void {
  if ($amountVal === null && $currency === null) return;
  if ($stmt = tryPrepare($conn, "UPDATE reservas SET monto_pagado=IFNULL(monto_pagado, ?), moneda=IFNULL(moneda, ?) WHERE TRIM(reference_id)=TRIM(?) LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, 'sss', $amountVal, $currency, $referenceId);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
  }
}

/* ---- Mail helpers (left in place; no-op when $ENABLE_WEBHOOK_EMAIL=false) ---- */
function resolve_mailer(): ?callable {
  $candidates = [
    __DIR__ . '/includes/send_confirmation.php',
    dirname(__DIR__) . '/includes/send_confirmation.php',
    dirname(__DIR__, 2) . '/includes/send_confirmation.php',
    (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'],'/').'/includes/send_confirmation.php' : null),
  ];
  foreach ($candidates as $p) {
    if ($p && file_exists($p)) {
      require_once $p;
      if (function_exists('send_confirmation_by_reference')) {
        return 'send_confirmation_by_reference';
      }
    }
  }
  @error_log('Webhook mail resolver: send_confirmation.php not found (email disabled)');
  return null;
}
function isRealizado(mysqli $conn, string $referenceId): bool {
  if ($stmt = $conn->prepare("SELECT 1 FROM reservas WHERE TRIM(reference_id)=TRIM(?) AND estado='realizado' LIMIT 1")) {
    $stmt->bind_param('s', $referenceId); $stmt->execute(); $stmt->store_result();
    $yes = $stmt->num_rows > 0; $stmt->free_result(); $stmt->close(); return $yes;
  }
  return false;
}
function mailedAlreadyForCapture(mysqli $conn, string $captureId): bool {
  if (!$captureId) return false;
  if ($stmt = tryPrepare($conn,
      "SELECT 1
         FROM paypal_webhook_events
        WHERE event_type='PAYMENT.CAPTURE.COMPLETED'
          AND (headers LIKE '%mailed%' OR status='mailed')
          AND payload LIKE CONCAT('%', ?, '%')
        LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, 's', $captureId);
    mysqli_stmt_execute($stmt); mysqli_stmt_store_result($stmt);
    $seen = ($stmt->num_rows > 0); mysqli_stmt_free_result($stmt); mysqli_stmt_close($stmt);
    return $seen;
  }
  return false;
}
function markMailed(mysqli $conn, string $eventId, ?string $captureId): void {
  if ($captureId && ($stmt = tryPrepare($conn, "UPDATE paypal_webhook_events SET headers = CONCAT(IFNULL(headers,''),'|mailed:',NOW(),'#',?) WHERE event_id=? LIMIT 1"))) {
    mysqli_stmt_bind_param($stmt, 'ss', $captureId, $eventId);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
  } else if ($stmt = tryPrepare($conn, "UPDATE paypal_webhook_events SET status='mailed' WHERE event_id=? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, 's', $eventId);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
  }
}

/* ---- Process events ---- */
try {
  switch ($eventType) {
    case 'CHECKOUT.ORDER.APPROVED': {
      $r           = $event['resource'] ?? [];
      $orderId     = $r['id'] ?? null;
      $pu          = $r['purchase_units'][0] ?? [];
      $referenceId = $pu['custom_id'] ?? ($pu['invoice_id'] ?? null); // <= also accept invoice_id
      if (!$referenceId && $orderId) { $referenceId = findReferenceByOrderId($conn, $orderId); }

      if ($referenceId && $orderId) {
        $stmt = $conn->prepare("
          UPDATE reservas
             SET order_id = IFNULL(order_id, ?),
                 estado   = IF(estado='realizado', estado, 'pendiente')
           WHERE TRIM(reference_id)=TRIM(?) LIMIT 1
        ");
        $stmt->bind_param('ss', $orderId, $referenceId);
        $stmt->execute(); $stmt->close();
      }
      break;
    }

    case 'PAYMENT.CAPTURE.COMPLETED': {
      $r           = $event['resource'] ?? [];
      $captureId   = $r['id'] ?? null;
      $orderId     = $r['supplementary_data']['related_ids']['order_id'] ?? null;
      $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null); // <= also accept invoice_id
      $amountVal   = $r['amount']['value'] ?? null;
      $currency    = $r['amount']['currency_code'] ?? null;

      if (!$referenceId && $orderId) { $referenceId = findReferenceByOrderId($conn, $orderId); }

      if ($captureId && $referenceId) {
        // Primary: match by trimmed reference_id
        $stmt = $conn->prepare("
          UPDATE reservas
             SET estado='realizado',
                 capture_id=?,
                 order_id=IFNULL(order_id, ?)
           WHERE TRIM(reference_id)=TRIM(?)
           LIMIT 1
        ");
        $stmt->bind_param('sss', $captureId, $orderId, $referenceId);
        $stmt->execute();
        $rows = $stmt->affected_rows;
        $stmt->close();

        // Fallback: match by order_id if reference compare missed
        if ($rows === 0 && $orderId) {
          if ($stmt = $conn->prepare("
            UPDATE reservas
               SET estado='realizado',
                   capture_id=?,
                   order_id=IFNULL(order_id, ?)
             WHERE order_id=?
             LIMIT 1
          ")) {
            $stmt->bind_param('sss', $captureId, $orderId, $orderId);
            $stmt->execute(); $rows = $stmt->affected_rows; $stmt->close();
          }
        }

        @error_log("CAPTURE.UPDATE rows={$rows} ref={$referenceId} order={$orderId} capture={$captureId}");
        tryUpdateAmounts($conn, $referenceId, $amountVal, $currency);

        // >>> EMAIL (DISABLED) — moved to cron worker
        if ($ENABLE_WEBHOOK_EMAIL) {
          $shouldMail = ($rows > 0) || isRealizado($conn, $referenceId);
          if ($shouldMail && !mailedAlreadyForCapture($conn, $captureId)) {
            $mailer = resolve_mailer();
            if ($mailer) {
              try {
                $mailer($conn, $referenceId);
                markMailed($conn, $eventId, $captureId);
              } catch (Throwable $e) {
                @error_log('Webhook mail error: '.$e->getMessage());
              }
            } else {
              @error_log('Webhook mail error: send_confirmation_by_reference() not resolved');
            }
          }
          // Save for a post-response safety pass
          $mailCheckRef = $referenceId;
          $mailCheckCap = $captureId;
        }
        // <<< END EMAIL (DISABLED)
      }
      break;
    }

    case 'PAYMENT.CAPTURE.PENDING': {
      $r           = $event['resource'] ?? [];
      $orderId     = $r['supplementary_data']['related_ids']['order_id'] ?? null;
      $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
      if (!$referenceId && $orderId) { $referenceId = findReferenceByOrderId($conn, $orderId); }
      if ($referenceId) {
        // Guard: no bajar una reserva ya 'realizado' a 'pendiente' por un
        // evento tardío/duplicado. Ver
        // docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
        $stmt = $conn->prepare("UPDATE reservas SET estado = CASE WHEN estado = 'realizado' THEN estado ELSE 'pendiente' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $referenceId);
        $stmt->execute(); $stmt->close();
      }
      break;
    }

    case 'PAYMENT.CAPTURE.DENIED':
    case 'PAYMENT.CAPTURE.DECLINED': {
      $r           = $event['resource'] ?? [];
      $orderId     = $r['supplementary_data']['related_ids']['order_id'] ?? null;
      $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
      if (!$referenceId && $orderId) { $referenceId = findReferenceByOrderId($conn, $orderId); }
      if ($referenceId) {
        // Guard: no bajar una reserva ya 'realizado' a 'fallido' por un
        // evento tardío/duplicado. Ver
        // docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
        $stmt = $conn->prepare("UPDATE reservas SET estado = CASE WHEN estado = 'realizado' THEN estado ELSE 'fallido' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $referenceId);
        $stmt->execute(); $stmt->close();
      }
      break;
    }

    case 'PAYMENT.CAPTURE.REFUNDED': {
      $r           = $event['resource'] ?? [];
      $orderId     = $r['supplementary_data']['related_ids']['order_id'] ?? null;
      $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
      $refundId    = $r['id'] ?? null;
      $amountVal   = $r['amount']['value'] ?? null;
      $currency    = $r['amount']['currency_code'] ?? null;

      if (!$referenceId && $orderId) { $referenceId = findReferenceByOrderId($conn, $orderId); }
      if ($referenceId) {
        $stmt = $conn->prepare("UPDATE reservas SET estado='refund' WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $referenceId);
        $stmt->execute(); $stmt->close();

        if ($stmt = tryPrepare($conn, "UPDATE reservas SET refund_id = IFNULL(refund_id, ?), refund_monto = IFNULL(refund_monto, ?), moneda = IFNULL(moneda, ?) WHERE TRIM(reference_id)=TRIM(?) LIMIT 1")) {
          $stmt->bind_param('ssss', $refundId, $amountVal, $currency, $referenceId);
          $stmt->execute(); $stmt->close();
        }
      }
      break;
    }

    default:
      // no-op; marked handled below
      break;
  }

  // Mark handled and respond ASAP to PayPal
  updateHandled($conn, $eventId);
  http_response_code(200); echo 'OK';

  // -------------------------------
  // Post-response email safety pass (DISABLED)
  // -------------------------------
  if ($ENABLE_WEBHOOK_EMAIL) {
    if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
    if ($mailCheckRef && isRealizado($conn, $mailCheckRef) && !mailedAlreadyForCapture($conn, $mailCheckCap)) {
      $mailer = resolve_mailer();
      if ($mailer) {
        try {
          $mailer($conn, $mailCheckRef);
          markMailed($conn, $eventId, $mailCheckCap);
        } catch (Throwable $e) {
          @error_log('Post-response mail error: '.$e->getMessage());
        }
      }
    }
  }

} catch (Throwable $e) {
  error_log('Webhook error: '.$e->getMessage());
  http_response_code(500); echo 'ERR';
}
