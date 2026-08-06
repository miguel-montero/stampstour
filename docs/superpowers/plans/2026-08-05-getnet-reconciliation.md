# Getnet Payment Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically recover `pendiente` reservations that Getnet actually approved but whose webhook notification never arrived, via a shared reconciliation function callable both from an hourly cron job and an on-demand admin button.

**Architecture:** One shared function (`reconcile_getnet_pending`) contains all the logic (query, live Getnet check, guarded write, logging); a thin cron script and a thin admin page both call it and format the result for their own context. This avoids duplicating the safety-critical UPDATE logic in two places.

**Tech Stack:** Plain PHP + mysqli prepared statements, no build step, no test framework in this repo.

## Global Constraints

- Full rationale: `docs/superpowers/specs/2026-08-05-getnet-reconciliation-design.md`. Read it first.
- `reservas.fecha_reserva` is set to `NOW()` at INSERT time (`submit.php:134`) — it is this table's de facto "created at" column. There is no separate `created_at` column (confirmed earlier this session: querying one produced `Unknown column 'created_at'`).
- `helpers.php`'s `getSessionInfo(int $requestId): array` is the existing, already-proven-working way to query Getnet's live session status (used successfully by `return.php` and `getnetcheck.php` this session). It returns the raw decoded Getnet API response on success, or `['ok' => false, 'error' => ..., 'http' => ...]` on failure — always check `isset($session['ok']) && $session['ok'] === false` before trusting the response shape.
- The correct, proven field-extraction pattern for a Getnet session response (verified against real production data via `getnetcheck.php` this session) is: payment-specific status takes priority over session-level status —
  ```php
  $status = $session['status']['status'] ?? null;
  $payment0 = $session['payment'][0] ?? null;
  $paymentStatus = $payment0['status']['status'] ?? null;
  $norm = strtoupper((string)($paymentStatus ?? $status ?? ''));
  ```
- `reservas.process_id` is stored as a string column (every existing write uses `bind_param` type `s` for it) — cast to `(int)` before passing to `getSessionInfo()`.
- The guarded UPDATE pattern (from `docs/superpowers/plans/2026-08-05-payment-status-downgrade-guard.md`, already live in `getnet_notify.php`) must be reused verbatim in this feature's own UPDATE — this is the safety-critical part of the whole plan.
- No automated test framework exists — verification is local logic-simulation scripts (same style as the downgrade-guard fix), not live HTTP calls to Getnet or to either webhook endpoint.

---

### Task 1: Shared reconciliation function

**Files:**
- Create: `includes/reconcile_getnet.php`

**Interfaces:**
- Consumes: `helpers.php`'s `getSessionInfo()` (existing), a `mysqli $conn` (existing `db_config.php` connection).
- Produces: `reconcile_getnet_pending(mysqli $conn, int $limit = 50): array` — consumed by Task 2 (cron) and Task 3 (admin page). Return shape:
  ```php
  [
    'checked' => int,
    'corrected' => int,
    'corrections' => [
      ['reference' => string, 'from' => 'pendiente', 'to' => string], // 'realizado' or 'refund'
      ...
    ],
  ]
  ```

- [ ] **Step 1: Write `includes/reconcile_getnet.php`**

