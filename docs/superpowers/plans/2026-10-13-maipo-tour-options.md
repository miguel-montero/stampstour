# Maipo Tour Options Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let travelers choose Classic / Classic + Lunch / Premium on the Maipo tour page, see the matching itinerary, and pay a server-calculated price recorded against the right `experiencias` row.

**Architecture:** Three `experiencias` rows (data-only migration). Option content lives in a PHP catalogue (`includes/tours/maipo_options.php`) rendered server-side for SEO; a small vanilla-JS file swaps the visible option. A new public endpoint `book_tour.php` recalculates the total from the DB and calls a booking-insert function extracted verbatim from `submit.php`, which keeps working unchanged for admin/manual/TA flows.

**Tech Stack:** PHP 8.3 (mysqli), MySQL/MariaDB, jQuery-era page JS (`js/tours.js`), Bootstrap/CityTours CSS, PHPUnit via `tests/tools/phpunit.phar`.

**Spec:** `docs/superpowers/specs/2026-10-13-maipo-tour-options-design.md`

## Global Constraints

- Working directory for every command: `STAMP/` (the git repo root). Branch: `feature/maipo-tour-options`.
- Run tests with: `php tests/tools/phpunit.phar` (baseline before this plan: 61 tests passing).
- Tests run against the `_test` database configured in `tests/test_db_config.php` (gitignored, already set up). After changing `tests/schema.sql`, re-run `bash tests/setup-test-db.sh`.
- Option slugs, DB names, public names and prices, verbatim:
  - `classic` → `MAIPO_CLASSIC` → `Maipo Valley Wine Tour - Classic Route` → adult 110.00, child 50.00, infant 0.00
  - `classic-lunch` → `MAIPO_CLASSIC_LUNCH` → `Classic Maipo Valley Tastings & Lunch` → adult 129.00, child 50.00, infant 0.00
  - `premium` → `Maipo` (existing row, id 2) → `Maipo Valley Wine Tour - Premium Route with Undurraga` → adult 139.00, child 50.00, infant 0.00
- Default option: `classic`.
- New rows copy `precio_concierge` and `TAR` from the existing `Maipo` row (149.00 / 99.00 in production).
- Public names for `classic` and `premium` must NOT contain (case-insensitive): `tasting`, `lunch`, `almuerzo`, `classic maipo valley`, `pvt`, `private`. The `classic-lunch` public name MUST contain `tastings & lunch` and `classic maipo valley`. (Admin reports categorize by these substrings.)
- Availability (`cupos`) is always read for the `Maipo` row (`window.EXP_NAME = 'Maipo'`) regardless of selected option.
- Canonical URL stays `https://stampstour.com/maipo-valley-wine-tour-santiago` (no `?option`).
- `submit.php` behavior must stay byte-for-byte equivalent for its callers: `booking_manual.php`, `admin/private-booking.php`, `admin/preferentials.php`, and the Valparaíso / Andes / Santiago / cruise pages.
- No visual redesign. Existing Bootstrap/CityTours classes; the only new CSS is `css/tour-options.css`. Theme accent color: `#008489`.
- Don't commit the pre-existing uncommitted edit to `portillo-inca-lagoon-andes-mountains-vineyard.php`. The pre-existing edit in `maipo-valley-wine-tour-santiago.php` (FAQ pickup text without "the Airport area") is absorbed into Task 8's FAQ text.
- Commit messages end with: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

## Review Focus

1. **Public POST smuggling admin-only fields.** A traveler adds `codigo_vendedor`, `is_private=1` or `airport_pick_up=Yes` to the `book_tour.php` POST. Expected: all ignored (no vendor attribution/commission, not private, no pickup flag). Pinned in Task 5.
2. **Allow-listed option whose DB row is missing** (page or endpoint deployed before the migration ran). Expected: the page renders with that option disabled and "Contact us"; `book_tour.php` returns a friendly error, not a fatal. Pinned in Tasks 5 and 7.
3. **Hostile or odd `?option=` values** (`PREMIUM`, `option[]=x`, `<script>`, empty). Expected: case-insensitive match or silent fallback to `classic`, never a PHP warning or reflected markup. Pinned in Task 3.
4. **Coupon rounding drift between JS and server.** The displayed discounted total must equal what `book_tour.php` stores, e.g. 33% off $110 → 73.70. Pinned in Task 1 (same formula as `api/apply_coupon.php`: `round(total * (1 - pct/100), 2)`).
5. **Booking for "today" near midnight.** The server compares against Santiago's date, not UTC; booking today's date is allowed and yesterday's is rejected. Pinned in Task 5 by passing an explicit `$today`.

---

### Task 1: Server-side price calculation

**Files:**
- Modify: `tests/schema.sql` (append `cupones` table)
- Modify: `includes/tour_price.php` (add `fetch_tour_prices`)
- Create: `includes/booking.php` (calculation half only in this task)
- Modify: `tests/bootstrap.php` (require new file)
- Test: `tests/BookingCalculateTest.php`

**Interfaces:**
- Produces:
  - `fetch_tour_prices(mysqli $conn, array $expNames): array`, returning `['<nombre>' => ['adult' => float, 'child' => float, 'infant' => float], ...]`. Missing names are absent from the result.
  - `booking_coupon_percentage(mysqli $conn, string $code): ?int`, a case-insensitive lookup that returns null for empty or unknown codes.
  - `booking_calculate_total(mysqli $conn, string $expName, int $adults, int $children, int $infants, string $coupon): array`, returning `['subtotal' => float, 'total' => float]`.
  - `class BookingUnknownExperienceException extends InvalidArgumentException`
  - `booking_clean($valor): string` (the old `limpiar()` from `submit.php`)

- [ ] **Step 1: Add the `cupones` table to the test schema**

Append to `tests/schema.sql`:

```sql
CREATE TABLE IF NOT EXISTS `cupones` (
  `id_cupon` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `porcentaje` int(11) NOT NULL,
  PRIMARY KEY (`id_cupon`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Run: `bash tests/setup-test-db.sh`
Expected: completes without error.

- [ ] **Step 2: Write the failing test**

Create `tests/BookingCalculateTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BookingCalculateTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->cleanup();
        $this->conn->query("INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante)
                            VALUES ('TEST_CLASSIC', 'Test Classic', 110.00, 50.00, 0.00),
                                   ('TEST_PREMIUM', 'Test Premium', 139.00, 50.00, 5.00)");
        $this->conn->query("INSERT INTO cupones (nombre, id_vendedor, porcentaje)
                            VALUES ('TEST10', 1, 10), ('TEST33', 1, 33), ('TEST15', 1, 15)");
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->query("DELETE FROM experiencias WHERE nombre LIKE 'TEST\\_%' ESCAPE '\\\\'");
        $this->conn->query("DELETE FROM cupones WHERE nombre LIKE 'TEST%'");
    }

    public function testFetchTourPricesReturnsRequestedRowsOnly(): void
    {
        $prices = fetch_tour_prices($this->conn, ['TEST_CLASSIC', 'TEST_PREMIUM', 'TEST_MISSING']);
        $this->assertSame(['adult' => 110.0, 'child' => 50.0, 'infant' => 0.0], $prices['TEST_CLASSIC']);
        $this->assertSame(['adult' => 139.0, 'child' => 50.0, 'infant' => 5.0], $prices['TEST_PREMIUM']);
        $this->assertArrayNotHasKey('TEST_MISSING', $prices);
    }

    public function testFetchTourPricesWithEmptyListReturnsEmpty(): void
    {
        $this->assertSame([], fetch_tour_prices($this->conn, []));
    }

    public function testTotalForAdultsChildrenInfants(): void
    {
        $r = booking_calculate_total($this->conn, 'TEST_PREMIUM', 2, 1, 1, '');
        $this->assertSame(['subtotal' => 333.0, 'total' => 333.0], $r); // 2*139 + 50 + 5
    }

    public function testCouponIsAppliedCaseInsensitively(): void
    {
        $r = booking_calculate_total($this->conn, 'TEST_CLASSIC', 2, 1, 0, 'test10');
        $this->assertSame(['subtotal' => 270.0, 'total' => 243.0], $r);
    }

    public function testCouponRoundingMatchesApplyCouponEndpoint(): void
    {
        // api/apply_coupon.php: round(total * (1 - pct/100), 2)
        $this->assertSame(73.7, booking_calculate_total($this->conn, 'TEST_CLASSIC', 1, 0, 0, 'TEST33')['total']);
        $this->assertSame(93.5, booking_calculate_total($this->conn, 'TEST_CLASSIC', 1, 0, 0, 'TEST15')['total']);
    }

    public function testUnknownCouponMeansNoDiscount(): void
    {
        $r = booking_calculate_total($this->conn, 'TEST_CLASSIC', 1, 0, 0, 'NOPE');
        $this->assertSame(['subtotal' => 110.0, 'total' => 110.0], $r);
    }

    public function testUnknownExperienceThrows(): void
    {
        $this->expectException(BookingUnknownExperienceException::class);
        booking_calculate_total($this->conn, 'TEST_MISSING', 1, 0, 0, '');
    }

    public function testNegativeCountsThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        booking_calculate_total($this->conn, 'TEST_CLASSIC', 1, -1, 0, '');
    }
}
```

Add to the end of `tests/bootstrap.php`:

```php
require_once __DIR__ . '/../includes/booking.php';
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter BookingCalculateTest`
Expected: FAIL. The bootstrap errors with "Failed opening required '.../includes/booking.php'".

- [ ] **Step 4: Implement**

Append to `includes/tour_price.php`:

```php

// Prices for several experiences in one query, keyed by experiencias.nombre.
// Used by pages that sell several variants of one tour (e.g. the Maipo
// options) and by includes/booking.php for server-side totals.
function fetch_tour_prices(mysqli $conn, array $expNames): array
{
    $expNames = array_values(array_unique(array_map('strval', $expNames)));
    if (empty($expNames)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($expNames), '?'));
    $stmt = $conn->prepare("SELECT nombre, precio_adulto, precio_nino, precio_infante FROM experiencias WHERE nombre IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($expNames)), ...$expNames);
    $stmt->execute();
    $result = $stmt->get_result();

    $prices = [];
    while ($row = $result->fetch_assoc()) {
        $prices[$row['nombre']] = [
            'adult'  => (float)$row['precio_adulto'],
            'child'  => (float)($row['precio_nino'] ?? 0),
            'infant' => (float)($row['precio_infante'] ?? 0),
        ];
    }
    $stmt->close();

    return $prices;
}
```

Create `includes/booking.php`:

```php
<?php
// includes/booking.php
// Shared booking logic. booking_calculate_total() is the server-side price
// used by book_tour.php, so the amount charged never comes from the browser.
// See docs/superpowers/specs/2026-10-13-maipo-tour-options-design.md

require_once __DIR__ . '/tour_price.php';
require_once __DIR__ . '/hotel_resolver.php';

final class BookingUnknownExperienceException extends InvalidArgumentException
{
}

/**
 * Limpia un valor para evitar pasar null a trim() (antes limpiar() en submit.php).
 */
function booking_clean($valor): string
{
    return htmlspecialchars(trim((string)($valor ?? '')));
}

function booking_coupon_percentage(mysqli $conn, string $code): ?int
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT porcentaje FROM cupones WHERE LOWER(nombre) = LOWER(?) LIMIT 1');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $stmt->bind_result($porcentaje);
    $found = $stmt->fetch();
    $stmt->close();

    return $found ? (int)$porcentaje : null;
}

/**
 * Total for a booking, from experiencias prices and the coupon percentage.
 * Coupon math matches api/apply_coupon.php so the total shown in the
 * browser equals the total stored here.
 *
 * @return array{subtotal: float, total: float}
 */
