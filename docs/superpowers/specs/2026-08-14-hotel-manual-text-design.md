# Hotel preference: free-text fallback instead of catalog inserts

## Context

An earlier fix (this branch, PR #2) addressed two related bugs:

1. `submit.php` never wrote `id_hotel` on reservation creation, so any hotel picked/typed on `admin/preferentials.php` or `booking_manual.php` was silently dropped at booking time.
2. `return.php`'s post-payment `updateHotel` AJAX handler — the only code that ever wrote `reservas.id_hotel` — unconditionally nulled the column whenever the customer picked "my hotel is not on this list," discarding the free-text hotel name they typed.

The first fix made both write paths persist the hotel selection, including free-typed names, by looking up `hoteles` by exact name and **inserting a new row into `hoteles` if no match was found**. The site owner does not want customer/admin free text creating rows in `hoteles` — that table is meant to stay a curated, admin-maintained catalog (used for the pickup-hotel autocomplete and for hotel `direccion`/`comuna` details). This design replaces the "insert into hoteles" fallback with a dedicated free-text column on `reservas`, and updates every place that displays the hotel to fall back to that text when there's no catalog match.

## Goals

- Never insert into `hoteles` from user input again. `hoteles` is only ever read from, never written to, by customer/admin-facing booking flows.
- A hotel name that doesn't match the catalog (typed in the "not listed" box, or typed into the autocomplete field without selecting a suggestion) is still saved — just as free text on the reservation itself, not as a new catalog entry.
- Every place that currently shows the reservation's hotel continues to show something meaningful, whether the hotel came from the catalog or was typed free-text.
- `id_hotel` and the new free-text column are mutually exclusive on any given reservation: exactly one is set, or neither (customer hasn't chosen yet).

## Non-goals

- No admin UI to "promote" a free-text hotel entry into a real `hoteles` catalog row. If that becomes useful later, it's a separate feature.
- No fuzzy/partial matching against `hoteles` (e.g. matching "Aji Hostel" typed with extra whitespace or different casing beyond a simple trim). Matching stays an exact `nombre_hotel` comparison, same as today.
- No changes to the `hoteles` table itself beyond it being read-only from these flows — its columns (`direccion`, `comuna`) are not replicated onto `reservas`.

## Design

### Schema

```sql
ALTER TABLE reservas ADD COLUMN hotel_manual VARCHAR(255) DEFAULT NULL AFTER id_hotel;
```

Already applied by the site owner to production. Also needs applying to the local dev DB (`stampst1_stamptour`) and to `tests/schema.sql` (the PHPUnit test-DB schema) as part of this change.

### Shared resolution logic

Both `submit.php` (booking creation) and `return.php` (post-payment update) currently duplicate the same ~20-line hotel-resolution block. Extract it into one function so it's written once and unit-testable in isolation:

`includes/hotel_resolver.php`:

```php
function resolve_hotel_selection(mysqli $conn, array $post): array
{
    // Returns [id_hotel, hotel_manual] — exactly one is non-null, or both are null.

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

No branch of this function ever writes to `hoteles`.

### Write paths

**`submit.php`** — call `resolve_hotel_selection()` before the `reservas` INSERT (replacing the current inline block that inserts into `hoteles`), and include both `id_hotel` and `hotel_manual` as columns in the INSERT.

**`return.php`**'s `updateHotel` AJAX handler — call `resolve_hotel_selection()` and always run a single update that sets both columns together:

```sql
UPDATE reservas SET id_hotel = ?, hotel_manual = ? WHERE id_reserva = ?
```

Setting both columns on every update (rather than conditionally updating just one) is what guarantees the mutual-exclusivity invariant holds even when a customer changes their mind (e.g. picks a catalog hotel after previously typing a custom one, or vice versa).

### Read / display paths

Two shapes exist across the 7 files that currently join `hoteles`:

**Simple joins** — `search_reservas.php`, `search_by_date.php`, `search_by_codigo.php`, `detalle_reservas.php`. Each has a line like:
```sql
h.nombre_hotel AS hotel
...
LEFT JOIN hoteles h ON r.id_hotel = h.id_hotel
```
Add `r.hotel_manual` to the SELECT and change the aliased column to:
```sql
COALESCE(h.nombre_hotel, r.hotel_manual) AS hotel
```

**Composed report builders** — `admin/check.php` (`buildWebHotelText()`), `admin/consolidate-day.php`, `admin/consolidate-month.php` (equivalent inline logic). These already do:
```php
foreach (['nombre_hotel', 'direccion', 'comuna'] as $key) {
    $value = clean($row[$key] ?? '');
    if ($value !== '' && !in_array($value, $parts, true)) {
        $parts[] = $value;
    }
}
```
Add `r.hotel_manual` to each query's SELECT. In the composing function, if `$parts` is still empty after the catalog-field loop, fall back to the trimmed `hotel_manual` value before falling through to the existing `"SCL Airport pickup"` / empty-string cases:
```php
if (empty($parts)) {
    $manual = clean($row['hotel_manual'] ?? '');
    if ($manual !== '') {
        return $manual;
    }
}
```

## Testing

Follow the existing PHPUnit pattern used for the payment-layer tests (`tests/`, `tests/schema.sql`, `tests/bootstrap.php`):

- Add `hotel_manual` to `tests/schema.sql`.
- New `tests/HotelResolverTest.php` covering `resolve_hotel_selection()` directly against the test DB:
  - numeric `id_hotel` from a `<select>` → returned as-is, `hotel_manual` null
  - `hotelOption=not_listed` + `customHotel` text → `hotel_manual` set, `id_hotel` null, no row added to `hoteles`
  - `hotelOption=decide_later` → both null
  - `hotel` text matching an existing `hoteles.nombre_hotel` exactly → resolves to that `id_hotel`
  - `hotel` text with no catalog match → falls back to `hotel_manual`, no row added to `hoteles`
  - assert `hoteles` row count is unchanged before/after every case

Manually re-verify end-to-end against the local dev DB (same approach as the earlier hotfix): run `submit.php` and `return.php`'s `updateHotel` handler through each of the above cases, confirm `reservas.id_hotel`/`hotel_manual` land correctly, confirm no new `hoteles` rows appear, then check that `detalle_reservas.php` and `admin/check.php` render the free-text hotel correctly. Clean up any test rows created (reservas/titulares) afterward — no `hoteles` rows to clean up this time, since none are ever created.

## Risks

- **Missed display spot**: if a hotel display location exists beyond the 7 files already found via `grep -rn "id_hotel\|nombre_hotel"`, it would keep showing blank for free-text hotels. Mitigated by the same grep being re-run as part of implementation to confirm the file list is still exhaustive.
- **Schema drift between environments**: the column now exists on production (already applied) and will be applied to local dev and the test-DB schema as part of this change — all three need to match before/while this code deploys, or `submit.php`/`return.php` will error with "unknown column" on whichever environment lags.
- **Existing reservations with an already-`INSERT`ed custom `hoteles` row**: the earlier hotfix (before this design) may have already created a small number of speculative `hoteles` rows in production from real customer/admin submissions between deploy and this fix. Those rows and their `id_hotel` references on `reservas` are harmless (a real hotel row with a real reference), but they won't be automatically cleaned up or merged back into `hotel_manual` — out of scope here since it's a one-time historical cleanup, not part of ongoing behavior. Worth a quick manual look at `hoteles` for very recently created rows before considering this done.
