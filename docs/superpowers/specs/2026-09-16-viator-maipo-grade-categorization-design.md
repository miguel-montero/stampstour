# Shared Viator Maipo grade categorization for consolidate/check/closing reports

## Context

The admin reporting pages that consolidate Viator bookings — `admin/consolidate-day.php`, `admin/consolidate-month.php`, `admin/check.php`, and `admin/closing.php` — each independently re-implement the logic that maps a Viator booking row (`Product Code` + `Tour Grade Code`) to one of this site's tour categories. All four copies have drifted out of sync, and real booking data (two live Viator export CSVs the user supplied, in `nuevo producto/bookings-report-*.csv`) shows they're now producing wrong categories for the Maipo Valley product family.

**The real data:** product `20268P25` ("Maipo Valley Wine Tour with 4 vineyards from Santiago" — the original/plain listing) has started appearing with `Tour Grade Code` `TG4~07:30`, in addition to its usual `TG1~07:30`. Product `20268P15` ("Maipo Valley Wine & Culinary Trail 3 Wineries Lunch Included" — a newer, separate Viator listing) appears with `TG1~07:30`.

**Confirmed with the user:**
- `20268P25`: `TG1` = plain Maipo Valley tour (no lunch). `TG2` = private/PVT plain Maipo Valley (existing rule, unchanged). `TG3` **or** `TG4` = the lunch-included variant.
- `20268P15`: `TG1` = lunch included. `TG2` = no lunch. No private/PVT variant exists for this product.

**Current bugs, confirmed by reading each file's actual logic (not assumed):**

| File | `20268P25` handling | `20268P15` handling |
|---|---|---|
| `admin/consolidate-day.php` (`is_viator_maipo_tastings_lunch_grade()`, line ~397) | Checks only `TG3` for the lunch upgrade — `TG4` falls through, miscategorized as plain | `categorize_from_viator_product()` (line ~365) maps it unconditionally to `'Maipo Valley Tastings & Lunch'` regardless of grade — a `TG2` (no-lunch) booking would still show as lunch |
| `admin/consolidate-month.php` (same function names, independently duplicated, line ~218) | Same `TG3`-only gap | Same unconditional-lunch gap |
| `admin/check.php` (`canonicalTourFromViator()`, line ~1485) | No grade-based lunch upgrade at all — `20268P25` always resolves to `'WINE TOUR MAIPO VALLEY'` (plain), regardless of grade | No product-code case for `20268P15` at all. Falls through to the name-matching fallback `canonicalTour()` (line ~1428), whose "tastings & lunch" phrase list doesn't match this product's actual name, so it falls to the generic `str_contains($p, 'maipo')` branch → **every `20268P15` booking is currently shown as the plain tour in this file**, even the lunch-included ones |
| `admin/closing.php` (`canonicalTourFromViator()`, line ~2663) | Same gap as `check.php` — always plain, no grade check | No product-code case either. Falls through to `canonicalTour()` (line ~2643), whose generic `maipo` + `lunch`/`tastings`/etc. substring check happens to match this product's real name ("...Lunch Included") — so it's *accidentally* right today, but is completely grade-blind, so a real `TG2` (no-lunch) `20268P15` booking would be mislabeled as lunch |

The root cause of the drift: four independent copies of the same classification logic, no shared source of truth. This spec fixes the immediate miscategorization bug and removes the drift risk by extracting the Maipo-specific classification into one shared file all four pages require — while leaving each file's own display-label vocabulary untouched (they use genuinely different strings for the same categories, e.g. `"Maipo Valley Tastings & Lunch"` vs. `"WINE TOUR MAIPO VALLEY TASTINGS & LUNCH"`, and unifying that is out of scope — nobody asked for it and it's a much bigger blast radius).

## Goals

- Fix the confirmed miscategorization: `20268P25`/`TG4` and `20268P15` (grade-aware: `TG1` lunch, `TG2` plain) must resolve correctly in all four files.
- Do it once, in a shared location all four files consume, so a future Viator grade change doesn't require hunting down four separate copies again.
- Zero behavior change for every other tour/product these files already categorize correctly (Valparaíso, Portillo/Inca Lagoon, City Tour, GYG bookings, low-cost variants, etc.).

## Non-goals

- **GetYourGuide (GYG) bookings** — already use separate product IDs for the lunch/no-lunch Maipo variants (`288540` = plain, `1333337` = lunch), not a grade-based split within one product. Confirmed via `categorize_from_gyg_product_id()`/`canonicalTourFromGyg()` in all four files — no bug there, not touched.
- **Unifying the four files' display-label vocabularies** — `"Maipo Valley Tastings & Lunch"` (consolidate-day/month) vs. `"WINE TOUR MAIPO VALLEY TASTINGS & LUNCH"` (check/closing) stay exactly as they are. The shared file returns a neutral classification key; each consumer maps that key to its own existing label.
- **Any other product code or tour** — Valparaíso (`20268P5`/`P32`), Portillo (`20268P12`), City Tour (`20268P8`), the cruise-transfer products, etc. are untouched; their existing per-file logic stays as-is.
- **The CSV import/upload mechanism itself** — this spec only changes classification logic, not how files are uploaded or parsed.

