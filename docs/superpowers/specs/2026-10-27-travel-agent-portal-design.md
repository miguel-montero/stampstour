# Travel Agent Portal — Design

**Date:** 2026-10-27
**Status:** Approved by site owner in conversation; written up for planning.

## Why

Today every travel-agent (TA) booking goes through the site owner by hand: the agent messages the tour and passenger details, the owner fills in `admin/preferentials.php` using an agent's vendor code (e.g. Joyce, `id_vendedor = 11`), then sends the agent the payment link. The owner is the bottleneck on every TA booking.

The goal is that agents sign up once and from then on make their own bookings at the TA rate, with the owner only approving who gets access.

## Goals

1. **Public sign-up page.** An agent applies with name (required), email (required), phone (optional).
2. **Owner notification.** Each new application emails `miguel@stampstour.com` (owner's choice). Store it as a config value next to the mailer config, not hard-coded in pages.
3. **Admin approval panel.** The owner sees Pending / Active / Inactive agents. Approving generates a random, unique 4-digit code and emails it to the agent automatically. The owner can deactivate an agent (code stops working) and reactivate them.
4. **Public booking page.** The agent enters their code to unlock the form, then enters the customer's first name, last name and WhatsApp number, the tour, date, passengers and pickup hotel. The booking's email is the agent's email on file (not typed). On submit the agent is redirected to `shopping.php` for payment, exactly as `admin/preferentials.php` does today.
5. **Existing agents re-apply.** Owner's decision: Joyce (`vendedores.id_vendedor = 11`, `canal_venta = 'TA'`, no email on file) signs up again through the new page like any agent and gets a new row. No agent is migrated or given a code automatically. Her old row and its past bookings are left untouched.

## Non-goals

- No new tours. Only the original four: `Valparaiso`, `Andes`, `Santiago`, `Maipo` (the `experiencias.nombre` values used by the current page).
- No change to pricing rules (see Pricing).
- No agent login/accounts, dashboards, booking history or commission reports for agents.
- No change to `shopping.php` or the payment flow.
- No Spanish version (English only, like the current page) — owner's call by default, can be added later.
- **Do not modify anything the public tour pages use**: `submit.php`, `includes/booking.php` (only call it), `js/tours.js`, `book_tour.php`, the tour pages themselves. Owner's explicit instruction. Known issue left for later (owner will handle): `submit.php` is public, stores the posted total and honours any `codigo_vendedor`, so prices and vendor attribution can be forged through it. The anti-goals below apply to the new pages only.

## Anti-goals

- Prices must not be settable from the browser. A total posted by the client must never be stored for these bookings.
- 4-digit codes must not be guessable by brute force.
- Must not break existing internal tools: `admin/preferentials.php`, `admin/private-booking.php`, `booking_manual.php` keep working through `submit.php` as today.

## Existing system (relevant facts)

- `vendedores (id_vendedor, nombre_vendedor, canal_venta, telefono, email)` — agents are rows with `canal_venta = 'TA'`.
- `reservas.id_vendedor` attributes a booking to a vendor; `includes/booking.php::booking_insert_reservation()` sets it from `$post['codigo_vendedor']` (looked up as an `id_vendedor` number, or by name).
- `titulares (nombre, apellido, email, telefono)` holds the booking contact. For TA bookings: `nombre`/`apellido` = customer, `telefono` = customer WhatsApp, `email` = agent's email.
- `includes/booking.php::booking_calculate_total()` and `includes/book_tour_handler.php` are the existing server-side pricing/validation pattern (`book_tour.php`). Follow it.
- `includes/Mailer.php` (`build_mailer()`, PHPMailer, config in `includes/mailer_config.php`) is the email path.
- `admin/_auth.php` gates admin pages; `admin/_nav.php` holds the admin menu.
- Schema changes follow the `includes/migrations/` pattern (idempotent PHP migration, run while logged in) and must be mirrored into `tests/schema.sql`. Tests are PHPUnit in `tests/`.

## Design

### Data

Add to `vendedores` (one table for all vendors; agents remain vendor rows so `reservas.id_vendedor` attribution keeps working):

- `ta_code CHAR(4) NULL UNIQUE` — the agent's code. Separate from `id_vendedor`.
- `ta_status ENUM('pending','active','inactive','rejected') NULL` — NULL for non-TA vendors.
- `ta_applied_at DATETIME NULL`, `ta_approved_at DATETIME NULL`.

Sign-up inserts a `vendedores` row with `canal_venta = 'TA'`, `ta_status = 'pending'`, no code. Codes are only assigned on approval.

A small table for failed code attempts (rate limiting), e.g. `ta_code_attempts (ip VARCHAR(45), attempted_at DATETIME)`.

Migration: adds the columns/table only. Existing vendor rows are not changed (their `ta_status` stays NULL). Idempotent.

### Code generation

Random (`random_int`) 4-digit string `0000`–`9999`, retried until unique among all `ta_code` values (including inactive/rejected, so a code is never reused). Codes are never derived from `id_vendedor`.

### Pages

1. **`/agents/apply.php`** (public). Form: name, email, phone (optional). Validates email, rejects duplicate email that is already pending/active (shows a friendly message). On success: insert pending row, email the owner (name, email, phone, link to admin panel), show "Thanks — we'll review your application and email you."
2. **`/admin/travel-agents.php`** (behind `_auth.php`, added to `_nav.php`). Lists Pending (Approve / Reject), Active (code, booking count, Deactivate), Inactive (Reactivate). Approve: assign code, set active, email the code + booking-page link to the agent. Reject: status `rejected`, no email to the applicant. Deactivate/reactivate flip status; code is kept.
3. **`/agents/book.php`** (public). Same fields and look as `admin/preferentials.php`, minus admin nav, with customer name + WhatsApp instead of lead traveler/email. Code entry unlocks the form via a JSON endpoint that only accepts active codes and returns the agent's name and (masked) email. Live total shown in the browser for convenience only.
4. **`/agents/book_submit.php`** (POST handler, modelled on `book_tour.php` + `book_tour_handler.php`): re-validates the code is active, validates fields (customer first/last name, WhatsApp, date not in past, tour in the allowed four, passenger counts), calculates the total server-side, sets `email_booking` to the agent's stored email and `codigo_vendedor` to the agent's `id_vendedor`, calls `booking_insert_reservation()`, redirects to `shopping.php?reference_id=…`.

### Pricing (unchanged)

Per tour from `experiencias`: adult = `TAR` if > 0 else `precio_adulto`; child = `precio_nino`; infant = `precio_infante`. No coupons. Total = sum, computed on the server. Extract this into one function used by both the page (display) and the submit handler.

### Brute-force protection

Code checks (unlock endpoint and submit) count failures per IP: after 10 failures within 15 minutes, further checks from that IP are refused until the window passes. Exact numbers are the builder's call.

### Existing pages

- `admin/preferentials.php` stays as an internal tool, unchanged.
- `api/validate_vendor_ta.php` (used only by `admin/preferentials.php`) is tightened to accept TA vendors whose `ta_status` is NULL (legacy rows such as Joyce's id 11, so the owner's internal page keeps working as today) or `'active'`; pending, rejected and inactive applicants are refused. Builder's call (minor): it still looks up by `id_vendedor`, since the admin page uses that.
- `/preferentials.php` currently redirects to the admin page; leave it.

## Builder's call

- Exact file names/paths above are suggestions; follow repo conventions.
- Email wording and HTML, page copy, styling (reuse the current preferentials page look).
- WhatsApp field format/validation (require something phone-like with a country code hint).
- Rate-limit thresholds and table shape.
- Test coverage, following existing PHPUnit patterns (code generation uniqueness, status gating, server-side total, rate limit).
