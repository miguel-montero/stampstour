# Payment test infrastructure and backfilled tests

## Context

This session shipped three pieces of payment-critical logic — the downgrade-protection guard (`getnet_notify.php`, `paypal_webhook.php`), and the Getnet reconciliation feature (`includes/reconcile_getnet.php`) — with no automated test framework in the repo at all. Every verification so far has been a throwaway PHP script: written during planning, run once, deleted. That's real evidence at the moment it runs, but it leaves zero regression protection — a future edit to any of these files has nothing catching a reintroduced bug.

The user wants tests in place, specifically because this code is revenue-critical, before building the next piece (PayPal reprocessing, a separate follow-up spec). This spec covers only the infrastructure and backfilling tests for what's already shipped — the PayPal feature itself is out of scope here.

Two real constraints shaped every decision below:
- **The live webhook files must not be refactored.** `getnet_notify.php` and `paypal_webhook.php` are payment-critical, running against real transactions right now; the user explicitly prefers not to change their structure just to make them more testable.
- **Nothing test-related can end up in the production deploy.** This repo deploys via `git pull` only — no build step — and currently commits its entire `vendor/` directory wholesale. Anything added to the repo's own `composer.json` would ship to the live site.

## Goals

- A local, isolated test database with the real schema (not guessed), safe to wipe and reseed on every test run.
- PHPUnit usable locally, with zero footprint in the deployed site.
- Real regression tests for the downgrade guard's SQL (both `getnet_notify.php` and `paypal_webhook.php`'s guarded statements) and for `reconcile_getnet_pending()` — executing real SQL against a real (test) database, not re-simulating the logic in PHP.
- A safeguard against test/production drift: if a guarded UPDATE's SQL in the live file ever changes without the corresponding test being updated, a test must fail loudly, not silently keep testing stale SQL.
- `getSessionInfo()` (the live Getnet API call) must be mockable in tests without ever making a real network call during a test run.

## Non-goals

- The PayPal reprocessing feature itself — separate, sequenced-after spec.
- Refactoring `getnet_notify.php` or `paypal_webhook.php`'s structure.
- CI/automated test running on every commit — this is a local, developer-run test suite for now.
- Testing anything outside the payment layer (no attempt to backfill tests for the rest of the site).

## Design

### 1. Test database

A dedicated local MySQL database, `stampst1_stamptour_test`, using the same local MySQL server and credentials already confirmed working this session (`localhost`, `stampst1_user`). Schema comes from `tests/schema.sql` (committed) — `CREATE TABLE` statements only, extracted from the user's real local dump (`localhost.sql`) for exactly the tables the payment layer touches: `reservas`, `paypal_webhook_events`, `getnet_webhook_events`, `titulares`, `experiencias`, `hoteles`, `vendedores`. No data, no real customer information, ever committed.

`tests/setup-test-db.sh` (committed): idempotent script that creates the database if missing and (re)imports `tests/schema.sql`. Safe to re-run at any time to reset to a clean structure.

`tests/test_db_config.php` (gitignored, like the real `db_config.php`) holds the test DB's connection details; `tests/test_db_config.php.example` (committed) is the template showing the expected shape, with no real values.

Each test truncates the tables it touches in `setUp()` before inserting its own fixture rows, and again in `tearDown()` — tests never depend on state left by a previous test or a previous run.

### 2. PHPUnit, kept out of the deployed site

`tests/setup-test-env.sh` (committed) downloads a specific pinned version of `phpunit.phar` (PHPUnit's official single-file distribution — no composer, no dependency on the repo's own `composer.json`/`vendor`) into `tests/tools/phpunit.phar` (gitignored). Running tests locally is then `php tests/tools/phpunit.phar -c phpunit.xml`.

`phpunit.xml` (committed, repo root): points at `tests/`, uses `tests/bootstrap.php` as the bootstrap file (requires the test DB config, sets up a shared `$conn` for tests, requires `includes/reconcile_getnet.php` and any other files under test).

### 3. Testing the guard logic without touching the live files

Neither `getnet_notify.php` nor `paypal_webhook.php`'s guarded UPDATE is a callable function — both are inline in top-to-bottom scripts. Each guard gets a test that does two things:

