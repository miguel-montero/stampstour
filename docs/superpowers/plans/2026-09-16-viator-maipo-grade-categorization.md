# Shared Viator Maipo Grade Categorization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix miscategorized Viator Maipo Valley bookings (product `20268P25` grade `TG4`, and product `20268P15` grade-aware `TG1`/`TG2`) across all four admin reporting pages that currently re-implement this logic independently and have drifted out of sync, by extracting the classification into one shared file.

**Architecture:** New shared file `admin/_viator_categorization.php` with one pure function, `classify_viator_maipo_grade()`, that all four consumer pages `require` and call. Each page maps the function's neutral return key to its own existing display-label vocabulary — no consumer's other product-handling logic changes.

**Tech Stack:** Plain PHP, PHPUnit (already set up in this repo — `tests/`, `phpunit.xml`, runnable via `php tests/tools/phpunit.phar`).

**Spec:** `docs/superpowers/specs/2026-09-16-viator-maipo-grade-categorization-design.md`

## Global Constraints

- New function signature: `function classify_viator_maipo_grade(?string $productCode, ?string $tourGrade, ?string $tourGradeTitle = ''): ?string`
- Return values: exactly one of the strings `'MAIPO_PLAIN'`, `'MAIPO_PLAIN_PRIVATE'`, `'MAIPO_LUNCH'`, or `null` (for any product code other than `20268P25`/`20268P15`).
- Confirmed grade rule (do not deviate): `20268P25` — `TG3` or `TG4` → `MAIPO_LUNCH`; `TG2` → `MAIPO_PLAIN_PRIVATE`; everything else → `MAIPO_PLAIN`. `20268P15` — `TG2` → `MAIPO_PLAIN`; everything else (including `TG1`) → `MAIPO_LUNCH`.
- No other product code's classification logic changes in any of the 4 files — only the Maipo (`20268P25`/`20268P15`) branches.
- GetYourGuide (GYG) booking handling is untouched in all 4 files — out of scope per the spec.
- New shared file must be named and located exactly `admin/_viator_categorization.php`, matching this repo's existing `admin/_auth.php`/`admin/_nav.php` naming convention, required via `require __DIR__ . '/_viator_categorization.php';`.

---

### Task 1: Create the shared classifier with a PHPUnit test (TDD)