```php
<?php
// includes/reconcile_getnet.php
// Recovers 'pendiente' reservations that Getnet actually approved but whose
// webhook notification (getnet_notify.php) never arrived. See
// docs/superpowers/specs/2026-08-05-getnet-reconciliation-design.md
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

/**
 * Checks up to $limit 'pendiente' reservations (that have a process_id and
 * were created within the last day) against Getnet's live session API, and
 * corrects any Getnet actually resolved (approved or refunded) that our
 * webhook never recorded.
 *
 * @return array{checked:int, corrected:int, corrections:array<int,array{reference:string,from:string,to:string}>}
 */
function reconcile_getnet_pending(mysqli $conn, int $limit = 50): array
{
    $statusMap = [
        'APPROVED' => 'realizado',
        'PENDING'  => 'pendiente',
        'REJECTED' => 'fallido',
        'FAILED'   => 'fallido',
        'EXPIRED'  => 'fallido',
        'REFUNDED' => 'refund',
    ];

    $checked = 0;
    $corrected = 0;
    $corrections = [];

    $stmt = $conn->prepare("
        SELECT id_reserva, reference_id, process_id
        FROM reservas
        WHERE estado = 'pendiente'
          AND process_id IS NOT NULL
          AND fecha_reserva >= NOW() - INTERVAL 1 DAY
        ORDER BY id_reserva ASC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $checked++;
        $reference = (string)$row['reference_id'];
        $processId = (string)$row['process_id'];

        try {
            $session = getSessionInfo((int)$processId);
        } catch (Throwable $e) {
            error_log("reconcile_getnet: getSessionInfo threw for ref=$reference process_id=$processId: " . $e->getMessage());
            continue;
        }

        if (isset($session['ok']) && $session['ok'] === false) {
            error_log("reconcile_getnet: Getnet query failed for ref=$reference process_id=$processId: " . json_encode($session, JSON_UNESCAPED_SLASHES));
            continue;
        }

        // Proven field-extraction pattern (payment status takes priority
        // over session status) - see Global Constraints.
        $status = $session['status']['status'] ?? null;
        $payment0 = $session['payment'][0] ?? null;
        $paymentStatus = $payment0['status']['status'] ?? null;
        $norm = strtoupper((string)($paymentStatus ?? $status ?? ''));

        if ($norm === '' || !isset($statusMap[$norm])) {
            continue; // unknown or empty status - nothing safe to do
        }

        $nuevoEstado = $statusMap[$norm];

        // Only a real resolution is worth writing; a still-'pendiente' or
        // 'fallido' mapped result doesn't need reconciling here.
        if ($nuevoEstado !== 'realizado' && $nuevoEstado !== 'refund') {
            continue;
        }

        // Same guarded UPDATE as getnet_notify.php - defends against a
        // race with a real webhook resolving this row between our SELECT
        // and this UPDATE. See
        // docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
        $upd = $conn->prepare("
            UPDATE reservas
            SET estado = CASE
                           WHEN estado IN ('realizado', 'refund') AND ? <> 'refund' THEN estado
                           ELSE ?
                         END,
                updated_at = NOW()
            WHERE reference_id = ?
              AND process_id = ?
            LIMIT 1
        ");
        $upd->bind_param('ssss', $nuevoEstado, $nuevoEstado, $reference, $processId);
        $upd->execute();
        $rowsAffected = $upd->affected_rows;
        $upd->close();

        if ($rowsAffected > 0) {
            $corrected++;
            $corrections[] = ['reference' => $reference, 'from' => 'pendiente', 'to' => $nuevoEstado];
            error_log("reconcile_getnet: corrected ref=$reference pendiente -> $nuevoEstado (process_id=$processId)");
        }
    }

    return ['checked' => $checked, 'corrected' => $corrected, 'corrections' => $corrections];
}
```

- [ ] **Step 2: Syntax check**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l includes/reconcile_getnet.php
```

Expected: no syntax errors.

- [ ] **Step 3: Verify the query and guard logic with a local simulation**

No live DB or HTTP calls. Write a throwaway script (not committed) that:
1. Confirms the SQL text of both prepared statements (the SELECT and the guarded UPDATE) matches this task's Step 1 verbatim, by reading the file and asserting the exact strings are present (a simple `str_contains()` check against the file contents is sufficient — this catches accidental transcription drift).
2. Re-runs the exact same transition-matrix simulation used for the downgrade-guard fix (`docs/superpowers/plans/2026-08-05-payment-status-downgrade-guard.md` Task 1 Step 5), confirming the UPDATE's CASE logic is unchanged: `realizado`+`pendiente`→stays `realizado`; `refund`+`realizado`→stays `refund`; `pendiente`+`realizado`→becomes `realizado` (this is the new case this feature actually exercises — a `pendiente` row reconciled to `realizado` must genuinely write).
3. Confirms the field-extraction logic (`$paymentStatus ?? $status`) against a few representative fake Getnet response shapes (e.g. `['status'=>['status'=>'APPROVED'], 'payment'=>[['status'=>['status'=>'APPROVED']]]]` and a shape with only `status.status` set, no `payment` key) produces the expected normalized string in each case.

Run it, confirm all assertions pass, delete the script.

- [ ] **Step 4: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add includes/reconcile_getnet.php
git commit -m "Add shared Getnet payment reconciliation function"
```

