# Payment Test Infrastructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the payment layer (downgrade guard, Getnet reconciliation) real, automated regression tests running against a real local test database, with PHPUnit itself kept entirely outside the deployed site.

**Architecture:** A dedicated local test database (`stampst1_stamptour_test`) with schema-only structure; PHPUnit as a standalone `.phar`, never touching the repo's own `composer.json`/`vendor`; tests execute real SQL against the test database rather than re-simulating logic in PHP; a small, additive, backward-compatible change to `reconcile_getnet_pending()` makes its one external dependency (`getSessionInfo()`) injectable for testing.

**Tech Stack:** PHPUnit (via `.phar`, not composer), mysqli against a local MySQL test database, plain PHP.

## Global Constraints

- Full rationale: `docs/superpowers/specs/2026-08-06-payment-test-infrastructure-design.md`. Read it first.
- **`getnet_notify.php` and `paypal_webhook.php` are not modified in this plan at all.** Their guarded UPDATE SQL is tested by copying the exact text into a test (verified against the live file at planning time below) plus a separate "drift guard" assertion that re-reads the live file's source and confirms the SQL is still present verbatim.
- **`includes/reconcile_getnet.php` gets exactly one additive change**: a new optional `callable $sessionLookup = 'getSessionInfo'` parameter, with the internal `getSessionInfo((int)$processId)` call site changed to `$sessionLookup((int)$processId)`. Every existing real caller (the cron script, the admin page) is unaffected — neither passes a third argument, so both continue calling the real `getSessionInfo`.
- Exact guarded SQL text, confirmed present in the live files at planning time:
  - `getnet_notify.php` (lines ~130-138):
    ```sql
    UPDATE reservas
    SET estado = CASE
                   WHEN estado IN ('realizado', 'refund') AND ? <> 'refund' THEN estado
                   ELSE ?
                 END,
        updated_at = NOW()
    WHERE reference_id = ?
      AND process_id = ?
    LIMIT 1
    ```
    (`bind_param("ssss", $nuevoEstado, $nuevoEstado, $reference, $requestId)`)
  - `paypal_webhook.php:321` (`PAYMENT.CAPTURE.PENDING`):
    ```sql
    UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1
    ```
  - `paypal_webhook.php:338` (`PAYMENT.CAPTURE.DENIED`/`DECLINED`):
    ```sql
    UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'fallido' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1
    ```
- Test database credentials: same local MySQL server/user already used this session (`localhost`, `stampst1_user` / `D4t`), different database name (`stampst1_stamptour_test`) so it can never collide with the real local dev database.
- No automated test framework existed before this plan — this plan creates the first one.

---

### Task 1: Test database schema and setup script

**Files:**
- Create: `tests/schema.sql`
- Create: `tests/setup-test-db.sh`
- Create: `tests/test_db_config.php.example`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: a runnable `tests/setup-test-db.sh` and the schema it applies — consumed by every later task that needs the test database to exist.

- [ ] **Step 1: Write `tests/schema.sql`**

