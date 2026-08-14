# Hotel Free-Text Fallback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop `submit.php` and `return.php` from inserting customer/admin-typed hotel names into the `hoteles` catalog table; save unmatched names as free text on the reservation itself instead, and show that text everywhere the hotel currently displays.

**Architecture:** One new pure-ish resolver function (`resolve_hotel_selection()`) replaces the duplicated hotel-resolution logic currently inline in `submit.php` and `return.php`. It reads-only from `hoteles` (never inserts) and returns `[id_hotel, hotel_manual]`, where exactly one is non-null or both are null. Both write paths persist both columns together on every write, and 7 existing read paths fall back to `hotel_manual` when there's no catalog match.

**Tech Stack:** PHP 8 + mysqli (procedural style, matches existing files), PHPUnit 11.4.4 via `tests/tools/phpunit.phar`, MySQL/MariaDB.

## Global Constraints

- `hoteles` is read-only from every customer/admin-facing booking flow — no `INSERT INTO hoteles` may exist anywhere in `submit.php` or `return.php` after this plan.
- `id_hotel` and `hotel_manual` on `reservas` are mutually exclusive: every write sets both columns together (never a partial update of just one), so switching between a catalog pick and free text can't leave a stale value in the other column.
- `hotel_manual` is `VARCHAR(255)`, matching the existing `hoteles.nombre_hotel` convention — not a design gap, a deliberate match.
- Hotel-name matching against `hoteles` stays an exact `nombre_hotel` comparison (no fuzzy/partial matching) — same as the code being replaced.
- No admin UI to promote a `hotel_manual` value into a real `hoteles` row — out of scope for this plan.

---

## Task 1: Schema, shared resolver function, and unit tests

**Files:**
- Modify: `tests/schema.sql` (reservas table definition)
- Modify: `tests/bootstrap.php:40-41`
- Create: `includes/hotel_resolver.php`
- Create: `tests/HotelResolverTest.php`

**Interfaces:**
- Produces: `resolve_hotel_selection(mysqli $conn, array $post): array` — returns a 2-element array `[?int $id_hotel, ?string $hotel_manual]`. Consumed by Task 2.

**Context:** `reservas.hotel_manual` already exists on production and on the local dev DB (`stampst1_stamptour`, added directly by the site owner / during design review) — this task does not need to touch either of those databases. It only needs to exist in the PHPUnit test DB, which is (re)built from `tests/schema.sql` by `tests/setup-test-db.sh`.

- [ ] **Step 1: Set up the local PHPUnit test environment, if not already present in this worktree**

Check first — `tests/test_db_config.php` is gitignored, so a fresh worktree won't have it even if another worktree does:

```bash
ls tests/test_db_config.php
```

If that file is missing:

```bash
cp tests/test_db_config.php.example tests/test_db_config.php
```

Edit `tests/test_db_config.php` and fill in `$user`/`$password` with the same local MySQL credentials already used in `/Users/miguelmontero/Documents/superpowers/db_config.php` (same MySQL server, just a different — test — database name, already set to `stampst1_stamptour_test` in the copied file). Do not copy those credentials into any committed file — `tests/test_db_config.php` is gitignored specifically so this is safe to fill in locally.

Then, if `tests/tools/phpunit.phar` doesn't exist yet:

```bash
bash tests/setup-test-env.sh
```

Expected: prints the PHPUnit version (11.4.4) at the end, confirming the phar downloaded and runs.

- [ ] **Step 2: Add `hotel_manual` to the test DB schema**

In `tests/schema.sql`, in the `reservas` table definition, add this line immediately after the existing `id_hotel` line:

```sql
  `id_hotel` int(11) DEFAULT NULL,
  `hotel_manual` varchar(255) DEFAULT NULL,
```

- [ ] **Step 3: Build/rebuild the test database with the new column**

```bash
bash tests/setup-test-db.sh
```

Expected: ends with `Test database 'stampst1_stamptour_test' ready.`

- [ ] **Step 4: Write the failing test**