## Design

### 1. New shared file: `admin/_viator_categorization.php`

Matches this codebase's existing naming convention for shared admin includes (`admin/_auth.php`, `admin/_nav.php`), required the same way: `require __DIR__ . '/_viator_categorization.php';`.

One function, covering only the Maipo product family (the only place a grade-based split exists today):

```php
/**
 * Classifies a Viator Maipo Valley booking (product 20268P25 or 20268P15) by
 * product code + tour grade. Returns null for any other product code — callers
 * keep their own existing logic for every other tour.
 *
 * Confirmed grade rules (2026-09-16):
 *   20268P25 (plain Maipo listing): TG1 = plain, TG2 = private/PVT plain,
 *                                    TG3 or TG4 = lunch included
 *   20268P15 (wine trail listing):  TG1 = lunch included, TG2 = plain, no PVT variant
 *
 * @return string|null One of: 'MAIPO_PLAIN', 'MAIPO_PLAIN_PRIVATE', 'MAIPO_LUNCH', or null
 */
function classify_viator_maipo_grade(?string $productCode, ?string $tourGrade, ?string $tourGradeTitle = ''): ?string {
    $code = strtoupper(trim((string)($productCode ?? '')));
    $grade = strtoupper(trim((string)($tourGrade ?? '')));
    $title = mb_strtolower(trim((string)($tourGradeTitle ?? '')));

    if ($code === '20268P25') {
        if (preg_match('/^TG3(?:\b|~)/i', $grade) === 1 || preg_match('/^TG4(?:\b|~)/i', $grade) === 1) {
            return 'MAIPO_LUNCH';
        }
        // Safety fallback in case Viator changes/omits the grade code but keeps the title.
        if (str_contains($title, 'classic maipo valley')) {
            return 'MAIPO_LUNCH';
        }
        if (preg_match('/^TG2(?:\b|~)/i', $grade) === 1) {
            return 'MAIPO_PLAIN_PRIVATE';
        }
        return 'MAIPO_PLAIN';
    }

    if ($code === '20268P15') {
        if (preg_match('/^TG2(?:\b|~)/i', $grade) === 1) {
            return 'MAIPO_PLAIN';
        }
        // TG1 and any other/unrecognized grade on this listing defaults to lunch,
        // since this product's whole reason for existing is the lunch variant.
        return 'MAIPO_LUNCH';
    }

    return null;
}
```

Design notes:
- `20268P25`'s existing `TG3` handling and title-text safety fallback are preserved exactly as they exist today (in `consolidate-day.php`/`consolidate-month.php` only — `check.php`/`closing.php` never had this fallback and gain it here for the first time, closing a real gap).
- `20268P15` defaults to `MAIPO_LUNCH` for anything that isn't explicitly `TG2` — matching the confirmed rule that this listing's whole purpose is the lunch variant, and being defensive against a future unrecognized grade code (fails toward the product's primary purpose rather than toward silently downgrading a paying customer's lunch inclusion).
- No private/PVT branch for `20268P15`, per the user's confirmation.
- Returns `null` for every other product code — callers must keep using their own existing logic unchanged for anything this function doesn't recognize.

### 2. How each of the four files consumes it

Each file keeps its own `categorize_from_viator_product()` / `canonicalTourFromViator()` and `is_private_viator_product_grade()` / `isPrivateViatorProductGrade()` functions exactly as they are for every other product — only the Maipo-specific branches change, by calling `classify_viator_maipo_grade()` and mapping its neutral key to that file's own existing label.

**`admin/consolidate-day.php` and `admin/consolidate-month.php`** (identical fix in both):

Add the require near the top, alongside the existing `_auth.php` require:
```php
require __DIR__ . '/_viator_categorization.php';
```

