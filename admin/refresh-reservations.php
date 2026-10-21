<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

set_time_limit(0);

$configPath = __DIR__ . '/_apps_script_config.php';
$config = is_file($configPath) ? require $configPath : [];
$webappUrl = trim((string)($config['webapp_url'] ?? ''));
$secret = (string)($config['secret'] ?? '');

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_refresh'])) {

    if ($webappUrl === '' || $secret === '') {

        $error = 'Not configured yet - set webapp_url and secret in admin/_apps_script_config.php (see that file for setup steps).';

    } else {

        $url = $webappUrl . (str_contains($webappUrl, '?') ? '&' : '?') . 'secret=' . urlencode($secret);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $error = 'Request to Apps Script failed: ' . $curlError;
        } else {
            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                $error = 'Unexpected response from Apps Script: ' . substr($response, 0, 300);
            } elseif (empty($decoded['ok'])) {
                $error = (string)($decoded['error'] ?? 'Unknown error from Apps Script');
            } else {
                $result = $decoded;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Refresh Reservations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('refresh-reservations'); ?>
<div class="container">
  <h1 class="h4 mb-3">Refresh Reservations</h1>
  <p class="text-muted">
    Runs the Next-Day Reservations Extractor Google Apps Script, which
    re-scans Gmail for new Viator / GetYourGuide / B&oacute;kun / Civitatis
    booking emails and rewrites the "Hoja 1" sheet. Same effect as opening
    the Apps Script project and running <code>updateNextDayBookings</code>
    from Extensions &rarr; Apps Script manually.
  </p>

  <form method="post">
    <button type="submit" name="run_refresh" value="1" class="btn btn-primary">Check for New Reservations Now</button>
  </form>

  <?php if ($error !== ''): ?>
    <div class="alert alert-danger mt-4"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php elseif ($result !== null): ?>
    <div class="alert alert-success mt-4">
      Done - sheet rewritten with <?= (int)($result['rowsWritten'] ?? 0) ?> reservation row(s).
    </div>
  <?php endif; ?>
</div>
</body>
</html>