Create `tests/HotelResolverTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HotelResolverTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->conn->query("DELETE FROM hoteles WHERE nombre_hotel LIKE 'TEST\\_%' ESCAPE '\\\\'");
    }

    protected function tearDown(): void
    {
        $this->conn->query("DELETE FROM hoteles WHERE nombre_hotel LIKE 'TEST\\_%' ESCAPE '\\\\'");
    }

    private function insertTestHotel(string $nombre): int
    {
        $stmt = $this->conn->prepare("INSERT INTO hoteles (nombre_hotel) VALUES (?)");
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    private function hotelesCount(): int
    {
        $result = $this->conn->query("SELECT COUNT(*) c FROM hoteles");
        return (int)$result->fetch_assoc()['c'];
    }

    public function testDirectIdHotelFromSelect(): void
    {
        $id = $this->insertTestHotel('TEST_Direct Select Hotel');
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, ['id_hotel' => (string)$id]);
        $this->assertSame($id, $id_hotel);
        $this->assertNull($hotel_manual);
    }

    public function testNotListedWithCustomTextIsSavedAsManualText(): void
    {
        $before = $this->hotelesCount();
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, [
            'hotelOption' => 'not_listed',
            'customHotel' => 'TEST_Some Random Guesthouse',
        ]);
        $this->assertNull($id_hotel);
        $this->assertSame('TEST_Some Random Guesthouse', $hotel_manual);
        $this->assertSame($before, $this->hotelesCount(), 'must not insert into hoteles');
    }

    public function testNotListedWithoutTextResolvesToNothing(): void
    {
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, [
            'hotelOption' => 'not_listed',
            'customHotel' => '   ',
        ]);
        $this->assertNull($id_hotel);
        $this->assertNull($hotel_manual);
    }

    public function testDecideLaterResolvesToNothing(): void
    {
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, [
            'hotelOption' => 'decide_later',
            'hotel' => 'TEST_Should Be Ignored',
        ]);
        $this->assertNull($id_hotel);
        $this->assertNull($hotel_manual);
    }

    public function testAutocompleteTextMatchingCatalogResolvesToIdHotel(): void
    {
        $id = $this->insertTestHotel('TEST_Aji Hostel Clone');
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, ['hotel' => 'TEST_Aji Hostel Clone']);
        $this->assertSame($id, $id_hotel);
        $this->assertNull($hotel_manual);
    }

    public function testAutocompleteTextNotMatchingCatalogFallsBackToManualText(): void
    {
        $before = $this->hotelesCount();
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, ['hotel' => 'TEST_Nonexistent Hotel Name']);
        $this->assertNull($id_hotel);
        $this->assertSame('TEST_Nonexistent Hotel Name', $hotel_manual);
        $this->assertSame($before, $this->hotelesCount(), 'must not insert into hoteles');
    }

    public function testNoHotelFieldsAtAllResolvesToNothing(): void
    {
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, []);
        $this->assertNull($id_hotel);
        $this->assertNull($hotel_manual);
    }
}
```

- [ ] **Step 5: Run the test to verify it fails**

```bash
php tests/tools/phpunit.phar -c phpunit.xml --filter HotelResolverTest
```