function booking_calculate_total(mysqli $conn, string $expName, int $adults, int $children, int $infants, string $coupon): array
{
    if ($adults < 0 || $children < 0 || $infants < 0) {
        throw new InvalidArgumentException('Passenger counts cannot be negative');
    }

    $prices = fetch_tour_prices($conn, [$expName]);
    $p = $prices ? reset($prices) : null;
    if ($p === null) {
        throw new BookingUnknownExperienceException($expName);
    }

    $subtotal = round($adults * $p['adult'] + $children * $p['child'] + $infants * $p['infant'], 2);

    $pct = booking_coupon_percentage($conn, $coupon);
    $total = $pct === null ? $subtotal : round($subtotal * (1 - $pct / 100), 2);

    return ['subtotal' => $subtotal, 'total' => $total];
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php tests/tools/phpunit.phar`
Expected: PASS, with 69 tests (61 old + 8 new).

- [ ] **Step 6: Commit**

```bash
git add tests/schema.sql tests/bootstrap.php tests/BookingCalculateTest.php includes/tour_price.php includes/booking.php
git commit -m "feat: server-side booking total calculation from experiencias + coupon

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Extract the reservation insert from `submit.php`

**Files:**
- Modify: `includes/booking.php` (add `booking_insert_reservation`)
- Modify: `submit.php` (becomes a thin wrapper)
- Test: `tests/BookingInsertTest.php`

**Interfaces:**
- Consumes: `booking_clean`, `BookingUnknownExperienceException` (Task 1); `resolve_hotel_selection(mysqli, array): array` (existing).
- Produces: `booking_insert_reservation(mysqli $conn, array $post, float $subtotal, float $total): string`. It returns `reference_id` (`STAMP_` + 13 hex chars) and throws `BookingUnknownExperienceException` or `RuntimeException`.

- [ ] **Step 1: Write the failing test**

Create `tests/BookingInsertTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BookingInsertTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->cleanup();
        $this->conn->query("INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante)
                            VALUES ('TEST_TOUR', 'Test Tour', 110.00, 50.00, 0.00)");
        $this->conn->query("INSERT INTO vendedores (nombre_vendedor) VALUES ('TEST_VENDOR')");
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->query("DELETE FROM reservas");
        $this->conn->query("DELETE FROM titulares WHERE email LIKE '%@example.test'");
        $this->conn->query("DELETE FROM experiencias WHERE nombre LIKE 'TEST\\_%' ESCAPE '\\\\'");
        $this->conn->query("DELETE FROM vendedores WHERE nombre_vendedor LIKE 'TEST\\_%' ESCAPE '\\\\'");
    }

    private function post(array $overrides = []): array
    {
        return array_merge([
            'name_booking'      => 'Ana',
            'last_name_booking' => 'Traveler',
            'email_booking'     => 'ana@example.test',
            'phone_booking'     => '+56 9 1234 5678',
            'date_booking'      => '11-20-2026',
            'adults'            => '2',
            'children'          => '1',
            'infants'           => '0',
            'activity_name'     => 'TEST_TOUR',
            'coupon_code'       => '',
        ], $overrides);
    }

    private function reserva(string $ref): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM reservas WHERE reference_id = ?');
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function testStoresGivenTotalsAsIs(): void
    {
        // submit.php's admin/manual/TA callers rely on custom totals being stored unchanged
        $ref = booking_insert_reservation($this->conn, $this->post(), 999.99, 1.00);
        $this->assertMatchesRegularExpression('/^STAMP_[0-9a-f]{13}$/', $ref);
        $row = $this->reserva($ref);
        $this->assertSame('999.99', $row['subtotal']);
        $this->assertSame('1.00', $row['total_venta']);
        $this->assertSame('2026-11-20', $row['fecha_actividad']);
        $this->assertSame(2, (int)$row['adultos']);
        $this->assertSame(1, (int)$row['ninos']);
    }

    public function testCodigoVendedorOverrideStillWorks(): void
    {
        $ref = booking_insert_reservation($this->conn, $this->post(['codigo_vendedor' => 'TEST_VENDOR']), 10.0, 10.0);
        $this->assertNotNull($this->reserva($ref)['id_vendedor']);
    }

    public function testUnknownExperienceThrowsAndInsertsNothing(): void
    {
        try {
            booking_insert_reservation($this->conn, $this->post(['activity_name' => 'TEST_MISSING']), 1.0, 1.0);
            $this->fail('Expected BookingUnknownExperienceException');
        } catch (BookingUnknownExperienceException $e) {
            $count = (int)$this->conn->query("SELECT COUNT(*) c FROM titulares WHERE email LIKE '%@example.test'")->fetch_assoc()['c'];
            $this->assertSame(0, $count, 'no orphan titular row');
            $this->assertSame(0, (int)$this->conn->query('SELECT COUNT(*) c FROM reservas')->fetch_assoc()['c']);
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter BookingInsertTest`
Expected: FAIL with "Call to undefined function booking_insert_reservation()".

- [ ] **Step 3: Implement `booking_insert_reservation`**

Append to `includes/booking.php`. The body is moved from `submit.php` with `$_POST` replaced by `$post`, `die`/`exit` replaced by exceptions, and the experience lookup moved before the titular insert so an unknown experience no longer leaves an orphan `titulares` row.

```php

/**
 * Inserts titular + reserva. Moved from submit.php; behavior is unchanged
 * except that errors are thrown instead of die()/exit, and the experience
 * is resolved before the titular insert.
 *
 * $subtotal / $total are stored as given - callers decide where they come
 * from (submit.php: posted by trusted admin forms; book_tour.php: computed
 * by booking_calculate_total()).
 */
function booking_insert_reservation(mysqli $conn, array $post, float $subtotal, float $total): string
{
    $nombre    = booking_clean($post['name_booking']      ?? '');
    $apellido  = booking_clean($post['last_name_booking'] ?? '');
    $email     = booking_clean($post['email_booking']     ?? '');
    $telefono  = booking_clean($post['phone_booking']     ?? '');

    // Parseo de fecha MM-DD-YYYY → YYYY-MM-DD
    $rawDate   = (string)($post['date_booking'] ?? '');
    $dateObj   = DateTime::createFromFormat('m-d-Y', $rawDate);
    $fecha     = $dateObj ? $dateObj->format('Y-m-d') : null;

    // Cantidades
    $adultos   = intval($post['adults']   ?? 0);
    $ninos     = intval($post['children'] ?? 0);
    $infantes  = intval($post['infants']  ?? 0);

    // Airport pickup: 1 si está marcado, 0 si no
    $pickup    = (isset($post['airport_pick_up']) && $post['airport_pick_up'] === 'Yes') ? 1 : 0;

    // Private experience: 1 si el checkbox viene marcado, 0 si no (público no lo envía)
    $isPrivate = (isset($post['is_private']) && $post['is_private'] === '1') ? 1 : 0;

    $actividad = booking_clean($post['activity_name'] ?? '');
    $cupon     = booking_clean($post['coupon_code']   ?? '');

    // 1) Resolver id_experiencia (solo lectura) - antes del titular para no dejar huérfanos
    $id_experiencia = null;
    $stmt = $conn->prepare("
        SELECT id_experiencia
          FROM experiencias
         WHERE nombre = ?
         LIMIT 1
    ");
    $stmt->bind_param("s", $actividad);
    $stmt->execute();
    $stmt->bind_result($id_experiencia);
    $stmt->fetch();
    $stmt->close();

    if (!$id_experiencia) {
        throw new BookingUnknownExperienceException($actividad);
    }

    // 2) Insertar titular
    $stmt = $conn->prepare("
        INSERT INTO titulares (nombre, apellido, email, telefono)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssss", $nombre, $apellido, $email, $telefono);
    $stmt->execute();
    $id_titular = $stmt->insert_id;
    $stmt->close();

    // 3) Buscar cupón y vendedor
    $id_cupon    = null;
    $id_vendedor = null;
    if ($cupon !== '') {
        $stmt = $conn->prepare("
            SELECT id_cupon, id_vendedor
              FROM cupones
             WHERE nombre = ?
             LIMIT 1
        ");
        $stmt->bind_param("s", $cupon);
        $stmt->execute();
        $stmt->bind_result($id_cupon, $id_vendedor);
        $stmt->fetch();
        $stmt->close();
    }

    /* 3b) OVERRIDE MANUAL (mínimo cambio): si viene codigo_vendedor y se puede resolver, TIENE PRIORIDAD */
    if (!empty($post['codigo_vendedor'])) {
        $codigoV = trim((string)$post['codigo_vendedor']); // sin limpiar HTML, usamos el valor crudo para buscar

        if (ctype_digit($codigoV)) {
            $stmt = $conn->prepare("SELECT id_vendedor FROM vendedores WHERE id_vendedor = ? LIMIT 1");
            $tmpId = (int)$codigoV;
            $stmt->bind_param("i", $tmpId);
            $stmt->execute();
            $stmt->bind_result($foundId);
            if ($stmt->fetch()) { $id_vendedor = (int)$foundId; }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("SELECT id_vendedor FROM vendedores WHERE nombre_vendedor = ? LIMIT 1");
            $stmt->bind_param("s", $codigoV);
            $stmt->execute();
            $stmt->bind_result($foundId);
            if ($stmt->fetch()) { $id_vendedor = (int)$foundId; }
            $stmt->close();
        }
    }

    // 3c) Resolver hotel: id_hotel (catálogo, solo lectura) o hotel_manual (texto libre)
    [$id_hotel, $hotel_manual] = resolve_hotel_selection($conn, $post);

    // 4) Generar reference_id (reference_id es NOT NULL sin default)
    $stampCode = 'STAMP_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 13);

    // 5) Insertar reserva
    $stmt = $conn->prepare("
        INSERT INTO reservas (
            reference_id,
            fecha_reserva,
            fecha_actividad,
            adultos,
            ninos,
            infantes,
            airport_pickup,
            is_private,
            id_cupon,
            id_titular,
            id_vendedor,
            id_experiencia,
            subtotal,
            total_venta,
            id_hotel,
            hotel_manual
        ) VALUES (
            ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");
    if (!$stmt) {
        throw new RuntimeException("Error preparando inserción de reserva: " . $conn->error);
    }

    $stmt->bind_param(
        "ssiiiiiiiiiddis",
        $stampCode,
        $fecha,
        $adultos,
        $ninos,
        $infantes,
        $pickup,
        $isPrivate,
        $id_cupon,
        $id_titular,
        $id_vendedor,
        $id_experiencia,
        $subtotal,
        $total,
        $id_hotel,
        $hotel_manual
    );

    if (!$stmt->execute()) {
        throw new RuntimeException("Error al insertar reserva: " . $stmt->error);
    }
    $stmt->close();

    return $stampCode;
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/tools/phpunit.phar --filter BookingInsertTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Replace `submit.php` with the thin wrapper**

Overwrite `submit.php` entirely:

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../db_config.php';
require_once __DIR__ . '/includes/booking.php';

if (!$conn) {
    die("No hay conexión con la base de datos.");
}

// Trusted callers (booking_manual.php, admin/private-booking.php,
// admin/preferentials.php) post their own custom totals, stored as-is.
// Public tour pages are moving to book_tour.php, which recalculates the
// total server-side - see docs/superpowers/specs/2026-10-13-maipo-tour-options-design.md
$subtotal = floatval($_POST['subtotal']    ?? 0);
$total    = floatval($_POST['total_price'] ?? 0);

try {
    $stampCode = booking_insert_reservation($conn, $_POST, $subtotal, $total);
} catch (BookingUnknownExperienceException $e) {
    http_response_code(400);
    $actividad = booking_clean($_POST['activity_name'] ?? '');
    $safeName = htmlspecialchars($actividad ?: '(vacío)', ENT_QUOTES, 'UTF-8');
    echo "<h3>Experiencia no encontrada</h3>";
    echo "<p>La experiencia <strong>{$safeName}</strong> no existe en el catálogo.</p>";
    echo "<p>Por favor utiliza un nombre válido que ya exista en <code>experiencias</code> (por ejemplo, <code>custom</code>).</p>";
    exit;
} catch (RuntimeException $e) {
    die($e->getMessage());
}

// Guardar en sesión y redirigir (mismo comportamiento que antes)
session_start();
$_SESSION['reference_id'] = $stampCode;

header('Location: shopping.php?reference_id=' . urlencode($stampCode));
exit();
```

Run: `php -l submit.php && php tests/tools/phpunit.phar`
Expected: "No syntax errors detected", and every test passes (72).

- [ ] **Step 6: Smoke-test `submit.php` over HTTP against the test DB**

Run:

```bash
php -S 127.0.0.1:8099 >/tmp/stamp-submit-smoke.log 2>&1 &
sleep 1
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' -X POST http://127.0.0.1:8099/submit.php \
  -d 'activity_name=DOES_NOT_EXIST&name_booking=x&last_name_booking=y&email_booking=z@example.test&phone_booking=1&date_booking=11-20-2026&adults=1'
kill %1
```

Expected: `400`. The unknown-experience path still returns HTTP 400. This works against the local dev DB without inserting anything, because the experience lookup now runs first.

- [ ] **Step 7: Commit**

```bash
git add includes/booking.php submit.php tests/BookingInsertTest.php
git commit -m "refactor: extract reservation insert from submit.php into includes/booking.php

submit.php keeps identical behavior for admin/manual/TA callers; the
experience lookup now runs before the titular insert so an unknown
experience no longer leaves an orphan titulares row.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Maipo option catalogue

**Files:**
- Create: `includes/tours/maipo_options.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/MaipoOptionsTest.php`

**Interfaces:**
- Produces:
  - `maipo_options(): array`: an ordered map of `slug => option`. Each option has the keys `exp_name` (string), `public_name` (string), `label` (string), `tagline` (string), `final_winery` (string), `best_for` (string), `lunch_included` (bool), `stops` (a list of `['place','title','duration','icon','text']` strings, where `duration` may be ''), `included` (list of strings) and `not_included` (list of strings).
  - `maipo_default_option(): string`, returns `'classic'`.
  - `maipo_resolve_option(mixed $raw): string`, which returns a valid slug and never warns.
  - `maipo_option_exp_names(): array`, returns `slug => exp_name`.

- [ ] **Step 1: Write the failing test**

Create `tests/MaipoOptionsTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MaipoOptionsTest extends TestCase
{
    public function testThreeOptionsInDisplayOrder(): void
    {
        $this->assertSame(['classic', 'classic-lunch', 'premium'], array_keys(maipo_options()));
    }

    public function testExpNamesMatchDatabaseRows(): void
    {
        $this->assertSame(
            ['classic' => 'MAIPO_CLASSIC', 'classic-lunch' => 'MAIPO_CLASSIC_LUNCH', 'premium' => 'Maipo'],
            maipo_option_exp_names()
        );
    }

    /** @dataProvider resolveCases */
    public function testResolveOption($raw, string $expected): void
    {
        $this->assertSame($expected, maipo_resolve_option($raw));
    }

    public static function resolveCases(): array
    {
        return [
            'exact'          => ['premium', 'premium'],
            'uppercase'      => ['PREMIUM', 'premium'],
            'padded'         => ['  classic-lunch ', 'classic-lunch'],
            'missing'        => [null, 'classic'],
            'empty'          => ['', 'classic'],
            'unknown'        => ['deluxe', 'classic'],
            'array param'    => [['premium'], 'classic'],
            'markup'         => ['<script>alert(1)</script>', 'classic'],
        ];
    }

    public function testPublicNamesRouteToTheRightAdminReportCategory(): void
    {
        $opts = maipo_options();
        $lunch = mb_strtolower($opts['classic-lunch']['public_name']);
        $this->assertStringContainsString('tastings & lunch', $lunch);
        $this->assertStringContainsString('classic maipo valley', $lunch);

        foreach (['classic', 'premium'] as $slug) {
            $name = mb_strtolower($opts[$slug]['public_name']);
            $this->assertStringContainsString('maipo', $name);
            foreach (['tasting', 'lunch', 'almuerzo', 'classic maipo valley', 'pvt', 'private'] as $banned) {
                $this->assertStringNotContainsString($banned, $name, "$slug public name must not contain '$banned'");
            }
        }
    }

    public function testFinalStopAndLunchPerOption(): void
    {
        $opts = maipo_options();
        $finalTitle = fn(array $o) => $o['stops'][count($o['stops']) - 2]['title']; // last stop before return
        $this->assertSame('Viña Ballek', $finalTitle($opts['classic']));
        $this->assertSame('Viña Ballek', $finalTitle($opts['classic-lunch']));
        $this->assertSame('Viña Undurraga', $finalTitle($opts['premium']));
        $this->assertFalse($opts['classic']['lunch_included']);
        $this->assertTrue($opts['classic-lunch']['lunch_included']);
        $this->assertFalse($opts['premium']['lunch_included']);
    }

    public function testEveryOptionHasCompleteContent(): void
    {
        foreach (maipo_options() as $slug => $o) {
            foreach (['exp_name', 'public_name', 'label', 'tagline', 'final_winery', 'best_for'] as $key) {
                $this->assertNotSame('', trim($o[$key]), "$slug.$key");
            }
            $this->assertCount(6, $o['stops'], "$slug has pickup + 4 stops + return");
            foreach ($o['stops'] as $stop) {
                $this->assertSame(['place', 'title', 'duration', 'icon', 'text'], array_keys($stop));
                $this->assertNotSame('', $stop['title']);
                $this->assertNotSame('', $stop['text']);
            }
            $this->assertNotEmpty($o['included']);
            $this->assertNotEmpty($o['not_included']);
        }
    }
}
```

Add to `tests/bootstrap.php`:

```php
require_once __DIR__ . '/../includes/tours/maipo_options.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter MaipoOptionsTest`
Expected: FAIL with "Failed opening required '.../includes/tours/maipo_options.php'".

- [ ] **Step 3: Implement**

Create `includes/tours/maipo_options.php`:

```php
<?php
// includes/tours/maipo_options.php
// Content for the three shared Maipo Valley tour options. Prices are NOT
// here - they come from experiencias (exp_name) so admin price changes
// apply everywhere. public_name is the experiencias.nombre_publico the
// migration writes; admin reports categorize bookings by substrings of it
// (see the naming constraint in
// docs/superpowers/specs/2026-10-13-maipo-tour-options-design.md).

function maipo_options(): array
{
    $pickup = [
        'place' => 'Santiago',
        'title' => 'Hotel pickup',
        'duration' => '',
        'icon' => 'icon-location-outline',
        'text' => 'Pickup at your hotel or accommodation in Santiago Centro, Providencia, Las Condes, Vitacura or Recoleta, usually between 7:00 and 7:30 AM. Your exact pickup time is sent the night before.',
    ];
    $quirinca = [
        'place' => 'Isla de Maipo',
        'title' => 'Campo La Quirinca',
        'duration' => '1 hour 30 minutes',
        'icon' => 'icon-camera-alt',
        'text' => 'Start at a small family-run farm and winery. Walk the gardens and fields, learn how wine is made the traditional countryside way, and meet the farm animals, including alpacas and several breeds of chickens. Then relax in the tasting room with locally made wines and a tasting of Chilean pisco. Local artisan products are available to buy.',
    ];
    $santaEma = [
        'place' => 'Isla de Maipo',
        'title' => 'Viña Santa Ema',
        'duration' => '1 hour',
        'icon' => 'icon-wine',
        'text' => "Founded in 1956, Santa Ema makes wines shaped by the Maipo River and the valley's geography. Enjoy a guided tasting of three wines, including one of their blends.",
    ];
    $terraMater = function (bool $lunchIncluded): array {
        $lunch = $lunchIncluded
            ? 'Lunch is included in your booking at Zinfandel, the restaurant on the TerraMater estate.'
            : 'Lunch break at Zinfandel, the restaurant on the TerraMater estate. Lunch is at your own cost: order from the menu and pay at the restaurant, or bring your own food.';
        return [
            'place' => 'Isla de Maipo',
            'title' => 'Viña TerraMater',
            'duration' => '1 hour 30 minutes',
            'icon' => 'icon-restaurant',
            'text' => $lunch . ' TerraMater is also known for its award-winning olive oil, which you can buy in the shop at cellar-door prices.',
        ];
    };
    $ballek = [
        'place' => 'Isla de Maipo',
        'title' => 'Viña Ballek',
        'duration' => '1 hour',
        'icon' => 'icon-wine',
        'text' => 'End the day at a small family-run boutique winery on the banks of the Maipo River. Taste limited-production wines in a relaxed setting, often with the hosts themselves, and take time to enjoy the vineyards and countryside.',
    ];
    $undurraga = [
        'place' => 'Maipo Valley',
        'title' => 'Viña Undurraga',
        'duration' => '1 hour 30 minutes',
        'icon' => 'icon-wine',
        'text' => "Finish at one of Chile's most traditional wineries, with more than 130 years of history. An extended guided tour takes you through the gardens, vineyards, production area and barrel cellar, plus a pre-Columbian exhibit when it is open, and ends with a tasting of four premium wines.",
    ];
    $return = [
        'place' => 'Santiago',
        'title' => 'Return to your hotel',
        'duration' => '',
        'icon' => 'icon-location-outline',
        'text' => 'Drop-off back at your pickup point in Santiago, usually between 5:30 and 6:30 PM depending on traffic.',
    ];

    $includedCommon = [
        'Hotel pickup and drop-off (pickup time sent the night before)',
        'Professional English-speaking guide',
        'Air-conditioned vehicle',
        'Wine and pisco tasting at Campo La Quirinca',
        'Guided tasting of three wines at Viña Santa Ema',
        'Live coordination with your guide via WhatsApp',
    ];
    $notIncludedCommon = [
        'Extra drinks, wine bottles and souvenirs',
    ];
    $lunchNotIncluded = 'Lunch at Zinfandel restaurant (pay at the restaurant or bring your own food)';

    return [
        'classic' => [
            'exp_name' => 'MAIPO_CLASSIC',
            'public_name' => 'Maipo Valley Wine Tour - Classic Route',
            'label' => 'Classic Route',
            'tagline' => 'Ends at boutique Viña Ballek. Lunch at your own cost.',
            'final_winery' => 'Viña Ballek, a boutique family winery',
            'best_for' => 'A relaxed, social day in wine country',
            'lunch_included' => false,
            'stops' => [$pickup, $quirinca, $santaEma, $terraMater(false), $ballek, $return],
            'included' => array_merge($includedCommon, ['Wine tasting at Viña Ballek']),
            'not_included' => array_merge([$lunchNotIncluded], $notIncludedCommon),
        ],
        'classic-lunch' => [
            'exp_name' => 'MAIPO_CLASSIC_LUNCH',
            'public_name' => 'Classic Maipo Valley Tastings & Lunch',
            'label' => 'Classic Route + Lunch',
            'tagline' => 'Same Classic itinerary, with lunch at TerraMater included.',
            'final_winery' => 'Viña Ballek, a boutique family winery',
            'best_for' => 'Having everything paid in advance',
            'lunch_included' => true,
            'stops' => [$pickup, $quirinca, $santaEma, $terraMater(true), $ballek, $return],
            'included' => array_merge($includedCommon, ['Lunch at Zinfandel restaurant, Viña TerraMater', 'Wine tasting at Viña Ballek']),
            'not_included' => $notIncludedCommon,
        ],
        'premium' => [
            'exp_name' => 'Maipo',
            'public_name' => 'Maipo Valley Wine Tour - Premium Route with Undurraga',
            'label' => 'Premium Route',
            'tagline' => 'Ends with an extended guided tour of historic Viña Undurraga. Lunch at your own cost.',
            'final_winery' => 'Viña Undurraga, a historic winery with an extended guided tour',
            'best_for' => 'Wine lovers curious about history and winemaking',
            'lunch_included' => false,
            'stops' => [$pickup, $quirinca, $santaEma, $terraMater(false), $undurraga, $return],
            'included' => array_merge($includedCommon, ['Extended guided tour and tasting of four premium wines at Viña Undurraga']),
            'not_included' => array_merge([$lunchNotIncluded], $notIncludedCommon),
        ],
    ];
}

function maipo_default_option(): string
{
    return 'classic';
}

/** Valid slug for any raw ?option= value (string, array, null...) - never warns. */
function maipo_resolve_option($raw): string
{
    $slug = is_string($raw) ? strtolower(trim($raw)) : '';
    return array_key_exists($slug, maipo_options()) ? $slug : maipo_default_option();
}

/** @return array<string,string> slug => experiencias.nombre */
function maipo_option_exp_names(): array
{
    return array_map(fn(array $o) => $o['exp_name'], maipo_options());
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php tests/tools/phpunit.phar`
Expected: PASS. All tests, including 13 new `MaipoOptionsTest` cases (8 data-provider rows).

- [ ] **Step 5: Commit**

```bash
git add includes/tours/maipo_options.php tests/MaipoOptionsTest.php tests/bootstrap.php
git commit -m "feat: Maipo option catalogue (Classic, Classic + Lunch, Premium)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Data migration for the three `experiencias` rows

**Files:**
- Create: `includes/migrations/maipo_options_2026_10_13.php`
- Create: `migrate_2026_10_13_maipo_options.php` (web wrapper, one-time)
- Modify: `tests/bootstrap.php`
- Test: `tests/MaipoOptionsMigrationTest.php`

**Interfaces:**
- Consumes: `maipo_options()` (Task 3), specifically `exp_name` and `public_name`.
- Produces: `migrate_maipo_options(mysqli $conn): array`, which returns log lines that each start with `[ok]` or `[skip]`. It throws `RuntimeException` if the base `Maipo` row is missing.

- [ ] **Step 1: Write the failing test**

Create `tests/MaipoOptionsMigrationTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MaipoOptionsMigrationTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->query("DELETE FROM experiencias WHERE nombre IN ('Maipo', 'MAIPO_CLASSIC', 'MAIPO_CLASSIC_LUNCH')");
    }

    private function seedOriginalMaipoRow(): int
    {
        $this->conn->query("INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante, precio_concierge, TAR)
                            VALUES ('Maipo', 'Maipo Valley Wine Tour with 4 vineyards from Santiago', 121.00, 50.00, 0.00, 149.00, 99.00)");
        return (int)$this->conn->insert_id;
    }

    private function rows(): array
    {
        $res = $this->conn->query("SELECT * FROM experiencias WHERE nombre IN ('Maipo', 'MAIPO_CLASSIC', 'MAIPO_CLASSIC_LUNCH')");
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $out[$r['nombre']] = $r;
        }
        return $out;
    }

    public function testCreatesAndUpdatesTheThreeRows(): void
    {
        $maipoId = $this->seedOriginalMaipoRow();
        migrate_maipo_options($this->conn);
        $rows = $this->rows();

        $this->assertCount(3, $rows);
        $this->assertSame($maipoId, (int)$rows['Maipo']['id_experiencia'], 'premium keeps the existing id');
        $this->assertSame('139.00', $rows['Maipo']['precio_adulto']);
        $this->assertSame('Maipo Valley Wine Tour - Premium Route with Undurraga', $rows['Maipo']['nombre_publico']);
        $this->assertSame('110.00', $rows['MAIPO_CLASSIC']['precio_adulto']);
        $this->assertSame('Maipo Valley Wine Tour - Classic Route', $rows['MAIPO_CLASSIC']['nombre_publico']);
        $this->assertSame('129.00', $rows['MAIPO_CLASSIC_LUNCH']['precio_adulto']);
        $this->assertSame('Classic Maipo Valley Tastings & Lunch', $rows['MAIPO_CLASSIC_LUNCH']['nombre_publico']);

        foreach ($rows as $r) {
            $this->assertSame('50.00', $r['precio_nino']);
            $this->assertSame('0.00', $r['precio_infante']);
            $this->assertSame('149.00', $r['precio_concierge'], 'concierge price copied from Maipo');
            $this->assertSame('99.00', $r['TAR'], 'TA rate copied from Maipo');
        }
    }

    public function testSecondRunIsANoOp(): void
    {
        $this->seedOriginalMaipoRow();
        migrate_maipo_options($this->conn);
        $before = $this->rows();

        $log = migrate_maipo_options($this->conn);

        $this->assertSame($before, $this->rows());
        foreach ($log as $line) {
            $this->assertStringStartsWith('[skip]', $line);
        }
    }

    public function testFailsWithoutBaseMaipoRow(): void
    {
        $this->expectException(RuntimeException::class);
        migrate_maipo_options($this->conn);
    }
}
```

Add to `tests/bootstrap.php`:

```php
require_once __DIR__ . '/../includes/migrations/maipo_options_2026_10_13.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter MaipoOptionsMigrationTest`
Expected: FAIL with "Failed opening required '.../includes/migrations/maipo_options_2026_10_13.php'".