1. **Executes the real SQL.** The exact guarded UPDATE text (copied once, carefully, from the live file) runs against real fixture rows in the test database via the test's own `mysqli` connection, for every transition in the matrix already established when this guard was built (`realizado`+`pendiente`→stays `realizado`, `refund`+`realizado`→stays `refund`, `pendiente`+`realizado`→becomes `realizado`, etc.) — this is genuine SQL execution and assertion on the resulting `estado`, not a PHP-side re-simulation of what the SQL is supposed to do.
2. **Drift guard.** A separate assertion reads `getnet_notify.php` (or `paypal_webhook.php`) directly via `file_get_contents()` and asserts it still contains that exact SQL substring. If a future edit changes the live guard's SQL without updating the test's copy, this assertion fails immediately — the alternative (no drift guard) would let the test keep silently passing against SQL production no longer runs.

This covers `getnet_notify.php`'s single guarded UPDATE, and `paypal_webhook.php`'s two (`PAYMENT.CAPTURE.PENDING`, `PAYMENT.CAPTURE.DENIED`/`DECLINED`).

### 4. Testing `reconcile_getnet_pending()` directly

Unlike the guard logic, this function is already callable and testable as-is — except for its one external dependency, the live Getnet API call. Add one optional parameter so it's injectable:

```php
function reconcile_getnet_pending(mysqli $conn, int $limit = 50, callable $sessionLookup = 'getSessionInfo'): array
```

Internally, the existing `getSessionInfo((int)$processId)` call becomes `$sessionLookup((int)$processId)`. For every real caller (the cron script, the admin page), nothing changes — the default value `'getSessionInfo'` (PHP resolves a string default to the named global function) preserves current behavior exactly. Tests pass a closure returning canned Getnet-shaped responses (`['status' => ['status' => 'APPROVED'], ...]`, `['ok' => false, ...]`, a thrown exception, etc.) — full control over every branch this function has, with zero real network calls during any test run.

Tests then: insert fixture `reservas` rows (varying `estado`, `process_id`, `fecha_reserva`), call `reconcile_getnet_pending($testConn, 50, $fakeSessionLookup)`, and assert on both the returned summary array (`checked`/`corrected`/`failed`/`corrections`) and the actual resulting `estado` values in the test database — covering: a genuine recovery (`pendiente`→`realizado` via a mocked `APPROVED`), a still-pending skip (mocked `PENDING`, no write), a mocked API failure (increments `failed`, no write), and the eligibility window itself (a row outside the 1-day window or without a `process_id` must never be selected at all).

## Verification

1. `tests/setup-test-db.sh` runs cleanly on a fresh local MySQL, producing a database matching `tests/schema.sql` exactly.
2. `tests/setup-test-env.sh` downloads a working `phpunit.phar`.
3. The full suite (`php tests/tools/phpunit.phar -c phpunit.xml`) passes, covering: the transition matrix for both guarded files (real SQL execution + drift guard), and `reconcile_getnet_pending()`'s full behavior matrix via the injected mock.
4. Deliberately break something to confirm the tests actually catch it: temporarily edit `getnet_notify.php`'s guard SQL to remove the `refund` protection, confirm the corresponding test fails; revert. Do the same for `reconcile_getnet_pending()` (e.g. temporarily remove the `realizado`/`refund`-only write restriction), confirm the corresponding test fails; revert. This is the real proof the tests protect something, not just that they pass once.
5. Confirm no new files are committed to `vendor/`, and `composer.json`/`composer.lock` are untouched.

## Risks

- **The drift guard is a substring match, not a parser** — a semantically-identical SQL edit with different whitespace would break the drift guard even though behavior didn't change. Acceptable: a failing drift-guard test is a prompt to re-copy the SQL into the test, not a sign of a real bug — cheap to fix when it happens, and far better than silent staleness.
- **The injected-callable change to `reconcile_getnet_pending()`'s signature**, while backward-compatible via the default value, is still an edit to already-shipped, reviewed code — small and additive, but real. Verification step 4's "break it and confirm the test catches it" applies here specifically to make sure the change didn't accidentally alter real-caller behavior.
- **Local-only test database credentials must never leak toward production** — `tests/test_db_config.php` is gitignored specifically so no machine-specific or accidentally-real credentials ever get committed; only the `.example` template ships.
