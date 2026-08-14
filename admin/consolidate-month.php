<?php
/**
 * Booking Consolidator Multi-Day — Viator CSV + GYG multi-sheet XLSX/XLS + Civitatis CSV/XLS/XLSX + optional Guide Schedule + WEB DB
 *
 * Output: one copy/paste object grouped by DATE, then CATEGORY, with one empty row between days.
 * Copy order:
 * Date | Category | Passenger | Pax | Hotel | empty | Phone | Lang | Origin | Email | Booking Reference
 */
declare(strict_types=1);
require __DIR__ . '/_auth.php';

/* ---------- Autoload (Composer / PhpSpreadsheet) ---------- */
$autoload_candidates = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/phpspreadsheet_lib/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__) . '/phpspreadsheet_lib/vendor/autoload.php',
];
foreach ($autoload_candidates as $a) {
    if (is_file($a)) require_once $a;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

/* ---------- DB Config para reservas WEB / STAMP_ ---------- */
$dbConnected = false;
$dbError = '';
$webDbDebug = [];
$conn = null;
$dbConfigCandidates = [
    dirname(__DIR__, 2) . '/db_config.php',
    dirname(__DIR__) . '/db_config.php',
    __DIR__ . '/../db_config.php',
];
foreach ($dbConfigCandidates as $dbConfigPath) {
    if (is_file($dbConfigPath)) {
        require_once $dbConfigPath;
        break;
    }
}
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    $dbConnected = true;
    $webDbDebug[] = 'WEB/BD OK: conexión activa.';
} else {
    $dbError = 'No se pudo conectar a la BD interna. Revisa que ../db_config.php exista y cree $conn.';
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_error) $dbError .= ' MySQL: ' . $conn->connect_error;
    $webDbDebug[] = 'WEB/BD INFO: ' . $dbError;
}


/* ---------- GYG Gmail hotel lookup via Google Apps Script Web App ----------
 * Mantiene la función original para completar hoteles/pickups de GetYourGuide.
 * En modo multi-día se aplica sobre todas las filas GET guardadas en sesión,
 * independiente de la fecha u hoja desde donde vinieron.
 */
const GYG_GMAIL_LOOKUP_WEBAPP_URL = 'https://script.google.com/macros/s/AKfycbyM5cNfuThQt0gpqLO1rWfAgudzbIXCMGb039ZIosUxhmAcDLHpzisvMz4TAIRVBof7/exec';
const GYG_GMAIL_LOOKUP_TOKEN = 'D0ctorC4t';

/* ---------- Output columns ---------- */
const EXPECTED_COLS = [
    'G_Origin',
    'A_PassengerName',
    'B_TotalPax',
    'C_Hotels',
    'D_Empty',
    'E_Phone',
    'F_Language',
    'Experience',
    'H_Infants(n)',
    'I_Children(m)',
    'J_Email',
    'K_BookingReference',
    'TravelDate',
    'MysqlDate',
];

const CATEGORY_ORDER = [
    'Valparaíso & Viña del Mar' => 1,
    'PVT Valparaíso & Viña del Mar' => 2,
    'Discover Santiago' => 3,
    'PVT Discover Santiago' => 4,
    'Maipo Valley' => 5,
    'PVT Maipo Valley' => 6,
    'Maipo Valley Tastings & Lunch' => 7,
    'PVT Maipo Valley Tastings & Lunch' => 8,
    'Portillo / Inca Lagoon' => 9,
    'PVT Portillo / Inca Lagoon' => 10,
    'San Antonio after Cruise tour to Valparaiso and Casablanca Drop off in Santiago' => 11,
    'Valparaiso after Cruise tour to Valparaiso and Casablanca Drop off in Santiago' => 12,
    'Transfer to Valparaiso Cruise Terminal with Winery & Town Tour' => 13,
    'Transfer to San Antonio port prior cruise with tour in Valparaiso and Casablanca' => 14,
    'GYG Unmapped Product' => 98,
    'Uncategorized' => 99,
];

/* ---------- Helpers ---------- */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function clean_phone(?string $s): string { return preg_replace('/\D+/', '', (string)($s ?? '')) ?? ''; }
function blank_to_null($v): ?string { $v = trim((string)($v ?? '')); return $v === '' ? null : $v; }

function lang_code(?string $v): string {
    $v = mb_strtolower(trim((string)($v ?? '')));
    if ($v === '') return 'ENG';
    if (str_contains($v, 'span') || str_contains($v, 'españ') || str_contains($v, 'espan') || $v === 'es' || $v === 'spa') return 'SPA';
    if (str_contains($v, 'portu') || str_contains($v, 'brazil') || str_contains($v, 'brasil') || $v === 'pt' || $v === 'bra') return 'BRA';
    if (str_contains($v, 'eng') || $v === 'en') return 'ENG';
    return 'ENG';
}

function normalize_planilla_date($value): string {
    if ($value instanceof \DateTimeInterface) return $value->format('d/m/Y');
    $value = trim((string)($value ?? ''));
    if ($value === '') return '';

    if (is_numeric($value)) {
        try {
            if (class_exists(\PhpOffice\PhpSpreadsheet\Shared\Date::class)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)->format('d/m/Y');
            }
        } catch (Throwable $e) {}
    }

    if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{2,4})/', $value, $m)) {
        $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $year = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
        return "$day/$month/$year";
    }

    if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $value, $m)) return "$m[3]/$m[2]/$m[1]";

    $ts = strtotime($value);
    return $ts !== false ? date('d/m/Y', $ts) : $value;
}

function planilla_date_to_mysql(?string $value): string {
    $normalized = normalize_planilla_date($value ?? '');
    if ($normalized === '') return '';
    $dt = DateTime::createFromFormat('d/m/Y', $normalized);
    if ($dt instanceof DateTime) return $dt->format('Y-m-d');
    $ts = strtotime((string)$value);
    return $ts !== false ? date('Y-m-d', $ts) : '';
}

function mysql_to_planilla(?string $value): string {
    $value = trim((string)($value ?? ''));
    if ($value === '') return '';
    $dt = DateTime::createFromFormat('Y-m-d', substr($value, 0, 10));
    return $dt instanceof DateTime ? $dt->format('d/m/Y') : normalize_planilla_date($value);
}

function ensure_cols(array $row): array {
    $row['TravelDate'] = normalize_planilla_date($row['TravelDate'] ?? '');
    $row['MysqlDate'] = $row['MysqlDate'] ?? planilla_date_to_mysql($row['TravelDate']);
    foreach (EXPECTED_COLS as $c) {
        if (!array_key_exists($c, $row)) $row[$c] = in_array($c, ['B_TotalPax','H_Infants(n)','I_Children(m)'], true) ? 0 : '';
    }
    $ordered = [];
    foreach (EXPECTED_COLS as $c) $ordered[$c] = $row[$c];
    if (isset($row['CategoryHint'])) $ordered['CategoryHint'] = $row['CategoryHint'];
    if (isset($row['isPrivate'])) $ordered['isPrivate'] = (bool)$row['isPrivate'];
    return $ordered;
}

function normalize_for_match(string $name): string {
    $name = trim($name);
    if ($name === '') return '';
    if (str_contains($name, ',')) {
        [$last, $first] = array_map('trim', explode(',', $name, 2));
        $name = $first . ' ' . $last;
    }
    $name = preg_replace('/[,\.;]+/', ' ', $name) ?? $name;
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;
    $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if ($trans !== false) $name = $trans;
    return mb_strtolower(trim($name));
}

function choose_hotel_viator(array $row): string {
    foreach (['Hotel Pickup','Pickup Details','Meeting Point Details'] as $k) {
        $v = trim((string)($row[$k] ?? ''));
        if ($v !== '') return $v;
    }
    return '';
}