```sql
-- tests/schema.sql
-- Schema-only structure for the payment-layer test database. Extracted
-- from the real local dump's CREATE TABLE statements - no data, no real
-- customer information. See
-- docs/superpowers/specs/2026-08-06-payment-test-infrastructure-design.md

CREATE TABLE IF NOT EXISTS `reservas` (
  `id_reserva` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_externo` varchar(100) DEFAULT NULL,
  `reference_id` varchar(64) NOT NULL,
  `process_id` int(11) DEFAULT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `capture_id` varchar(50) DEFAULT NULL,
  `fecha_reserva` date NOT NULL,
  `fecha_actividad` date NOT NULL,
  `adultos` int(11) NOT NULL DEFAULT '0',
  `ninos` int(11) NOT NULL DEFAULT '0',
  `infantes` int(11) NOT NULL DEFAULT '0',
  `airport_pickup` tinyint(1) NOT NULL DEFAULT '0',
  `airport_dropoff` tinyint(1) NOT NULL DEFAULT '0',
  `pais_origen` varchar(100) DEFAULT NULL,
  `idioma_actividad` varchar(100) DEFAULT NULL,
  `id_cupon` int(11) DEFAULT NULL,
  `id_titular` int(11) NOT NULL,
  `id_hotel` int(11) DEFAULT NULL,
  `id_guia` int(11) DEFAULT NULL,
  `id_conductor` int(11) DEFAULT NULL,
  `id_vendedor` int(11) DEFAULT NULL,
  `id_experiencia` bigint(20) UNSIGNED DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_venta` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monto_pagado` decimal(10,2) DEFAULT NULL,
  `moneda` varchar(3) DEFAULT NULL,
  `refund_id` varchar(50) DEFAULT NULL,
  `refund_monto` decimal(10,2) DEFAULT NULL,
  `estado` enum('pendiente','realizado','fallido','refund') NOT NULL DEFAULT 'pendiente',
  `email_sent_at` datetime DEFAULT NULL,
  `email_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `last_email_error` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reserva`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `titulares` (
  `id_titular` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `apellido` varchar(255) NOT NULL,
  `area_code` varchar(8) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  PRIMARY KEY (`id_titular`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `experiencias` (
  `id_experiencia` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `nombre_publico` varchar(255) NOT NULL,
  `precio_adulto` decimal(10,2) NOT NULL,
  `precio_nino` decimal(10,2) DEFAULT NULL,
  `precio_infante` decimal(10,2) DEFAULT NULL,
  `precio_concierge` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sale_adulto` decimal(10,2) DEFAULT NULL,
  `sale_nino` decimal(10,2) DEFAULT NULL,
  `TAR` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id_experiencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hoteles` (
  `id_hotel` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_hotel` varchar(255) NOT NULL,
  `direccion` text,
  `comuna` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_hotel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vendedores` (
  `id_vendedor` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_vendedor` varchar(255) NOT NULL,
  `canal_venta` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_vendedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `paypal_webhook_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` varchar(64) NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'received',
  `verified` enum('SUCCESS','FAILURE') DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `handled_at` timestamp NULL DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `headers` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `getnet_webhook_events` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source_ip` varchar(45) DEFAULT NULL,
  `request_id` varchar(64) NOT NULL,
  `reference` varchar(64) NOT NULL,
  `status_text` varchar(32) NOT NULL,
  `status_date` varchar(40) NOT NULL,
  `signature` varchar(64) NOT NULL,
  `calc_signature` varchar(64) NOT NULL,
  `signature_valid` tinyint(1) NOT NULL,
  `http_response` int(11) DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Write `tests/setup-test-db.sh`**

```bash
#!/usr/bin/env bash
# tests/setup-test-db.sh
# Idempotent: creates the test database if missing, (re)imports the schema.
# Safe to re-run any time to reset to a clean structure.
set -euo pipefail

DB_NAME="stampst1_stamptour_test"
DB_USER="stampst1_user"
DB_PASS="D4t"
DB_HOST="localhost"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARSET=utf8mb4;"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCRIPT_DIR/schema.sql"

echo "Test database '$DB_NAME' ready."
```

- [ ] **Step 3: Write `tests/test_db_config.php.example`**

```php
<?php
// tests/test_db_config.php.example
// Copy this file to tests/test_db_config.php (gitignored) to run tests
// locally. Defines $conn, a mysqli connection to the local TEST database -
// never the real production database.
$host = "localhost";
$user = "stampst1_user";
$password = "D4t";
$dbname = "stampst1_stamptour_test";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Error de conexión (test DB): " . $conn->connect_error);
}
```

- [ ] **Step 4: Add gitignore entries**

Add to `.gitignore` (new section, e.g. near the end):

```
# Local test infrastructure - real credentials/tooling stay out of git,
# only the test code itself (tests/*.php, schema.sql, setup scripts) ships
/tests/test_db_config.php
/tests/tools/
```

- [ ] **Step 5: Run the setup script and confirm it works**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
chmod +x tests/setup-test-db.sh
./tests/setup-test-db.sh
mysql -h localhost -u stampst1_user -pD4t stampst1_stamptour_test -e "SHOW TABLES;"
```

Expected: script completes with "Test database 'stampst1_stamptour_test' ready.", and `SHOW TABLES` lists all 6 tables from `schema.sql`.

- [ ] **Step 6: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
cp tests/test_db_config.php.example tests/test_db_config.php
git add tests/schema.sql tests/setup-test-db.sh tests/test_db_config.php.example .gitignore
git commit -m "Add payment test database schema and setup script"
```

(`tests/test_db_config.php` itself is gitignored per Step 4 — only its `.example` template is committed. The local copy you just created is needed by later tasks' local test runs.)

---

### Task 2: PHPUnit setup

**Files:**
- Create: `tests/setup-test-env.sh`
- Create: `phpunit.xml`
- Create: `tests/bootstrap.php`

**Interfaces:**
- Consumes: `tests/test_db_config.php` (from Task 1, gitignored, must exist locally to run tests — not to write this task's files).
- Produces: a working `php tests/tools/phpunit.phar -c phpunit.xml` command, consumed by every later task that adds actual test files.

- [ ] **Step 1: Write `tests/setup-test-env.sh`**

```bash
#!/usr/bin/env bash
# tests/setup-test-env.sh
# Downloads a pinned PHPUnit .phar into tests/tools/ (gitignored). No
# composer involvement - keeps the test framework itself out of the
# repo's own committed vendor/, which ships to production on every
# git pull deploy.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLS_DIR="$SCRIPT_DIR/tools"
PHPUNIT_VERSION="11.4.4"
PHPUNIT_URL="https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar"

mkdir -p "$TOOLS_DIR"
curl -sSL -o "$TOOLS_DIR/phpunit.phar" "$PHPUNIT_URL"
chmod +x "$TOOLS_DIR/phpunit.phar"
php "$TOOLS_DIR/phpunit.phar" --version
```

- [ ] **Step 2: Write `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="payment">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Write `tests/bootstrap.php`**

```php
<?php
// tests/bootstrap.php
// PHPUnit bootstrap: makes the test DB connection and the code under test
// available to every test class.
declare(strict_types=1);

$testDbConfig = __DIR__ . '/test_db_config.php';
if (!file_exists($testDbConfig)) {
    fwrite(STDERR, "Missing tests/test_db_config.php - copy tests/test_db_config.php.example and run tests/setup-test-db.sh first.\n");
    exit(1);
}
require $testDbConfig; // defines $conn (mysqli), connected to the TEST database

require_once __DIR__ . '/../includes/reconcile_getnet.php';
```

- [ ] **Step 4: Run setup and confirm PHPUnit executes**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
chmod +x tests/setup-test-env.sh
./tests/setup-test-env.sh
php tests/tools/phpunit.phar -c phpunit.xml
```

Expected: `setup-test-env.sh` prints a PHPUnit version string. Running the suite with no test files yet reports "No tests executed" (or equivalent) without a fatal error — confirms `phpunit.xml`'s bootstrap runs cleanly (test DB connects, `reconcile_getnet.php` loads without error).

- [ ] **Step 5: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add tests/setup-test-env.sh phpunit.xml tests/bootstrap.php
git commit -m "Add PHPUnit setup for payment tests"
```

---

### Task 3: Make `reconcile_getnet_pending()`'s Getnet lookup injectable

**Files:**
- Modify: `includes/reconcile_getnet.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `reconcile_getnet_pending(mysqli $conn, int $limit = 50, callable $sessionLookup = 'getSessionInfo'): array` — the new signature, consumed by Task 5's tests.

- [ ] **Step 1: Change the function signature**

In `includes/reconcile_getnet.php`, change:
```php
function reconcile_getnet_pending(mysqli $conn, int $limit = 50): array
```
to:
```php
function reconcile_getnet_pending(mysqli $conn, int $limit = 50, callable $sessionLookup = 'getSessionInfo'): array
```

- [ ] **Step 2: Change the call site**

Change:
```php
        try {
            $session = getSessionInfo((int)$processId);
        } catch (Throwable $e) {
```
to:
```php
        try {
            $session = $sessionLookup((int)$processId);
        } catch (Throwable $e) {
```

- [ ] **Step 3: Update the docblock**

Add a `@param` line for the new parameter, e.g.:
```php
 * @param callable $sessionLookup Getnet session lookup, defaults to the
 *                  real getSessionInfo() (helpers.php). Injectable for
 *                  testing - see docs/superpowers/specs/2026-08-06-payment-test-infrastructure-design.md
```

- [ ] **Step 4: Syntax check and confirm real callers are unaffected**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l includes/reconcile_getnet.php
grep -n "reconcile_getnet_pending(" includes/cron_reconcile_getnet.php admin/getnet-reconcile.php
```

Expected: no syntax errors. Both grep results show calls with exactly 2 arguments (`$conn`, `50`) — confirming neither real caller needs to change, and both will use the new parameter's default value (the real `getSessionInfo`).

- [ ] **Step 5: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add includes/reconcile_getnet.php
git commit -m "Make reconcile_getnet_pending's Getnet lookup injectable for testing"
```

---

### Task 4: Guard logic tests (getnet_notify.php and paypal_webhook.php)

**Files:**
- Create: `tests/GetnetGuardTest.php`
- Create: `tests/PaypalGuardTest.php`

**Interfaces:**
- Consumes: the test database from Task 1, PHPUnit from Task 2.
- Produces: nothing for later tasks — these are leaf test files.

- [ ] **Step 1: Write `tests/GetnetGuardTest.php`**

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GetnetGuardTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->conn->query("DELETE FROM reservas");
    }

    protected function tearDown(): void
    {
        $this->conn->query("DELETE FROM reservas");
    }

    /**
     * The exact guarded UPDATE from getnet_notify.php - copied here
     * deliberately (not extracted into a shared function, per this
     * project's constraint against refactoring the live webhook files).
     * testSqlMatchesLiveFile() below guards against this copy drifting
     * from the real file.
     */
    private function runGuardedUpdate(string $nuevoEstado, string $reference, string $processId): int
    {
        $stmt = $this->conn->prepare("
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
        $stmt->bind_param('ssss', $nuevoEstado, $nuevoEstado, $reference, $processId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    private function insertReserva(string $reference, string $processId, string $estado): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO reservas (reference_id, process_id, fecha_reserva, fecha_actividad, id_titular, estado)
            VALUES (?, ?, CURDATE(), CURDATE(), 1, ?)
        ");
        $stmt->bind_param('sis', $reference, $processId, $estado);
        $stmt->execute();
        $stmt->close();
    }

    private function currentEstado(string $reference): string
    {
        $stmt = $this->conn->prepare("SELECT estado FROM reservas WHERE reference_id = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->bind_result($estado);
        $stmt->fetch();
        $stmt->close();
        return $estado;
    }

    public function testRealizadoStaysRealizadoWhenIncomingIsPendiente(): void
    {
        $this->insertReserva('TEST_A', '111', 'realizado');
        $this->runGuardedUpdate('pendiente', 'TEST_A', '111');
        $this->assertSame('realizado', $this->currentEstado('TEST_A'));
    }

    public function testRealizadoStaysRealizadoWhenIncomingIsFallido(): void
    {
        $this->insertReserva('TEST_B', '112', 'realizado');
        $this->runGuardedUpdate('fallido', 'TEST_B', '112');
        $this->assertSame('realizado', $this->currentEstado('TEST_B'));
    }

    public function testRefundStaysRefundWhenIncomingIsRealizado(): void
    {
        $this->insertReserva('TEST_C', '113', 'refund');
        $this->runGuardedUpdate('realizado', 'TEST_C', '113');
        $this->assertSame('refund', $this->currentEstado('TEST_C'));
    }

    public function testRealizadoCanBecomeRefund(): void
    {
        $this->insertReserva('TEST_D', '114', 'realizado');
        $this->runGuardedUpdate('refund', 'TEST_D', '114');
        $this->assertSame('refund', $this->currentEstado('TEST_D'));
    }

    public function testPendienteBecomesRealizadoOnApproval(): void
    {
        $this->insertReserva('TEST_E', '115', 'pendiente');
        $this->runGuardedUpdate('realizado', 'TEST_E', '115');
        $this->assertSame('realizado', $this->currentEstado('TEST_E'));
    }

    public function testMismatchedProcessIdMatchesNoRows(): void
    {
        $this->insertReserva('TEST_F', '116', 'pendiente');
        $affected = $this->runGuardedUpdate('realizado', 'TEST_F', '999');
        $this->assertSame(0, $affected);
        $this->assertSame('pendiente', $this->currentEstado('TEST_F'));
    }

    /**
     * Drift guard: if a future edit changes getnet_notify.php's guarded
     * SQL without updating this test's copy above, this fails loudly
     * instead of the test silently testing stale SQL forever.
     */
    public function testSqlMatchesLiveFile(): void
    {
        $source = file_get_contents(__DIR__ . '/../getnet_notify.php');
        $this->assertNotFalse($source);
        $this->assertStringContainsString(
            "WHEN estado IN ('realizado', 'refund') AND ? <> 'refund' THEN estado",
            $source,
            "getnet_notify.php's guard SQL has changed - update this test's copy in runGuardedUpdate() to match, then update this assertion."
        );
    }
}
```

- [ ] **Step 2: Write `tests/PaypalGuardTest.php`**

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PaypalGuardTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->conn->query("DELETE FROM reservas");
    }

    protected function tearDown(): void
    {
        $this->conn->query("DELETE FROM reservas");
    }

    /** Exact copy of paypal_webhook.php:321's PAYMENT.CAPTURE.PENDING guard. */
    private function runPendingGuard(string $reference): int
    {
        $stmt = $this->conn->prepare("UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /** Exact copy of paypal_webhook.php:338's PAYMENT.CAPTURE.DENIED/DECLINED guard. */
    private function runDeniedGuard(string $reference): int
    {
        $stmt = $this->conn->prepare("UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'fallido' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    private function insertReserva(string $reference, string $estado): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO reservas (reference_id, fecha_reserva, fecha_actividad, id_titular, estado)
            VALUES (?, CURDATE(), CURDATE(), 1, ?)
        ");
        $stmt->bind_param('ss', $reference, $estado);
        $stmt->execute();
        $stmt->close();
    }

    private function currentEstado(string $reference): string
    {
        $stmt = $this->conn->prepare("SELECT estado FROM reservas WHERE reference_id = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->bind_result($estado);
        $stmt->fetch();
        $stmt->close();
        return $estado;
    }

    public function testPendingGuardProtectsRealizado(): void
    {
        $this->insertReserva('TEST_PP_A', 'realizado');
        $this->runPendingGuard('TEST_PP_A');
        $this->assertSame('realizado', $this->currentEstado('TEST_PP_A'));
    }

    public function testPendingGuardProtectsRefund(): void
    {
        $this->insertReserva('TEST_PP_B', 'refund');
        $this->runPendingGuard('TEST_PP_B');
        $this->assertSame('refund', $this->currentEstado('TEST_PP_B'));
    }

    public function testPendingGuardAllowsPendienteToStayPendiente(): void
    {
        $this->insertReserva('TEST_PP_C', 'pendiente');
        $this->runPendingGuard('TEST_PP_C');
        $this->assertSame('pendiente', $this->currentEstado('TEST_PP_C'));
    }

    public function testDeniedGuardProtectsRealizado(): void
    {
        $this->insertReserva('TEST_PP_D', 'realizado');
        $this->runDeniedGuard('TEST_PP_D');
        $this->assertSame('realizado', $this->currentEstado('TEST_PP_D'));
    }

    public function testDeniedGuardAllowsPendienteToBecomeFallido(): void
    {
        $this->insertReserva('TEST_PP_E', 'pendiente');
        $this->runDeniedGuard('TEST_PP_E');
        $this->assertSame('fallido', $this->currentEstado('TEST_PP_E'));
    }

    /** Drift guard for both statements. */
    public function testSqlMatchesLiveFile(): void
    {
        $source = file_get_contents(__DIR__ . '/../paypal_webhook.php');
        $this->assertNotFalse($source);
        $this->assertStringContainsString(
            "UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END",
            $source,
            "paypal_webhook.php's PENDING guard SQL has changed - update this test's copy in runPendingGuard() to match, then update this assertion."
        );
        $this->assertStringContainsString(
            "UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'fallido' END",
            $source,
            "paypal_webhook.php's DENIED/DECLINED guard SQL has changed - update this test's copy in runDeniedGuard() to match, then update this assertion."
        );
    }
}
```

- [ ] **Step 3: Run these two test files and confirm all pass**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php tests/tools/phpunit.phar -c phpunit.xml tests/GetnetGuardTest.php tests/PaypalGuardTest.php
```

Expected: all tests pass (13 tests total across both files: 6 + 1 in GetnetGuardTest, 5 + 1 in PaypalGuardTest).

- [ ] **Step 4: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add tests/GetnetGuardTest.php tests/PaypalGuardTest.php
git commit -m "Add regression tests for the payment downgrade guard"
```

---

### Task 5: `reconcile_getnet_pending()` integration tests

**Files:**
- Create: `tests/GetnetReconciliationTest.php`

**Interfaces:**
- Consumes: `reconcile_getnet_pending()` (Task 3's injectable version), the test database (Task 1), PHPUnit (Task 2).
- Produces: nothing for later tasks.

- [ ] **Step 1: Write `tests/GetnetReconciliationTest.php`**

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GetnetReconciliationTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->conn->query("DELETE FROM reservas");
    }

    protected function tearDown(): void
    {
        $this->conn->query("DELETE FROM reservas");
    }

    private function insertReserva(
        string $reference,
        ?string $processId,
        string $estado,
        string $fechaReserva = 'today'
    ): void {
        $stmt = $this->conn->prepare("
            INSERT INTO reservas (reference_id, process_id, fecha_reserva, fecha_actividad, id_titular, estado)
            VALUES (?, ?, ?, CURDATE(), 1, ?)
        ");
        $fecha = date('Y-m-d', strtotime($fechaReserva));
        $stmt->bind_param('siss', $reference, $processId, $fecha, $estado);
        $stmt->execute();
        $stmt->close();
    }

    private function currentEstado(string $reference): string
    {
        $stmt = $this->conn->prepare("SELECT estado FROM reservas WHERE reference_id = ?");
        $stmt->bind_param('s', $reference);
        $stmt->execute();
        $stmt->bind_result($estado);
        $stmt->fetch();
        $stmt->close();
        return $estado;
    }

    public function testApprovedGetnetSessionCorrectsPendienteToRealizado(): void
    {
        $this->insertReserva('TEST_RC_A', '2001', 'pendiente');
        $fakeLookup = fn(int $id) => ['status' => ['status' => 'APPROVED']];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['corrected']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame('realizado', $this->currentEstado('TEST_RC_A'));
        $this->assertCount(1, $result['corrections']);
        $this->assertSame('TEST_RC_A', $result['corrections'][0]['reference']);
        $this->assertSame('realizado', $result['corrections'][0]['to']);
    }

    public function testStillPendingGetnetSessionMakesNoWrite(): void
    {
        $this->insertReserva('TEST_RC_B', '2002', 'pendiente');
        $fakeLookup = fn(int $id) => ['status' => ['status' => 'PENDING']];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(0, $result['corrected']);
        $this->assertSame('pendiente', $this->currentEstado('TEST_RC_B'));
    }

    public function testFailedGetnetLookupIncrementsFailedNotCorrected(): void
    {
        $this->insertReserva('TEST_RC_C', '2003', 'pendiente');
        $fakeLookup = fn(int $id) => ['ok' => false, 'error' => 'network'];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(0, $result['corrected']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('pendiente', $this->currentEstado('TEST_RC_C'));
    }

    public function testThrowingLookupIncrementsFailedAndDoesNotCrash(): void
    {
        $this->insertReserva('TEST_RC_D', '2004', 'pendiente');
        $fakeLookup = function (int $id): array {
            throw new RuntimeException('simulated network failure');
        };

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('pendiente', $this->currentEstado('TEST_RC_D'));
    }

    public function testRefundedGetnetSessionCorrectsToRefund(): void
    {
        $this->insertReserva('TEST_RC_E', '2005', 'pendiente');
        $fakeLookup = fn(int $id) => ['status' => ['status' => 'REFUNDED']];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(1, $result['corrected']);
        $this->assertSame('refund', $this->currentEstado('TEST_RC_E'));
    }

    public function testRowWithoutProcessIdIsNeverSelected(): void
    {
        $this->insertReserva('TEST_RC_F', null, 'pendiente');
        $fakeLookup = function (int $id): array {
            throw new RuntimeException('should never be called - row has no process_id');
        };

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(0, $result['checked']);
    }

    public function testRowOutsideOneDayWindowIsNeverSelected(): void
    {
        $this->insertReserva('TEST_RC_G', '2007', 'pendiente', '-2 days');
        $fakeLookup = function (int $id): array {
            throw new RuntimeException('should never be called - row is outside the 1-day window');
        };

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(0, $result['checked']);
    }

    public function testAlreadyRealizadoRowIsNeverSelected(): void
    {
        $this->insertReserva('TEST_RC_H', '2008', 'realizado');
        $fakeLookup = function (int $id): array {
            throw new RuntimeException('should never be called - row is not pendiente');
        };

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame(0, $result['checked']);
    }

    public function testPaymentStatusTakesPriorityOverSessionStatus(): void
    {
        $this->insertReserva('TEST_RC_I', '2009', 'pendiente');
        // session-level status says PENDING, but payment[0].status.status says APPROVED -
        // payment status must win, per the proven field-extraction pattern.
        $fakeLookup = fn(int $id) => [
            'status' => ['status' => 'PENDING'],
            'payment' => [['status' => ['status' => 'APPROVED']]],
        ];

        $result = reconcile_getnet_pending($this->conn, 50, $fakeLookup);

        $this->assertSame('realizado', $this->currentEstado('TEST_RC_I'));
    }

    public function testDefaultParameterStillCallsRealGetSessionInfo(): void
    {
        // No fixture reservation matches (no eligible rows), so the real
        // getSessionInfo() is never actually invoked - this only proves
        // the function is callable with its default third argument and
        // doesn't fatal, confirming real callers (cron, admin page) that
        // pass no third argument remain unaffected by Task 3's change.
        $result = reconcile_getnet_pending($this->conn, 50);
        $this->assertSame(0, $result['checked']);
    }
}
```

- [ ] **Step 2: Run this test file and confirm all pass**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php tests/tools/phpunit.phar -c phpunit.xml tests/GetnetReconciliationTest.php
```

Expected: all 10 tests pass.

- [ ] **Step 3: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add tests/GetnetReconciliationTest.php
git commit -m "Add integration tests for reconcile_getnet_pending"
```

---

### Task 6: Full-suite verification, including deliberately breaking things

**Files:**
- None modified — this task only verifies. If Step 3 or 4 reveals a real problem (not the deliberate break, an actual gap), fix the affected test or source file, re-verify, then commit the fix separately.

**Interfaces:**
- Consumes: everything from Tasks 1-5.
- Produces: verification evidence only — final task in the plan.

- [ ] **Step 1: Run the full suite clean**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php tests/tools/phpunit.phar -c phpunit.xml
```

Expected: all tests across all 3 test files pass (23 tests total: 7 in GetnetGuardTest, 6 in PaypalGuardTest, 10 in GetnetReconciliationTest — confirm the printed summary shows 0 failures, 0 errors).

- [ ] **Step 2: Deliberately break `getnet_notify.php`'s guard and confirm the drift-guard test catches it**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
cp getnet_notify.php /tmp/getnet_notify.php.bak
sed -i '' "s/WHEN estado IN ('realizado', 'refund') AND ? <> 'refund' THEN estado/WHEN estado = 'realizado' THEN estado/" getnet_notify.php
php tests/tools/phpunit.phar -c phpunit.xml tests/GetnetGuardTest.php
```

Expected: `testSqlMatchesLiveFile` fails with the custom message about the guard SQL having changed. This is the proof the drift guard actually works, not just that it exists.

```bash
cp /tmp/getnet_notify.php.bak getnet_notify.php
php -l getnet_notify.php
git diff getnet_notify.php
```

Expected: `php -l` clean, `git diff` shows no changes (file fully restored to its committed state).

- [ ] **Step 3: Deliberately break `reconcile_getnet_pending()`'s write restriction and confirm a test catches it**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
cp includes/reconcile_getnet.php /tmp/reconcile_getnet.php.bak
```

Temporarily comment out or invert the `if ($nuevoEstado !== 'realizado' && $nuevoEstado !== 'refund') { continue; }` check in `includes/reconcile_getnet.php` (e.g. change to `if (false) { continue; }`), then:

```bash
php tests/tools/phpunit.phar -c phpunit.xml tests/GetnetReconciliationTest.php
```

Expected: `testStillPendingGetnetSessionMakesNoWrite` fails (a still-`PENDING` mocked result now incorrectly writes, since the skip condition was disabled).

```bash
cp /tmp/reconcile_getnet.php.bak includes/reconcile_getnet.php
php -l includes/reconcile_getnet.php
git diff includes/reconcile_getnet.php
```

Expected: `php -l` clean, `git diff` shows no changes (fully restored).

- [ ] **Step 4: Confirm nothing test-related leaked into the deployed footprint**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git status --short
git diff --stat composer.json composer.lock
git ls-files vendor/ | wc -l
```

Expected: `git status --short` clean (both backup-restore round trips left no residue). `composer.json`/`composer.lock` show no diff. The `vendor/` file count matches what it was before this plan started (confirm against a note taken before Task 1, or simply confirm no PHPUnit-related paths appear: `git ls-files vendor/ | grep -i phpunit` returns nothing).

- [ ] **Step 5: Run the full suite one final time**

```bash
php tests/tools/phpunit.phar -c phpunit.xml
```

Expected: clean pass, same as Step 1 — confirms the deliberate-break round trips in Steps 2-3 left the real source files exactly as committed.
