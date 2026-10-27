# Travel Agent Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agents apply once, the owner approves them in admin (which emails a 4-digit code), and agents book the four original tours at the TA rate on a public page unlocked by that code.

**Architecture:** Agents are `vendedores` rows (`canal_venta = 'TA'`) with new `ta_code` / `ta_status` columns. Pure logic lives in `includes/travel_agents.php` (applications, codes, statuses, rate limit) and `includes/ta_booking.php` (pricing + submit validation, modelled on `includes/book_tour_handler.php`). Thin pages in `agents/` and `admin/` call those functions. The booking is saved via the existing `booking_insert_reservation()` with a server-computed total.

**Tech Stack:** PHP 8.3 (mysqli), MySQL/MariaDB, PHPMailer via `includes/Mailer.php::build_mailer()`, jQuery + Bootstrap pages, PHPUnit via `php tests/tools/phpunit.phar`.

**Spec:** `docs/superpowers/specs/2026-10-27-travel-agent-portal-design.md`

## Global Constraints

- Baseline: `php tests/tools/phpunit.phar` → 156 tests OK. Must stay green after every task.
- **Do not modify** `submit.php`, `includes/booking.php`, `includes/book_tour_handler.php`, `js/tours.js`, `book_tour.php`, any public tour page, or `admin/preferentials.php`. Call `booking_insert_reservation()`; never edit it.
- Bookable tours (exact `experiencias.nombre`): `Valparaiso`, `Andes`, `Santiago`, `Maipo`.
- TA price: adult = `TAR` if `TAR > 0` else `precio_adulto`; child = `precio_nino`; infant = `precio_infante`. No coupons. Total computed server-side only.
- Codes: 4-digit strings `0000`–`9999` from `random_int`, unique across all `vendedores.ta_code` (never reused), never derived from `id_vendedor`.
- Owner notification address: `miguel@stampstour.com`, read from `includes/ta_config.php`.
- Rate limit: 10 failed code checks per IP within 15 minutes → refuse further checks from that IP until the window passes.
- English copy only. Public pages carry `<meta name="robots" content="noindex">`.
- DB path from a page in `agents/` or `admin/`: `require __DIR__ . '/../../db_config.php';`.

## Review Focus

1. Code entered with spaces/leading zeros (` 0427 `, `427`): trim; only exact 4-digit strings match; `0427` must work (stored as CHAR(4), never cast to int). → test in Task 2.
2. Inactive agent with the page already unlocked submits a booking: submit re-checks status and refuses. → test in Task 3.
3. Re-applying with the same email in a different case (`Joyce@X.com` vs `joyce@x.com`): treated as duplicate while pending/active. → test in Task 2.
4. Posted `total_price`/`subtotal`/`coupon_code`/`email_booking`/`codigo_vendedor` from the browser are ignored; stored total and vendor come from the server. → test in Task 3.
5. Approving a non-pending row (double click, already active) doesn't change its code. → test in Task 2.

---

### Task 1: Schema migration (+ apply to local DB)

**Files:**
- Create: `includes/migrations/travel_agents_2026_10_27.php`
- Create: `migrate_2026_10_27_travel_agents.php` (web runner, copy the shape of `migrate_2026_10_13_maipo_options.php`: `_auth.php`, text/plain, "[ok]/[skip]" lines, "delete this file" note)
- Modify: `tests/schema.sql` (vendedores block + new table), `tests/bootstrap.php` (require the migration + new includes as they are created)
- Test: `tests/TravelAgentsMigrationTest.php`

**Interfaces:**
- Produces: `migrate_travel_agents(mysqli $conn): array` (list of log lines). Columns on `vendedores`: `ta_code CHAR(4) NULL`, `ta_status ENUM('pending','active','inactive','rejected') NULL`, `ta_applied_at DATETIME NULL`, `ta_approved_at DATETIME NULL`, `UNIQUE KEY uniq_ta_code (ta_code)`. Table `ta_code_attempts (id INT AUTO_INCREMENT PK, ip VARCHAR(45) NOT NULL, attempted_at DATETIME NOT NULL, KEY idx_ip_time (ip, attempted_at))`.