**Files:**
- Create: `admin/_viator_categorization.php`
- Create: `tests/ViatorCategorizationTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: `classify_viator_maipo_grade(?string $productCode, ?string $tourGrade, ?string $tourGradeTitle = ''): ?string` — the function Tasks 2 and 3 will call.

- [ ] **Step 1: Add the new file to the test bootstrap**

In `tests/bootstrap.php`, find this line near the bottom:

```php
require_once __DIR__ . '/../includes/hotel_resolver.php';
```

Add a new line directly after it:

```php
require_once __DIR__ . '/../admin/_viator_categorization.php';
```

- [ ] **Step 2: Write the failing test**

Create `tests/ViatorCategorizationTest.php`. These are the exact real booking rows this test is built from — extracted from two live Viator export CSVs the user supplied (`nuevo producto/bookings-report-20268-20260916120858.csv` and `nuevo producto/bookings-report-20268-20260916120909.csv`, outside this repo, not needed to run the test — the values are hardcoded below):

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ViatorCategorizationTest extends TestCase
{
    /**
     * Real rows from two live Viator booking export CSVs (2026-09-16),
     * covering every Maipo product/grade combination seen in that data.
     */
    public static function maipoRowsProvider(): array
    {
        return [
            'P25 TG1 (French title) -> plain'   => ['20268P25', 'TG1~07:30', 'Journée complète Maipo : 4 vignobles 07:30', 'MAIPO_PLAIN'],
            'P25 TG1 (English title) -> plain'  => ['20268P25', 'TG1~07:30', 'Full-Day Maipo: 4 Wineries 07:30', 'MAIPO_PLAIN'],
            'P25 TG1 (Portuguese title) -> plain'=> ['20268P25', 'TG1~07:30', 'Maipo de dia inteiro: 4 vinícolas 07:30', 'MAIPO_PLAIN'],
            'P25 TG4 -> lunch (the confirmed bug)' => ['20268P25', 'TG4~07:30', 'Lunch included 07:30', 'MAIPO_LUNCH'],
            'P15 TG1 -> lunch'                  => ['20268P15', 'TG1~07:30', 'Full wine trail  07:30', 'MAIPO_LUNCH'],
        ];
    }

    /** @dataProvider maipoRowsProvider */
    public function testRealMaipoBookingRows(string $productCode, string $tourGrade, string $tourGradeTitle, string $expected): void
    {
        $this->assertSame($expected, classify_viator_maipo_grade($productCode, $tourGrade, $tourGradeTitle));
    }

    public function testP25Tg2IsPrivatePlain(): void
    {
        // TG2 on P25 means private/PVT plain Maipo - existing rule, not present
        // in the sample data but must not regress.
        $this->assertSame('MAIPO_PLAIN_PRIVATE', classify_viator_maipo_grade('20268P25', 'TG2~07:30', ''));
    }

    public function testP25Tg3StillMapsToLunch(): void
    {
        // TG3 was the original lunch-grade code before TG4 appeared in real
        // data - must keep working, not just TG4.
        $this->assertSame('MAIPO_LUNCH', classify_viator_maipo_grade('20268P25', 'TG3~07:30', ''));
    }

    public function testP25TitleFallbackWhenGradeCodeMissing(): void
    {
        // Safety fallback: if Viator ever omits/changes the grade code but
        // keeps a recognizable title, still classify as lunch.
        $this->assertSame('MAIPO_LUNCH', classify_viator_maipo_grade('20268P25', 'DEFAULT~07:30', 'Classic Maipo Valley Tour'));
    }

    public function testP15Tg2IsPlainNotLunch(): void
    {
        // Confirmed rule: P15/TG2 = no lunch, despite P15 being the "lunch
        // trail" product overall. Not present in the sample data - grade
        // determines this, not the product code alone.
        $this->assertSame('MAIPO_PLAIN', classify_viator_maipo_grade('20268P15', 'TG2~07:30', ''));
    }

    public function testP15HasNoPrivateVariant(): void
    {
        // Confirmed: P15 never returns MAIPO_PLAIN_PRIVATE, even for a TG2
        // (which for P25 would mean private) - P15's TG2 means plain, not private.
        $this->assertNotSame('MAIPO_PLAIN_PRIVATE', classify_viator_maipo_grade('20268P15', 'TG2~07:30', ''));
    }

    public function testOtherProductCodesReturnNull(): void
    {
        // Every other product code (Valparaíso, Portillo, City Tour, etc.)
        // must return null so callers keep their own existing logic.
        $this->assertNull(classify_viator_maipo_grade('20268P5', 'TG1~07:30', ''));
        $this->assertNull(classify_viator_maipo_grade('20268P12', 'TG1~07:30', ''));
        $this->assertNull(classify_viator_maipo_grade('20268P8', 'TG1~07:30', ''));
        $this->assertNull(classify_viator_maipo_grade('', '', ''));
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php tests/tools/phpunit.phar tests/ViatorCategorizationTest.php
```

