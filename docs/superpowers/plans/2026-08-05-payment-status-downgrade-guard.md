# Payment Status Downgrade Guard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop `getnet_notify.php` and `paypal_webhook.php` from ever downgrading a reservation's `estado` away from `realizado` (except for a genuine refund), fixing a confirmed live bug where a stale/late webhook from an abandoned payment attempt on one provider can silently mark a reservation paid via the *other* provider as unpaid again.

**Architecture:** Both files get the same shape of change — the unconditional `SET estado = ...` in each affected `UPDATE` becomes a `CASE`/conditional expression that only allows the write when the current stored `estado` isn't already `realizado`, or when the new status is a refund.

**Tech Stack:** Plain PHP + mysqli prepared statements, no build step, no test framework in this repo.

## Global Constraints

- Full rationale and the confirmed incident evidence: `docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md`. Read it first.
- Exactly 3 `UPDATE` statements change, across 2 files:
  - `getnet_notify.php` — the single `estado = ?` update (around line 128).
  - `paypal_webhook.php` — the `PAYMENT.CAPTURE.PENDING` case's `estado='pendiente'` update (around line 318).
  - `paypal_webhook.php` — the `PAYMENT.CAPTURE.DENIED`/`DECLINED` case's `estado='fallido'` update (around line 332).
- `PAYMENT.CAPTURE.REFUNDED` (in `paypal_webhook.php`) and `PAYMENT.CAPTURE.COMPLETED`'s own `estado='realizado'` update are **not touched** — moving *to* `realizado` or *to* `refund` are both legitimate and already correct as unconditional writes.
- `CHECKOUT.ORDER.APPROVED` (in `paypal_webhook.php`) is **not touched** — it already has the correct guard (`estado = IF(estado='realizado', estado, 'pendiente')`).
- No automated test framework exists in this repo — verification is a local PHP script that simulates the exact `CASE` logic against every real state transition, not a live DB test.

---

### Task 1: Add the downgrade guard to both webhook handlers

**Files:**
- Modify: `getnet_notify.php`
- Modify: `paypal_webhook.php`

**Interfaces:**
- Consumes: nothing (single task, no dependencies).
- Produces: nothing for later tasks — this plan has only one task.

- [ ] **Step 1: Guard `getnet_notify.php`'s status update**

Replace:

```php
  // Update mínimo: exige que el webhook corresponda al process_id vigente.
  $stmt = $conn->prepare("
    UPDATE reservas
    SET estado = ?, updated_at = NOW()
    WHERE reference_id = ?
      AND process_id = ?
    LIMIT 1
  ");
  $stmt->bind_param("sss", $nuevoEstado, $reference, $requestId);
```

With:

```php
  // Update mínimo: exige que el webhook corresponda al process_id vigente.
  // Guard: nunca permitir que un webhook (posiblemente tardío, de una sesión
  // abandonada) baje una reserva ya 'realizado' a otro estado, salvo un
  // 'refund' genuino. Ver docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
  $stmt = $conn->prepare("
    UPDATE reservas
    SET estado = CASE
                   WHEN estado = 'realizado' AND ? <> 'refund' THEN estado
                   ELSE ?
                 END,
        updated_at = NOW()
    WHERE reference_id = ?
      AND process_id = ?
    LIMIT 1
  ");
  $stmt->bind_param("ssss", $nuevoEstado, $nuevoEstado, $reference, $requestId);
```

(Everything below this block — the `if (!$stmt->execute())` error check and `$stmt->close()` — is unchanged.)

- [ ] **Step 2: Guard `paypal_webhook.php`'s `PAYMENT.CAPTURE.PENDING` update**

Replace:

```php
      if ($referenceId) {
        $stmt = $conn->prepare("UPDATE reservas SET estado='pendiente' WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $referenceId);
        $stmt->execute(); $stmt->close();
      }
      break;
    }

    case 'PAYMENT.CAPTURE.DENIED':
```

With:

```php
      if ($referenceId) {
        // Guard: no bajar una reserva ya 'realizado' a 'pendiente' por un
        // evento tardío/duplicado. Ver
        // docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
        $stmt = $conn->prepare("UPDATE reservas SET estado = CASE WHEN estado = 'realizado' THEN estado ELSE 'pendiente' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $referenceId);
        $stmt->execute(); $stmt->close();
      }
      break;
    }

    case 'PAYMENT.CAPTURE.DENIED':
```

- [ ] **Step 3: Guard `paypal_webhook.php`'s `PAYMENT.CAPTURE.DENIED`/`DECLINED` update**

Replace:

```php
      if ($referenceId) {
        $stmt = $conn->prepare("UPDATE reservas SET estado='fallido' WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $referenceId);
        $stmt->execute(); $stmt->close();
      }
```