- [ ] **Step 3: Implement**

Create `includes/migrations/maipo_options_2026_10_13.php`:

```php
<?php
// includes/migrations/maipo_options_2026_10_13.php
// Data-only migration for the Maipo tour options: repurposes the existing
// 'Maipo' row as Premium and adds MAIPO_CLASSIC / MAIPO_CLASSIC_LUNCH.
// Idempotent. Run via migrate_2026_10_13_maipo_options.php (web, logged in)
// or the CLI one-liner in the implementation plan.

require_once __DIR__ . '/../tours/maipo_options.php';

const MAIPO_MIGRATION_PRICES = [
    'classic'       => ['adult' => 110.00, 'child' => 50.00, 'infant' => 0.00],
    'classic-lunch' => ['adult' => 129.00, 'child' => 50.00, 'infant' => 0.00],
    'premium'       => ['adult' => 139.00, 'child' => 50.00, 'infant' => 0.00],
];

function migrate_maipo_options(mysqli $conn): array
{
    $base = $conn->query("SELECT precio_concierge, TAR FROM experiencias WHERE nombre = 'Maipo' LIMIT 1")->fetch_assoc();
    if (!$base) {
        throw new RuntimeException("Base 'Maipo' row not found in experiencias - nothing migrated.");
    }
    $concierge = (float)$base['precio_concierge'];
    $tar = $base['TAR'] !== null ? (float)$base['TAR'] : null;

    $log = [];
    foreach (maipo_options() as $slug => $opt) {
        $price = MAIPO_MIGRATION_PRICES[$slug];
        $name = $opt['exp_name'];
        $public = $opt['public_name'];

        $stmt = $conn->prepare('SELECT nombre_publico, precio_adulto, precio_nino, precio_infante FROM experiencias WHERE nombre = ? LIMIT 1');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $same = $row['nombre_publico'] === $public
                && (float)$row['precio_adulto'] === $price['adult']
                && (float)$row['precio_nino'] === $price['child']
                && (float)$row['precio_infante'] === $price['infant'];
            if ($same) {
                $log[] = "[skip] $name already up to date";
                continue;
            }
            $stmt = $conn->prepare('UPDATE experiencias SET nombre_publico = ?, precio_adulto = ?, precio_nino = ?, precio_infante = ? WHERE nombre = ?');
            $stmt->bind_param('sddds', $public, $price['adult'], $price['child'], $price['infant'], $name);
            $stmt->execute();
            $stmt->close();
            $log[] = "[ok] updated $name: '$public' adult {$price['adult']}";
            continue;
        }

        $stmt = $conn->prepare('INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante, precio_concierge, TAR) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssddddd', $name, $public, $price['adult'], $price['child'], $price['infant'], $concierge, $tar);
        $stmt->execute();
        $stmt->close();
        $log[] = "[ok] inserted $name: '$public' adult {$price['adult']}";
    }

    return $log;
}
```