/* ---------- Categorization ---------- */
function categorize_experience_fallback(?string $name): string {
    $n = mb_strtolower((string)($name ?? ''));
    $n = strtr($n, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ã'=>'a']);
    if (preg_match('/valpa|valpo|vina|vinamar|vina del mar/', $n)) return 'Valparaíso & Viña del Mar';
    if (preg_match('/\bmaipo\b/', $n)) return 'Maipo Valley';
    if (preg_match('/lagun|lagoon|inca|portillo|andes\s*range|ski portillo/', $n)) return 'Portillo / Inca Lagoon';
    if (preg_match('/discover\s+santiago|city\s*tour.*santiago|santiago.*city\s*tour|half-day walking|brunch/', $n)) return 'Discover Santiago';
    return 'Uncategorized';
}

function categorize_from_viator_product(?string $prodCode, ?string $prodName, ?bool &$isLowCost = null): ?string {
    $isLowCost = false;
    $code = strtoupper(trim((string)$prodCode));
    return match ($code) {
        '20268P5' => 'Valparaíso & Viña del Mar',
        '20268P32' => (($isLowCost = true) ? 'Valparaíso & Viña del Mar' : 'Valparaíso & Viña del Mar'),
        '20268P25' => 'Maipo Valley',
        '20268P15' => 'Maipo Valley Tastings & Lunch',
        '20268P12' => 'Portillo / Inca Lagoon',
        '20268P8' => 'Discover Santiago',
        '20268P29' => 'San Antonio after Cruise tour to Valparaiso and Casablanca Drop off in Santiago',
        '20268P26' => 'Valparaiso after Cruise tour to Valparaiso and Casablanca Drop off in Santiago',
        '20268P24' => 'Transfer to Valparaiso Cruise Terminal with Winery & Town Tour',
        '20268P23' => 'Transfer to San Antonio port prior cruise with tour in Valparaiso and Casablanca',
        default => null,
    };
}

function is_viator_maipo_tastings_lunch_grade(?string $productCode, ?string $tourGrade, ?string $tourGradeTitle = ''): bool {
    $code = strtoupper(trim((string)($productCode ?? '')));
    $grade = strtoupper(trim((string)($tourGrade ?? '')));
    $title = mb_strtolower(trim((string)($tourGradeTitle ?? '')));

    if ($code !== '20268P25') return false;

    // Viator Maipo 4-winery product can now sell the Classic / 3-winery lunch option as TG3.
    // Keep TG2 as private Maipo; TG1 stays regular Maipo Valley.
    if (preg_match('/^TG3(?:\b|~)/i', $grade) === 1) return true;

    // Safety fallback in case Viator changes or omits the grade code but keeps the title.
    return str_contains($title, 'classic maipo valley');
}

function is_private_viator_product_grade(?string $productCode, ?string $tourGrade): bool {
    $code = strtoupper(trim((string)$productCode));
    $grade = strtoupper(trim((string)$tourGrade));
    if ($code === '20268P5') return preg_match('/^TG3(?:\b|~)/i', $grade) === 1;
    if ($code === '20268P8') return preg_match('/^TG1(?:\b|~)/i', $grade) === 1;
    if ($code === '20268P12') return preg_match('/^TG1(?:\b|~)/i', $grade) === 1;
    if ($code === '20268P25') return preg_match('/^TG2(?:\b|~)/i', $grade) === 1;
    return false;
}

function extract_gyg_product_id(?string $prodIdRaw): string {
    $prodIdRaw = trim((string)($prodIdRaw ?? ''));
    if ($prodIdRaw === '') return '';

    // GYG product cells can look like:
    // "273397 [VLP 1st] Santiago: Valparaiso..."
    // Capture only the first real product/activity ID, not digits from tags like "1st".
    if (preg_match('/\b(\d{5,})\b/', $prodIdRaw, $m)) {
        return $m[1];
    }
    return '';
}

function categorize_from_gyg_product_id(?string $prodIdRaw, ?string $title, ?bool &$isLowCost = null): ?string {
    $isLowCost = false;
    $digits = extract_gyg_product_id($prodIdRaw);
    if ($digits === '') return null;

    return match ($digits) {
        '878148' => (($isLowCost = true) ? 'Valparaíso & Viña del Mar' : 'Valparaíso & Viña del Mar'),
        '273397' => 'Valparaíso & Viña del Mar',
        '288540' => 'Maipo Valley',
        '1333337' => 'Maipo Valley Tastings & Lunch',
        '765423' => 'Discover Santiago',
        '277632' => 'Portillo / Inca Lagoon',
        default => null,
    };
}
function categorize_from_gyg_col_g(?string $gCell): ?string {
    $s = trim((string)($gCell ?? ''));
    if ($s === '') return null;
    if (preg_match('/^\s*\[?\s*PVT\s+VLP\s*\]?/i', $s)) return 'Valparaíso & Viña del Mar';
    if (preg_match('/^\s*\[?\s*PVT\s+MPO\s*\]?/i', $s)) return 'Maipo Valley';
    if (preg_match('/^\s*\[?\s*PVT\s+PRT\s*\]?/i', $s)) return 'Portillo / Inca Lagoon';
    if (preg_match('/^\s*\[?\s*PVT\s+CTY\s*\]?/i', $s)) return 'Discover Santiago';
    if (preg_match('/^\s*\[vlp\]/i', $s)) return 'Valparaíso & Viña del Mar';
    if (preg_match('/^\s*\[mpo\]/i', $s)) return 'Maipo Valley';
    if (preg_match('/^\s*\[prt\]/i', $s)) return 'Portillo / Inca Lagoon';
    if (preg_match('/^\s*\[(default|cty)\]/i', $s)) return 'Discover Santiago';
    return null;
}

function is_private_gyg_option(?string $value): bool {
    return preg_match('/^\s*\[?\s*PVT\s+(VLP|MPO|PRT|CTY)\s*\]?/i', trim((string)($value ?? ''))) === 1;
}

function apply_private_category(?string $baseCategory, bool $isPrivate): string {
    $baseCategory = $baseCategory ?: 'Uncategorized';
    if ($isPrivate && in_array($baseCategory, ['Valparaíso & Viña del Mar','Maipo Valley','Maipo Valley Tastings & Lunch','Portillo / Inca Lagoon','Discover Santiago'], true)) return 'PVT ' . $baseCategory;
    return $baseCategory;
}

function planilla_category_label($cat): string {
    $labels = [
        'Valparaíso & Viña del Mar' => 'VALPARAISO & VIÑA DEL MAR',
        'PVT Valparaíso & Viña del Mar' => 'PVT VALPARAISO & VIÑA DEL MAR',
        'Discover Santiago' => 'CITY TOUR',
        'PVT Discover Santiago' => 'PVT CITY TOUR',
        'Maipo Valley' => 'WINE TOUR MAIPO VALLEY',
        'PVT Maipo Valley' => 'PVT WINE TOUR MAIPO VALLEY',
        'Maipo Valley Tastings & Lunch' => 'WINE TOUR MAIPO VALLEY TASTINGS & LUNCH',
        'PVT Maipo Valley Tastings & Lunch' => 'PVT WINE TOUR MAIPO VALLEY TASTINGS & LUNCH',
        'Portillo / Inca Lagoon' => 'PORTILLO INCA LAGOON',
        'PVT Portillo / Inca Lagoon' => 'PVT PORTILLO INCA LAGOON',
        'San Antonio after Cruise tour to Valparaiso and Casablanca Drop off in Santiago' => 'SAN ANTONIO AFTER CRUISE VALPARAISO + CASABLANCA DROP IN SANTIAGO',
        'Valparaiso after Cruise tour to Valparaiso and Casablanca Drop off in Santiago' => 'VALPARAISO AFTER CRUISE VALPARAISO + CASABLANCA DROP IN SANTIAGO',
        'Transfer to Valparaiso Cruise Terminal with Winery & Town Tour' => 'TRANSFER TO VALPARAISO CRUISE TERMINAL WINERY + TOWN TOUR',
        'Transfer to San Antonio port prior cruise with tour in Valparaiso and Casablanca' => 'TRANSFER TO SAN ANTONIO PRIOR CRUISE VALPARAISO + CASABLANCA',
        'GYG Unmapped Product' => 'GYG UNMAPPED PRODUCT',
        'Uncategorized' => 'UNCATEGORIZED',
    ];
    return $labels[$cat] ?? strtoupper((string)$cat);
}

/* ---------- Parsers ---------- */
function parse_viator_csv(string $tmpPath): array {
    $rows = [];
    if (!is_readable($tmpPath)) return $rows;
    $fh = fopen($tmpPath, 'r');
    if (!$fh) return $rows;
    $header = fgetcsv($fh);
    if (!$header) { fclose($fh); return $rows; }
    $map = [];
    foreach ($header as $i => $h) $map[trim((string)$h)] = $i;
    $get = fn(array $arr, string $key) => isset($map[$key], $arr[$map[$key]]) ? $arr[$map[$key]] : null;

    while (($line = fgetcsv($fh)) !== false) {
        $status = trim((string)($get($line, 'Status') ?? ''));
        if (!in_array($status, ['Confirmed','Amended'], true)) continue;
        $travelDateRaw = (string)($get($line, 'Travel Date') ?? $get($line, 'Travel date') ?? $get($line, 'Date') ?? '');
        $travelDate = normalize_planilla_date($travelDateRaw);
        $mysqlDate = planilla_date_to_mysql($travelDate);
        if ($mysqlDate === '') continue;

        $experience = (string)($get($line, 'Product Name') ?? '');
        $prodCode = (string)($get($line, 'Product Code') ?? $get($line, 'Product code') ?? $get($line, 'Product ID') ?? '');
        $tourGrade = (string)($get($line, 'Tour Grade Code') ?? $get($line, 'Tour Grade') ?? '');
        $tourGradeTitle = (string)($get($line, 'Tour Grade Title') ?? '');
        $isLowCost = false;
        $catHint = categorize_from_viator_product($prodCode, $experience, $isLowCost);
        if (is_viator_maipo_tastings_lunch_grade($prodCode, $tourGrade, $tourGradeTitle)) {
            $catHint = 'Maipo Valley Tastings & Lunch';
        }
        if ($isLowCost) $experience = rtrim($experience) . ' (Low Cost)';

        $contactInfo = (string)($get($line, 'Lead traveler Contact Info') ?? '');
        $emailRaw = $get($line, 'Lead traveler Email') ?? $get($line, 'Lead Traveler Email') ?? $get($line, 'Lead traveler email') ?? $get($line, 'Email') ?? null;
        $email = trim((string)($emailRaw ?? ''));
        if ($email === '' && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $contactInfo, $m)) $email = $m[0];

        $rows[] = ensure_cols([
            'G_Origin' => 'TRIP',
            'A_PassengerName' => trim((string)($get($line, 'Lead traveler Name') ?? '')),
            'B_TotalPax' => (int)($get($line, 'Number of Passengers') ?? 0),
            'C_Hotels' => choose_hotel_viator([
                'Hotel Pickup' => $get($line, 'Hotel Pickup'),
                'Pickup Details' => $get($line, 'Pickup Details'),
                'Meeting Point Details' => $get($line, 'Meeting Point Details'),
            ]),
            'D_Empty' => '',
            'E_Phone' => clean_phone($contactInfo),
            'F_Language' => lang_code((string)($get($line, 'Tour Language') ?? $get($line, 'Language') ?? '')),
            'Experience' => trim($experience),
            'H_Infants(n)' => (int)($get($line, 'Infants') ?? 0),
            'I_Children(m)' => (int)($get($line, 'Children') ?? 0),
            'J_Email' => $email,
            'K_BookingReference' => trim((string)($get($line, 'Booking Reference') ?? '')),
            'TravelDate' => $travelDate,
            'MysqlDate' => $mysqlDate,
            'CategoryHint' => $catHint,
            'isPrivate' => is_private_viator_product_grade($prodCode, $tourGrade),
        ]);
    }
    fclose($fh);
    return $rows;
}