Expected: FAIL — `Error: Call to undefined function classify_viator_maipo_grade()` (the file it would live in doesn't exist yet).

- [ ] **Step 4: Create `admin/_viator_categorization.php` with the minimal implementation**

```php
<?php
/**
 * Shared Viator Maipo Valley product/grade classification.
 *
 * Required by admin/consolidate-day.php, admin/consolidate-month.php,
 * admin/check.php, and admin/closing.php - previously each of these four
 * files independently re-implemented this same logic and drifted out of
 * sync, causing real booking miscategorization (see
 * docs/superpowers/specs/2026-09-16-viator-maipo-grade-categorization-design.md
 * for the full investigation). Any future Viator grade change for the
 * Maipo product family should be made HERE ONLY, not in any consumer file.
 */

/**
 * Classifies a Viator Maipo Valley booking (product 20268P25 or 20268P15) by
 * product code + tour grade. Returns null for any other product code -
 * callers keep their own existing logic for every other tour.
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

- [ ] **Step 5: Run the test to verify it passes**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php tests/tools/phpunit.phar tests/ViatorCategorizationTest.php
```

Expected: all tests pass (5 rows from the data provider + 6 individual test methods = 11 assertions total across the test class, 0 failures).

- [ ] **Step 6: Run the full existing test suite to confirm no regression**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php tests/tools/phpunit.phar
```

Expected: all existing tests (`GetnetGuardTest`, `GetnetReconciliationTest`, `HotelResolverTest`, `PaypalGuardTest`, `PaypalReprocessTest`) plus the new `ViatorCategorizationTest` all pass. The new `require_once` in `tests/bootstrap.php` only defines one new function — it must not conflict with any existing function name (confirm no "cannot redeclare function" error).

- [ ] **Step 7: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add admin/_viator_categorization.php tests/ViatorCategorizationTest.php tests/bootstrap.php
git commit -m "$(cat <<'EOF'
feat: add shared Viator Maipo grade categorization function

Four admin pages (consolidate-day, consolidate-month, check, closing)
each independently re-implement Viator product/grade categorization
for the Maipo Valley product family and have drifted out of sync -
confirmed via real booking data that 20268P25/TG4 and 20268P15 (grade-
aware: TG1 lunch, TG2 plain) are miscategorized in some or all of the
four files today. This adds the single shared classifier the next two
tasks wire into each consumer file.
EOF
)"
```

---

### Task 2: Wire the classifier into `consolidate-day.php` and `consolidate-month.php`

**Files:**
- Modify: `admin/consolidate-day.php`
- Modify: `admin/consolidate-month.php`

**Interfaces:**
- Consumes: `classify_viator_maipo_grade()` from Task 1.
- Produces: nothing new for later tasks — this task's output is consumed only by the reports themselves.

Both files have near-identical existing code for this (independently duplicated), so apply the same edit to both.

- [ ] **Step 1: In `admin/consolidate-day.php`, add the require**

Find:
```php
require __DIR__ . '/_auth.php';
```

Replace with:
```php
require __DIR__ . '/_auth.php';
require __DIR__ . '/_viator_categorization.php';
```

- [ ] **Step 2: In the same file, find the Viator CSV parsing call site (inside `parse_viator_csv()`)**

Find this exact block:
```php
        // Viator-specific category hint from product code
        $isLowCost = false;
        $catHint   = categorize_from_viator_product($prodCode, $experience, $isLowCost);
        if (is_viator_maipo_tastings_lunch_grade($prodCode, $tourGrade, $tourGradeTitle)) {
            $catHint = 'Maipo Valley Tastings & Lunch';
        }
        $isPrivate = is_private_viator_product_grade($prodCode, $tourGrade);
```

Replace with:
```php
        // Viator-specific category hint from product code
        $isLowCost = false;
        $catHint   = categorize_from_viator_product($prodCode, $experience, $isLowCost);
        $maipoGrade = classify_viator_maipo_grade($prodCode, $tourGrade, $tourGradeTitle);
        if ($maipoGrade === 'MAIPO_LUNCH') {
            $catHint = 'Maipo Valley Tastings & Lunch';
        } elseif ($maipoGrade === 'MAIPO_PLAIN') {
            $catHint = 'Maipo Valley';
        }
        $isPrivate = $maipoGrade === 'MAIPO_PLAIN_PRIVATE'
            ? true
            : ($maipoGrade !== null ? false : is_private_viator_product_grade($prodCode, $tourGrade));
```

- [ ] **Step 3: Verify PHP syntax**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l admin/consolidate-day.php
```

Expected: `No syntax errors detected in admin/consolidate-day.php`.

- [ ] **Step 4: Repeat Steps 1-2 identically in `admin/consolidate-month.php`**

Find:
```php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
```

Replace with:
```php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/_viator_categorization.php';
```

Then find this exact block (inside its own Viator CSV parser):
```php
        $isLowCost = false;
        $catHint = categorize_from_viator_product($prodCode, $experience, $isLowCost);
        if (is_viator_maipo_tastings_lunch_grade($prodCode, $tourGrade, $tourGradeTitle)) {
            $catHint = 'Maipo Valley Tastings & Lunch';
        }
```

Replace with:
```php
        $isLowCost = false;
        $catHint = categorize_from_viator_product($prodCode, $experience, $isLowCost);
        $maipoGrade = classify_viator_maipo_grade($prodCode, $tourGrade, $tourGradeTitle);
        if ($maipoGrade === 'MAIPO_LUNCH') {
            $catHint = 'Maipo Valley Tastings & Lunch';
        } elseif ($maipoGrade === 'MAIPO_PLAIN') {
            $catHint = 'Maipo Valley';
        }
```

Then find the `isPrivate` key in the row array further down (in this file it's inline in the `ensure_cols([...])` array, not a separate `$isPrivate` variable):
```php
            'isPrivate' => is_private_viator_product_grade($prodCode, $tourGrade),
```

Replace with:
```php
            'isPrivate' => $maipoGrade === 'MAIPO_PLAIN_PRIVATE'
                ? true
                : ($maipoGrade !== null ? false : is_private_viator_product_grade($prodCode, $tourGrade)),
```

- [ ] **Step 5: Verify PHP syntax**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l admin/consolidate-month.php
```

Expected: `No syntax errors detected in admin/consolidate-month.php`.

- [ ] **Step 6: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add admin/consolidate-day.php admin/consolidate-month.php
git commit -m "$(cat <<'EOF'
fix: use shared Maipo grade classifier in consolidate-day/month

Both files' 20268P25 lunch-grade check only recognized TG3, missing
the TG4 grade now appearing in real Viator booking exports. Both also
categorized every 20268P15 booking as the lunch variant regardless of
grade, when TG2 on that product means no lunch. Now delegate to
classify_viator_maipo_grade() (admin/_viator_categorization.php),
which handles both cases correctly.
EOF
)"
```

---

### Task 3: Wire the classifier into `check.php` and `closing.php`

**Files:**
- Modify: `admin/check.php`
- Modify: `admin/closing.php`

**Interfaces:**
- Consumes: `classify_viator_maipo_grade()` from Task 1.
- Produces: nothing new for later tasks.

Both files' `canonicalTourFromViator()` functions are near-identical (independently duplicated) and get the same fix.

- [ ] **Step 1: In `admin/check.php`, add the require**

Find:
```php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
```

Replace with:
```php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/_viator_categorization.php';
```

- [ ] **Step 2: In the same file, find `canonicalTourFromViator()`**

Find this exact function:
```php
function canonicalTourFromViator(string $product, string $productCode, string $tourGrade = ''): string
{
    $code = strtoupper(clean($productCode));

    if ($code === '20268P5' || $code === '20268P32') {
        $tour = 'VALPARAISO & VIÑA DEL MAR';
    } elseif ($code === '20268P25') {
        $tour = 'WINE TOUR MAIPO VALLEY';
    } elseif ($code === '20268P12') {
        $tour = 'PORTILLO INCA LAGOON';
    } elseif ($code === '20268P8') {
        $tour = 'CITY TOUR';
    } else {
        $tour = canonicalTour($product);
    }

    $isPrivate = isPrivateViatorProductGrade($productCode, $tourGrade);

    return applyPrivateTourLabel($tour, $isPrivate);
}
```

Replace with:
```php
function canonicalTourFromViator(string $product, string $productCode, string $tourGrade = ''): string
{
    $code = strtoupper(clean($productCode));
    $maipoGrade = classify_viator_maipo_grade($productCode, $tourGrade, '');

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

    $isPrivate = $maipoGrade === 'MAIPO_PLAIN_PRIVATE'
        ? true
        : ($maipoGrade !== null ? false : isPrivateViatorProductGrade($productCode, $tourGrade));

    return applyPrivateTourLabel($tour, $isPrivate);
}
```

(Note: `$maipoGrade` is computed but the `20268P25` explicit branch below it becomes dead code for that one product code — left in place deliberately per the spec's Risks section, since `classify_viator_maipo_grade()` never returns `null` for `20268P25`, so that branch is now unreachable but harmless. Do not delete it.)

- [ ] **Step 3: Verify PHP syntax**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l admin/check.php
```

