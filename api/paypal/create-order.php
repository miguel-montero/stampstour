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

if (!isset($conn) || !($conn instanceof mysqli)) err('DB', 'Invalid DB connection $conn', 500);

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) err('BAD_JSON', 'Invalid JSON body', 400);

$referenceId = isset($in['reference_id']) ? trim((string)$in['reference_id']) : '';
if ($referenceId === '') err('BAD_REFERENCE', 'reference_id is required');

// Amount lookup: prefer DB by reservas.reference_id; fallback to session
$amount = null; $currency = 'USD';
if ($stmt = $conn->prepare("SELECT total_venta FROM reservas WHERE reference_id = ? LIMIT 1")) {
  $stmt->bind_param('s', $referenceId);
  $stmt->execute();
  $stmt->bind_result($total);
  if ($stmt->fetch()) {
    $amount = (float)$total;
  }
  $stmt->close();
}
if ($amount === null && isset($_SESSION['order_total_usd'])) {
  $amount = (float)$_SESSION['order_total_usd'];
}
if ($amount === null) err('AMOUNT_NOT_FOUND', 'Unable to determine order total for reference_id', 500);

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

// Create order
$body = [
  'intent' => 'CAPTURE',
  'purchase_units' => [[
    'amount' => ['currency_code'=>$currency, 'value'=>number_format($amount,2,'.','')],
    'invoice_id' => $referenceId,
    'custom_id'  => $referenceId
  ]],
];

$ch = curl_init($PP_BASE . '/v2/checkout/orders');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $access,
    'Content-Type: application/json',
    'PayPal-Request-Id: ' . $referenceId  // idempotency
  ],
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($body),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 30,
]);
$out = curl_exec($ch);
if ($out === false) err('CURL_CREATE', curl_error($ch), 502);

// NEW: store PayPal order id into reservas.order_id
$pp = json_decode($out, true);
if (is_array($pp) && !empty($pp['id'])) {
  if ($stmt = $conn->prepare("UPDATE reservas SET order_id = ? WHERE reference_id = ? LIMIT 1")) {
    $stmt->bind_param('ss', $pp['id'], $referenceId);
    if (!$stmt->execute()) {
      error_log('create-order.php: failed to update order_id for ' . $referenceId . ' - ' . $stmt->error);
    }
    $stmt->close();
  } else {
    error_log('create-order.php: prepare failed for UPDATE reservas.order_id - ' . $conn->error);
  }
} else {
  // If PayPal didn’t return JSON with id, log it (still return whatever PayPal sent to the client)
  error_log('create-order.php: unexpected PayPal response (no id) for reference_id ' . $referenceId . ' -> ' . substr($out,0,500));
}

// Pass through PayPal's JSON
http_response_code(200);
echo $out;