- [ ] **Step 1: Write failing test** `TravelAgentsMigrationTest::test_migration_is_idempotent_and_leaves_existing_rows_alone`: insert `('TEST_LEGACY','TA')` vendor; run `migrate_travel_agents($conn)` twice (no exception); assert `SHOW COLUMNS FROM vendedores LIKE 'ta_code'` returns 1 row; `SHOW TABLES LIKE 'ta_code_attempts'` returns 1 row; the legacy row's `ta_status` and `ta_code` are NULL; second run's log lines all start with `[skip]`.
- [ ] **Step 2: Run** `php tests/tools/phpunit.phar --filter TravelAgentsMigrationTest` → FAIL (function undefined).
- [ ] **Step 3: Implement** `migrate_travel_agents()`: check each column via `information_schema.COLUMNS` (or `SHOW COLUMNS`) before `ALTER TABLE ... ADD COLUMN`; `CREATE TABLE IF NOT EXISTS ta_code_attempts`; no data changes. Update `tests/schema.sql` so a fresh test DB already has the columns and table (then the test exercises the `[skip]` path; to exercise `[ok]`, the test may `ALTER TABLE vendedores DROP COLUMN` first — builder's call).
- [ ] **Step 4: Run** the filter, then the full suite → PASS, 157+ tests.
- [ ] **Step 5: Apply to local dev DB** `php -r 'require "../db_config.php"; require "includes/migrations/travel_agents_2026_10_27.php"; foreach (migrate_travel_agents($conn) as $l) echo $l, "\n";'` → `[ok]` lines; run again → all `[skip]`. Also apply to the test DB via `tests/setup-test-db.sh` if that's how schema.sql is loaded (check the script).
- [ ] **Step 6: Commit** `feat(ta): add travel agent columns and code-attempt table migration`

### Task 2: Agent records, codes, statuses, rate limit

**Files:**
- Create: `includes/travel_agents.php`, `includes/ta_config.php` (`return ['owner_email' => 'miguel@stampstour.com'];`)
- Modify: `api/validate_vendor_ta.php` (use `ta_legacy_vendor_lookup`)
- Test: `tests/TravelAgentsTest.php`

**Interfaces (all in `includes/travel_agents.php`):**
- `const TA_RATE_LIMIT_MAX = 10; const TA_RATE_LIMIT_WINDOW_MIN = 15;`
- `ta_apply(mysqli $conn, string $name, string $email, string $phone, DateTimeImmutable $now): array` → `['ok'=>true,'id'=>int]` or `['ok'=>false,'error'=>string]`. Trims; email lowercased and `FILTER_VALIDATE_EMAIL`; name required (≤255), phone optional (≤50, stored NULL when empty). Duplicate if a row with `LOWER(email)` equal and `ta_status IN ('pending','active')` exists → error `'An application with this email already exists. We will contact you soon.'`. Inserts `canal_venta='TA', ta_status='pending', ta_applied_at=$now`.
- `ta_generate_code(mysqli $conn): string` — `str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT)`, retry while it exists in `vendedores.ta_code`; throw `RuntimeException` after 50 tries.
- `ta_get(mysqli $conn, int $id): ?array` — row with keys `id_vendedor, nombre_vendedor, email, telefono, ta_code, ta_status, ta_applied_at, ta_approved_at`.
- `ta_approve(mysqli $conn, int $id, DateTimeImmutable $now): ?array` — only when status is `pending`: set code, `active`, `ta_approved_at`; return the updated row. Otherwise return null and change nothing.
- `ta_reject(mysqli $conn, int $id): bool` — pending → rejected.
- `ta_set_active(mysqli $conn, int $id, bool $active): bool` — active ↔ inactive only; code unchanged.
- `ta_find_active_by_code(mysqli $conn, string $code): ?array` — trims; returns null unless `preg_match('/^\d{4}$/')` and a row has that `ta_code` with `ta_status='active'`. Compare as string.
- `ta_list(mysqli $conn): array` → `['pending'=>[], 'active'=>[], 'inactive'=>[]]`, each row plus `bookings` (COUNT of `reservas` by `id_vendedor`), ordered newest applied first.
- `ta_is_rate_limited(mysqli $conn, string $ip, DateTimeImmutable $now): bool` — failures for IP with `attempted_at > now - 15 min` ≥ 10.
- `ta_record_failure(mysqli $conn, string $ip, DateTimeImmutable $now): void` — insert; also delete rows older than the window.
- `ta_mask_email(string $email): string` — `joyce@agency.com` → `j****@agency.com` (first char + `*` per remaining local char, max 4 stars — builder's call on star count).
- `ta_legacy_vendor_lookup(mysqli $conn, int $id): ?array` — `['id_vendedor','nombre_vendedor']` for `canal_venta='TA' AND (ta_status IS NULL OR ta_status='active')`, else null.

- [ ] **Step 1: Write failing tests** (use `TEST_` name prefix + `@example.test` emails; clean up in setUp/tearDown like `BookTourHandlerTest`):
  - `test_apply_creates_pending_row` — status `pending`, `ta_code` NULL, email lowercased, empty phone → NULL.
  - `test_apply_rejects_invalid_email_and_missing_name`.
  - `test_apply_rejects_duplicate_email_case_insensitive_while_pending_or_active` — and allows re-applying after the earlier row is `rejected`.
  - `test_approve_assigns_unique_4_digit_code` — matches `/^\d{4}$/`, status active, `ta_approved_at` set; approving 30 agents gives 30 distinct codes.
  - `test_approve_twice_keeps_code` — second `ta_approve` returns null, code unchanged.
  - `test_generate_code_skips_taken_codes` — fill `ta_code` for all but one value is impractical; instead insert rows holding codes and assert the result isn't one of them over 200 calls (builder may inject the RNG if they prefer).
  - `test_find_active_by_code` — finds `'0427'` with input `' 0427 '`; null for `'427'`, `'04271'`, `'abcd'`; null after `ta_set_active(..., false)`; found again after reactivation with the same code.
  - `test_reject_only_from_pending`.
  - `test_rate_limit_after_10_failures_in_15_minutes` — 9 failures → false; 10th → true; at `now + 16 min` → false; other IP unaffected.
  - `test_list_groups_by_status_with_booking_counts`.
  - `test_legacy_lookup_allows_null_status_and_active_only` — NULL-status TA row found; pending/rejected/inactive → null; non-TA vendor → null.
  - `test_mask_email`.
- [ ] **Step 2: Run** `--filter TravelAgentsTest` → FAIL.
- [ ] **Step 3: Implement** the functions above (prepared statements throughout). Change `api/validate_vendor_ta.php` to call `ta_legacy_vendor_lookup()`; keep its JSON response shape exactly (`ok`, `id_vendedor`, `nombre_vendedor`).
- [ ] **Step 4: Run** filter + full suite → PASS.
- [ ] **Step 5: Commit** `feat(ta): agent applications, codes, statuses and rate limit`

### Task 3: TA pricing and booking submit handler

**Files:**
- Create: `includes/ta_booking.php`
- Test: `tests/TaBookingTest.php`

**Interfaces:**
- Consumes: `ta_find_active_by_code`, `ta_is_rate_limited`, `ta_record_failure` (Task 2); `booking_insert_reservation(mysqli, array $post, float $subtotal, float $total): string` and `BOOK_TOUR_MAX_TRAVELERS` (existing).
- Produces:
  - `const TA_TOURS = ['Valparaiso', 'Andes', 'Santiago', 'Maipo'];`
  - `ta_tour_prices(mysqli $conn): array` → `['Valparaiso'=>['adult'=>float,'child'=>float,'infant'=>float], ...]` for tours present in `experiencias`, applying the TAR rule.
  - `ta_booking_handle(mysqli $conn, array $post, string $ip, DateTimeImmutable $today): array` → `['ok'=>true,'reference_id'=>string]` or `['ok'=>false,'error'=>string]`.

Handler order: non-string post values → fail; rate-limited IP → fail `'Too many attempts. Please wait 15 minutes and try again.'`; `ta_find_active_by_code($post['ta_code'])` null → `ta_record_failure` + fail `'Your agent code is not valid or is no longer active.'`; tour in `TA_TOURS` and priced; `customer_first_name`, `customer_last_name`, `customer_whatsapp` non-empty (WhatsApp: digits/spaces/`+`/`-`/parentheses, ≥ 8 digits); `date_booking` strict `m-d-Y`, not before today (same check as `book_tour_handle`); adults ≥ 1, children/infants ≥ 0, sum ≤ `BOOK_TOUR_MAX_TRAVELERS`. Then build a fresh array — only `name_booking`, `last_name_booking`, `phone_booking` (= WhatsApp), `email_booking` (= agent email), `date_booking`, `adults`, `children`, `infants`, `activity_name`, `codigo_vendedor` (= `(string)$agent['id_vendedor']`), and the hotel keys `resolve_hotel_selection()` reads (`hotel`, `hotelOption`, `customHotel`) — and call `booking_insert_reservation($conn, $clean, $total, $total)`.

- [ ] **Step 1: Write failing tests** (seed `experiencias` rows for the four tours with known prices, e.g. Valparaiso precio_adulto 79 / TAR 59 / nino 40 / infante 0; Andes TAR NULL precio_adulto 79):
  - `test_prices_use_tar_for_adults_and_fallback_when_missing` — Valparaiso adult 59.0, Andes adult 79.0, child 40.0.
  - `test_successful_booking_stores_server_total_agent_email_and_vendor` — 2 adults + 1 child Valparaiso → `reservas.total_venta = 158.00`, `subtotal = 158.00`, `id_vendedor` = agent id, `titulares.email` = agent email, `titulares.nombre/apellido/telefono` = customer values.
  - `test_browser_supplied_price_vendor_email_and_coupon_are_ignored` — post `total_price=1`, `subtotal=1`, `coupon_code=TEST10`, `email_booking=evil@example.test`, `codigo_vendedor=1` → same stored values as above, `id_cupon` NULL.
  - `test_inactive_agent_cannot_book` — deactivate after unlocking → fail, no `reservas` row.
  - `test_invalid_code_records_failure_and_blocks_after_limit` — 10 bad submits → 11th with a *valid* code also fails with the too-many-attempts message.
  - `test_rejects_tour_outside_the_four` (`MAIPO_CLASSIC`), `test_rejects_past_date`, `test_requires_customer_name_and_whatsapp`, `test_rejects_zero_adults_and_over_15_travelers`.
- [ ] **Step 2: Run** `--filter TaBookingTest` → FAIL.
- [ ] **Step 3: Implement** `includes/ta_booking.php`; add it to `tests/bootstrap.php`.
- [ ] **Step 4: Run** filter + full suite → PASS.
- [ ] **Step 5: Commit** `feat(ta): server-priced travel agent booking handler`

### Task 4: Emails

**Files:**
- Create: `includes/ta_mail.php`
- Test: `tests/TaMailTest.php`

**Interfaces:**
- `ta_owner_notification_email(array $agent, string $adminUrl): array` → `['to'=>owner_email from ta_config, 'subject'=>'New travel agent application: <name>', 'html'=>..., 'text'=>...]` containing name, email, phone (or "not given"), and `$adminUrl`.
- `ta_code_email(array $agent, string $bookUrl): array` → `['to'=>agent email, 'subject'=>"Your Stamp's Tour agent code", 'html', 'text']` containing the code and `$bookUrl`.
- `ta_send_email(array $msg): bool` — `build_mailer()`, `addAddress($msg['to'])`, Subject/Body/AltBody, `send()`; catch `Throwable`, `error_log`, return false. (Do not use `send_booking_email()` — it adds booking BCCs.)
- All user values HTML-escaped in `html`.

- [ ] **Step 1: Write failing tests** for the two builders: recipients, subject, required content present, `<script>` in a name comes out escaped in `html`.
- [ ] **Step 2: Run** `--filter TaMailTest` → FAIL.
- [ ] **Step 3: Implement**; require `ta_mail.php` in bootstrap (it must not require `Mailer.php` at load time if that pulls in PHPMailer config — require `Mailer.php` inside `ta_send_email()`).
- [ ] **Step 4: Run** filter + full suite → PASS.
- [ ] **Step 5: Commit** `feat(ta): application and code emails`

### Task 5: Public pages — apply and book

**Files:**
- Create: `agents/apply.php`, `agents/validate_code.php`, `agents/book.php`, `agents/book_submit.php`

**Interfaces:** consumes Tasks 2–4.

- `agents/apply.php`: GET shows the form (name *, email *, phone optional); POST calls `ta_apply`; on ok → `ta_send_email(ta_owner_notification_email($agent, 'https://' . $_SERVER['HTTP_HOST'] . '/admin/travel-agents.php'))` (mail failure is logged, the agent still sees success), then show the thank-you message from the spec; on error re-show the form with the message and their values. Same look as `admin/preferentials.php` (its CSS links + logo), no admin nav.
- `agents/validate_code.php`: POST `code` → JSON. Rate-limited → `{ok:false, error:'Too many attempts…'}`; valid → `{ok:true, name, email_masked}`; invalid → record failure, `{ok:false, error:'Invalid or inactive agent code.'}`. IP = `$_SERVER['REMOTE_ADDR']`.
- `agents/book.php`: copy the structure and JS of `admin/preferentials.php` (locked fieldset, hotel autocomplete, date mirroring to `date_booking`, live total from `ta_tour_prices()` JSON), with: code input `name="ta_code"` (maxlength 4, inputmode numeric) unlocking via `/agents/validate_code.php`; welcome line `Welcome, <name>. Confirmations go to <email_masked>.`; fields `customer_first_name`, `customer_last_name`, `customer_whatsapp` (placeholder `+56 9 1234 5678`, help text "Include country code"); no email field; form posts to `/agents/book_submit.php`; total label "Total (TA Rate)". The live total is display only.
- `agents/book_submit.php`: same shape as `book_tour.php` — POST only, `ta_booking_handle($conn, $_POST, $_SERVER['REMOTE_ADDR'], new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))`, on ok set `$_SESSION['reference_id']` and redirect to `/shopping.php?reference_id=…`; on error the same "couldn't complete" page with a back link.

- [ ] **Step 1: Syntax check** `for f in agents/*.php; do php -l $f; done` → "No syntax errors".
- [ ] **Step 2: Manual run** `php -S localhost:8000` from the repo root (check how the project serves locally; `db_config.php` lives one level up). Apply with a test email → row appears pending; owner email attempted (check `storage/php_errors.log` if SMTP is unavailable locally). Approve the row via Task 2's `ta_approve` in a `php -r` one-liner (admin page comes in Task 6), unlock `/agents/book.php` with the code, book 2 adults Valparaiso → redirected to `shopping.php` showing 2 × TAR. Bad code ×10 → locked message.
- [ ] **Step 3: Full suite** → PASS.
- [ ] **Step 4: Commit** `feat(ta): public apply and booking pages`

### Task 6: Admin approval panel

**Files:**
- Create: `admin/travel-agents.php`
- Modify: `admin/_nav.php` (add `'travel-agents' => ['label' => 'Travel Agents', 'href' => '/admin/travel-agents.php']` after `preferentials`), `admin.php` card grid near the Preferentials card (line ~489) — a "Travel Agents" card with the same markup.

- Page: `require __DIR__ . '/_auth.php'`, nav `stamp_admin_nav('travel-agents')`. Three tables from `ta_list()`: Pending (name, email, phone, applied date, Approve / Reject buttons), Active (name, email, phone, code, bookings, Deactivate), Inactive (name, email, code, Reactivate). Actions are POST forms (`action`, `id`), handled at the top of the page, then redirect (POST-redirect-GET) with a flash message in the session. Approve → `ta_approve`; if it returns a row → `ta_send_email(ta_code_email($row, 'https://' . $_SERVER['HTTP_HOST'] . '/agents/book.php'))`; flash `Approved <name> — code <code> emailed` or `Approved <name> — code <code>. Email failed, send it manually.` Reject asks a JS `confirm()` first. Also show the public sign-up link (`/agents/apply.php`) at the top so the owner can copy it to agents.
- [ ] **Step 1:** `php -l admin/travel-agents.php admin/_nav.php admin.php` → no errors.
- [ ] **Step 2: Manual run** logged in: the Task 5 test applicant is listed; approve → code shown and email attempted; deactivate → `/agents/book.php` rejects the code; reactivate → same code works again; reject a second test applicant → disappears from lists. Remove the test rows afterwards.
- [ ] **Step 3: Full suite** → PASS.
- [ ] **Step 4: Commit** `feat(ta): admin travel agent approval panel`

### Task 7: Deployment notes

**Files:**
- Modify: the plan's "Deploy" section below if anything changed during build.

- [ ] **Step 1:** Confirm `.superpowers/` is in `.gitignore` (add it if missing) and commit `chore: ignore brainstorm scratch files`.

## Deploy (owner)

1. Upload new/changed files: `includes/travel_agents.php`, `includes/ta_booking.php`, `includes/ta_mail.php`, `includes/ta_config.php`, `includes/migrations/travel_agents_2026_10_27.php`, `migrate_2026_10_27_travel_agents.php`, `agents/*`, `admin/travel-agents.php`, `admin/_nav.php`, `admin.php`, `api/validate_vendor_ta.php`.
2. Logged in, open `/migrate_2026_10_27_travel_agents.php` once; every line `[ok]` or `[skip]`; delete the file from the server.
3. Send agents (Joyce first) the link `/agents/apply.php`.