function parse_gyg_workbook(string $tmpPath): array {
    if (!class_exists(IOFactory::class) || !is_readable($tmpPath)) return [];
    $rows = [];
    $spreadsheet = IOFactory::load($tmpPath);

    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        $data = $sheet->toArray(null, true, true, true);
        if (count($data) < 2) continue;

        $header = array_shift($data);
        $map = [];
        foreach ($header as $col => $name) $map[trim((string)$name)] = $col;
        $get = fn(array $row, string $key) => isset($map[$key]) ? ($row[$map[$key]] ?? null) : null;

        $paxCols = ['Adult','Senior','Student (with ID)','EU citizens (with ID)','EU Citizens (with ID)','Student EU citizens (with ID)','Student EU Citizens (with ID)','Military (with ID)','Youth','Child','Infant'];

        foreach ($data as $row) {
            $bookingRef = trim((string)($get($row, 'Booking Ref #') ?? $get($row, 'Booking Ref No.') ?? $get($row, 'Booking Ref No') ?? $get($row, 'Booking Reference') ?? ($row['D'] ?? '')));
            if ($bookingRef === '') continue;

            $travelDateRaw = $get($row, 'Date') ?? ($row['A'] ?? '');
            $travelDate = normalize_planilla_date($travelDateRaw);
            $mysqlDate = planilla_date_to_mysql($travelDate);
            if ($mysqlDate === '') continue;

            $option = (string)($get($row, 'Option') ?? ($row['G'] ?? ''));
            $prod = (string)($get($row, 'Product') ?? ($row['F'] ?? $option));
            $prodIdRaw = (string)($row['F'] ?? $prod);
            $isPrivate = is_private_gyg_option($option);
            $isLowCost = false;
            $gygProductId = extract_gyg_product_id($prodIdRaw);
            $catHint = categorize_from_gyg_product_id($prodIdRaw, $option, $isLowCost);

            // If column F contains an unknown GYG product ID, keep it separate for review
            // instead of merging it by text into an existing tour.
            if ($catHint === null && $gygProductId !== '') {
                $catHint = 'GYG Unmapped Product';
            }

            // Legacy fallback only when no product ID exists in column F.
            if ($catHint === null) {
                $catHint = categorize_from_gyg_col_g($option);
            }

            if ($catHint === null) {
                $catHint = 'GYG Unmapped Product';
            }

            if ($isLowCost) $prod = rtrim($prod) . ' (Low Cost)';

            $tot = 0;
            foreach ($paxCols as $pc) $tot += (int)($get($row, $pc) ?? 0);
            if ($isPrivate) {
                $groupRaw = $get($row, 'Group') ?? ($row['X'] ?? null);
                $groupPax = (int)(preg_replace('/\D+/', '', (string)($groupRaw ?? '')) ?: 0);
                if ($groupPax > 0) $tot = $groupPax;
            }

            $firstName = (string)($get($row, "Traveller's First Name") ?? $get($row, "Traveler's First Name") ?? $get($row, 'Traveller First Name') ?? $get($row, 'Traveler First Name') ?? $get($row, 'First Name') ?? '');
            $lastName = (string)($get($row, "Traveller's Surname") ?? $get($row, "Traveler's Last Name") ?? $get($row, "Traveller's Last Name") ?? $get($row, 'Traveller Surname') ?? $get($row, 'Traveler Last Name') ?? $get($row, 'Last Name') ?? $get($row, 'Surname') ?? '');
            $name = trim($firstName . ' ' . $lastName);
            $additionalInfo = trim((string)($get($row, 'Additional Information') ?? ''));
            $hotel = ($additionalInfo !== '' && $additionalInfo !== "'") ? trim($additionalInfo, "' \t\n\r\0\x0B") : '';

            $rows[] = ensure_cols([
                'G_Origin' => 'GET',
                'A_PassengerName' => $name,
                'B_TotalPax' => $tot,
                'C_Hotels' => $hotel,
                'D_Empty' => '',
                'E_Phone' => clean_phone((string)($get($row, 'Phone') ?? '')),
                'F_Language' => lang_code((string)($get($row, 'Language') ?? '')),
                'Experience' => trim($prod),
                'H_Infants(n)' => (int)($get($row, 'Infant') ?? 0),
                'I_Children(m)' => (int)($get($row, 'Child') ?? 0),
                'J_Email' => trim((string)($get($row, 'Email') ?? ($row['L'] ?? ''))),
                'K_BookingReference' => $bookingRef,
                'TravelDate' => $travelDate,
                'MysqlDate' => $mysqlDate,
                'CategoryHint' => $catHint,
                'isPrivate' => $isPrivate,
            ]);
        }
    }
    return $rows;
}

function detect_csv_delimiter(string $tmpPath): string {
    $sample = file_get_contents($tmpPath, false, null, 0, 4096) ?: '';
    return substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',';
}