Create `migrate_2026_10_13_maipo_options.php`:

```php
<?php
// One-time migration: Maipo tour options (Classic / Classic + Lunch / Premium).
// Repurposes experiencias 'Maipo' as Premium ($139) and adds MAIPO_CLASSIC
// ($110) and MAIPO_CLASSIC_LUNCH ($129). Safe to run more than once.
//
// Visit this file once in a browser while logged into /login.php, confirm
// every line says [ok] or [skip], then DELETE THIS FILE from the server.
// Run it BEFORE deploying the new maipo-valley-wine-tour-santiago.php.

require __DIR__ . '/admin/_auth.php';
require __DIR__ . '/../db_config.php';
require __DIR__ . '/includes/migrations/maipo_options_2026_10_13.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Stamp's Tour migration -- " . date('Y-m-d H:i:s') . "\n\n";

try {
    foreach (migrate_maipo_options($conn) as $line) {
        echo $line . "\n";
    }
    echo "\nDone. Delete this file from the server.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo '[error] ' . $e->getMessage() . "\n";
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php -l migrate_2026_10_13_maipo_options.php && php tests/tools/phpunit.phar`
Expected: no syntax errors, and all tests pass (3 new).

- [ ] **Step 5: Commit**

```bash
git add includes/migrations/maipo_options_2026_10_13.php migrate_2026_10_13_maipo_options.php tests/MaipoOptionsMigrationTest.php tests/bootstrap.php
git commit -m "feat: idempotent data migration for the three Maipo experiencias rows

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Public booking endpoint `book_tour.php`

**Files:**
- Create: `includes/book_tour_handler.php`
- Create: `book_tour.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/BookTourHandlerTest.php`

**Interfaces:**
- Consumes: `booking_calculate_total`, `booking_insert_reservation`, `BookingUnknownExperienceException` (Tasks 1–2); `maipo_option_exp_names()` (Task 3).
- Produces: `book_tour_handle(mysqli $conn, array $post, DateTimeImmutable $today): array`. It returns `['ok' => true, 'reference_id' => string, 'total' => float]` or `['ok' => false, 'error' => string]`, and lets only database/unexpected errors through as exceptions.
- Produces: `book_tour_allowed_experiences(): array` (a list of `experiencias.nombre`).

- [ ] **Step 1: Write the failing test**

Create `tests/BookTourHandlerTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BookTourHandlerTest extends TestCase
{
    private mysqli $conn;
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->today = new DateTimeImmutable('2026-10-13 23:30:00', new DateTimeZone('America/Santiago'));
        $this->cleanup();
        $this->conn->query("INSERT INTO experiencias (nombre, nombre_publico, precio_adulto, precio_nino, precio_infante)
                            VALUES ('MAIPO_CLASSIC', 'Maipo Valley Wine Tour - Classic Route', 110.00, 50.00, 0.00),
                                   ('Maipo', 'Maipo Valley Wine Tour - Premium Route with Undurraga', 139.00, 50.00, 0.00),
                                   ('Valparaiso', 'Valparaiso', 79.00, 40.00, 0.00)");
        // MAIPO_CLASSIC_LUNCH deliberately absent: simulates the page deployed before the migration
        $this->conn->query("INSERT INTO cupones (nombre, id_vendedor, porcentaje) VALUES ('TEST10', 1, 10)");
        $this->conn->query("INSERT INTO vendedores (nombre_vendedor) VALUES ('TEST_VENDOR')");
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->query("DELETE FROM reservas");
        $this->conn->query("DELETE FROM titulares WHERE email LIKE '%@example.test'");
        $this->conn->query("DELETE FROM experiencias WHERE nombre IN ('MAIPO_CLASSIC', 'MAIPO_CLASSIC_LUNCH', 'Maipo', 'Valparaiso')");
        $this->conn->query("DELETE FROM cupones WHERE nombre LIKE 'TEST%'");
        $this->conn->query("DELETE FROM vendedores WHERE nombre_vendedor LIKE 'TEST\\_%' ESCAPE '\\\\'");
    }

    private function post(array $overrides = []): array
    {
        return array_merge([
            'name_booking'      => 'Ana',
            'last_name_booking' => 'Traveler',
            'email_booking'     => 'ana@example.test',
            'phone_booking'     => '+56 9 1234 5678',
            'date_booking'      => '11-20-2026',
            'adults'            => '2',
            'children'          => '1',
            'infants'           => '0',
            'activity_name'     => 'MAIPO_CLASSIC',
            'coupon_code'       => '',
            'subtotal'          => '1',
            'total_price'       => '1',
        ], $overrides);
    }

    private function reserva(string $ref): array
    {
        $stmt = $this->conn->prepare('SELECT r.*, e.nombre AS exp FROM reservas r JOIN experiencias e ON e.id_experiencia = r.id_experiencia WHERE r.reference_id = ?');
        $stmt->bind_param('s', $ref);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function reservasCount(): int
    {
        return (int)$this->conn->query('SELECT COUNT(*) c FROM reservas')->fetch_assoc()['c'];
    }

    public function testPostedTotalIsIgnoredAndServerTotalStored(): void
    {
        $r = book_tour_handle($this->conn, $this->post(), $this->today);
        $this->assertTrue($r['ok']);
        $this->assertSame(270.0, $r['total']);
        $row = $this->reserva($r['reference_id']);
        $this->assertSame('270.00', $row['total_venta']);
        $this->assertSame('270.00', $row['subtotal']);
        $this->assertSame('MAIPO_CLASSIC', $row['exp']);
    }

    public function testPremiumIsBookedAgainstTheMaipoRow(): void
    {
        $r = book_tour_handle($this->conn, $this->post(['activity_name' => 'Maipo', 'children' => '0']), $this->today);
        $this->assertTrue($r['ok']);
        $this->assertSame('278.00', $this->reserva($r['reference_id'])['total_venta']);
    }

    public function testCouponAppliedServerSide(): void
    {
        $r = book_tour_handle($this->conn, $this->post(['coupon_code' => 'test10']), $this->today);
        $row = $this->reserva($r['reference_id']);
        $this->assertSame('270.00', $row['subtotal']);
        $this->assertSame('243.00', $row['total_venta']);
    }

    public function testAdminOnlyFieldsAreIgnored(): void
    {
        $r = book_tour_handle($this->conn, $this->post([
            'codigo_vendedor' => 'TEST_VENDOR',
            'is_private'      => '1',
            'airport_pick_up' => 'Yes',
        ]), $this->today);
        $row = $this->reserva($r['reference_id']);
        $this->assertNull($row['id_vendedor']);
        $this->assertSame(0, (int)$row['is_private']);
        $this->assertSame(0, (int)$row['airport_pickup']);
    }

    public function testTodayInSantiagoIsBookable(): void
    {
        $r = book_tour_handle($this->conn, $this->post(['date_booking' => '10-13-2026']), $this->today);
        $this->assertTrue($r['ok']);
    }

    /** @dataProvider rejectedPosts */
    public function testRejectedWithoutInserting(array $overrides): void
    {
        $r = book_tour_handle($this->conn, $this->post($overrides), $this->today);
        $this->assertFalse($r['ok']);
        $this->assertNotSame('', $r['error']);
        $this->assertSame(0, $this->reservasCount());
    }

    public static function rejectedPosts(): array
    {
        return [
            'not allow-listed tour'      => [['activity_name' => 'Valparaiso']],
            'allow-listed but no DB row' => [['activity_name' => 'MAIPO_CLASSIC_LUNCH']],
            'no adults'                  => [['adults' => '0', 'children' => '2']],
            'more than 15 travelers'     => [['adults' => '10', 'children' => '6']],
            'non-numeric count'          => [['adults' => 'abc']],
            'negative count'             => [['children' => '-1']],
            'past date'                  => [['date_booking' => '10-12-2026']],
            'impossible date'            => [['date_booking' => '13-45-2026']],
            'wrong date format'          => [['date_booking' => '2026-11-20']],
            'missing email'              => [['email_booking' => '']],
            'invalid email'              => [['email_booking' => 'not-an-email']],
            'missing phone'              => [['phone_booking' => '  ']],
        ];
    }
}
```

Add to `tests/bootstrap.php`:

```php
require_once __DIR__ . '/../includes/book_tour_handler.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter BookTourHandlerTest`
Expected: FAIL with "Failed opening required '.../includes/book_tour_handler.php'".

- [ ] **Step 3: Implement the handler**

Create `includes/book_tour_handler.php`:

```php
<?php
// includes/book_tour_handler.php
// Validation + server-side pricing for book_tour.php (public tour pages).
// The browser's subtotal/total_price are never used; admin-only fields
// (codigo_vendedor, is_private, airport_pick_up) are stripped.

require_once __DIR__ . '/booking.php';
require_once __DIR__ . '/tours/maipo_options.php';

const BOOK_TOUR_MAX_TRAVELERS = 15;

/** experiencias.nombre values that may be booked through book_tour.php */
function book_tour_allowed_experiences(): array
{
    return array_values(maipo_option_exp_names());
}

function book_tour_handle(mysqli $conn, array $post, DateTimeImmutable $today): array
{
    $fail = fn(string $msg) => ['ok' => false, 'error' => $msg];

    $exp = trim((string)($post['activity_name'] ?? ''));
    if (!in_array($exp, book_tour_allowed_experiences(), true)) {
        return $fail('This tour cannot be booked online. Please contact us.');
    }

    foreach (['name_booking', 'last_name_booking', 'email_booking', 'phone_booking'] as $field) {
        if (trim((string)($post[$field] ?? '')) === '') {
            return $fail('Please fill in your name, last name, email and phone.');
        }
    }
    if (filter_var(trim((string)$post['email_booking']), FILTER_VALIDATE_EMAIL) === false) {
        return $fail('Please enter a valid email address.');
    }

    $rawDate = trim((string)($post['date_booking'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('!m-d-Y', $rawDate, $today->getTimezone());
    if ($date === false || $date->format('m-d-Y') !== $rawDate) {
        return $fail('Please choose a valid tour date.');
    }
    if ($date < $today->setTime(0, 0)) {
        return $fail('The tour date cannot be in the past.');
    }

    $counts = [];
    foreach (['adults', 'children', 'infants'] as $field) {
        $raw = trim((string)($post[$field] ?? '0'));
        if (preg_match('/^\d{1,2}$/', $raw) !== 1) {
            return $fail('Please enter a valid number of travelers.');
        }
        $counts[$field] = (int)$raw;
    }
    if ($counts['adults'] < 1) {
        return $fail('At least one adult is required.');
    }
    if (array_sum($counts) > BOOK_TOUR_MAX_TRAVELERS) {
        return $fail('Groups are limited to ' . BOOK_TOUR_MAX_TRAVELERS . ' travelers. Please contact us for larger groups.');
    }

    try {
        $amounts = booking_calculate_total($conn, $exp, $counts['adults'], $counts['children'], $counts['infants'], (string)($post['coupon_code'] ?? ''));
    } catch (BookingUnknownExperienceException $e) {
        return $fail('This tour option is not available right now. Please contact us.');
    }

    $clean = $post;
    unset($clean['codigo_vendedor']);      // vendor attribution is admin-only
    $clean['is_private'] = '0';
    $clean['airport_pick_up'] = 'No';
    $clean['adults'] = (string)$counts['adults'];
    $clean['children'] = (string)$counts['children'];
    $clean['infants'] = (string)$counts['infants'];
    $clean['activity_name'] = $exp;
    $clean['date_booking'] = $rawDate;

    $ref = booking_insert_reservation($conn, $clean, $amounts['subtotal'], $amounts['total']);

    return ['ok' => true, 'reference_id' => $ref, 'total' => $amounts['total']];
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/tools/phpunit.phar --filter BookTourHandlerTest`
Expected: PASS (5 tests + 12 data-provider cases).

- [ ] **Step 5: Create the endpoint**

Create `book_tour.php`:

```php
<?php
// book_tour.php
// Public booking endpoint for tour pages that sell through the option
// catalogue (Maipo first). Unlike submit.php, the total is calculated
// server-side - see includes/book_tour_handler.php and
// docs/superpowers/specs/2026-10-13-maipo-tour-options-design.md

require __DIR__ . '/../db_config.php';
require_once __DIR__ . '/includes/book_tour_handler.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /');
    exit;
}

try {
    $result = book_tour_handle($conn, $_POST, new DateTimeImmutable('now', new DateTimeZone('America/Santiago')));
} catch (Throwable $e) {
    error_log('book_tour.php: ' . $e->getMessage());
    $result = ['ok' => false, 'error' => 'We could not save your booking. Please try again or contact us.'];
}

if ($result['ok']) {
    session_start();
    $_SESSION['reference_id'] = $result['reference_id'];
    header('Location: shopping.php?reference_id=' . urlencode($result['reference_id']));
    exit;
}

http_response_code(400);
$message = htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Booking not completed | Stamp's Tour</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; background: #f9f9f9; color: #333; margin: 0; padding: 48px 16px; }
    .box { max-width: 520px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 24px; }
    h1 { font-size: 20px; margin-top: 0; }
    a { display: inline-block; margin-top: 12px; background: #008489; color: #fff; padding: 10px 18px; border-radius: 4px; text-decoration: none; }
  </style>
</head>
<body>
  <div class="box">
    <h1>We couldn't complete your booking</h1>
    <p><?= $message ?></p>
    <a href="javascript:history.back()">Go back and fix it</a>
  </div>
</body>
</html>
```

Run: `php -l book_tour.php`
Expected: "No syntax errors detected".

- [ ] **Step 6: Run the full suite**

Run: `php tests/tools/phpunit.phar`
Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add includes/book_tour_handler.php book_tour.php tests/BookTourHandlerTest.php tests/bootstrap.php
git commit -m "feat: book_tour.php public endpoint with server-side pricing

Ignores browser-posted totals and admin-only fields (codigo_vendedor,
is_private, airport_pick_up); allow-listed to the Maipo options.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Product schema with per-option offers

**Files:**
- Modify: `includes/tour_schema.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/TourSchemaTest.php`

**Interfaces:**
- Produces: `render_tour_product_schema(array $data)` now accepts an optional `offers` key, a list of `['name' => string, 'price' => float, 'url' => string]`. When that list isn't empty it emits an `AggregateOffer`; otherwise it falls back to the existing single `Offer` built from `price`.

- [ ] **Step 1: Write the failing test**

Create `tests/TourSchemaTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TourSchemaTest extends TestCase
{
    private function render(array $data): array
    {
        ob_start();
        render_tour_product_schema($data);
        $html = ob_get_clean();
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m));
        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function base(): array
    {
        return ['name' => 'Tour', 'description' => 'D', 'image' => 'img/x.webp', 'url' => 'https://stampstour.com/t', 'price' => 110.0];
    }

    public function testSinglePriceKeepsOfferShape(): void
    {
        $s = $this->render($this->base());
        $this->assertSame('Offer', $s['offers']['@type']);
        $this->assertSame('110.00', $s['offers']['price']);
    }

    public function testOffersListBecomesAggregateOffer(): void
    {
        $s = $this->render($this->base() + ['offers' => [
            ['name' => 'Classic Route', 'price' => 110.0, 'url' => 'https://stampstour.com/t?option=classic'],
            ['name' => 'Classic Route + Lunch', 'price' => 129.0, 'url' => 'https://stampstour.com/t?option=classic-lunch'],
            ['name' => 'Premium Route', 'price' => 139.0, 'url' => 'https://stampstour.com/t?option=premium'],
        ]]);

        $agg = $s['offers'];
        $this->assertSame('AggregateOffer', $agg['@type']);
        $this->assertSame('USD', $agg['priceCurrency']);
        $this->assertSame('110.00', $agg['lowPrice']);
        $this->assertSame('139.00', $agg['highPrice']);
        $this->assertSame(3, $agg['offerCount']);
        $this->assertSame('Premium Route', $agg['offers'][2]['name']);
        $this->assertSame('139.00', $agg['offers'][2]['price']);
        $this->assertSame('https://stampstour.com/t?option=premium', $agg['offers'][2]['url']);
        $this->assertSame('https://schema.org/InStock', $agg['offers'][0]['availability']);
    }

    public function testEmptyOffersFallsBackToPrice(): void
    {
        $s = $this->render($this->base() + ['offers' => []]);
        $this->assertSame('Offer', $s['offers']['@type']);
    }
}
```

Add to `tests/bootstrap.php`:

```php
require_once __DIR__ . '/../includes/tour_schema.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter TourSchemaTest`
Expected: `testOffersListBecomesAggregateOffer` fails (`'Offer'` doesn't match `'AggregateOffer'`), and the other two pass.

- [ ] **Step 3: Implement**

In `includes/tour_schema.php`, update the header comment: change `// Tripadvisor listing).` to:

```php
// Tripadvisor listing), offers (optional list of ['name','price','url'] for
// tours sold in several variants - emitted as an AggregateOffer).
```

Replace the block from `$price = $data['price'] ?? null;` through the closing `}` of `if ($price !== null) { ... }` with:

```php
    $price = $data['price'] ?? null;
    $validUntil = date('Y-m-d', strtotime('+6 months'));
    $offers = array_values((array)($data['offers'] ?? []));

    if (!empty($offers)) {
        $items = [];
        $prices = [];
        foreach ($offers as $offer) {
            $offerPrice = (float)$offer['price'];
            $prices[] = $offerPrice;
            $items[] = [
                '@type' => 'Offer',
                'name' => (string)$offer['name'],
                'url' => (string)($offer['url'] ?? $url),
                'priceCurrency' => 'USD',
                'price' => number_format($offerPrice, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
                'priceValidUntil' => $validUntil,
            ];
        }
        $schema['offers'] = [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'USD',
            'lowPrice' => number_format(min($prices), 2, '.', ''),
            'highPrice' => number_format(max($prices), 2, '.', ''),
            'offerCount' => count($items),
            'offers' => $items,
        ];
    } elseif ($price !== null) {
        $schema['offers'] = [
            '@type' => 'Offer',
            'url' => $url,
            'priceCurrency' => 'USD',
            'price' => number_format((float)$price, 2, '.', ''),
            'availability' => 'https://schema.org/InStock',
            'priceValidUntil' => $validUntil,
        ];
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php tests/tools/phpunit.phar`
Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add includes/tour_schema.php tests/TourSchemaTest.php tests/bootstrap.php
git commit -m "feat: AggregateOffer support in tour Product schema

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Option selector + itinerary renderers and CSS

**Files:**
- Create: `includes/tours/option_ui.php`
- Create: `css/tour-options.css`
- Modify: `tests/bootstrap.php`
- Test: `tests/OptionUiTest.php`

**Interfaces:**
- Consumes: the option array shape from Task 3.
- Produces:
  - `render_option_selector(array $options, array $prices, string $selected, string $name, string $legend, string $modifier = ''): void`. `$prices` is `slug => ['adult' => float, ...]`, and slugs missing from it render disabled with "Contact us". Every radio has the `data-booking-lock` attribute. The fieldset has `data-tour-options`.
  - `render_option_block_start(string $slug, string $selected): void` / `render_option_block_end(): void`, which wrap per-option content in `<div data-option="slug">` (with `hidden` unless selected).
  - `render_option_itinerary(string $slug, array $option, string $selected): void`
  - `render_option_compare_table(array $options, array $prices): void`

- [ ] **Step 1: Write the failing test**

Create `tests/OptionUiTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OptionUiTest extends TestCase
{
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return (string)ob_get_clean();
    }

    private function prices(): array
    {
        return ['classic' => ['adult' => 110.0], 'premium' => ['adult' => 139.0]]; // classic-lunch missing
    }

    public function testSelectorChecksSelectedAndDisablesUnpriced(): void
    {
        $html = $this->capture(fn() => render_option_selector(maipo_options(), $this->prices(), 'premium', 'tour_option', 'Choose your tour'));
        $this->assertStringContainsString('data-tour-options', $html);
        $this->assertMatchesRegularExpression('/value="premium"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="classic"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/value="classic-lunch"[^>]*disabled/', $html);
        $this->assertStringContainsString('Contact us', $html);
        $this->assertStringContainsString('$139', $html);
        $this->assertSame(3, substr_count($html, 'data-booking-lock'));
    }

    public function testItineraryHiddenUnlessSelected(): void
    {
        $opts = maipo_options();
        $visible = $this->capture(fn() => render_option_itinerary('premium', $opts['premium'], 'premium'));
        $hidden = $this->capture(fn() => render_option_itinerary('classic', $opts['classic'], 'premium'));
        $this->assertStringContainsString('<div data-option="premium">', $visible);
        $this->assertStringContainsString('Viña Undurraga', $visible);
        $this->assertStringContainsString('<div data-option="classic" hidden>', $hidden);
        $this->assertStringContainsString('Viña Ballek', $hidden);
    }

    public function testCompareTableListsAllOptions(): void
    {
        $html = $this->capture(fn() => render_option_compare_table(maipo_options(), $this->prices()));
        foreach (['Classic Route', 'Classic Route + Lunch', 'Premium Route', 'Included', 'Own cost', '$110', '$139'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function testOutputIsEscaped(): void
    {
        $opts = ['x' => array_merge(maipo_options()['classic'], ['label' => '<b>x</b>'])];
        $html = $this->capture(fn() => render_option_selector($opts, [], 'x', 'n', 'L'));
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }
}
```

Add to `tests/bootstrap.php`:

```php
require_once __DIR__ . '/../includes/tours/option_ui.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter OptionUiTest`
Expected: FAIL with "Failed opening required '.../includes/tours/option_ui.php'".

- [ ] **Step 3: Implement the renderers**

Create `includes/tours/option_ui.php`:

```php
<?php
// includes/tours/option_ui.php
// Server-rendered UI for tours sold in several options (Maipo first).
// Everything is in the HTML for SEO; js/maipo-options.js only toggles the
// [data-option] blocks and keeps the [data-tour-options] radios in sync.
// Option array shape: see includes/tours/maipo_options.php.

function option_ui_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function option_ui_price($amount): string
{
    return '$' . number_format((float)$amount, 0);
}

function render_option_selector(array $options, array $prices, string $selected, string $name, string $legend, string $modifier = ''): void
{
    ?>
    <fieldset class="tour-options<?= $modifier !== '' ? ' tour-options--' . option_ui_h($modifier) : '' ?>" data-tour-options>
     <legend class="tour-options__legend"><?= option_ui_h($legend) ?></legend>
     <?php foreach ($options as $slug => $opt): ?>
      <?php $price = $prices[$slug]['adult'] ?? null; $id = $name . '-' . $slug; ?>
      <label class="tour-option" for="<?= option_ui_h($id) ?>">
       <input type="radio" name="<?= option_ui_h($name) ?>" id="<?= option_ui_h($id) ?>" value="<?= option_ui_h($slug) ?>" data-booking-lock<?= $slug === $selected ? ' checked' : '' ?><?= $price === null ? ' disabled' : '' ?>>
       <span class="tour-option__body">
        <span class="tour-option__label"><?= option_ui_h($opt['label']) ?></span>
        <span class="tour-option__price"><?= $price !== null ? option_ui_h(option_ui_price($price)) : 'Contact us' ?></span>
        <span class="tour-option__tagline"><?= option_ui_h($opt['tagline']) ?></span>
       </span>
      </label>
     <?php endforeach; ?>
    </fieldset>
    <?php
}

function render_option_block_start(string $slug, string $selected): void
{
    echo '<div data-option="' . option_ui_h($slug) . '"' . ($slug === $selected ? '' : ' hidden') . '>';
}

function render_option_block_end(): void
{
    echo '</div>';
}

function render_option_itinerary(string $slug, array $option, string $selected): void
{
    render_option_block_start($slug, $selected);
    ?>
     <ul class="cbp_tmtimeline">
      <?php foreach ($option['stops'] as $stop): ?>
       <li>
        <?php if ($stop['duration'] !== ''): ?>
         <time class="cbp_tmtime"><span><?= option_ui_h($stop['duration']) ?></span><span></span></time>
        <?php endif; ?>
        <div class="cbp_tmicon <?= option_ui_h($stop['icon']) ?>"></div>
        <div class="cbp_tmlabel">
         <h2><span><?= option_ui_h($stop['place']) ?></span> <?= option_ui_h($stop['title']) ?></h2>
         <p><?= option_ui_h($stop['text']) ?></p>
        </div>
       </li>
      <?php endforeach; ?>
     </ul>
    <?php
    render_option_block_end();
}

function render_option_compare_table(array $options, array $prices): void
{
    ?>
    <div class="table-responsive">
     <table class="table table-striped tour-compare">
      <thead>
       <tr>
        <th scope="col"></th>
        <?php foreach ($options as $opt): ?>
         <th scope="col"><?= option_ui_h($opt['label']) ?></th>
        <?php endforeach; ?>
       </tr>
      </thead>
      <tbody>
       <tr>
        <th scope="row">Campo La Quirinca, Santa Ema, TerraMater</th>
        <?php foreach ($options as $opt): ?><td>Yes</td><?php endforeach; ?>
       </tr>
       <tr>
        <th scope="row">Final winery</th>
        <?php foreach ($options as $opt): ?><td><?= option_ui_h($opt['final_winery']) ?></td><?php endforeach; ?>
       </tr>
       <tr>
        <th scope="row">Lunch at TerraMater</th>
        <?php foreach ($options as $opt): ?><td><?= $opt['lunch_included'] ? 'Included' : 'Own cost' ?></td><?php endforeach; ?>
       </tr>
       <tr>
        <th scope="row">Best for</th>
        <?php foreach ($options as $opt): ?><td><?= option_ui_h($opt['best_for']) ?></td><?php endforeach; ?>
       </tr>
       <tr>
        <th scope="row">Adult price</th>
        <?php foreach ($options as $slug => $opt): ?>
         <td><?= isset($prices[$slug]['adult']) ? option_ui_h(option_ui_price($prices[$slug]['adult'])) : 'Contact us' ?></td>
        <?php endforeach; ?>
       </tr>
      </tbody>
     </table>
    </div>
    <?php
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php tests/tools/phpunit.phar`
Expected: all tests pass.

- [ ] **Step 5: Add the CSS**

Create `css/tour-options.css`:

```css
/* css/tour-options.css
 * Option selector cards + comparison table for tour pages sold in several
 * options (Maipo first). Rendered by includes/tours/option_ui.php. */
.tour-options { border: 0; margin: 0 0 20px; padding: 0; min-width: 0; }
.tour-options__legend { float: none; width: auto; font-size: 13px; font-weight: 700; text-transform: uppercase; margin-bottom: 10px; }
.tour-option { display: block; position: relative; margin: 0 0 8px; cursor: pointer; }
.tour-option input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.tour-option__body { display: grid; grid-template-columns: 1fr auto; gap: 2px 12px; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; background: #fff; transition: border-color .15s ease, box-shadow .15s ease; }
.tour-option__label { font-weight: 700; color: #333; }
.tour-option__price { font-weight: 700; color: #008489; text-align: right; }
.tour-option__tagline { grid-column: 1 / -1; font-size: 12px; line-height: 1.4; color: #666; }
.tour-option input:checked + .tour-option__body { border-color: #008489; box-shadow: 0 0 0 2px rgba(0, 132, 137, .2); }
.tour-option input:focus-visible + .tour-option__body { outline: 2px solid #333; outline-offset: 2px; }
.tour-option input:disabled + .tour-option__body { opacity: .55; cursor: not-allowed; }
.tour-options--inline { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; }
.tour-options--inline .tour-options__legend { grid-column: 1 / -1; }
.tour-options--inline .tour-option { margin: 0; }
.tour-compare th, .tour-compare td { font-size: 14px; vertical-align: top; }
```

- [ ] **Step 6: Commit**

```bash
git add includes/tours/option_ui.php css/tour-options.css tests/OptionUiTest.php tests/bootstrap.php
git commit -m "feat: server-rendered option selector, itinerary and comparison table

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Maipo page integration (server side)

**Files:**
- Modify: `maipo-valley-wine-tour-santiago.php`

**Interfaces:**
- Consumes: `maipo_options`, `maipo_resolve_option` (Task 3); `fetch_tour_prices` (Task 1); `render_tour_product_schema` with `offers` (Task 6); `render_option_selector`, `render_option_itinerary`, `render_option_compare_table`, `render_option_block_start/end` (Task 7).
- Produces (for Task 9): `window.MAIPO_OPTIONS` = `{slug: {exp, adult, child, infant}}` (only options with a DB price), `window.MAIPO_SELECTED` = slug, `#activity_name` pre-filled with the selected option's `exp`, and form `#booking` with `action="book_tour.php"`.

- [ ] **Step 1: Replace the top PHP block (current lines 1–22, through the closing `?>`)**

```php
<?php
$page_title       = 'Maipo Valley Wine Tour from Santiago: 4 Wineries, Classic or Premium';
$page_description = 'Small-group Maipo Valley wine tour from Santiago: 4 wineries with tastings, hotel pickup and guide. Choose Classic, Classic with lunch included, or Premium with Undurraga.';
$page_canonical   = 'https://stampstour.com/maipo-valley-wine-tour-santiago';
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
$lcp_preload_image = 'img/Tours/Maipo/big-optimized.webp';
$vendor_css_variant = 'tour';
$exp_name = 'Maipo'; // availability (cupos) is shared by all three options - always the Maipo row
require __DIR__ . '/../db_config.php';
require __DIR__ . '/includes/tour_price.php';
require __DIR__ . '/includes/tour_faq.php';
require __DIR__ . '/includes/tours/maipo_options.php';
require __DIR__ . '/includes/tours/option_ui.php';

$maipo_options  = maipo_options();
$maipo_selected = maipo_resolve_option($_GET['option'] ?? null);
$maipo_db_prices = fetch_tour_prices($conn, array_column($maipo_options, 'exp_name'));

// slug => {exp, adult, child, infant}; options without a DB row are left out
// (the selector shows them disabled with "Contact us")
$maipo_js_options = [];
foreach ($maipo_options as $slug => $opt) {
    if (!isset($maipo_db_prices[$opt['exp_name']])) {
        continue;
    }
    $maipo_js_options[$slug] = ['exp' => $opt['exp_name']] + $maipo_db_prices[$opt['exp_name']];
}
$maipo_adult_prices  = array_column($maipo_js_options, 'adult');
$dynamic_price_adult = $maipo_adult_prices ? min($maipo_adult_prices) : null;

$tour_faqs = [
    ['q' => 'How many wineries do we visit on the Maipo Valley wine tour?', 'a' => "Four stops in the Maipo Valley on every option: Campo La Quirinca (wine and pisco tasting), Viña Santa Ema (wine tasting), Viña TerraMater (lunch stop) and a final winery that depends on the option you book - boutique Viña Ballek on the Classic routes, or historic Viña Undurraga on the Premium route."],
    ['q' => "What's the difference between Classic, Classic + Lunch and Premium?", 'a' => "The first three stops are the same. Classic ends at Viña Ballek, a relaxed boutique winery by the Maipo River, with lunch at your own cost. Classic + Lunch is the same itinerary with lunch at TerraMater included in the price. Premium replaces Ballek with an extended guided tour of Viña Undurraga - gardens, production area, barrel cellar and a tasting of four premium wines - with lunch at your own cost."],
    ['q' => 'Do we visit both Viña Ballek and Viña Undurraga?', 'a' => 'No. They are alternative final stops: the Classic routes end at Ballek and the Premium route ends at Undurraga.'],
    ['q' => 'Is lunch included?', 'a' => 'Only on the Classic Route + Lunch option. On Classic and Premium you stop for lunch at Zinfandel restaurant on the TerraMater estate and pay there, or you can bring your own food.'],
    ['q' => 'Where does the tour pick me up?', 'a' => 'Hotel pickup is offered from Las Condes, Vitacura, Providencia, Santiago Centro, and Recoleta; your exact pickup time is sent the night before.'],
    ['q' => 'How many people are on the tour?', 'a' => "Groups are capped at 15 travelers, with a minimum of 4 required to run - if the minimum isn't met, you'll be offered another date or a full refund."],
    ['q' => "What's the cancellation policy?", 'a' => "Free cancellation up to 24 hours before the tour's start time; cancellations made inside that window aren't refunded."],
    ['q' => 'Is the Maipo Valley wine tour worth it?', 'a' => "If you want a taste of Chilean wine country without renting a car or planning your own route between wineries, yes - in one day you visit four different Maipo Valley wineries with guided tastings and hotel pickup included."],
    ['q' => 'Can I book this tour privately?', 'a' => 'A private version of this tour is available - pricing depends on your group size, so please contact us directly to inquire.'],
];
?>
```

- [ ] **Step 2: Load the option CSS in `<head>`**

After the `<noscript><link href="css/timeline.css" rel="stylesheet"></noscript>` line, add:

```html
  <link rel="stylesheet" href="css/tour-options.css">
```

- [ ] **Step 3: Replace the schema call**

Replace the whole `render_tour_product_schema([ ... ]);` call (currently `'name' => 'Small-Group Maipo Valley Wine Tour: 4 Vineyards from Santiago'` … `]);`) with:

```php
  $schema_offers = [];
  foreach ($maipo_js_options as $slug => $o) {
      $schema_offers[] = ['name' => $maipo_options[$slug]['label'], 'price' => $o['adult'], 'url' => $page_canonical . '?option=' . $slug];
  }
  render_tour_product_schema([
      'name' => 'Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries',
      'description' => $page_description,
      'image' => $lcp_preload_image,
      'url' => $page_canonical,
      'price' => $dynamic_price_adult,
      'offers' => $schema_offers,
      'sameAs' => ['https://www.tripadvisor.com/AttractionProductReview-g294305-d15040546-Maipo_Valley_Wine_Tour_with_4_vineyards_from_Santiago-Santiago_Santiago_Metropolit.html'],
  ]);
```

- [ ] **Step 4: Update the hero H1 and price label**

Replace:

```html
       <h1>Small-Group Maipo Valley Wine Tour: 4 Vineyards from Santiago</h1>
```

with:

```html
       <h1>Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries</h1>
```

In `#price_single_main`, replace the text `Special offer` with `From`.

- [ ] **Step 5: Replace the overview section**

Replace everything from `<div class="row">` directly after `<!-- End Image Gallery -->` + `<hr/>`, through its `<!-- End row -->` comment (the block holding `<h2>Maipo Wine Tour Overview & Highlights</h2>` and "What's included"), with:

```php
      <div class="row">
        <h2>Maipo Wine Tour Overview & Highlights</h2>
       <div class="col-lg-3">
        <h3>Description</h3>
       </div>
       <div class="col-lg-9">
        <p>Spend a day in Chilean wine country around Isla de Maipo, a farming town named for the tributaries of the Maipo River that surround it and make for perfect grape-growing conditions. Every option visits four stops, from a small family farm to established wineries, with guided tastings, hotel pickup and an English-speaking guide.</p>
        <h4>Choose your route</h4>
        <p>All three options share the first three stops: Campo La Quirinca, Vi&ntilde;a Santa Ema and Vi&ntilde;a TerraMater. They differ in the final winery and in whether lunch is included.</p>
        <?php render_option_compare_table($maipo_options, $maipo_js_options); ?>
        <h4>What to expect</h4>
        <p>Your day begins with pickup from your hotel or residence in Santiago Centro, Providencia, Las Condes, Vitacura or Recoleta. If you are staying elsewhere, send us your address and we will confirm pickup or the nearest meeting point.</p>
        <p>The first stop is Campo La Quirinca, a family farm where you learn about traditional Chilean winemaking and taste local wines and Chilean pisco. Next is Vi&ntilde;a Santa Ema for a guided tasting of three wines, then Vi&ntilde;a TerraMater for lunch at its Zinfandel restaurant.</p>
        <p>The Classic routes end at Vi&ntilde;a Ballek, a boutique family winery by the Maipo River. The Premium route ends instead at Vi&ntilde;a Undurraga, one of Chile's most traditional wineries, with an extended guided tour and a tasting of four premium wines. The day ends with drop-off at your original pickup point.</p>
        <h4>What's included</h4>
        <?php foreach ($maipo_options as $slug => $opt): ?>
         <?php render_option_block_start($slug, $maipo_selected); ?>
          <div class="row">
           <div class="col-md-6">
            <ul class="list_ok">
             <?php foreach ($opt['included'] as $item): ?>
              <li><?= option_ui_h($item) ?></li>
             <?php endforeach; ?>
            </ul>
           </div>
           <div class="col-md-6">
            <p><strong>Not included</strong></p>
            <ul>
             <?php foreach ($opt['not_included'] as $item): ?>
              <li><?= option_ui_h($item) ?></li>
             <?php endforeach; ?>
            </ul>
           </div>
          </div>
         <?php render_option_block_end(); ?>
        <?php endforeach; ?>
       </div>
      </div>
      <!-- End row -->
```

- [ ] **Step 6: Replace the timeline section**

Replace everything from `<div class="main_title">` (the "Detailed timeline" heading) through the closing `</div>` of `<div class="text-center">` that holds `#toggle-btn` (the `render_tour_faq` line stays) with:

```php
      <div class="main_title">
       <h2>Detailed <span>timeline</span> for your tour</h2>
       <p>Pick an option to see its itinerary</p>
      </div>
      <?php render_option_selector($maipo_options, $maipo_js_options, $maipo_selected, 'tour_option_itin', 'Itinerary for', 'inline'); ?>
      <hr/>
      <div id="itinerary-wrapper" style="position:relative; max-height:180px; overflow:hidden; transition:max-height 0.5s ease;">
       <?php foreach ($maipo_options as $slug => $opt): ?>
        <?php render_option_itinerary($slug, $opt, $maipo_selected); ?>
       <?php endforeach; ?>
       <div id="fade-effect" class="fade-overlay" style="position:absolute; bottom:0; left:0; right:0; height:160px; background:linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1)); pointer-events:none; transition: opacity 0.3s ease;"></div>
      </div>
      <div class="text-center">
       <button id="toggle-btn" class="btn_1" onclick="toggleItinerary()">See more</button>
      </div>
```

- [ ] **Step 7: Point the booking form at `book_tour.php` and add the selector**

Replace:

```html
       <form action="submit.php" method="POST" id="booking">
        <input id="activity_name" name="activity_name" type="hidden" value="2"/>
        <!-- <input id="activity_name" name="activity_name" type="hidden" value=""/> -->
        <h3 class="inner">- Booking -</h3>
```

with:

```php
       <form action="book_tour.php" method="POST" id="booking">
        <input id="activity_name" name="activity_name" type="hidden" value="<?= option_ui_h($maipo_options[$maipo_selected]['exp_name']) ?>"/>
        <h3 class="inner">- Booking -</h3>
        <?php render_option_selector($maipo_options, $maipo_js_options, $maipo_selected, 'tour_option', 'Choose your tour'); ?>
```

- [ ] **Step 8: Inject the option data before the shared scripts**

Replace:

```php
<?php include __DIR__ . '/includes/tour-scripts.php'; ?>
```

with:

```php
<script>
window.MAIPO_OPTIONS = <?= json_encode((object)$maipo_js_options, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.MAIPO_SELECTED = <?= json_encode($maipo_selected) ?>;
</script>
<?php include __DIR__ . '/includes/tour-scripts.php'; ?>
<script defer src="js/maipo-options.js"></script>
```

- [ ] **Step 9: Run the migration against the LOCAL dev database**

First confirm the target. Run: `php -r 'require "../db_config.php"; echo $host, " / ", $dbname, PHP_EOL;'`
Expected: `localhost / stampst1_stamptour`, which is the local copy. If the host is not localhost, STOP and ask.

Run:

```bash
php -r 'require "../db_config.php"; require "includes/migrations/maipo_options_2026_10_13.php"; foreach (migrate_maipo_options($conn) as $l) echo $l, PHP_EOL;'
```

Expected: `[ok] inserted MAIPO_CLASSIC…`, `[ok] inserted MAIPO_CLASSIC_LUNCH…` and `[ok] updated Maipo…`. A second run prints three `[skip]` lines.

- [ ] **Step 10: Verify the rendered page**

Run:

```bash
php -l maipo-valley-wine-tour-santiago.php
php -S 127.0.0.1:8099 >/tmp/stamp-maipo-server.log 2>&1 &
sleep 1
for o in classic classic-lunch premium PREMIUM bogus; do
  echo "== $o"
  curl -s "http://127.0.0.1:8099/maipo-valley-wine-tour-santiago.php?option=$o" > /tmp/maipo-$o.html
  grep -o 'MAIPO_SELECTED = "[a-z-]*"' /tmp/maipo-$o.html
  grep -o 'name="activity_name" type="hidden" value="[A-Za-z_]*"' /tmp/maipo-$o.html
  grep -o 'data-option="' /tmp/maipo-$o.html | wc -l
done
grep -o '<link rel="canonical" href="[^"]*"' /tmp/maipo-premium.html
grep -o '"@type":"AggregateOffer"[^]]*' /tmp/maipo-premium.html | head -c 300; echo
grep -c 'Warning\|Notice\|Fatal' /tmp/maipo-*.html /tmp/stamp-maipo-server.log
kill %1
```

Expected:
- `classic` → `MAIPO_SELECTED = "classic"` and `value="MAIPO_CLASSIC"`
- `classic-lunch` → `MAIPO_CLASSIC_LUNCH`
- `premium` and `PREMIUM` → `"premium"` and `value="Maipo"`
- `bogus` → `"classic"`
- `data-option="` count is 6 on every page (3 included-blocks + 3 itineraries)
- The canonical URL has no `?option`
- AggregateOffer shows `"lowPrice":"110.00","highPrice":"139.00","offerCount":3`
- 0 warnings or notices in every file

- [ ] **Step 11: Commit (page only, not the Portillo WIP)**

```bash
git add maipo-valley-wine-tour-santiago.php
git commit -m "feat: Maipo page sells Classic / Classic + Lunch / Premium options

Server-rendered selector, per-option itineraries and inclusions,
comparison table, updated FAQ and AggregateOffer schema; booking form
posts to book_tour.php.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Client-side option switching

**Files:**
- Create: `js/maipo-options.js`
- Modify: `js/tours.js` (price source, coupon lock, form action)

**Interfaces:**
- Consumes: `window.MAIPO_OPTIONS`, `window.MAIPO_SELECTED`, `#activity_name`, `[data-tour-options] input[type=radio]`, `[data-option]`, `[data-booking-lock]` (Tasks 7–8).
- Produces: the `maipo:optionchange` CustomEvent on `document` (`detail.option` = slug), which `tours.js` listens for.

- [ ] **Step 1: Create `js/maipo-options.js`**

```js
/* js/maipo-options.js
 * Option selector for the Maipo tour page (Classic / Classic + Lunch / Premium).
 * Keeps both selectors, the visible itinerary + "what's included" blocks,
 * the hidden activity_name field and the URL (?option=) in sync, then tells
 * tours.js to recalculate the total via the 'maipo:optionchange' event.
 * Data comes from window.MAIPO_OPTIONS / window.MAIPO_SELECTED (set by the
 * page). Loaded after tours.js so its listener is registered first.
 */
document.addEventListener('DOMContentLoaded', function () {
  var options = window.MAIPO_OPTIONS || {};
  var radios = document.querySelectorAll('[data-tour-options] input[type="radio"]');
  var activity = document.getElementById('activity_name');

  function select(slug, fromUser) {
    if (!options[slug]) return;
    window.MAIPO_SELECTED = slug;
    if (activity) activity.value = options[slug].exp;

    radios.forEach(function (r) { r.checked = (r.value === slug); });
    document.querySelectorAll('[data-option]').forEach(function (el) {
      el.hidden = el.getAttribute('data-option') !== slug;
    });

    if (fromUser && window.history && history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.set('option', slug);
      history.replaceState(null, '', url);
    }

    document.dispatchEvent(new CustomEvent('maipo:optionchange', { detail: { option: slug } }));

    if (fromUser && typeof window.gtag === 'function') {
      window.gtag('event', 'select_item', {
        item_list_name: 'Maipo tour options',
        items: [{ item_id: options[slug].exp, item_name: slug, price: options[slug].adult }]
      });
    }
  }

  radios.forEach(function (r) {
    r.addEventListener('change', function () {
      if (r.checked) select(r.value, true);
    });
  });

  select(window.MAIPO_SELECTED, false);
});
```

- [ ] **Step 2: Make `tours.js` take prices from `MAIPO_OPTIONS` when present**

In `js/tours.js`, replace this block (currently around lines 73–87):

```js
     // Fetch tour prices from server
     const exp = window.EXP_NAME || 'Maipo';
     fetch(`get_prices.php?nombre=${encodeURIComponent(exp)}`)
       .then(res => res.json())
       .then(data => {
         prices.adult = parseFloat(data.precio_adulto) || 0;
         prices.child = parseFloat(data.precio_nino) || 0;
         document.getElementById('dynamic_price').textContent = prices.adult;
         document.getElementById('activity_name').value = data.nombre || 'Maipo';
         calcularTotal(); // initialize total with fetched prices
       })
       .catch(err => {
         console.error('Error fetching prices:', err);
         // if error, prices remain 0 (total will be 0, form submit disabled)
       });
```

with:

```js
     if (window.MAIPO_OPTIONS) {
       // Pages with options (Maipo) inject all prices server-side; js/maipo-options.js
       // owns activity_name and fires 'maipo:optionchange' when the option changes.
       const applyOptionPrices = () => {
         const p = window.MAIPO_OPTIONS[window.MAIPO_SELECTED] || {};
         prices.adult = parseFloat(p.adult) || 0;
         prices.child = parseFloat(p.child) || 0;
       };
       applyOptionPrices();
       document.addEventListener('maipo:optionchange', () => {
         applyOptionPrices();
         calcularTotal();
       });
     } else {
       // Fetch tour prices from server
       const exp = window.EXP_NAME || 'Maipo';
       fetch(`get_prices.php?nombre=${encodeURIComponent(exp)}`)
         .then(res => res.json())
         .then(data => {
           prices.adult = parseFloat(data.precio_adulto) || 0;
           prices.child = parseFloat(data.precio_nino) || 0;
           document.getElementById('dynamic_price').textContent = prices.adult;
           document.getElementById('activity_name').value = data.nombre || 'Maipo';
           calcularTotal(); // initialize total with fetched prices
         })
         .catch(err => {
           console.error('Error fetching prices:', err);
           // if error, prices remain 0 (total will be 0, form submit disabled)
         });
     }
```

- [ ] **Step 3: Lock the option selectors once a coupon is applied**

In `js/tours.js`, directly after the `forEach` that disables `#booking input, #booking select, #booking textarea` (inside the `if (data.valid)` branch of the coupon handler), add:

```js
           // Option selectors (incl. the one above the itinerary) are locked too,
           // so the discounted total can't drift from the selected option
           document.querySelectorAll('[data-booking-lock]').forEach(el => { el.disabled = true; });
```

- [ ] **Step 4: Post to the form's own action**

In `js/tours.js` submit handler, replace:

```js
       form.action = 'submit.php';
```

with:

```js
       form.action = this.getAttribute('action') || 'submit.php'; // Maipo: book_tour.php
```

Run: `grep -n 'action="submit.php"' valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php portillo-inca-lagoon-andes-mountains-vineyard.php discover-santiago-city-tour.php`
Expected: one match per file. Those pages still post to `submit.php`, so their behavior is unchanged.

- [ ] **Step 5: Browser verification on the local server**

Start the server with `php -S 127.0.0.1:8099`, open `http://127.0.0.1:8099/maipo-valley-wine-tour-santiago.php?option=premium`, and check:
1. "Premium Route" is selected in both selectors. The itinerary ends at Viña Undurraga, "What's included" shows the Undurraga tasting, and there are no console errors.
2. Clicking "Classic Route + Lunch" in the itinerary selector also checks it in the booking box, the itinerary switches (TerraMater says lunch included), the URL becomes `?option=classic-lunch` without a reload, and with 2 adults the total is `258`.
3. Switching to Classic with 2 adults + 1 child gives a total of `270`.
4. The calendar still blocks the dates closed in `cupos` for the `Maipo` row: the network tab shows `api/cupos_status.php?nombre=Maipo…`.
5. Apply a valid local coupon: both selectors become disabled and the new total is shown.
6. Load `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`: its total still calculates (it still makes the `get_prices.php` request) and there are no console errors.

Stop the server.

- [ ] **Step 6: Commit**

```bash
git add js/maipo-options.js js/tours.js
git commit -m "feat: client-side Maipo option switching synced with booking total

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10: End-to-end verification and deploy notes

**Files:**
- Create: `docs/superpowers/reports/2026-10-13-maipo-tour-options-verification.md`

- [ ] **Step 1: Full test suite**

Run: `php tests/tools/phpunit.phar`
Expected: all tests pass (61 baseline + the new ones), with 0 failures and 0 errors.

- [ ] **Step 2: Book each option end to end (local dev DB, no payment)**

With `php -S 127.0.0.1:8099` running, book each option once from the page (2 adults, a future date, an email at `@example.test`). Each booking should redirect to `shopping.php?reference_id=STAMP_…`. Then run:

```bash
mysql -h localhost -u stampst1_user -p stampst1_stamptour -e "
SELECT r.reference_id, e.nombre, e.nombre_publico, r.adultos, r.ninos, r.subtotal, r.total_venta, r.airport_pickup, r.is_private, r.id_vendedor
FROM reservas r JOIN experiencias e ON e.id_experiencia = r.id_experiencia
JOIN titulares t ON t.id_titular = r.id_titular
WHERE t.email LIKE '%@example.test' ORDER BY r.id_reserva DESC LIMIT 3;"
```

Expected: rows for `MAIPO_CLASSIC` 220.00, `MAIPO_CLASSIC_LUNCH` 258.00 and `Maipo` 278.00, each with `airport_pickup=0`, `is_private=0` and `id_vendedor` NULL. `shopping.php` shows the same amounts.

- [ ] **Step 3: Tampering check**

```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' -X POST http://127.0.0.1:8099/book_tour.php \
  -d 'activity_name=Maipo&name_booking=Tam&last_name_booking=Per&email_booking=tamper@example.test&phone_booking=1&date_booking=12-01-2026&adults=2&children=0&infants=0&subtotal=1&total_price=1&codigo_vendedor=1'
```

Expected: `302 …shopping.php?reference_id=STAMP_…`, and the matching `reservas` row has `total_venta = 278.00` and `id_vendedor` NULL.

- [ ] **Step 4: Admin flows regression**

Logged into `/login.php` locally, create one booking each in `booking_manual.php`, `admin/private-booking.php` and `admin/preferentials.php` with a custom or TA total. Expected: each `reservas.total_venta` equals the total entered in that form, unchanged from before.

- [ ] **Step 5: Admin report categorization**

Open `admin/check.php` (or `admin/consolidate-day.php`) for the date used in Step 2. Expected: the `MAIPO_CLASSIC_LUNCH` booking is under `WINE TOUR MAIPO VALLEY TASTINGS & LUNCH`, and the `MAIPO_CLASSIC` and `Maipo` bookings are under `WINE TOUR MAIPO VALLEY`.

- [ ] **Step 6: Clean up local test bookings**

```bash
mysql -h localhost -u stampst1_user -p stampst1_stamptour -e "
DELETE r FROM reservas r JOIN titulares t ON t.id_titular = r.id_titular WHERE t.email LIKE '%@example.test';
DELETE FROM titulares WHERE email LIKE '%@example.test';"
```

- [ ] **Step 7: Write the verification report and deploy notes**

Create `docs/superpowers/reports/2026-10-13-maipo-tour-options-verification.md` with the actual outputs of Steps 1–5 (paste them; don't paraphrase), plus this deploy order:

```markdown
## Deploy order (production)

1. Upload: includes/booking.php, includes/tour_price.php, includes/tour_schema.php,
   includes/book_tour_handler.php, includes/tours/, includes/migrations/,
   submit.php, book_tour.php, migrate_2026_10_13_maipo_options.php.
2. Logged in, visit /migrate_2026_10_13_maipo_options.php -> three [ok] lines.
   Delete the file from the server.
3. Upload: maipo-valley-wine-tour-santiago.php, css/tour-options.css,
   js/maipo-options.js, js/tours.js.
4. Smoke test: /maipo-valley-wine-tour-santiago?option=premium shows Premium;
   one real booking reaches the payment page with the right amount.
5. Rich Results Test on the live URL: Product (AggregateOffer) + FAQPage valid.

## Follow-ups (not in this change)
- Move Valparaíso, Andes, Santiago and cruise pages to book_tour.php, then
  restrict submit.php to logged-in admin use (closes tampering site-wide).
- Separate ops category for Premium vs Classic in admin reports.
- Visual redesign "lab" (Tailwind/shadcn-style kit) + new photos in ../photos/.
```

- [ ] **Step 8: Commit**

```bash
git add docs/superpowers/reports/2026-10-13-maipo-tour-options-verification.md
git commit -m "docs: Maipo tour options verification report and deploy order

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