Expected: FAIL / ERROR — `resolve_hotel_selection()` is not defined (the include doesn't exist yet).

- [ ] **Step 6: Implement the resolver function**

Create `includes/hotel_resolver.php`:

```php
<?php
// includes/hotel_resolver.php
// Resolves a submitted hotel selection (booking_manual.php's numeric
// <select>, or preferentials.php's/return.php's autocomplete text +
// "not listed" custom text + "decide later" fields) into
// [id_hotel, hotel_manual]. Exactly one of the two is non-null, or both
// are null. Never writes to the hoteles catalog table — an unmatched
// name is returned as hotel_manual, not inserted.

function resolve_hotel_selection(mysqli $conn, array $post): array
{
    if (!empty($post['id_hotel']) && ctype_digit((string)$post['id_hotel'])) {
        return [(int)$post['id_hotel'], null];
    }

    $hotelOption = $post['hotelOption'] ?? '';

    if ($hotelOption === 'not_listed') {
        $texto = trim($post['customHotel'] ?? '');
        return $texto !== '' ? [null, $texto] : [null, null];
    }

    if ($hotelOption === 'decide_later') {
        return [null, null];
    }

    $nombre = trim($post['hotel'] ?? '');
    if ($nombre === '') {
        return [null, null];
    }

    $stmt = $conn->prepare("SELECT id_hotel FROM hoteles WHERE nombre_hotel = ? LIMIT 1");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $stmt->bind_result($foundId);
    $found = $stmt->fetch();
    $stmt->close();

    return $found ? [(int)$foundId, null] : [null, $nombre];
}
```

- [ ] **Step 7: Load the new include in the test bootstrap**

In `tests/bootstrap.php`, after line 41 (`require_once __DIR__ . '/../includes/reprocess_paypal_events.php';`), add:

```php
require_once __DIR__ . '/../includes/hotel_resolver.php';
```

- [ ] **Step 8: Run the test to verify it passes**

```bash
php tests/tools/phpunit.phar -c phpunit.xml --filter HotelResolverTest
```

Expected: `OK (7 tests, ...)`.

- [ ] **Step 9: Run the full suite to confirm no regressions**

```bash
php tests/tools/phpunit.phar -c phpunit.xml
```

Expected: all tests pass (the 4 pre-existing Getnet/PayPal test files plus the new one).

- [ ] **Step 10: Commit**

```bash
git add tests/schema.sql tests/bootstrap.php includes/hotel_resolver.php tests/HotelResolverTest.php
git commit -m "feat: add resolve_hotel_selection(), never inserts into hoteles catalog"
```

---

## Task 2: Wire submit.php and return.php to the resolver

**Files:**
- Modify: `submit.php:1-5` (add require), `submit.php:115-145` (replace resolution block), `submit.php:151-192` (INSERT + bind_param)
- Modify: `return.php:13-14` (add require), `return.php:27-79` (rewrite updateHotel handler)
- Test: `tests/HotelResolverTest.php` (add one more test)

**Interfaces:**
- Consumes: `resolve_hotel_selection(mysqli $conn, array $post): array` from Task 1.

- [ ] **Step 1: Write the failing regression test**

Add to `tests/HotelResolverTest.php` (new method, inside the existing class):

```php
    public function testSubmitAndReturnNeverInsertIntoHoteles(): void
    {
        foreach (['submit.php', 'return.php'] as $file) {
            $source = file_get_contents(__DIR__ . '/../' . $file);
            $this->assertNotFalse($source);
            $this->assertStringNotContainsStringIgnoringCase(
                'INSERT INTO hoteles',
                $source,
                "$file must never insert into the hoteles catalog table"
            );
        }
    }
```

- [ ] **Step 2: Run it to verify it fails**

```bash
php tests/tools/phpunit.phar -c phpunit.xml --filter testSubmitAndReturnNeverInsertIntoHoteles
```

Expected: FAIL — both files still contain `INSERT INTO hoteles` at this point.

- [ ] **Step 3: Rewrite `submit.php`'s hotel handling**

In `submit.php`, change line 5 from:
```php
require __DIR__ . '/../db_config.php';
```
to:
```php
require __DIR__ . '/../db_config.php';
require_once __DIR__ . '/includes/hotel_resolver.php';
```

Replace the entire block at lines 115-145 (from `// 3c) Resolver id_hotel...` through the closing `}` of that section) with:

```php
// 3c) Resolver hotel: id_hotel (catálogo, solo lectura) o hotel_manual (texto libre)
[$id_hotel, $hotel_manual] = resolve_hotel_selection($conn, $_POST);
```

Replace the `INSERT INTO reservas` statement (currently lines 152-171) with:

```php
$stmt = $conn->prepare("
    INSERT INTO reservas (
        reference_id,
        fecha_reserva,
        fecha_actividad,
        adultos,
        ninos,
        infantes,
        airport_pickup,
        id_cupon,
        id_titular,
        id_vendedor,
        id_experiencia,
        subtotal,
        total_venta,
        id_hotel,
        hotel_manual
    ) VALUES (
        ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
");
```

Replace the `bind_param` call (currently lines 176-192) with:

```php
// 2 strings, 9 integers, 2 doubles, 1 string = 14 parámetros
$stmt->bind_param(
    "ssiiiiiiiiddis",
    $stampCode,       // s: reference_id
    $fecha,           // s: fecha actividad (YYYY-MM-DD)
    $adultos,         // i
    $ninos,           // i
    $infantes,        // i
    $pickup,          // i
    $id_cupon,        // i (puede ser null)
    $id_titular,      // i
    $id_vendedor,     // i (puede ser null; ya con override si aplica)
    $id_experiencia,  // i
    $subtotal,        // d
    $total,           // d
    $id_hotel,        // i (puede ser null)
    $hotel_manual     // s (puede ser null)
);
```

- [ ] **Step 4: Rewrite `return.php`'s updateHotel handler**

In `return.php`, change line 14 from:
```php
require_once __DIR__ . '/helpers.php';
```
to:
```php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/includes/hotel_resolver.php';
```

Replace the entire AJAX handler block (currently lines 27-79, from `// ——— AJAX endpoint...` through the closing `}` right before the `// ---...--- 1) Obtener reference` section) with:

```php
// ——— AJAX endpoint: actualizar hotel sin recargar ———
if (isset($_GET['action']) && $_GET['action'] === 'updateHotel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res_id = (int)($_POST['id_reserva'] ?? 0);

    [$id_hotel, $hotel_manual] = resolve_hotel_selection($conn, $_POST);

    $upd = $conn->prepare("UPDATE reservas SET id_hotel = ?, hotel_manual = ? WHERE id_reserva = ?");
    $upd->bind_param("isi", $id_hotel, $hotel_manual, $res_id);
    $upd->execute();
    $upd->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
```

(This also drops the vestigial `list($nombre) = explode(' – ', $_POST['hotel'], 2);` from the old autocomplete branch — confirmed dead: `get_hotels.php:46-47` always returns the plain `nombre_hotel` as both `label` and `value`, so that delimiter never appears in submitted data.)

- [ ] **Step 5: Run the regression test to verify it passes**

```bash
php tests/tools/phpunit.phar -c phpunit.xml --filter testSubmitAndReturnNeverInsertIntoHoteles
```

Expected: PASS.

- [ ] **Step 6: Run the full suite**

```bash
php tests/tools/phpunit.phar -c phpunit.xml
```

Expected: all tests pass.

- [ ] **Step 7: Lint both changed files**

```bash
php -l submit.php
php -l return.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 8: Manual end-to-end verification against the local dev DB**

Create a throwaway script (not committed) to exercise both files exactly the way the earlier hotfix was verified — via `pcntl_fork()` + `include`, since both files end in `header()`/`exit()`:

```bash
cat > tmp_verify_hotel_manual.php << 'PHPEOF'
<?php
chdir(__DIR__);

function run_case($email, $extra) {
    $pid = pcntl_fork();
    if ($pid === -1) { die("fork failed\n"); }
    if ($pid === 0) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'name_booking' => 'TestHotelManual', 'last_name_booking' => 'Case',
            'email_booking' => $email, 'phone_booking' => '555',
            'date_booking' => date('m-d-Y', strtotime('+5 days')),
            'adults' => '1', 'children' => '0', 'infants' => '0',
            'airport_pick_up' => 'No', 'activity_name' => 'custom',
            'coupon_code' => '', 'subtotal' => '10', 'total_price' => '10',
        ];
        $_POST = array_merge($_POST, $extra);
        include 'submit.php';
        exit(0);
    }
    pcntl_waitpid($pid, $status);
}

