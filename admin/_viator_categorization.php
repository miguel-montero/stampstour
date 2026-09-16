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