function parse_civitatis_csv(string $tmpPath): array {
    $rows = [];
    if (!is_readable($tmpPath)) return $rows;
    $delimiter = detect_csv_delimiter($tmpPath);
    $fh = fopen($tmpPath, 'r');
    if (!$fh) return $rows;
    $header = fgetcsv($fh, 0, $delimiter);
    if (!$header) { fclose($fh); return $rows; }
    if (count($header) === 1 && $delimiter !== ';') {
        rewind($fh); $delimiter = ';'; $header = fgetcsv($fh, 0, $delimiter);
    }
    $map = [];
    foreach ($header as $i => $h) $map[trim((string)$h)] = $i;
    $get = fn(array $arr, string $key) => isset($map[$key], $arr[$map[$key]]) ? $arr[$map[$key]] : null;

    while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
        $status = mb_strtolower(trim((string)($get($line, 'Estado') ?? '')));
        if ($status !== '' && !str_contains($status, 'confirm')) continue;
        $bookingRef = trim((string)($get($line, 'Reserva') ?? ''));
        $name = trim((string)($get($line, 'Nombre') ?? ''));
        if ($bookingRef === '' && $name === '') continue;
        $travelDate = normalize_planilla_date((string)($get($line, 'Fecha Realización') ?? $get($line, 'Fecha Realizacion') ?? ''));
        $mysqlDate = planilla_date_to_mysql($travelDate);
        if ($mysqlDate === '') continue;
        $experience = trim((string)($get($line, 'Producto') ?? ''));
        $adults = (int)($get($line, 'Adultos') ?? 0);
        $children = (int)(($get($line, 'Niños de 4 a 12 años') ?? $get($line, 'Crianças de 4 a 12 anos') ?? 0));
        $infants = (int)(($get($line, 'Menores de 4 años') ?? $get($line, 'Menores de 4 anos') ?? 0));
        $paxRaw = (int)($get($line, 'Pasajeros') ?? 0);
        $pax = $paxRaw > 0 ? $paxRaw : ($adults + $children + $infants);

        $rows[] = ensure_cols([
            'G_Origin' => 'CIV',
            'A_PassengerName' => $name,
            'B_TotalPax' => $pax,
            'C_Hotels' => trim((string)($get($line, 'Info') ?? '')),
            'D_Empty' => '',
            'E_Phone' => '',
            'F_Language' => 'SPA',
            'Experience' => $experience,
            'H_Infants(n)' => $infants,
            'I_Children(m)' => $children,
            'J_Email' => '',
            'K_BookingReference' => $bookingRef,
            'TravelDate' => $travelDate,
            'MysqlDate' => $mysqlDate,
            'CategoryHint' => categorize_experience_fallback($experience),
            'isPrivate' => false,
        ]);
    }
    fclose($fh);
    return $rows;
}

function parse_civitatis_xls_blocks(string $tmpPath): array {
    if (!class_exists(IOFactory::class) || !is_readable($tmpPath)) return [];
    $rows = [];
    $sheet = IOFactory::load($tmpPath)->getSheet(0);
    $data = $sheet->toArray(null, true, true, true);
    $currentExperience = '';
    $currentDate = '';
    $currentCategory = null;
    $insideTable = false;
    foreach ($data as $row) {
        $a = trim((string)($row['A'] ?? ''));
        $b = trim((string)($row['B'] ?? ''));
        $c = trim((string)($row['C'] ?? ''));
        if ($a !== '' && $b === '' && $c === '' && stripos($a, 'Reserva') === false && strtoupper($a) !== 'TOTAL') {
            $parts = array_map('trim', explode(',', $a));
            $currentExperience = $parts[0] ?? $a;
            $currentDate = '';
            if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $a, $m)) $currentDate = mysql_to_planilla($m[1]);
            $currentCategory = categorize_experience_fallback($currentExperience);
            $insideTable = false;
            continue;
        }
        if (mb_strtolower($a) === 'reserva') { $insideTable = true; continue; }
        if (!$insideTable || $a === '') continue;
        if (mb_strtoupper($a) === 'TOTAL') { $insideTable = false; continue; }
        $adults = (int)($row['D'] ?? 0);
        $children = (int)($row['E'] ?? 0);
        $infants = (int)($row['F'] ?? 0);
        $rows[] = ensure_cols([
            'G_Origin' => 'CIV',
            'A_PassengerName' => $b,
            'B_TotalPax' => $adults + $children + $infants,
            'C_Hotels' => preg_replace('/^\s*:\s*/', '', $c) ?? $c,
            'E_Phone' => '',
            'F_Language' => 'SPA',
            'Experience' => $currentExperience,
            'H_Infants(n)' => $infants,
            'I_Children(m)' => $children,
            'J_Email' => '',
            'K_BookingReference' => $a,
            'TravelDate' => $currentDate,
            'MysqlDate' => planilla_date_to_mysql($currentDate),
            'CategoryHint' => $currentCategory,
            'isPrivate' => false,
        ]);
    }
    return $rows;
}

function parse_civitatis_file(string $tmpPath, string $originalName): array {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'csv') return parse_civitatis_csv($tmpPath);
    if (in_array($ext, ['xls','xlsx'], true)) return parse_civitatis_xls_blocks($tmpPath);
    return [];
}

function parse_passenger_list_lookup(string $tmpPath): array {
    if (!class_exists(IOFactory::class) || !is_readable($tmpPath)) return [];
    $sheet = IOFactory::load($tmpPath)->getSheet(0);
    $data = $sheet->toArray(null, true, true, true);
    $lookup = [];
    foreach ($data as $row) {
        $rawName = (string)($row['H'] ?? '');
        $key = normalize_for_match($rawName);
        if ($key === '') continue;
        $lookup[$key] = [
            'phone' => clean_phone((string)($row['I'] ?? '')),
            'email' => trim((string)($row['J'] ?? '')),
            'orig' => $rawName,
        ];
    }
    return $lookup;
}

function enrich_viator_with_passenger_lookup(array &$viatorRows, array $lookup, array &$unmatchedOut): void {
    $unmatched = $lookup;
    foreach ($viatorRows as &$row) {
        if (($row['G_Origin'] ?? '') !== 'TRIP') continue;
        $key = normalize_for_match((string)($row['A_PassengerName'] ?? ''));
        if ($key !== '' && isset($lookup[$key])) {
            if (!empty($lookup[$key]['phone'])) $row['E_Phone'] = $lookup[$key]['phone'];
            if (!empty($lookup[$key]['email'])) $row['J_Email'] = $lookup[$key]['email'];
            unset($unmatched[$key]);
        }
    }
    unset($row);
    $unmatchedOut = array_values(array_map(fn($x) => $x['orig'] ?? '', $unmatched));
}

/* ---------- WEB DB ---------- */
function is_private_web_db_row(array $row): bool {
    $txt = mb_strtolower((string)(($row['experiencia_nombre'] ?? '') . ' ' . ($row['experiencia_nombre_publico'] ?? '')));
    $txt = strtr($txt, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
    return str_contains($txt, 'pvt') || str_contains($txt, 'private') || str_contains($txt, 'privado');
}

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

function fetch_web_reservations_for_dates(mysqli $conn, array $mysqlDates, array &$webDbDebug = []): array {
    $mysqlDates = array_values(array_unique(array_filter($mysqlDates)));
    if (empty($mysqlDates)) return [];
    $rows = [];
    $placeholders = implode(',', array_fill(0, count($mysqlDates), '?'));
    $sql = "
        SELECT
            r.id_reserva, r.reference_id, r.fecha_reserva, r.fecha_actividad,
            r.adultos, r.ninos, r.infantes, r.airport_pickup, r.estado,
            r.pais_origen, r.idioma_actividad,
            e.nombre AS experiencia_nombre, e.nombre_publico AS experiencia_nombre_publico,
            t.nombre AS titular_nombre, t.apellido AS titular_apellido,
            t.area_code AS titular_area_code, t.telefono AS titular_telefono, t.email AS titular_email,
            h.nombre_hotel, h.direccion, h.comuna,
            r.hotel_manual
        FROM reservas r
        LEFT JOIN experiencias e ON e.id_experiencia = r.id_experiencia
        LEFT JOIN titulares t ON t.id_titular = r.id_titular
        LEFT JOIN hoteles h ON h.id_hotel = r.id_hotel
        WHERE r.fecha_actividad IN ($placeholders)
          AND TRIM(COALESCE(r.estado, '')) = 'realizado'
        ORDER BY r.fecha_actividad, COALESCE(e.nombre_publico, e.nombre), t.apellido, t.nombre, r.id_reserva
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { $webDbDebug[] = 'WEB/BD ERROR prepare: ' . $conn->error; return []; }
    $types = str_repeat('s', count($mysqlDates));
    $stmt->bind_param($types, ...$mysqlDates);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $experience = trim((string)(($row['experiencia_nombre_publico'] ?? '') ?: ($row['experiencia_nombre'] ?? '')));
        $adults = (int)($row['adultos'] ?? 0);
        $children = (int)($row['ninos'] ?? 0);
        $infants = (int)($row['infantes'] ?? 0);
        $mysqlDate = substr((string)($row['fecha_actividad'] ?? ''), 0, 10);
        $rows[] = ensure_cols([
            'G_Origin' => 'WEB',
            'A_PassengerName' => trim((string)(($row['titular_nombre'] ?? '') . ' ' . ($row['titular_apellido'] ?? ''))),
            'B_TotalPax' => $adults + $children + $infants,
            'C_Hotels' => build_web_hotel_text($row),
            'D_Empty' => '',
            'E_Phone' => clean_phone((string)(($row['titular_area_code'] ?? '') . ($row['titular_telefono'] ?? ''))),
            'F_Language' => lang_code((string)($row['idioma_actividad'] ?? '')),
            'Experience' => $experience,
            'H_Infants(n)' => $infants,
            'I_Children(m)' => $children,
            'J_Email' => trim((string)($row['titular_email'] ?? '')),
            'K_BookingReference' => trim((string)($row['reference_id'] ?? '')),
            'TravelDate' => mysql_to_planilla($mysqlDate),
            'MysqlDate' => $mysqlDate,
            'CategoryHint' => categorize_experience_fallback($experience),
            'isPrivate' => is_private_web_db_row($row),
        ]);
    }
    $stmt->close();
    $webDbDebug[] = 'WEB/BD: ' . count($rows) . ' reserva(s) WEB realizada(s) encontradas para ' . count($mysqlDates) . ' fecha(s).';
    return $rows;
}

