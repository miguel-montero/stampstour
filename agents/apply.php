<?php
// agents/apply.php - public travel-agent application form.
ini_set('display_errors', '0');

require __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../includes/travel_agents.php';
require_once __DIR__ . '/../includes/ta_mail.php';

function ta_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$name = '';
$email = '';
$phone = '';
$error = '';
$done = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name  = (string)($_POST['name'] ?? '');
    $email = (string)($_POST['email'] ?? '');
    $phone = (string)($_POST['phone'] ?? '');
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
        $result = ta_apply($conn, $name, $email, $phone, $now);
        if ($result['ok']) {
            $agent = ta_get($conn, (int)$result['id']);
            if ($agent) {
                $adminUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.stampstour.com') . '/admin/travel-agents.php';
                try {
                    ta_send_email(ta_owner_notification_email($agent, $adminUrl));
                } catch (Throwable $e) {
                    error_log('agents/apply.php notify: ' . $e->getMessage());
                }
            }
            $done = true;
        } else {
            $error = $result['error'];
        }
    } catch (Throwable $e) {
        error_log('agents/apply.php: ' . $e->getMessage());
        $error = 'Could not save your application. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="robots" content="noindex">
  <title>Travel Agent Application | Stamp's Tour</title>
  <link rel="shortcut icon" href="/img/favicon.ico" type="image/x-icon"/>
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
  <style>
    main { padding: 32px 0; }
    .card { border-radius: 14px; }
    .logo-container { text-align:center; margin-bottom:20px; }
    .logo-container img { max-width: 240px; height:auto; }
  </style>
</head>
<body>
<main>
  <div class="container">
    <div class="logo-container">
      <img src="/img/logolargo.png" alt="Stamp’s Tour Logo" width="320" height="114"/>
    </div>
    <div class="row justify-content-center">
      <div class="col-xl-5 col-lg-6 col-md-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="h4 mb-3 text-center">Travel Agent Application</h3>
<?php if ($done): ?>
            <p class="text-center" id="ta_thanks">Thanks — we'll review your application and email you.</p>
<?php else: ?>
            <p class="text-muted text-center mb-4">Apply to book Stamp's Tour experiences at travel-agent rates.</p>
<?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= ta_h($error) ?></div>
<?php endif; ?>
            <form action="/agents/apply.php" method="POST">
              <div class="form-group">
                <label for="name">Name</label>
                <input type="text" class="form-control" id="name" name="name" maxlength="255" required value="<?= ta_h($name) ?>">
              </div>
              <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="255" required value="<?= ta_h($email) ?>">
              </div>
              <div class="form-group">
                <label for="phone">Phone <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control" id="phone" name="phone" maxlength="50" value="<?= ta_h($phone) ?>">
              </div>
              <button type="submit" class="btn btn-primary btn-block">Apply</button>
            </form>
<?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
</body>
</html>