Replace the body of `is_viator_maipo_tastings_lunch_grade()` (or remove it entirely and inline the check at the call site — implementer's choice, whichever keeps the diff smallest) so the call site becomes:

```php
$catHint = categorize_from_viator_product($prodCode, $experience, $isLowCost);
$maipoGrade = classify_viator_maipo_grade($prodCode, $tourGrade, $tourGradeTitle);
if ($maipoGrade === 'MAIPO_LUNCH') {
    $catHint = 'Maipo Valley Tastings & Lunch';
} elseif ($maipoGrade === 'MAIPO_PLAIN') {
    $catHint = 'Maipo Valley';
}
// MAIPO_PLAIN_PRIVATE and null: leave $catHint as categorize_from_viator_product() already set it
```

And the `is_private_viator_product_grade()` call site becomes (private flag now also correctly reflects `20268P15`, which per the confirmed rule is never private — `classify_viator_maipo_grade()` never returns `MAIPO_PLAIN_PRIVATE` for `20268P15`, so this is a strict correctness improvement, not a behavior change for any currently-working case):
```php
$maipoGrade = classify_viator_maipo_grade($prodCode, $tourGrade, $tourGradeTitle);
$isPrivate = $maipoGrade === 'MAIPO_PLAIN_PRIVATE'
    ? true
    : ($maipoGrade !== null ? false : is_private_viator_product_grade($prodCode, $tourGrade));
```

(The existing `is_private_viator_product_grade()` function stays in each file, untouched, still handling `20268P5`/`P8`/`P12`'s private-grade checks — only its `20268P25` branch becomes dead code reachable only as the `$maipoGrade === null` fallback, which never happens for `20268P25` since the shared function always returns a non-null value for it. Leave the old `20268P25` branch in place rather than deleting it — harmless, and deleting it is unnecessary risk for this fix.)

**`admin/check.php` and `admin/closing.php`** (identical fix in both):

Add the require near the top, alongside the existing `_auth.php` (or equivalent) require.

In `canonicalTourFromViator()`, replace the `20268P25` branch and add a new `20268P15` branch:

```php
function canonicalTourFromViator(string $product, string $productCode, string $tourGrade = ''): string
{
    $code = strtoupper(clean($productCode));
    $tourGradeTitle = ''; // these two files don't currently pass grade title into this function — see note below

    $maipoGrade = classify_viator_maipo_grade($productCode, $tourGrade, $tourGradeTitle);
    if ($maipoGrade === 'MAIPO_LUNCH') {
        $tour = 'WINE TOUR MAIPO VALLEY TASTINGS & LUNCH';
    } elseif ($maipoGrade === 'MAIPO_PLAIN' || $maipoGrade === 'MAIPO_PLAIN_PRIVATE') {
        $tour = 'WINE TOUR MAIPO VALLEY';
    } elseif ($code === '20268P5' || $code === '20268P32') {
        $tour = 'VALPARAISO & VIÑA DEL MAR';
    } elseif ($code === '20268P12') {
        $tour = 'PORTILLO INCA LAGOON';
    } elseif ($code === '20268P8') {
        $tour = 'CITY TOUR';
    } else {
        $tour = canonicalTour($product);
    }

    $isPrivate = $maipoGrade === 'MAIPO_PLAIN_PRIVATE' ? true : (($maipoGrade !== null) ? false : isPrivateViatorProductGrade($productCode, $tourGrade));

    return applyPrivateTourLabel($tour, $isPrivate);
}
```

Note: `check.php`'s and `closing.php`'s `canonicalTourFromViator()` signature doesn't currently receive `Tour Grade Title` at all (only `consolidate-day.php`/`consolidate-month.php` thread that value through). The `TG3`/`TG4` regex check alone is sufficient for the confirmed rule — the title-text fallback is a defensive extra that these two files can live without for now, since they're not currently passing that data through their call chain and threading a new parameter through every call site of `canonicalTourFromViator()` (used for more than just Viator rows in these files) is unnecessary scope for this fix. Passing an empty string for `tourGradeTitle` is correct and intentional here, not an oversight.

### 3. Testing

Real data, not synthetic — this is the whole point of the fix:

- Write a small standalone PHP test script that `require`s the new `admin/_viator_categorization.php` and runs `classify_viator_maipo_grade()` directly against every Maipo-related row extracted from both CSVs in `nuevo producto/bookings-report-*.csv` (7 Maipo rows total, confirmed during this investigation: five `20268P25`/`TG1` rows expected `MAIPO_PLAIN`, one `20268P25`/`TG4` row expected `MAIPO_LUNCH`, one `20268P15`/`TG1` row expected `MAIPO_LUNCH`). Assert each row's actual output matches the expected key.
- Confirm each of the four consumer files still parses (`php -l`) after the edit.
- Since these are authenticated admin pages behind `_auth.php`, full end-to-end UI verification (actually uploading a CSV through the browser) requires admin credentials — do this manually if practical; otherwise the direct-function test above plus a `php -l` syntax check is the achievable automated verification for this fix.
- Regression check: confirm a non-Maipo row (e.g. a `20268P5` Valparaíso row from the CSVs) still classifies the same before/after in all four files — proves the Maipo-specific change didn't leak into other product handling.

## Risks

- **`check.php`/`closing.php` not threading `Tour Grade Title` through `canonicalTourFromViator()`** means those two files lose the title-text safety fallback that `consolidate-day.php`/`consolidate-month.php` have. If Viator ever changes `20268P25`'s grade code again without the title still saying "Classic Maipo Valley" or similar, only two of the four files would have a defensive fallback. Accepted for now per Non-goals — threading a new parameter through every call site of a shared, multi-purpose function is a bigger change than this fix's actual scope.
- **The old per-file `20268P25` branches in `is_private_viator_product_grade()`/`isPrivateViatorProductGrade()` become dead code** (never reached, since the shared classifier always returns non-null for `20268P25`). Left in place deliberately rather than deleted, to keep this diff minimal and reversible — cleanup can happen later if desired.
- **Low risk of regression to other products** — the shared function returns `null` for anything that isn't `20268P25`/`20268P15`, and every call site explicitly falls back to each file's pre-existing logic when the result is `null`, so no other product's classification path changes.