function run_ajax($res_id, $extra) {
    $pid = pcntl_fork();
    if ($pid === -1) { die("fork failed\n"); }
    if ($pid === 0) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['action'] = 'updateHotel';
        $_POST = array_merge(['id_reserva' => $res_id], $extra);
        include 'return.php';
        exit(0);
    }
    pcntl_waitpid($pid, $status);
}

run_case('test_hotelmanual_a@example.invalid', ['hotelOption' => 'not_listed', 'customHotel' => 'ZZTEST New Custom Hotel']);
run_case('test_hotelmanual_b@example.invalid', ['hotel' => 'ZZTEST Unmatched Autocomplete Text']);

require '/Users/miguelmontero/Documents/superpowers/db_config.php';
$stmt = $conn->prepare("SELECT t.email, r.id_reserva, r.id_hotel, r.hotel_manual FROM reservas r JOIN titulares t ON t.id_titular=r.id_titular WHERE t.email LIKE 'test_hotelmanual_%' ORDER BY t.email");
$stmt->execute();
$res = $stmt->get_result();
$resId = null;
while ($row = $res->fetch_assoc()) {
    echo $row['email'] . " => id_hotel=" . ($row['id_hotel'] ?? 'NULL') . " hotel_manual=" . ($row['hotel_manual'] ?? 'NULL') . "\n";
    if ($row['email'] === 'test_hotelmanual_a@example.invalid') { $resId = (int)$row['id_reserva']; }
}
$hcount = $conn->query("SELECT COUNT(*) c FROM hoteles WHERE nombre_hotel LIKE 'ZZTEST%'")->fetch_assoc()['c'];
echo "hoteles rows created (expect 0): $hcount\n";
$conn->close();

