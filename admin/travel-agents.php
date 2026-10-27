<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';
require __DIR__ . '/../includes/travel_agents.php';
require __DIR__ . '/../includes/ta_mail.php';

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$tz = new DateTimeZone('America/Santiago');
$taCfg = require __DIR__ . '/../includes/ta_config.php';
$baseUrl = rtrim((string)$taCfg['base_url'], '/');

if (empty($_SESSION['ta_csrf']) || !is_string($_SESSION['ta_csrf'])) {
    $_SESSION['ta_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['ta_csrf'];

// ---- POST actions (PRG) ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $flash = ['type' => 'danger', 'msg' => 'Nothing changed (the agent may have already been updated).'];

    $csrfOk = hash_equals($csrf, (string)($_POST['csrf'] ?? ''));

    if ($csrfOk && $id > 0) {
        $agent = ta_get($conn, $id);
        $name = $agent['nombre_vendedor'] ?? 'agent';
        if ($action === 'approve') {
            $row = ta_approve($conn, $id, new DateTimeImmutable('now', $tz));
            if ($row) {
                $sent = ta_send_email(ta_code_email($row, $baseUrl . '/agents/book.php'));
                $flash = $sent
                    ? ['type' => 'success', 'msg' => "Approved {$row['nombre_vendedor']} — code {$row['ta_code']} emailed"]
                    : ['type' => 'warning', 'msg' => "Approved {$row['nombre_vendedor']} — code {$row['ta_code']}. Email failed, send it manually."];
            }
        } elseif ($action === 'reject') {
            if (ta_reject($conn, $id)) {
                $flash = ['type' => 'success', 'msg' => "Rejected $name"];
            }
        } elseif ($action === 'deactivate' || $action === 'reactivate') {
            if (ta_set_active($conn, $id, $action === 'reactivate')) {
                $flash = ['type' => 'success', 'msg' => ($action === 'reactivate' ? 'Reactivated ' : 'Deactivated ') . $name];
            }
        }
    }
    $_SESSION['ta_flash'] = $flash;
    header('Location: /admin/travel-agents.php');
    exit;
}

$flash = $_SESSION['ta_flash'] ?? null;
unset($_SESSION['ta_flash']);

$lists = ta_list($conn);
$applyUrl = $baseUrl . '/agents/apply.php';

function ta_fmt_date($v): string
{
    return $v ? date('Y-m-d H:i', strtotime((string)$v)) : '';
}

function ta_action_form(string $csrf, int $id, string $action, string $label, string $btnClass, string $confirm = ''): void
{
    $onsubmit = $confirm !== '' ? ' onsubmit="return confirm(' . h(json_encode($confirm)) . ');"' : '';
    echo '<form method="post" class="d-inline"' . $onsubmit . '>'
        . '<input type="hidden" name="csrf" value="' . h($csrf) . '">'
        . '<input type="hidden" name="action" value="' . h($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn btn-sm ' . h($btnClass) . '">' . h($label) . '</button></form> ';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="robots" content="noindex">
  <title>Travel Agents</title>
  <link rel="shortcut icon" href="/img/favicon.ico" type="image/x-icon"/>
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
  <style>main { padding: 24px 0; }</style>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('travel-agents'); ?>
<main>
  <div class="container">
    <h1 class="h3 mb-3">Travel Agents</h1>

    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="card mb-4"><div class="card-body">
      <label for="apply_url" class="mb-1">Public sign-up link (share with agents)</label>
      <input type="text" id="apply_url" class="form-control" readonly value="<?= h($applyUrl) ?>" onclick="this.select();">
    </div></div>

    <h2 class="h5">Pending (<?= count($lists['pending']) ?>)</h2>
    <div class="table-responsive mb-4">
      <table class="table table-sm table-striped">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Applied</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lists['pending'] as $a): ?>
          <tr>
            <td><?= h($a['nombre_vendedor']) ?></td>
            <td><?= h($a['email']) ?></td>
            <td><?= h($a['telefono']) ?></td>
            <td><?= h(ta_fmt_date($a['ta_applied_at'])) ?></td>
            <td class="text-nowrap">
              <?php ta_action_form($csrf, (int)$a['id_vendedor'], 'approve', 'Approve', 'btn-success');
                    ta_action_form($csrf, (int)$a['id_vendedor'], 'reject', 'Reject', 'btn-outline-danger', 'Reject this application?'); ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$lists['pending']): ?><tr><td colspan="5" class="text-muted">No pending applications.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <h2 class="h5">Active (<?= count($lists['active']) ?>)</h2>
    <div class="table-responsive mb-4">
      <table class="table table-sm table-striped">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Code</th><th>Reservations</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lists['active'] as $a): ?>
          <tr>
            <td><?= h($a['nombre_vendedor']) ?></td>
            <td><?= h($a['email']) ?></td>
            <td><?= h($a['telefono']) ?></td>
            <td><code><?= h($a['ta_code']) ?></code></td>
            <td><?= (int)$a['bookings'] ?></td>
            <td><?php ta_action_form($csrf, (int)$a['id_vendedor'], 'deactivate', 'Deactivate', 'btn-outline-secondary'); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$lists['active']): ?><tr><td colspan="6" class="text-muted">No active agents.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <h2 class="h5">Inactive (<?= count($lists['inactive']) ?>)</h2>
    <div class="table-responsive mb-4">
      <table class="table table-sm table-striped">
        <thead><tr><th>Name</th><th>Email</th><th>Code</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lists['inactive'] as $a): ?>
          <tr>
            <td><?= h($a['nombre_vendedor']) ?></td>
            <td><?= h($a['email']) ?></td>
            <td><code><?= h($a['ta_code']) ?></code></td>
            <td><?php ta_action_form($csrf, (int)$a['id_vendedor'], 'reactivate', 'Reactivate', 'btn-outline-primary'); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$lists['inactive']): ?><tr><td colspan="4" class="text-muted">No inactive agents.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</body>
</html>