Expected: `No syntax errors detected in admin/check.php`.

- [ ] **Step 4: Repeat Steps 1-2 identically in `admin/closing.php`**

Find:
```php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
```

Replace with:
```php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/_viator_categorization.php';
```

Then find this exact function:
```php
function canonicalTourFromViator(string $product, string $productCode, string $tourGrade = ''): string
{
    $code = strtoupper(clean($productCode));
    if ($code === '20268P5' || $code === '20268P32') $tour = 'VALPARAISO & VIÑA DEL MAR';
    elseif ($code === '20268P25') $tour = 'WINE TOUR MAIPO VALLEY';
    elseif ($code === '20268P12') $tour = 'PORTILLO INCA LAGOON';
    elseif ($code === '20268P8') $tour = 'CITY TOUR';
    else $tour = canonicalTour($product);
    return applyPrivateTourLabel($tour, isPrivateViatorProductGrade($productCode, $tourGrade));
}
```

Replace with:
```php
function canonicalTourFromViator(string $product, string $productCode, string $tourGrade = ''): string
{
    $code = strtoupper(clean($productCode));
    $maipoGrade = classify_viator_maipo_grade($productCode, $tourGrade, '');

    if ($maipoGrade === 'MAIPO_LUNCH') $tour = 'WINE TOUR MAIPO VALLEY TASTINGS & LUNCH';
    elseif ($maipoGrade === 'MAIPO_PLAIN' || $maipoGrade === 'MAIPO_PLAIN_PRIVATE') $tour = 'WINE TOUR MAIPO VALLEY';
    elseif ($code === '20268P5' || $code === '20268P32') $tour = 'VALPARAISO & VIÑA DEL MAR';
    elseif ($code === '20268P12') $tour = 'PORTILLO INCA LAGOON';
    elseif ($code === '20268P8') $tour = 'CITY TOUR';
    else $tour = canonicalTour($product);

    $isPrivate = $maipoGrade === 'MAIPO_PLAIN_PRIVATE'
        ? true
        : ($maipoGrade !== null ? false : isPrivateViatorProductGrade($productCode, $tourGrade));

    return applyPrivateTourLabel($tour, $isPrivate);
}
```

