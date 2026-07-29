<?php
// /includes/cron_send_confirmations.php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');

$LOCK = sys_get_temp_dir() . '/cron_send_confirmations.lock';
$fh = fopen($LOCK, 'c');
if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) { exit; } // avoid overlap

require __DIR__ . '/../../db_config.php';   // defines $conn (mysqli)
require __DIR__ . '/send_confirmation.php';     // your existing function

$BATCH = 25;  // send up to 25 per run
$SLEEP = 0;   // seconds between sends (0-1s if you want to be gentle)

$stmt = $conn->prepare("
  SELECT id_reserva, reference_id
    FROM reservas
   WHERE estado='realizado'
     AND (email_sent_at IS NULL)
   ORDER BY id_reserva ASC
   LIMIT ?
");
$stmt->bind_param('i', $BATCH);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($rows as $row) {
  $id  = (int)$row['id_reserva'];
  $ref = (string)$row['reference_id'];

  // Claim row atomically to avoid double-send if two cron runs overlap.
  $claim = $conn->prepare("
    UPDATE reservas
       SET email_attempts = email_attempts + 1
     WHERE id_reserva = ?
       AND email_sent_at IS NULL
    LIMIT 1
  ");
  $claim->bind_param('i', $id);
  $claim->execute();
  $claimed = $claim->affected_rows > 0;
  $claim->close();
  if (!$claimed) { continue; }

  try {
    $ok = send_confirmation_by_reference($conn, $ref, [
      // Optional public base if needed:
       'publicTicketsBaseUrl' => 'https://stampstour.com/tickets/'
    ]);

    if ($ok) {
      $upd = $conn->prepare("UPDATE reservas SET email_sent_at = NOW(), last_email_error=NULL WHERE id_reserva=? LIMIT 1");
      $upd->bind_param('i', $id);
      $upd->execute(); $upd->close();
    } else {
      $upd = $conn->prepare("UPDATE reservas SET last_email_error = 'send_confirmation returned false' WHERE id_reserva=? LIMIT 1");
      $upd->bind_param('i', $id);
      $upd->execute(); $upd->close();
    }
  } catch (Throwable $e) {
    $msg = substr($e->getMessage(), 0, 250);
    $upd = $conn->prepare("UPDATE reservas SET last_email_error = ? WHERE id_reserva=? LIMIT 1");
    $upd->bind_param('si', $msg, $id);
    $upd->execute(); $upd->close();
    @error_log('cron_send_confirmations error: '.$e->getMessage());
  }

  if ($SLEEP > 0) { sleep($SLEEP); }
}

flock($fh, LOCK_UN);
fclose($fh);