With (this is the `DENIED`/`DECLINED` case block, immediately after the one from Step 2 — do not confuse the two, they have identical surrounding shape but different literal status string):

```php
      if ($referenceId) {
        // Guard: no bajar una reserva ya 'realizado' a 'fallido' por un
        // evento tardío/duplicado. Ver
        // docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
        $stmt = $conn->prepare("UPDATE reservas SET estado = CASE WHEN estado = 'realizado' THEN estado ELSE 'fallido' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $referenceId);
        $stmt->execute(); $stmt->close();
      }
```

- [ ] **Step 4: Syntax check both files**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l getnet_notify.php
php -l paypal_webhook.php
```

Expected: no syntax errors on either file.

- [ ] **Step 5: Verify the transition matrix with a local logic simulation**

There's no test framework in this repo, and these are live payment webhooks — do not hit them with real or fake HTTP requests. Instead, write a standalone PHP script that reproduces the exact `CASE` expression logic (translate the SQL `CASE`/`WHEN` into equivalent PHP conditionals — same operators, same literal comparisons) and runs it against every real transition the `estado` column can be in. Save it as a throwaway script (not committed) and run it, e.g. `/tmp/verify-downgrade-guard.php`:

```php
<?php
// Simulates getnet_notify.php's CASE expression:
//   estado = CASE WHEN estado = 'realizado' AND $new <> 'refund' THEN estado ELSE $new END
function getnetNewEstado(string $current, string $new): string {
    if ($current === 'realizado' && $new !== 'refund') {
        return $current;
    }
    return $new;
}

// Simulates paypal_webhook.php's PENDING/DENIED-DECLINED CASE expressions:
//   estado = CASE WHEN estado = 'realizado' THEN estado ELSE $new END
function paypalNewEstado(string $current, string $new): string {
    if ($current === 'realizado') {
        return $current;
    }
    return $new;
}

$cases = [
    // [label, current, incoming, expected, fn]
    ['Getnet: pendiente -> realizado (normal approval)', 'pendiente', 'realizado', 'realizado', 'getnetNewEstado'],
    ['Getnet: pendiente -> fallido (normal rejection)',  'pendiente', 'fallido',   'fallido',   'getnetNewEstado'],
    ['Getnet: realizado -> pendiente (THE BUG, must block)', 'realizado', 'pendiente', 'realizado', 'getnetNewEstado'],
    ['Getnet: realizado -> fallido (same bug shape, must block)', 'realizado', 'fallido', 'realizado', 'getnetNewEstado'],
    ['Getnet: realizado -> refund (legitimate, must work)', 'realizado', 'refund', 'refund', 'getnetNewEstado'],
    ['Getnet: fallido -> realizado (retry succeeds, must work)', 'fallido', 'realizado', 'realizado', 'getnetNewEstado'],

    ['PayPal PENDING: pendiente -> pendiente (no-op, fine)', 'pendiente', 'pendiente', 'pendiente', 'paypalNewEstado'],
    ['PayPal PENDING: realizado -> pendiente (THE BUG, must block)', 'realizado', 'pendiente', 'realizado', 'paypalNewEstado'],
    ['PayPal DENIED: pendiente -> fallido (normal rejection)', 'pendiente', 'fallido', 'fallido', 'paypalNewEstado'],
    ['PayPal DENIED: realizado -> fallido (same bug shape, must block)', 'realizado', 'fallido', 'realizado', 'paypalNewEstado'],
];

$failures = 0;
foreach ($cases as [$label, $current, $incoming, $expected, $fn]) {
    $actual = $fn($current, $incoming);
    $pass = $actual === $expected;
    if (!$pass) $failures++;
    printf("[%s] %s (current=%s incoming=%s -> got=%s expected=%s)\n",
        $pass ? 'PASS' : 'FAIL', $label, $current, $incoming, $actual, $expected);
}

echo $failures === 0 ? "\nAll transitions correct.\n" : "\n$failures FAILURE(S).\n";
exit($failures === 0 ? 0 : 1);
```

Run it: `php /tmp/verify-downgrade-guard.php`

Expected: all 10 cases print `PASS`, final line "All transitions correct.", exit code 0.

If any case fails, the SQL `CASE` expressions in Steps 1-3 don't match this simulation exactly — re-check the actual PHP file contents against the simulation logic (not the other way around: the simulation encodes the *intended* behavior from the design spec, so a mismatch means the file edit is wrong).

- [ ] **Step 6: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add getnet_notify.php paypal_webhook.php
git commit -m "Guard webhook handlers against downgrading an already-paid reservation"
```