---

### Task 2: Cron entry point

**Files:**
- Create: `includes/cron_reconcile_getnet.php`

**Interfaces:**
- Consumes: `reconcile_getnet_pending()` from Task 1.
- Produces: nothing for later tasks.

- [ ] **Step 1: Write `includes/cron_reconcile_getnet.php`**

Match the existing `includes/cron_send_confirmations.php`'s lock-file shape exactly (same lock path convention, same require pattern):

```php
<?php
// includes/cron_reconcile_getnet.php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$LOCK = sys_get_temp_dir() . '/cron_reconcile_getnet.lock';
$fh = fopen($LOCK, 'c');
if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) { exit; } // avoid overlap

require __DIR__ . '/../../db_config.php'; // defines $conn (mysqli)
require __DIR__ . '/reconcile_getnet.php';

$result = reconcile_getnet_pending($conn, 50);

error_log(sprintf(
    "cron_reconcile_getnet: checked=%d corrected=%d",
    $result['checked'],
    $result['corrected']
));
```

- [ ] **Step 2: Syntax check**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l includes/cron_reconcile_getnet.php
```

Expected: no syntax errors.

- [ ] **Step 3: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add includes/cron_reconcile_getnet.php
git commit -m "Add cron entry point for Getnet reconciliation"
```

---

### Task 3: Admin page and nav entry

**Files:**
- Create: `admin/getnet-reconcile.php`
- Modify: `admin/_nav.php`

**Interfaces:**
- Consumes: `reconcile_getnet_pending()` from Task 1, `admin/_auth.php` (existing), `admin/_nav.php` (existing, modified here).
- Produces: nothing for later tasks — last task before verification/deploy.

- [ ] **Step 1: Add the nav entry**

In `admin/_nav.php`, the `$toolsLinks` array currently reads:

```php
    $toolsLinks = [
        'gallery' => ['label' => 'Gallery Upload', 'href' => '/admin/gallery-upload.php'],
    ];
```

Change to:

```php
    $toolsLinks = [
        'gallery' => ['label' => 'Gallery Upload', 'href' => '/admin/gallery-upload.php'],
        'getnet-reconcile' => ['label' => 'Getnet Reconciliation', 'href' => '/admin/getnet-reconcile.php'],
    ];
```

- [ ] **Step 2: Write `admin/getnet-reconcile.php`**

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../db_config.php';
require __DIR__ . '/../includes/reconcile_getnet.php';

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_check'])) {
    $result = reconcile_getnet_pending($conn, 50);
}

$active = 'getnet-reconcile';
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
<?php require __DIR__ . '/_nav.php'; ?>
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
         <strong>Corrected:</strong> <?= (int)$result['corrected'] ?></p>

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
```

- [ ] **Step 3: Syntax check**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l admin/getnet-reconcile.php
php -l admin/_nav.php
```

Expected: no syntax errors on either file.

- [ ] **Step 4: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add admin/getnet-reconcile.php admin/_nav.php
git commit -m "Add admin page and Admin Tools entry for Getnet reconciliation"
```

---

### Task 4: Local verification

**Files:**
- None modified — this task only verifies. If a check fails, fix the affected file in place, re-verify, then re-run the relevant earlier task's syntax checks before re-committing.

**Interfaces:**
- Consumes: the committed state from Tasks 1-3.
- Produces: verification evidence only.

- [ ] **Step 1: Start a local PHP server and confirm the admin page requires login**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" -L http://localhost:8899/admin/getnet-reconcile.php
```