- [ ] **Step 5: Verify PHP syntax**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l admin/closing.php
```

Expected: `No syntax errors detected in admin/closing.php`.

- [ ] **Step 6: Commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
git add admin/check.php admin/closing.php
git commit -m "$(cat <<'EOF'
fix: use shared Maipo grade classifier in check/closing

Both files had no grade-based lunch upgrade for 20268P25 at all
(always categorized as the plain tour), and no explicit product-code
case for 20268P15 - it fell through to name-matching fallbacks that,
in check.php, actually mapped it to the WRONG (plain) category for
every single P15 booking. Now delegate to classify_viator_maipo_grade()
(admin/_viator_categorization.php) for both products, matching the
fix already applied to consolidate-day/month.
EOF
)"
```

---

### Task 4: Full regression verification

**Files:**
- No files modified — verification only.

**Interfaces:**
- Consumes: the classifier and all three consumer-file changes from Tasks 1-3.
- Produces: confirmation the fix is complete and correct before considering this plan done.

- [ ] **Step 1: Run the full PHPUnit suite one more time**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php tests/tools/phpunit.phar
```

Expected: all tests pass, including `ViatorCategorizationTest` and every pre-existing test class.

- [ ] **Step 2: Syntax-check all four modified/created files together**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
for f in admin/_viator_categorization.php admin/consolidate-day.php admin/consolidate-month.php admin/check.php admin/closing.php; do
  php -l "$f"
done
```

Expected: `No syntax errors detected` for all five files.

- [ ] **Step 3: Confirm every consumer file actually calls the new function (catches a missed wiring step)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
for f in admin/consolidate-day.php admin/consolidate-month.php admin/check.php admin/closing.php; do
  count=$(grep -c "classify_viator_maipo_grade(" "$f")
  echo "$f: $count call(s)"
done
```

Expected: each of the 4 files prints a count of at least `1` (consolidate-day/month have 1 call site each inside their CSV parser; check.php/closing.php have 1 call site each inside `canonicalTourFromViator()`). If any file prints `0`, that file's Task 2/3 edit was not actually applied — stop and fix before proceeding.

- [ ] **Step 4: Confirm no file still has a reachable path to the old, buggy behavior for the two specific real-data cases**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -r '
require "admin/_viator_categorization.php";
$cases = [
    ["20268P25", "TG4~07:30", "Lunch included 07:30", "MAIPO_LUNCH"],
    ["20268P15", "TG1~07:30", "Full wine trail  07:30", "MAIPO_LUNCH"],
    ["20268P15", "TG2~07:30", "", "MAIPO_PLAIN"],
];
$allOk = true;
foreach ($cases as [$code, $grade, $title, $expected]) {
    $actual = classify_viator_maipo_grade($code, $grade, $title);
    $ok = $actual === $expected;
    $allOk = $allOk && $ok;
    echo ($ok ? "OK  " : "FAIL") . " $code / $grade -> $actual (expected $expected)\n";
}
echo $allOk ? "ALL PASS\n" : "SOME FAILED\n";
'
```

Expected: `OK` on all 3 lines, `ALL PASS` at the end.

- [ ] **Step 5: Report completion**

No commit needed for this task (verification-only). Summarize for the user: the shared classifier is in place, all four admin pages consume it, the full test suite (including the new real-data-backed test) passes, and the two originally-reported real bugs (`20268P25`/`TG4` and `20268P15` grade handling) are confirmed fixed.

---