/* ---------- Consolidate ---------- */
function consolidate_rows(array $frames): array {
    foreach ($frames as &$r) {
        $r = ensure_cols($r);
        $baseCategory = !empty($r['CategoryHint']) ? $r['CategoryHint'] : categorize_experience_fallback($r['Experience']);
        $r['Category'] = apply_private_category($baseCategory, !empty($r['isPrivate']));
        unset($r['CategoryHint'], $r['isPrivate']);
    }
    unset($r);
    usort($frames, function($a, $b) {
        $oa = CATEGORY_ORDER[$a['Category']] ?? 99;
        $ob = CATEGORY_ORDER[$b['Category']] ?? 99;
        return [$a['MysqlDate'], $oa, $a['Experience'], $a['A_PassengerName']] <=> [$b['MysqlDate'], $ob, $b['Experience'], $b['A_PassengerName']];
    });
    return $frames;
}

function group_by_date_and_category(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        $dateKey = $r['MysqlDate'] ?: planilla_date_to_mysql($r['TravelDate']);
        if ($dateKey === '') $dateKey = '9999-12-31';
        $cat = $r['Category'] ?? 'Uncategorized';
        $out[$dateKey]['label'] = $r['TravelDate'] ?: mysql_to_planilla($dateKey);
        $out[$dateKey]['categories'][$cat][] = $r;
    }
    ksort($out);
    foreach ($out as &$day) {
        uksort($day['categories'], fn($a, $b) => (CATEGORY_ORDER[$a] ?? 99) <=> (CATEGORY_ORDER[$b] ?? 99));
    }
    unset($day);
    return $out;
}

function day_visual_row_count(array $categories): int {
    $total = 0;
    foreach ($categories as $rows) $total += count($rows) + 1; // bookings + total row
    return $total;
}


/* ---------- Horario guías ---------- */
function normalize_guide_name(string $name): string {
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    return $name;
}

function guide_schedule_cell_day($value): ?int {
    if ($value instanceof \DateTimeInterface) return (int)$value->format('j');
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    if (is_numeric($value)) return (int)$value;
    if (preg_match('/\b(\d{1,2})\b/', $value, $m)) return (int)$m[1];
    return null;
}

function parse_guide_schedule_file(string $tmpPath, array $targetMysqlDates): array {
    if (!class_exists(IOFactory::class) || !is_readable($tmpPath)) return [];

    $targetMysqlDates = array_values(array_unique(array_filter($targetMysqlDates)));
    if (empty($targetMysqlDates)) return [];

    // Lookup by day of month + weekday number. This handles schedules that cross month boundaries.
    $targets = [];
    foreach ($targetMysqlDates as $date) {
        $ts = strtotime($date);
        if ($ts === false) continue;
        $targets[(int)date('j', $ts) . '-' . (int)date('N', $ts)][] = $date;
    }

    $schedule = [];
    $spreadsheet = IOFactory::load($tmpPath);
    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        $data = $sheet->toArray(null, true, true, true);
        $maxRow = count($data);

        for ($rowNum = 1; $rowNum <= $maxRow; $rowNum++) {
            $row = $data[$rowNum] ?? [];
            $a = mb_strtolower(trim((string)($row['A'] ?? '')));
            if (!str_contains($a, 'semana')) continue;

            // Header row: C-I = Lunes-Domingo day numbers.
            $colToDates = [];
            $cols = ['C'=>1,'D'=>2,'E'=>3,'F'=>4,'G'=>5,'H'=>6,'I'=>7];
            foreach ($cols as $col => $weekday) {
                $dayNum = guide_schedule_cell_day($row[$col] ?? null);
                if ($dayNum === null) continue;
                $key = $dayNum . '-' . $weekday;
                if (isset($targets[$key])) $colToDates[$col] = $targets[$key];
            }
            if (empty($colToDates)) continue;

            // Guide rows usually start two rows below the SEMANA row and continue until blank guide name.
            for ($r = $rowNum + 2; $r <= $maxRow; $r++) {
                $guide = normalize_guide_name((string)($data[$r]['B'] ?? ''));
                if ($guide === '') break;
                if (mb_strtolower($guide) === 'guias') continue;

                foreach ($colToDates as $col => $dates) {
                    $status = mb_strtolower(trim((string)($data[$r][$col] ?? '')));
                    $status = strtr($status, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
                    if (!str_contains($status, 'trabajo')) continue;
                    foreach ($dates as $date) {
                        if (!isset($schedule[$date])) $schedule[$date] = [];
                        if (!in_array($guide, $schedule[$date], true)) $schedule[$date][] = $guide;
                    }
                }
            }
        }
    }

    ksort($schedule);
    return $schedule;
}


/* ---------- GYG Gmail hotel lookup bridge ---------- */
function build_gyg_hotel_lookup_planilla_multiday(array $rows): array {
    $planilla = [];
    foreach ($rows as $idx => $row) {
        $origin = strtoupper(trim((string)($row['G_Origin'] ?? '')));
        $hotel = trim((string)($row['C_Hotels'] ?? ''));
        $bookingRef = trim((string)($row['K_BookingReference'] ?? ''));
        $email = trim((string)($row['J_Email'] ?? ''));
        $passenger = trim((string)($row['A_PassengerName'] ?? ''));
        $date = trim((string)($row['TravelDate'] ?? ''));

        if ($origin !== 'GET') continue;
        if ($hotel !== '') continue;
        if ($passenger === '') continue;
        if ($bookingRef === '' && $email === '') continue;

        $planilla[] = [
            'row_id' => (string)$idx, // índice interno dentro de $_SESSION['consolidated_multiday']
            'origin' => 'GET',
            'date' => $date,
            'passenger' => $passenger,
            'booking_ref' => $bookingRef,
            'email' => $email,
            'hotel' => $hotel,
        ];
    }
    return $planilla;
}

