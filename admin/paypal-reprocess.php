<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';
require __DIR__ . '/../includes/reprocess_paypal_events.php';

set_time_limit(0);

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_check'])) {
    $paypalConfig = require __DIR__ . '/../../paypal_config.php';
    $result = reprocess_paypal_stuck_events($conn, $paypalConfig, 50);
}

$active = 'paypal-reprocess';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PayPal Reprocessing</title>
  <link href="/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('paypal-reprocess'); ?>
<div class="container">
  <h1 class="h4 mb-3">PayPal Reprocessing</h1>
  <p class="text-muted">
    Checks <code>paypal_webhook_events</code> rows that never reached
    <code>status='handled'</code> (received more than 5 minutes ago, within
    the last 30 days), re-verifies their signature if needed, and finishes
    processing them - recovering payments PayPal notified us about but that
    we never fully recorded.
  </p>

  <form method="post">
    <button type="submit" name="run_check" value="1" class="btn btn-primary">Run Check Now</button>
  </form>

  <?php if ($result !== null): ?>
    <div class="mt-4">
      <p>
        <strong>Checked:</strong> <?= (int)$result['checked'] ?> &nbsp;
        <strong>Reprocessed:</strong> <?= (int)$result['reprocessed'] ?> &nbsp;
        <strong>Failed:</strong> <?= (int)$result['failed'] ?>
      </p>

      <?php if (!empty($result['details'])): ?>
        <table class="table table-striped table-sm">
          <thead>
            <tr><th>Event ID</th><th>Type</th><th>Result</th></tr>
          </thead>
          <tbody>
            <?php foreach ($result['details'] as $d): ?>
              <tr>
                <td><?= htmlspecialchars($d['event_id'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($d['event_type'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($d['result'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-success">Nothing to reprocess.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