Expected: without a session cookie, `admin/_auth.php` redirects to `/login.php` — confirm via `-L` that the final page reached is the login page (check the response body for a login form, e.g. `curl -s -L http://localhost:8899/admin/getnet-reconcile.php | grep -c 'name="password"'` should return `1` or more).

- [ ] **Step 2: Confirm the nav entry appears correctly**

Load `admin/gallery-upload.php` (an existing, already-working admin tools page) the same way — if login is required locally and can't be completed without real credentials, instead statically confirm via `grep` that `admin/_nav.php`'s `$toolsLinks` array contains both `'gallery'` and `'getnet-reconcile'` keys, and that the HTML the array produces (trace through the `foreach` in `_nav.php`) would render both as dropdown items.

- [ ] **Step 3: Confirm the cron script's lock behavior**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php includes/cron_reconcile_getnet.php &
PID1=$!
php includes/cron_reconcile_getnet.php &
PID2=$!
wait $PID1 $PID2
echo "Both invocations completed without error (one should have exited immediately due to the lock)"
```

This will fail with a require error against `../../db_config.php` when run from this directory structure in the local sandbox (the file lives one level above the repo root in production, not present locally) — that's expected and fine; the goal of this step is only to confirm the *lock* mechanism itself works (one process acquires it, the second exits immediately via the `flock(..., LOCK_EX | LOCK_NB)` failure path, before either would reach the `require` line). If both processes hang or both error identically in a way that doesn't clearly show the lock took effect, adjust the script temporarily (comment out the `require` lines) to isolate and confirm the lock behavior in isolation, then revert.

- [ ] **Step 4: If any check failed, fix and re-verify**

Re-run Steps 1-3 after any fix, and re-run the relevant task's `php -l` checks before considering the fix complete.

- [ ] **Step 5: Commit (only if Step 4 required a fix)**

```bash
git add -A
git commit -m "Fix issue found during Getnet reconciliation verification"
```

If no fix was needed, skip this step.

- [ ] **Step 6: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

---

### Task 5: Deploy and give crontab instructions

**Files:**
- None modified — this task pushes already-committed changes and confirms the live site.

**Interfaces:**
- Consumes: the commits from Tasks 1-4.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push
```

- [ ] **Step 2: Remind the user to deploy and give the crontab line**

State clearly: pushing doesn't deploy — pull on the cPanel server. This is a pure-PHP, no-cache-affected change (new files + one small edit to an existing admin nav file), so no Cloudflare purge is needed (admin pages aren't edge-cached, confirmed `DYNAMIC` earlier this session for PHP pages generally).

Give the user this crontab line to add via cPanel's "Cron Jobs" tool, running hourly:
```
0 * * * * /usr/local/bin/php /home/stampst1/includes/cron_reconcile_getnet.php >/dev/null 2>&1
```
(Adjust the PHP binary path and the absolute path to `includes/cron_reconcile_getnet.php` to match how the existing `cron_send_confirmations.php` entry is already configured on this server — ask the user to check their existing crontab entry for the exact paths in use, since this repo doesn't have visibility into the server's actual crontab.)

- [ ] **Step 3: Once deployed, spot-check production**

Log into the admin panel, navigate to Admin Tools → Getnet Reconciliation, click "Run Check Now," and confirm it returns a result (checked count, corrected count) without an error — regardless of whether there happen to be any eligible reservations at that moment. Confirm the page requires login (log out and confirm `admin/getnet-reconcile.php` redirects to `/login.php`).

- [ ] **Step 4: Confirm the cron is registered**

Ask the user to confirm the crontab entry from Step 2 was added successfully (cPanel's Cron Jobs tool shows it in the list). No further action needed from this plan — the next scheduled run will pick it up automatically.
