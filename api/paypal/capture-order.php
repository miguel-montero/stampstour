<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
ini_set('log_errors','1');
@mkdir(__DIR__ . '/../../logs');
ini_set('error_log', __DIR__ . '/../../logs/paypal.log');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['error'=>'METHOD','message'=>'POST required']); exit;
}

session_start();

function err($code, $msg, $http=400){
  http_response_code($http);
  echo json_encode(['error'=>$code,'message'=>$msg], JSON_UNESCAPED_SLASHES); exit;
}

$cfgPath = __DIR__ . '/../../../paypal_config.php';
$dbPath  = __DIR__ . '/../../../db_config.php';

if (!file_exists($cfgPath)) err('CONFIG_MISSING', 'paypal_config.php not found at ' . $cfgPath, 500);
if (!file_exists($dbPath))  err('DBCFG_MISSING',  'db_config.php not found at ' . $dbPath, 500);

require $cfgPath;   // sets $PP_CLIENT_ID, $PP_SECRET, $PP_BASE
require $dbPath;    // sets $conn (mysqli)

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) err('BAD_JSON', 'Invalid JSON body', 400);

$orderId     = isset($in['orderID']) ? trim((string)$in['orderID']) : '';
$referenceId = isset($in['reference_id']) ? trim((string)$in['reference_id']) : '';
if ($orderId === '')     err('BAD_ORDERID', 'orderID is required');
if ($referenceId === '') err('BAD_REFERENCE', 'reference_id is required');

// Token
$ch = curl_init($PP_BASE . '/v1/oauth2/token');
curl_setopt_array($ch, [
  CURLOPT_USERPWD => $PP_CLIENT_ID . ':' . $PP_SECRET,
  CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 20,
]);
$res = curl_exec($ch);
if ($res === false) err('CURL_TOKEN', curl_error($ch), 502);
$tok = json_decode($res, true);
if (empty($tok['access_token'])) err('NO_TOKEN', 'Unable to obtain PayPal access token', 502);
$access = $tok['access_token'];

// Capture
$ch = curl_init($PP_BASE . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $access,
    'Content-Type: application/json',
    'PayPal-Request-Id: ' . $referenceId
  ],
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 30,
]);
$out = curl_exec($ch);
if ($out === false) err('CURL_CAPTURE', curl_error($ch), 502);
$cap = json_decode($out, true);

// --- normalize + (optional) DB update ---
$status    = $cap['status'] ?? '';
$captureId = $cap['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

if ($status === 'COMPLETED' && $captureId) {
  // Update your reservas row (uses your schema: estado + capture_id)
  $stmt = $conn->prepare("UPDATE reservas SET estado='realizado', capture_id=? WHERE reference_id=?");
  $stmt->bind_param('ss', $captureId, $referenceId);
  $stmt->execute();
  $stmt->close();
}

http_response_code(200);
echo json_encode([
  'ok'        => ($status === 'COMPLETED'),
  'status'    => $status,
  'captureId' => $captureId
]);