// Customer returns after payment and picks "not listed" on the same reservation
run_ajax($resId, ['hotelOption' => 'not_listed', 'customHotel' => 'ZZTEST Return Custom Hotel']);
require '/Users/miguelmontero/Documents/superpowers/db_config.php';
$stmt = $conn->prepare("SELECT id_hotel, hotel_manual FROM reservas WHERE id_reserva = ?");
$stmt->bind_param('i', $resId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
echo "after return.php update => id_hotel=" . ($row['id_hotel'] ?? 'NULL') . " hotel_manual=" . ($row['hotel_manual'] ?? 'NULL') . "\n";
$hcount2 = $conn->query("SELECT COUNT(*) c FROM hoteles WHERE nombre_hotel LIKE 'ZZTEST%'")->fetch_assoc()['c'];
echo "hoteles rows created after return.php (expect 0): $hcount2\n";
PHPEOF
php tmp_verify_hotel_manual.php
```

Expected output: `test_hotelmanual_a...` shows `id_hotel=NULL hotel_manual=ZZTEST New Custom Hotel`; `test_hotelmanual_b...` shows `id_hotel=NULL hotel_manual=ZZTEST Unmatched Autocomplete Text`; `hoteles rows created (expect 0): 0`; after the `return.php` update, `id_hotel=NULL hotel_manual=ZZTEST Return Custom Hotel`; second `hoteles` count also `0`.

- [ ] **Step 9: Clean up the test data and throwaway script**

```bash
php -r '
require "/Users/miguelmontero/Documents/superpowers/db_config.php";
$r = $conn->query("SELECT r.id_reserva, t.id_titular FROM reservas r JOIN titulares t ON t.id_titular=r.id_titular WHERE t.email LIKE \"test_hotelmanual_%\"");
$reservas = []; $titulares = [];
while ($row = $r->fetch_assoc()) { $reservas[] = $row["id_reserva"]; $titulares[] = $row["id_titular"]; }
if ($reservas) { $conn->query("DELETE FROM reservas WHERE id_reserva IN (" . implode(",", array_map("intval",$reservas)) . ")"); }
if ($titulares) { $conn->query("DELETE FROM titulares WHERE id_titular IN (" . implode(",", array_map("intval",$titulares)) . ")"); }
echo "cleanup done\n";
'
rm -f tmp_verify_hotel_manual.php
```

- [ ] **Step 10: Commit**

```bash
git add submit.php return.php tests/HotelResolverTest.php
git commit -m "fix: wire submit.php and return.php through resolve_hotel_selection()"
```

---

## Task 3: Fall back to hotel_manual in the 4 simple display joins

**Files:**
- Modify: `search_reservas.php:25`
- Modify: `search_by_date.php:37`
- Modify: `search_by_codigo.php:27`
- Modify: `detalle_reservas.php:29`

These 4 files share the exact same one-line pattern: `h.nombre_hotel AS hotel,` in a query that already does `LEFT JOIN hoteles h ON r.id_hotel = h.id_hotel`. No test framework exists for these (they're authenticated admin AJAX/report endpoints with no PHPUnit coverage today, consistent with the rest of the codebase) — verify directly against the dev DB instead.

- [ ] **Step 1: Re-confirm the file list is still exhaustive**

```bash
grep -rn "id_hotel\|nombre_hotel" --include="*.php" . | grep -v -E "vendor/|_archive/|\.claude/"
```

Expected: the same 7 files as the design doc lists (`search_reservas.php`, `search_by_date.php`, `search_by_codigo.php`, `detalle_reservas.php`, `admin/check.php`, `admin/consolidate-day.php`, `admin/consolidate-month.php`), plus `get_hotels.php`, `booking_manual.php`, and the write paths already handled in Task 1/2. If any new file shows up here that wasn't in that list, stop and flag it before continuing — it's a display spot this plan didn't account for.

- [ ] **Step 2: Change `search_reservas.php:25`**

From:
```php
    h.nombre_hotel AS hotel,
```
To:
```php
    COALESCE(h.nombre_hotel, r.hotel_manual) AS hotel,
```

- [ ] **Step 3: Change `search_by_date.php:37`** — same replacement (`h.nombre_hotel AS hotel,` → `COALESCE(h.nombre_hotel, r.hotel_manual) AS hotel,`).

- [ ] **Step 4: Change `search_by_codigo.php:27`** — same replacement.

- [ ] **Step 5: Change `detalle_reservas.php:29`** — same replacement.

- [ ] **Step 6: Lint all 4 files**

```bash
php -l search_reservas.php
php -l search_by_date.php
php -l search_by_codigo.php
php -l detalle_reservas.php
```

Expected: `No syntax errors detected` for all 4.

- [ ] **Step 7: Verify the COALESCE behavior directly against the dev DB**

```bash
php -r '
require "/Users/miguelmontero/Documents/superpowers/db_config.php";
$conn->query("INSERT INTO titulares (nombre, apellido, email, telefono) VALUES (\"ZZVerify\",\"Case\",\"zzverify_task3@example.invalid\",\"555\")");
$idTitular = $conn->insert_id;
$stmt = $conn->prepare("INSERT INTO reservas (reference_id, fecha_reserva, fecha_actividad, id_titular, hotel_manual) VALUES (?, CURDATE(), CURDATE(), ?, ?)");
$ref = "ZZVERIFY_TASK3"; $manual = "ZZTEST Coalesce Check";
$stmt->bind_param("sis", $ref, $idTitular, $manual);
$stmt->execute();
$idReserva = $stmt->insert_id;
$stmt->close();

$r = $conn->query("SELECT COALESCE(h.nombre_hotel, r.hotel_manual) AS hotel FROM reservas r LEFT JOIN hoteles h ON r.id_hotel = h.id_hotel WHERE r.id_reserva = $idReserva");
echo $r->fetch_assoc()["hotel"] . "\n";

$conn->query("DELETE FROM reservas WHERE id_reserva = $idReserva");
$conn->query("DELETE FROM titulares WHERE id_titular = $idTitular");
'
```

Expected: prints `ZZTEST Coalesce Check`.

- [ ] **Step 8: Commit**

```bash
git add search_reservas.php search_by_date.php search_by_codigo.php detalle_reservas.php
git commit -m "fix: fall back to hotel_manual in hotel search/detail queries"
```

---

## Task 4: Fall back to hotel_manual in the 3 report-builder files

**Files:**
- Modify: `admin/check.php:896-916` (`buildWebHotelText()`), `admin/check.php:940` (SELECT)
- Modify: `admin/consolidate-day.php:890-899` (`build_web_hotel_text()`), `admin/consolidate-day.php:921` (SELECT)
- Modify: `admin/consolidate-month.php:622-631` (`build_web_hotel_text()`), `admin/consolidate-month.php:646` (SELECT)

- [ ] **Step 1: `admin/check.php` — add `r.hotel_manual` to the SELECT**

At line 940, change:
```php
            r.id_hotel,
```
to:
```php
            r.id_hotel,
            r.hotel_manual,
```

- [ ] **Step 2: `admin/check.php` — fall back in `buildWebHotelText()`**

Replace lines 896-916:
```php
function buildWebHotelText(array $row): string
{
    $parts = [];

    foreach (['nombre_hotel', 'direccion', 'comuna'] as $key) {
        $value = clean($row[$key] ?? '');
        if ($value !== '' && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
    }

    if (!empty($parts)) {
        return implode(', ', $parts);
    }

    if ((int)($row['airport_pickup'] ?? 0) === 1) {
        return 'SCL Airport pickup';
    }

    return '';
}
```
with:
```php
function buildWebHotelText(array $row): string
{
    $parts = [];

    foreach (['nombre_hotel', 'direccion', 'comuna'] as $key) {
        $value = clean($row[$key] ?? '');
        if ($value !== '' && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
    }

    if (!empty($parts)) {
        return implode(', ', $parts);
    }

    $manual = clean($row['hotel_manual'] ?? '');
    if ($manual !== '') {
        return $manual;
    }

    if ((int)($row['airport_pickup'] ?? 0) === 1) {
        return 'SCL Airport pickup';
    }

    return '';
}
```

- [ ] **Step 3: `admin/consolidate-day.php` — add `r.hotel_manual` to the SELECT**

At line 921, change:
```php
            r.id_hotel,
```
to:
```php
            r.id_hotel,
            r.hotel_manual,
```

- [ ] **Step 4: `admin/consolidate-day.php` — fall back in `build_web_hotel_text()`**

Replace lines 890-899:
```php
function build_web_hotel_text(array $row): string {
    $parts = [];
    foreach (['nombre_hotel', 'direccion', 'comuna'] as $k) {
        $v = trim((string)($row[$k] ?? ''));
        if ($v !== '' && !in_array($v, $parts, true)) $parts[] = $v;
    }
    if (!empty($parts)) return implode(', ', $parts);
    if ((int)($row['airport_pickup'] ?? 0) === 1) return 'SCL Airport pickup';
    return '';
}
```
with:
```php
function build_web_hotel_text(array $row): string {
    $parts = [];
    foreach (['nombre_hotel', 'direccion', 'comuna'] as $k) {
        $v = trim((string)($row[$k] ?? ''));
        if ($v !== '' && !in_array($v, $parts, true)) $parts[] = $v;
    }
    if (!empty($parts)) return implode(', ', $parts);
    $manual = trim((string)($row['hotel_manual'] ?? ''));
    if ($manual !== '') return $manual;
    if ((int)($row['airport_pickup'] ?? 0) === 1) return 'SCL Airport pickup';
    return '';
}
```

- [ ] **Step 5: `admin/consolidate-month.php` — add `r.hotel_manual` to the SELECT**

At line 646, change:
```php
            h.nombre_hotel, h.direccion, h.comuna
```
to:
```php
            h.nombre_hotel, h.direccion, h.comuna,
            r.hotel_manual
```

- [ ] **Step 6: `admin/consolidate-month.php` — fall back in `build_web_hotel_text()`**

Replace lines 622-631 (identical body to consolidate-day.php's function) with the same replacement as Step 4.

- [ ] **Step 7: Lint all 3 files**

```bash
php -l admin/check.php
php -l admin/consolidate-day.php
php -l admin/consolidate-month.php
```

Expected: `No syntax errors detected` for all 3.

- [ ] **Step 8: Manual verification**

`admin/check.php`, `admin/consolidate-day.php`, and `admin/consolidate-month.php` are full page scripts (not includable as libraries without triggering their auth/rendering code), so verify the 3 fallback functions with a standalone reproduction of the (now-identical-in-behavior) logic against sample `$row` arrays instead — no DB or admin login needed, since these are pure functions of their input array:

```bash
php -r '
function clean($v) { return trim((string)$v); }
function buildWebHotelText(array $row): string {
    $parts = [];
    foreach (["nombre_hotel", "direccion", "comuna"] as $key) {
        $value = clean($row[$key] ?? "");
        if ($value !== "" && !in_array($value, $parts, true)) { $parts[] = $value; }
    }
    if (!empty($parts)) return implode(", ", $parts);
    $manual = clean($row["hotel_manual"] ?? "");
    if ($manual !== "") return $manual;
    if ((int)($row["airport_pickup"] ?? 0) === 1) return "SCL Airport pickup";
    return "";
}
echo buildWebHotelText(["nombre_hotel" => null, "direccion" => null, "comuna" => null, "hotel_manual" => "ZZTEST Manual Fallback", "airport_pickup" => 0]) . "\n";
echo buildWebHotelText(["nombre_hotel" => "Real Hotel", "direccion" => "Some Address", "comuna" => "Vitacura", "hotel_manual" => null, "airport_pickup" => 0]) . "\n";
echo buildWebHotelText(["nombre_hotel" => null, "direccion" => null, "comuna" => null, "hotel_manual" => null, "airport_pickup" => 1]) . "\n";
'
```

Expected:
```
ZZTEST Manual Fallback
Real Hotel, Some Address, Vitacura
SCL Airport pickup
```

This confirms the fallback logic itself is correct; combined with Step 7's lint pass and Task 2's confirmation that `resolve_hotel_selection()` correctly populates `hotel_manual` on real rows, the full path is covered without needing to authenticate into the admin UI.

- [ ] **Step 9: Commit**

```bash
git add admin/check.php admin/consolidate-day.php admin/consolidate-month.php
git commit -m "fix: fall back to hotel_manual in admin report builders"
```

---

## Post-plan note (not a task)

The spec flagged a residual risk: the earlier hotfix (before this design) may have already created a handful of speculative rows in production's `hoteles` table from real customer/admin submissions between that deploy and this fix landing. Those rows are harmless (real hotel names with real `reservas.id_hotel` references), but won't be automatically detected or merged into `hotel_manual` by this plan — it's a one-time historical check, not ongoing behavior. Suggest the site owner run something like `SELECT * FROM hoteles ORDER BY id_hotel DESC LIMIT 20;` against production after this deploys, to eyeball whether any recently-added rows look like one-off customer text rather than real catalog entries worth keeping.
