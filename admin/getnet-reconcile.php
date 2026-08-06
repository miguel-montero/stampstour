<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';
require __DIR__ . '/../includes/reconcile_getnet.php';

set_time_limit(0);

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_check'])) {
    $result = reconcile_getnet_pending($conn, 50);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Getnet Reconciliation</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('getnet-reconcile'); ?>
<div class="container">
  <h1 class="h4 mb-3">Getnet Reconciliation</h1>
  <p class="text-muted">
    Checks reservations that are still <code>pendiente</code>, have a Getnet
    <code>process_id</code>, and were created in the last 24 hours, against
    Getnet's live session status. Corrects any that Getnet actually approved
    or refunded but whose webhook never arrived.
  </p>

  <form method="post">
    <button type="submit" name="run_check" value="1" class="btn btn-primary">Run Check Now</button>
  </form>

  <?php if ($result !== null): ?>
    <div class="mt-4">
      <p><strong>Checked:</strong> <?= (int)$result['checked'] ?> &nbsp;
         <strong>Corrected:</strong> <?= (int)$result['corrected'] ?> &nbsp;
         <strong>Failed:</strong> <?= (int)$result['failed'] ?></p>

      <?php if (!empty($result['corrections'])): ?>
        <table class="table table-striped table-sm">
          <thead>
            <tr><th>Reference</th><th>From</th><th>To</th></tr>
          </thead>
          <tbody>
            <?php foreach ($result['corrections'] as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c['reference'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($c['from'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($c['to'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-success">No corrections needed.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
