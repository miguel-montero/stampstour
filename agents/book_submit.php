<?php
// agents/book_submit.php - travel-agent booking endpoint (total computed server-side).
ini_set('display_errors', '0');

require __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../includes/ta_booking.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /agents/book.php');
    exit;
}

try {
    $result = ta_booking_handle(
        $conn,
        $_POST,
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        new DateTimeImmutable('now', new DateTimeZone('America/Santiago'))
    );
} catch (Throwable $e) {
    error_log('agents/book_submit.php: ' . $e->getMessage());
    $result = ['ok' => false, 'error' => 'We could not save your booking. Please try again or contact us.'];
}

if ($result['ok']) {
    session_start();
    $_SESSION['reference_id'] = $result['reference_id'];
    header('Location: /shopping.php?reference_id=' . urlencode($result['reference_id']));
    exit;
}

http_response_code(400);
$message = htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Booking not completed | Stamp's Tour</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; background: #f9f9f9; color: #333; margin: 0; padding: 48px 16px; }
    .box { max-width: 520px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 24px; }
    h1 { font-size: 20px; margin-top: 0; }
    a { display: inline-block; margin-top: 12px; background: #008489; color: #fff; padding: 10px 18px; border-radius: 4px; text-decoration: none; }
  </style>
</head>
<body>
  <div class="box">
    <h1>We couldn't complete your booking</h1>
    <p><?= $message ?></p>
    <a href="javascript:history.back()">Go back and fix it</a>
  </div>
</body>
</html>