function call_gyg_hotel_lookup_webapp(array $planilla): array {
    if (GYG_GMAIL_LOOKUP_WEBAPP_URL === '' || GYG_GMAIL_LOOKUP_TOKEN === 'CHANGE_ME_LONG_RANDOM_TOKEN') {
        throw new RuntimeException('Missing Apps Script configuration. Set GYG_GMAIL_LOOKUP_WEBAPP_URL and GYG_GMAIL_LOOKUP_TOKEN in this PHP file.');
    }

    if (empty($planilla)) {
        return ['ok' => true, 'results' => [], 'message' => 'No missing GYG hotels to look up.'];
    }

    $payload = json_encode([
        'token' => GYG_GMAIL_LOOKUP_TOKEN,
        'planilla' => $planilla,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false) throw new RuntimeException('Could not encode lookup payload as JSON.');

    $ch = curl_init(GYG_GMAIL_LOOKUP_WEBAPP_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 90,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $raw === '') {
        throw new RuntimeException('Apps Script lookup returned no response. cURL error: ' . $err);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Apps Script lookup returned invalid JSON. HTTP status ' . $status . '. Response: ' . substr($raw, 0, 300));
    }

    if (empty($decoded['ok'])) {
        $msg = isset($decoded['error']) ? (string)$decoded['error'] : 'Unknown Apps Script lookup error.';
        throw new RuntimeException($msg);
    }

    return $decoded;
}

function apply_gyg_hotel_lookup_results_multiday(array &$rows, array $lookupResponse): array {
    $updated = 0;
    $notFound = 0;
    $results = $lookupResponse['results'] ?? [];

    foreach ($results as $result) {
        $rowId = isset($result['row_id']) ? (string)$result['row_id'] : '';
        if ($rowId === '' || !ctype_digit($rowId)) continue;

        $idx = (int)$rowId;
        if (!isset($rows[$idx])) continue;

        $pickup = trim((string)($result['pickup'] ?? ''));
        if ($pickup !== '') {
            $rows[$idx]['C_Hotels'] = $pickup;
            $updated++;
        } else {
            $notFound++;
        }
    }

    return [
        'checked' => count($results),
        'updated' => $updated,
        'not_found' => $notFound,
        'message' => (string)($lookupResponse['message'] ?? ''),
    ];
}

/* ---------- POST handling ---------- */
$errors = [];
$consolidated = [];
$unmatchedPassengers = [];
$webRows = [];
$webSummary = [];
$guideSchedule = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'consolidate');

    if ($action === 'lookup_gyg_hotels') {
        $consolidated = $_SESSION['consolidated_multiday'] ?? [];
        if (empty($consolidated) || !is_array($consolidated)) {
            $errors[] = 'No hay planilla consolidada en sesión. Primero sube archivos y presiona Consolidar multi-día.';
        } else {
            try {
                $lookupPlanilla = build_gyg_hotel_lookup_planilla_multiday($consolidated);
                $lookupResponse = call_gyg_hotel_lookup_webapp($lookupPlanilla);
                $summary = apply_gyg_hotel_lookup_results_multiday($consolidated, $lookupResponse);
                $_SESSION['consolidated_multiday'] = $consolidated;
                $_SESSION['lookup_summary_multiday'] = $summary;
            } catch (Throwable $e) {
                $errors[] = 'GYG hotel lookup failed: ' . $e->getMessage();
            }
        }
    } else {
    $viatorRows = [];
    $gygRows = [];
    $civRows = [];
    $guideSchedule = [];

    if (!empty($_FILES['viator_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['viator_file']['name'], PATHINFO_EXTENSION));
        if ($ext === 'csv') $viatorRows = parse_viator_csv($_FILES['viator_file']['tmp_name']);
        else $errors[] = 'Trip/Viator debe ser CSV.';
    }

    if (!empty($_FILES['gyg_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['gyg_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx','xls'], true)) $gygRows = parse_gyg_workbook($_FILES['gyg_file']['tmp_name']);
        else $errors[] = 'GYG debe ser XLSX/XLS.';
    }

    if (!empty($_FILES['civitatis_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['civitatis_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['csv','xlsx','xls'], true)) $civRows = parse_civitatis_file($_FILES['civitatis_file']['tmp_name'], $_FILES['civitatis_file']['name']);
        else $errors[] = 'Civitatis debe ser CSV/XLSX/XLS.';
    }
    if (empty($viatorRows) && empty($gygRows) && empty($civRows)) $errors[] = 'Sube al menos un archivo: Viator CSV, GYG XLSX/XLS o Civitatis CSV/XLS/XLSX.';


    $sourceRowsForDates = array_merge($viatorRows, $gygRows, $civRows);
    $mysqlDates = array_values(array_unique(array_filter(array_map(fn($r) => (string)($r['MysqlDate'] ?? ''), $sourceRowsForDates))));

    if (empty($errors) && !empty($_FILES['guide_schedule_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['guide_schedule_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx','xls'], true)) $guideSchedule = parse_guide_schedule_file($_FILES['guide_schedule_file']['tmp_name'], $mysqlDates);
        else $errors[] = 'Horario guías debe ser XLSX/XLS.';
    }

    if (empty($errors) && $dbConnected && $conn instanceof mysqli) {
        $webRows = fetch_web_reservations_for_dates($conn, $mysqlDates, $webDbDebug);
    } elseif (empty($errors)) {
        $webDbDebug[] = 'WEB/BD: se omite enriquecimiento WEB porque no hay conexión. ' . $dbError;
    }

    if (empty($errors)) {
        $consolidated = consolidate_rows(array_merge($viatorRows, $gygRows, $civRows, $webRows));
        $_SESSION['consolidated_multiday'] = $consolidated;
        $_SESSION['guide_schedule_multiday'] = $guideSchedule;
        $_SESSION['web_summary_multiday'] = [
            'connected' => $dbConnected,
            'dates' => $mysqlDates,
            'count' => count($webRows),
            'debug' => $webDbDebug,
        ];
        unset($_SESSION['lookup_summary_multiday']);
    }
    }
}

if (!empty($_SESSION['consolidated_multiday'])) $consolidated = $_SESSION['consolidated_multiday'];
if (!empty($_SESSION['guide_schedule_multiday'])) $guideSchedule = $_SESSION['guide_schedule_multiday'];
if (!empty($_SESSION['web_summary_multiday'])) $webSummary = $_SESSION['web_summary_multiday'];
$lookupSummary = $_SESSION['lookup_summary_multiday'] ?? [];
$grouped = group_by_date_and_category($consolidated);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Booking Consolidator Multi-Day</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="/css/bootstrap.min.css" rel="stylesheet">
<link href="/css/style.css" rel="stylesheet">
<link href="/css/vendors.css" rel="stylesheet">
<link href="/css/admin.css" rel="stylesheet">
<link href="/css/custom.css" rel="stylesheet">
<style>
:root{--bg:#0b0e13;--card:#121722;--chip:#1b2331;--muted:#9aa3b2;--ink:#e6ebf2;--brand:#6aa1ff;--ok:#65d6ad;--warn:#f0b35d}
*{box-sizing:border-box} body{margin:0;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--ink)}
.wrap{max-width:1200px;margin:auto;padding:20px} h1{font-size:22px;margin:0 0 8px}.sub{color:var(--muted);margin:0 0 16px}
form{background:var(--card);border-radius:16px;padding:16px;margin-bottom:16px}.row{display:flex;gap:12px;flex-wrap:wrap}.box{flex:1 1 260px;background:var(--chip);border-radius:12px;padding:12px} input[type=file]{width:100%}
.btn{background:var(--brand);border:none;border-radius:10px;padding:8px 12px;color:white;font-weight:700;cursor:pointer}.btn-mini{background:#2a3650;border:1px solid #42567a;border-radius:8px;padding:4px 8px;color:#cfe0ff;font-size:12px;margin-left:8px}
.err{background:#3a1d1d;border:1px solid #8c3333;color:#ffdede;padding:10px;border-radius:8px;margin:10px 0}.card{background:var(--card);border-radius:16px;padding:16px;margin-top:16px}.day{background:#0e1420;border:1px solid #22324b;border-radius:14px;margin:18px 0;padding:12px}.group{background:var(--chip);border-radius:12px;margin:12px 0}.group h3{margin:0;padding:10px 12px;display:flex;align-items:center}.subtot{display:flex;gap:18px;color:var(--muted);padding:0 12px 10px 12px;flex-wrap:wrap}
table{width:100%;border-collapse:collapse;margin:0} th,td{padding:8px 10px;border-bottom:1px solid #1f2735;vertical-align:top} th{color:var(--muted);text-align:left}.pill{display:inline-block;padding:2px 8px;border-radius:999px;background:#24314a;color:#cfe0ff;font-size:12px;margin-right:6px}.actions{display:flex;gap:10px;margin-top:8px;flex-wrap:wrap}.note{color:#cfe0ff;font-size:12px;margin-top:6px}
.copy-planilla-table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:10pt;background:#fff;color:#000;text-align:center!important;vertical-align:middle!important}.copy-planilla-table td{border:1px solid #000;text-align:center!important;vertical-align:middle!important;white-space:normal!important;word-wrap:break-word;overflow-wrap:break-word;word-break:normal;padding:4px 6px;font-size:10pt;mso-number-format:"\\@"}.copy-planilla-hidden{position:absolute;left:-9999px;top:-9999px;width:auto;height:auto;overflow:hidden}.copy-planilla-total td{background:#eeeeee;font-weight:bold}.copy-planilla-spacer td{height:26px!important;background:#ffffff!important;border:none!important;padding:0!important}.copy-planilla-table td.copy-planilla-guide{border:none!important;background:#ffffff!important;text-align:left!important;vertical-align:middle!important;mso-border-alt:none!important}.copy-planilla-total td.copy-planilla-guide{background:#ffffff!important;font-weight:normal!important}
</style>
<script>
function cellText(td){ return (td.innerText || '').replace(/\s+/g,' ').trim(); }

async function copyFullMultiDayPlanilla(){
  const table = document.getElementById('full-planilla-copy');
  if(!table){ alert('No hay planilla para copiar.'); return; }

  const tableForCopy = table.cloneNode(true);
  tableForCopy.setAttribute('align','center');
  tableForCopy.setAttribute('valign','middle');
  tableForCopy.setAttribute('cellpadding','0');
  tableForCopy.setAttribute('cellspacing','0');
  tableForCopy.querySelectorAll('td,th').forEach(cell => {
    cell.setAttribute('align','center');
    cell.setAttribute('valign','middle');
    const prev = cell.getAttribute('style') || '';
    const isSpacer = cell.closest('tr') && cell.closest('tr').classList.contains('copy-planilla-spacer');
    const isGuide = cell.classList && cell.classList.contains('copy-planilla-guide');
    if(isSpacer){
      cell.setAttribute('style', prev + ';border:none!important;background:#ffffff!important;height:26px!important;padding:0!important;mso-border-alt:none!important;');
    } else if(isGuide){
      cell.setAttribute('style', prev + ';border:none!important;background:#ffffff!important;text-align:left!important;vertical-align:middle!important;font-family:Arial,sans-serif;font-size:10pt;mso-border-alt:none!important;');
    } else {
      cell.setAttribute('style', prev + ';border:1px solid #000;text-align:center!important;vertical-align:middle!important;font-family:Arial,sans-serif;font-size:10pt;white-space:normal!important;word-wrap:break-word;overflow-wrap:break-word;word-break:normal;mso-number-format:"\\@"');
    }
  });

  const html = '<html><head><meta charset="utf-8"><style>table{border-collapse:collapse;text-align:center;vertical-align:middle}td,th{border:1px solid #000;text-align:center!important;vertical-align:middle!important;font-family:Arial,sans-serif;font-size:10pt;white-space:normal!important;word-wrap:break-word;overflow-wrap:break-word;word-break:normal;mso-number-format:"\\@"}.copy-planilla-spacer td{border:none!important;background:#ffffff!important;height:26px!important;padding:0!important;mso-border-alt:none!important}.copy-planilla-guide{border:none!important;background:#ffffff!important;mso-border-alt:none!important}</style></head><body>' + tableForCopy.outerHTML + '</body></html>';

  const plainRows = [];
  table.querySelectorAll('tr').forEach(tr => {
    const row = [];
    tr.querySelectorAll('td').forEach(td => row.push(cellText(td)));
    plainRows.push(row.join('\t'));
  });
  const plainText = plainRows.join('\n');

  try {
    if(navigator.clipboard && window.ClipboardItem){
      await navigator.clipboard.write([new ClipboardItem({
        'text/html': new Blob([html], {type:'text/html'}),
        'text/plain': new Blob([plainText], {type:'text/plain'})
      })]);
    } else {
      await navigator.clipboard.writeText(plainText);
    }
    const btn = document.getElementById('btn-full-planilla');
    const prev = btn.innerText;
    btn.innerText = 'Copiado'; btn.style.background = 'var(--ok)';
    setTimeout(()=>{btn.innerText = prev; btn.style.background='';}, 1400);
  } catch(e) {
    try { await navigator.clipboard.writeText(plainText); alert('Copiado como texto plano. El navegador bloqueó el formato HTML.'); }
    catch(e2){ alert('No se pudo copiar. Usa HTTPS o localhost para permitir clipboard.'); }
  }
}

function copyDay(dayId){
  const table = document.getElementById('day-table-' + dayId);
  if(!table) return;
  const rows = [];
  table.querySelectorAll('tbody tr').forEach(tr => {
    const cells = Array.from(tr.querySelectorAll('td')).map(cellText);
    rows.push(cells.join('\t'));
  });
  navigator.clipboard.writeText(rows.join('\n')).then(()=>alert('Día copiado como texto.'));
}
</script>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('consolidate-month'); ?>
<div class="wrap">
  <h1>Booking Consolidator Multi-Day</h1>
  <p class="sub">Sube Viator CSV, GYG XLSX/XLS con varias hojas por fecha, Civitatis CSV/XLS/XLSX y, opcionalmente, Horario guías XLSX/XLS. El botón principal copia todos los días en el mismo formato, separados por una fila vacía.</p>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="consolidate">
    <div class="row">
      <div class="box"><b>Trip/Viator CSV</b><input type="file" name="viator_file" accept=".csv"><div class="note">Bookings report.</div></div>
      <div class="box"><b>Horario guías XLSX/XLS</b><input type="file" name="guide_schedule_file" accept=".xlsx,.xls"><div class="note">Opcional: agrega guías que trabajan por fecha en la columna izquierda.</div></div>
      <div class="box"><b>GetYourGuide XLSX/XLS</b><input type="file" name="gyg_file" accept=".xlsx,.xls"><div class="note">Lee todas las hojas del libro.</div></div>
      <div class="box"><b>Civitatis CSV/XLSX/XLS</b><input type="file" name="civitatis_file" accept=".csv,.xlsx,.xls"><div class="note">Soporta CSV separado por ; con Fecha Realización.</div></div>
    </div>
    <div class="actions"><button class="btn" type="submit">Consolidar multi-día</button></div>
  </form>

  <div class="card" style="border:2px solid var(--brand); margin-top:16px;">
    <b>Hoteles GYG desde Gmail</b><br>
    <div class="note">Primero presiona <b>Consolidar multi-día</b>. Luego usa este botón para completar hoteles/pickups faltantes de GetYourGuide sin perder el consolidado.</div>
    <form method="post" style="background:transparent;padding:0;margin:10px 0 0 0">
      <input type="hidden" name="action" value="lookup_gyg_hotels">
      <button class="btn" type="submit" style="background:#65d6ad;color:#07100d;">Descargar hoteles GYG desde Gmail</button>
    </form>
  </div>

  <?php if (!empty($errors)): ?><div class="err"><b>Errores:</b><br><?php foreach($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>

  <?php if (!empty($guideSchedule) && empty($errors)): ?>
    <div class="card" style="border:1px solid var(--ok);">
      <b>Horario guías cargado.</b><br>
      Fechas con guías asignados: <?php echo count($guideSchedule); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($webSummary) && empty($errors)): ?>
    <div class="card" style="border:1px solid <?php echo !empty($webSummary['connected']) ? 'var(--ok)' : 'var(--warn)'; ?>">
      <b>WEB / database enrichment</b><br>
      Fechas consultadas: <?php echo count($webSummary['dates'] ?? []); ?> · Reservas WEB agregadas: <?php echo (int)($webSummary['count'] ?? 0); ?>
      <details class="note"><summary>Debug</summary><?php foreach(($webSummary['debug'] ?? []) as $msg) echo h($msg) . '<br>'; ?></details>
    </div>
  <?php endif; ?>

  <?php if (!empty($lookupSummary) && empty($errors)): ?>
    <div class="card" style="border:1px solid var(--ok);">
      <b>GYG Gmail hotel lookup completado.</b><br>
      Revisadas: <?php echo (int)($lookupSummary['checked'] ?? 0); ?> fila(s) ·
      Hoteles actualizados: <?php echo (int)($lookupSummary['updated'] ?? 0); ?> ·
      No encontrados: <?php echo (int)($lookupSummary['not_found'] ?? 0); ?>
      <?php if (!empty($lookupSummary['message'])): ?><div class="note"><?php echo h($lookupSummary['message']); ?></div><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($consolidated) && empty($errors)): ?>
    <?php
      $grandPax = $grandReservations = $grandInf = $grandChd = 0;
      foreach ($consolidated as $r) { $grandReservations++; $grandPax += (int)$r['B_TotalPax']; $grandInf += (int)$r['H_Infants(n)']; $grandChd += (int)$r['I_Children(m)']; }
    ?>
    <div class="card">
      <h2 style="margin:0 0 8px">Objeto consolidado multi-día</h2>
      <div class="actions" style="margin:0 0 10px 0">
        <button id="btn-full-planilla" class="btn" type="button" onclick="copyFullMultiDayPlanilla()">Copiar planilla completa multi-día</button>
        <form method="post" style="display:inline;background:transparent;padding:0;margin:0">
          <input type="hidden" name="action" value="lookup_gyg_hotels">
          <button class="btn" type="submit">Descargar hoteles GYG desde Gmail</button>
        </form>
      </div>
      <div class="sub"><span class="pill">Días: <?php echo count($grouped); ?></span><span class="pill">Reservas: <?php echo $grandReservations; ?></span><span class="pill">Pax: <?php echo $grandPax; ?></span><span class="pill">Infantes: <?php echo $grandInf; ?></span><span class="pill">Niños: <?php echo $grandChd; ?></span></div>

      <?php foreach($grouped as $dateKey => $day): ?>
        <?php
          $dayId = preg_replace('/[^0-9a-z]+/i', '-', $dateKey);
          $dayPax = $dayRes = $dayInf = $dayChd = 0;
          foreach ($day['categories'] as $rows) foreach ($rows as $r) { $dayRes++; $dayPax += (int)$r['B_TotalPax']; $dayInf += (int)$r['H_Infants(n)']; $dayChd += (int)$r['I_Children(m)']; }
        ?>
        <div class="day">
          <h2 style="margin:0 0 6px"><?php echo h($day['label']); ?></h2>
          <div class="sub"><span class="pill">Reservas: <?php echo $dayRes; ?></span><span class="pill">Pax: <?php echo $dayPax; ?></span><span class="pill">Infantes: <?php echo $dayInf; ?></span><span class="pill">Niños: <?php echo $dayChd; ?></span></div>
          <?php foreach($day['categories'] as $cat => $rows): ?>
            <?php $subPax = $subInf = $subChd = 0; foreach($rows as $r){ $subPax += (int)$r['B_TotalPax']; $subInf += (int)$r['H_Infants(n)']; $subChd += (int)$r['I_Children(m)']; } ?>
            <div class="group">
              <h3><?php echo h($cat); ?></h3>
              <div class="subtot"><div>Total pax: <b><?php echo $subPax; ?></b></div><div>Reservas: <b><?php echo count($rows); ?></b></div><div>Infantes: <b><?php echo $subInf; ?></b></div><div>Niños: <b><?php echo $subChd; ?></b></div></div>
              <div style="overflow:auto"><table><thead><tr><th>Passenger</th><th>Total</th><th>Hotel / Address</th><th>Phone</th><th>Lang</th><th>Origin</th><th>Experience</th><th>Infants</th><th>Children</th><th>Email</th><th>Booking Reference</th></tr></thead><tbody>
              <?php foreach($rows as $r): ?><tr><td><?php echo h($r['A_PassengerName']); ?></td><td><?php echo (int)$r['B_TotalPax']; ?></td><td><?php echo h($r['C_Hotels']); ?></td><td><?php echo h($r['E_Phone']); ?></td><td><?php echo h($r['F_Language']); ?></td><td><?php echo h($r['G_Origin']); ?></td><td><?php echo h($r['Experience']); ?></td><td><?php echo (int)$r['H_Infants(n)']; ?></td><td><?php echo (int)$r['I_Children(m)']; ?></td><td><?php echo h($r['J_Email']); ?></td><td><?php echo h($r['K_BookingReference']); ?></td></tr><?php endforeach; ?>
              </tbody></table></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <div class="copy-planilla-hidden" aria-hidden="true">
        <table id="full-planilla-copy" class="copy-planilla-table" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:10pt;background:#ffffff;color:#000000;text-align:center!important;vertical-align:middle!important;">
          <tbody>
          <?php $dayIndex = 0; $dayCount = count($grouped); ?>
          <?php foreach($grouped as $dateKey => $day): ?>
            <?php $dayIndex++; $dayRowspan = day_visual_row_count($day['categories']); $firstDateCell = true; $guideNamesForDay = $guideSchedule[$dateKey] ?? []; $guideRowIndex = 0; ?>
            <?php foreach($day['categories'] as $cat => $rows): ?>
              <?php $catTotal = 0; foreach($rows as $r) $catTotal += (int)$r['B_TotalPax']; $catRowspan = count($rows) + 1; $firstCatCell = true; $catLabel = planilla_category_label($cat); ?>
              <?php foreach($rows as $r): ?>
                <tr>
                  <td class="copy-planilla-guide" style="border:none!important;background:#fff;text-align:left!important;vertical-align:middle!important;width:95px;padding:4px 6px;font-size:10pt;mso-border-alt:none!important;"><?php echo h($guideNamesForDay[$guideRowIndex++] ?? ''); ?></td>
                  <?php if($firstDateCell): ?><td rowspan="<?php echo (int)$dayRowspan; ?>" style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:90px;white-space:normal;padding:4px 6px;background:#fff;font-size:10pt;"><?php echo h($day['label']); ?></td><?php $firstDateCell = false; endif; ?>
                  <?php if($firstCatCell): ?><td rowspan="<?php echo (int)$catRowspan; ?>" style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:260px;white-space:normal!important;word-wrap:break-word;overflow-wrap:break-word;padding:4px 6px;font-size:10pt;"><?php echo h($catLabel); ?></td><?php $firstCatCell = false; endif; ?>
                  <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:190px;padding:4px 6px;font-size:10pt;"><?php echo h($r['A_PassengerName']); ?></td>
                  <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:55px;padding:4px 6px;font-size:10pt;"><?php echo (int)$r['B_TotalPax']; ?></td>
                  <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:290px;padding:4px 6px;font-size:10pt;white-space:normal!important;"><?php echo h($r['C_Hotels']); ?></td>
                  <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:35px;padding:4px 6px;font-size:10pt;"></td>
                  <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:140px;padding:4px 6px;font-size:10pt;mso-number-format:'\@';"><?php echo h($r['E_Phone']); ?></td>
                  <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:60px;padding:4px 6px;font-size:10pt;"><?php echo h($r['F_Language']); ?></td>
                  <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:70px;padding:4px 6px;font-size:10pt;"><?php echo h($r['G_Origin']); ?></td>
                  <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:260px;padding:4px 6px;font-size:10pt;white-space:normal!important;"><?php echo h($r['J_Email']); ?></td>
                  <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;width:160px;padding:4px 6px;font-size:10pt;mso-number-format:'\@';"><?php echo h($r['K_BookingReference']); ?></td>
                </tr>
              <?php endforeach; ?>
              <tr class="copy-planilla-total">
                <td class="copy-planilla-guide" style="border:none!important;background:#fff;text-align:left!important;vertical-align:middle!important;width:95px;padding:4px 6px;font-size:10pt;mso-border-alt:none!important;"><?php echo h($guideNamesForDay[$guideRowIndex++] ?? ''); ?></td>
                <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;background:#eee;font-weight:bold;padding:4px 6px;font-size:10pt;"></td>
                <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;background:#eee;font-weight:bold;padding:4px 6px;font-size:10pt;"><?php echo (int)$catTotal; ?></td>
                <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;background:#eee;font-weight:bold;padding:4px 6px;font-size:10pt;"></td>
                <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;background:#eee;font-weight:bold;padding:4px 6px;font-size:10pt;"></td>
                <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;background:#eee;font-weight:bold;padding:4px 6px;font-size:10pt;"></td>
                <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;background:#eee;font-weight:bold;padding:4px 6px;font-size:10pt;"></td>
                <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;background:#eee;font-weight:bold;padding:4px 6px;font-size:10pt;"></td>
                <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;background:#eee;font-weight:bold;padding:4px 6px;font-size:10pt;"></td>
                <td style="border:1px solid #000;text-align:center!important;vertical-align:middle!important;background:#eee;font-weight:bold;padding:4px 6px;font-size:10pt;"></td>
              </tr>
            <?php endforeach; ?>
            <?php if($dayIndex < $dayCount): ?>
              <tr class="copy-planilla-spacer">
                <?php for($i = 0; $i < 12; $i++): ?><td style="border:none!important;background:#ffffff!important;height:26px!important;padding:0!important;mso-border-alt:none!important;"></td><?php endfor; ?>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
