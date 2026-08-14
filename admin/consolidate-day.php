<?php
declare(strict_types=1);
/**
 * Booking Consolidator — Viator CSV + (optional) GYG XLSX + (optional) Civitatis XLS + (optional) Viator Passenger List XLSX
 *
 * - Trip/Viator: CSV ONLY (bookings report)
 *      * Phone from "Lead traveler Contact Info" (digits only)
 *      * Email from "Lead traveler Email"/"Email" or extracted from "Lead traveler Contact Info"
 * - GYG: XLSX (bookings-export)
 * - Civitatis: XLS/XLSX
 *      * Header row contains tour/date/time
 *      * A Reserva, B Nombre, C Hotel/Info, D Adults, E Children, F Infants
 *      * Origin CIV and language SPA
 * - Viator Passenger List: XLSX/XLS
 *      * Name in column H ("Last, First")
 *      * Phone in column I (digits expected; we will clean to digits)
 *      * Email in column J
 *
 * Categories:
 * - Viator product codes (CSV) and GYG product IDs are primary category hints.
 * - GYG column G tags [VLP]/[MPO]/[PRT]/[default] are secondary hints.
 * - Fallback keyword-based categorization remains as last resort.
 *
 * Emails:
 * - TRIP (Viator):
 *      1) First from Viator CSV (Lead traveler Email / Email / contact info)
 *      2) If Passenger List is provided, it OVERRIDES phone and email using
 *         parse_passenger_list_lookup() + name-normalized matching.
 * - GET (GYG):
 *      From Email header or column L.
 *
 * Bottom UI:
 * - Lists Passenger List names that did not match any Viator CSV row
 *   (after normalization).
 */

require __DIR__ . '/_auth.php';

/* ---------- Autoload (Composer) ---------- */
$autoload_candidates = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/phpspreadsheet_lib/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__) . '/phpspreadsheet_lib/vendor/autoload.php',
];

foreach ($autoload_candidates as $a) {
    if (is_file($a)) {
        require_once $a;
    }
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
    $dbNameResult = $conn->query('SELECT DATABASE() AS db_name');
    $dbNameRow = $dbNameResult ? $dbNameResult->fetch_assoc() : null;
    $webDbDebug[] = 'WEB/BD OK: conexión activa. Base actual: ' . ($dbNameRow['db_name'] ?? 'desconocida');
} else {
    $dbError = 'No se pudo conectar a la BD interna. Revisa que ../db_config.php exista y cree $conn.';
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_error) {
        $dbError .= ' MySQL: ' . $conn->connect_error;
    }
    $webDbDebug[] = 'WEB/BD INFO: ' . $dbError;
}

/* ---------- GYG Gmail hotel lookup via Google Apps Script Web App ---------- */
/*
 * 1) Deploy the Apps Script provided with this file as a Web App.
 * 2) Paste the /exec URL below.
 * 3) Use the same token in PHP and Apps Script.
 */
const GYG_GMAIL_LOOKUP_WEBAPP_URL = 'https://script.google.com/macros/s/AKfycbyM5cNfuThQt0gpqLO1rWfAgudzbIXCMGb039ZIosUxhmAcDLHpzisvMz4TAIRVBof7/exec'; // Example: https://script.google.com/macros/s/XXXX/exec
const GYG_GMAIL_LOOKUP_TOKEN = 'D0ctorC4t';


/* ---------- Config & Helpers ---------- */
/* Column order (Origin before Experience) */
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
    'J_Email',     // Email
    'K_BookingReference', // Booking Reference / Booking Ref No. appended as last column
    'TravelDate', // Internal only: source travel date used for copied planilla first column
];

const CATEGORY_ORDER = [
    'Valparaíso & Viña del Mar' => 1,
    'PVT Valparaíso & Viña del Mar' => 2,
    'Discover Santiago'         => 3,
    'PVT Discover Santiago'     => 4,
    'Maipo Valley'              => 5,
    'PVT Maipo Valley'          => 6,
    'Maipo Valley Tastings & Lunch' => 7,
    'PVT Maipo Valley Tastings & Lunch' => 8,
    'Portillo / Inca Lagoon'    => 9,
    'PVT Portillo / Inca Lagoon'=> 10,
    'San Antonio after Cruise tour to Valparaiso and Casablanca Drop off in Santiago' => 11,
    'Valparaiso after Cruise tour to Valparaiso and Casablanca Drop off in Santiago'  => 12,
    'Transfer to Valparaiso Cruise Terminal with Winery & Town Tour'                  => 13,
    'Transfer to San Antonio port prior cruise with tour in Valparaiso and Casablanca'=> 14,
    'GYG Unmapped Product'      => 98,
    'Uncategorized'             => 99,
];

/* Keep only digits (concatenate parts) */
function clean_phone(?string $s): string {
    if ($s === null) return '';
    $digits = preg_replace('/\D+/', '', $s);
    return $digits ?? '';
}

function lang_code(?string $v): string {
    if ($v === null) return '';
    $v = mb_strtolower(trim($v));
    if (strpos($v, 'span') !== false) return 'SPA';
    if (strpos($v, 'portu') !== false || strpos($v, 'brazil') !== false || strpos($v, 'brasil') !== false || strpos($v, 'port ') !== false) return 'BRA';
    if (strpos($v, 'eng') !== false) return 'ENG';
    return 'ENG';
}

function choose_hotel_viator(array $row): string {
    foreach (['Hotel Pickup','Pickup Details','Meeting Point Details'] as $k) {
        if (!empty($row[$k])) {
            $v = trim((string)$row[$k]);
            if ($v !== '') return $v;
        }
    }
    return '';
}

function normalize_planilla_date($value): string {
    if ($value instanceof \DateTimeInterface) {
        return $value->format('d/m/Y');
    }

    $value = trim((string)$value);
    if ($value === '') return '';

    // Excel serial date, common when dates come from XLSX.
    if (is_numeric($value)) {
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value);
            return $dt->format('d/m/Y');
        } catch (Throwable $e) {
            return '';
        }
    }

    // Already in dd/mm/yyyy or dd-mm-yyyy.
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $value, $m)) {
        $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $year = strlen($m[3]) === 2 ? ('20' . $m[3]) : $m[3];
        return $day . '/' . $month . '/' . $year;
    }

    // ISO-like or English text date fallback.
    $ts = strtotime($value);
    if ($ts !== false) {
        return date('d/m/Y', $ts);
    }

    return $value;
}

function get_planilla_date_from_rows(array $rows): string {
    foreach ($rows as $r) {
        $date = normalize_planilla_date($r['TravelDate'] ?? '');
        if ($date !== '') return $date;
    }

    // Fallback only if no source travel date was found.
    return date('d/m/Y');
}

function planilla_date_to_mysql(?string $value): string {
    $normalized = normalize_planilla_date($value ?? '');
    if ($normalized === '') return '';

    $dt = DateTime::createFromFormat('d/m/Y', $normalized);
    if ($dt instanceof DateTime) {
        return $dt->format('Y-m-d');
    }

    $ts = strtotime((string)$value);
    return $ts !== false ? date('Y-m-d', $ts) : '';
}

function web_db_date_to_planilla(?string $value): string {
    $value = trim((string)($value ?? ''));
    if ($value === '') return '';
    $dt = DateTime::createFromFormat('Y-m-d', substr($value, 0, 10));
    if ($dt instanceof DateTime) return $dt->format('d/m/Y');
    return normalize_planilla_date($value);
}


function parse_civitatis_header_info(?string $headerText): array {
    $headerText = trim((string)($headerText ?? ''));
    $date = '';
    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $headerText, $m)) {
        $date = web_db_date_to_planilla($m[1]);
    }

    $experience = $headerText;
    if ($headerText !== '') {
        // Civitatis header usually is: Tour name, Tour language,YYYY-MM-DD, HH:MMhoras
        $parts = array_map('trim', explode(',', $headerText));
        $experience = $parts[0] ?? $headerText;
    }

    return [
        'experience' => $experience,
        'date' => $date,
        'category' => categorize_experience_fallback($experience),
    ];
}

function clean_civitatis_hotel(?string $value): string {
    $value = trim((string)($value ?? ''));
    if ($value === '') return '';
    $value = preg_replace('/^\s*:\s*/', '', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim((string)$value);
}

/* Fallback keyword categorizer — used when no explicit code-based or tag-based hint is found */
function categorize_experience_fallback(?string $name): string {
    if ($name === null) return 'Uncategorized';
    $n = mb_strtolower($name);
    if (preg_match('/valpa|valpo|viñ|viña|vina|vin(a|ã)|vi(n|ñ)amar|v(i|í)na del mar/u', $n)) return 'Valparaíso & Viña del Mar';
    if (preg_match('/\bmaipo\b/u', $n)) return 'Maipo Valley';
    if (preg_match('/lagun|lagoon|inca|portillo|andes\s*range/u', $n)) return 'Portillo / Inca Lagoon';
    if (preg_match('/discover\s+santiago|city\s*tour.*santiago|santiago.*city\s*tour|city\s*tour$/u', $n)) return 'Discover Santiago';
    return 'Uncategorized';
}

/* GYG category from column G prefix (legacy tags + new PVT tags) */
function categorize_from_gyg_col_g(?string $gCell): ?string {
    if ($gCell === null) return null;
    $s = trim((string)$gCell);
    if ($s === '') return null;

    // Private options used in GYG option column, e.g. PVT VLP / [PVT VLP].
    // This returns the parent category; consolidate_rows() adds the PVT prefix.
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

function is_private_viator_product_grade(?string $productCode, ?string $tourGrade): bool {
    $code = strtoupper(trim((string)($productCode ?? '')));
    $grade = strtoupper(trim((string)($tourGrade ?? '')));

    // Viator private tour-grade logic:
    // Valparaíso = TG3
    // City Tour  = TG1
    // Portillo   = TG1
    // Maipo      = TG2
    if ($code === '20268P5') {
        return preg_match('/^TG3(?:\b|~)/i', $grade) === 1;
    }

    if ($code === '20268P8') {
        return preg_match('/^TG1(?:\b|~)/i', $grade) === 1;
    }

    if ($code === '20268P12') {
        return preg_match('/^TG1(?:\b|~)/i', $grade) === 1;
    }

    if ($code === '20268P25') {
        return preg_match('/^TG2(?:\b|~)/i', $grade) === 1;
    }

    return false;
}

function apply_private_category(?string $baseCategory, bool $isPrivate): string {
    $baseCategory = $baseCategory ?: 'Uncategorized';
    if ($isPrivate && in_array($baseCategory, [
        'Valparaíso & Viña del Mar',
        'Maipo Valley',
        'Maipo Valley Tastings & Lunch',
        'Portillo / Inca Lagoon',
        'Discover Santiago',
    ], true)) {
        return 'PVT ' . $baseCategory;
    }
    return $baseCategory;
}

/**
 * Normalization for matching (Passenger List + Viator CSV):
 * - Trims
 * - Converts "Last, First" -> "First Last"
 * - Removes punctuation , . ;
 * - Collapses spaces
 * - Removes accents via iconv
 * - Lowercase
 */
function normalize_for_match(string $name): string {
    $name = trim($name);
    if ($name === '') return '';
    // "Last, First" → "First Last"
    if (strpos($name, ',') !== false) {
        [$last, $first] = array_map('trim', explode(',', $name, 2));
        $name = $first . ' ' . $last;
    }
    // Remove punctuation and collapse spaces
    $name = preg_replace('/[,\.;]+/', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    // Remove accents
    $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if ($trans !== false) $name = $trans;
    $name = mb_strtolower(trim($name));
    return $name;
}

/**
 * Viator category hint from PRODUCT CODE (primary) with low-cost flag.
 */
function categorize_from_viator_product(?string $prodCode, ?string $prodName, ?bool &$isLowCost = null): ?string {
    $isLowCost = false;
    $code = strtoupper(trim((string)$prodCode));
    if ($code !== '') {
        switch ($code) {
            case '20268P5':
                return 'Valparaíso & Viña del Mar';
            case '20268P32':
                $isLowCost = true;
                return 'Valparaíso & Viña del Mar';
            case '20268P25':
                return 'Maipo Valley';
            case '20268P15':
                return 'Maipo Valley Tastings & Lunch';
            case '20268P12':
                return 'Portillo / Inca Lagoon';
            case '20268P8':
                return 'Discover Santiago';
            case '20268P29':
                return 'San Antonio after Cruise tour to Valparaiso and Casablanca Drop off in Santiago';
            case '20268P26':
                return 'Valparaiso after Cruise tour to Valparaiso and Casablanca Drop off in Santiago';
            case '20268P24':
                return 'Transfer to Valparaiso Cruise Terminal with Winery & Town Tour';
            case '20268P23':
                return 'Transfer to San Antonio port prior cruise with tour in Valparaiso and Casablanca';
        }
    }
    return null;
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

/**
 * GYG category hint from PRODUCT ID (column F) with low-cost flag.
 *
 * Product id can appear with extra text; we strip to first digits.
 */
function extract_gyg_product_id(?string $prodIdRaw): string {
    $prodIdRaw = trim((string)($prodIdRaw ?? ''));
    if ($prodIdRaw === '') return '';

    // GYG product cells can look like:
    // "273397 [VLP 1st] Santiago: Valparaiso..."
    // The old logic removed all non-digits and produced "2733971" because of "1st".
    // This captures only the first real product/activity ID.
    if (preg_match('/\b(\d{5,})\b/', $prodIdRaw, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * GYG category hint from PRODUCT ID (column F) with low-cost flag.
 * Column F / Product is the primary unique identifier for GYG products.
 */
function categorize_from_gyg_product_id(?string $prodIdRaw, ?string $gTitle, ?bool &$isLowCost = null): ?string {
    $isLowCost = false;
    $digits = extract_gyg_product_id($prodIdRaw);
    if ($digits === '') return null;

    switch ($digits) {
        case '878148': // Valpo / Viña low-cost variant
            $isLowCost = true;
            return 'Valparaíso & Viña del Mar';
        case '273397': // regular Valparaíso, Viña del Mar & Casablanca
            return 'Valparaíso & Viña del Mar';
        case '288540': // Maipo 4 vineyards
            return 'Maipo Valley';
        case '1333337': // NEW: Maipo with tastings & lunch; separate product, separate label
            return 'Maipo Valley Tastings & Lunch';
        case '277632': // Portillo / Inca Lagoon
            return 'Portillo / Inca Lagoon';
        case '765423': // Santiago city tour
            return 'Discover Santiago';
        default:
            return null;
    }
}
function ensure_cols(array $row): array {
    foreach (EXPECTED_COLS as $c) {
        if (!array_key_exists($c, $row)) {
            $row[$c] = in_array($c, ['B_TotalPax','H_Infants(n)','I_Children(m)'], true) ? 0 : '';
        }
    }
    $ordered = [];
    foreach (EXPECTED_COLS as $c) $ordered[$c] = $row[$c];
    if (isset($row['CategoryHint'])) $ordered['CategoryHint'] = $row['CategoryHint'];
    if (isset($row['isPrivate'])) $ordered['isPrivate'] = (bool)$row['isPrivate'];
    return $ordered;
}

/* ---------- Parsers ---------- */
// Trip/Viator CSV (CSV ONLY)
function parse_viator_csv(string $tmpPath): array {
    $rows = [];
    if (!is_readable($tmpPath)) return $rows;
    $fh = fopen($tmpPath, 'r');
    if (!$fh) return $rows;

    $header = fgetcsv($fh);
    if (!$header) {
        fclose($fh);
        return $rows;
    }
    $map = [];
    foreach ($header as $i => $h) {
        $map[trim((string)$h)] = $i;
    }
    $get = fn(array $arr, string $key) => isset($map[$key]) && isset($arr[$map[$key]]) ? $arr[$map[$key]] : null;

    while (($line = fgetcsv($fh)) !== false) {
        $status = (string)($get($line, 'Status') ?? '');
        if (!in_array($status, ['Confirmed','Amended'], true)) continue;

        $bookingRef = trim((string)($get($line, 'Booking Reference') ?? ''));
        // Viator bookings report: Travel Date is column F in the CSV export.
        $travelDate = (string)(
            $get($line, 'Travel Date') ??
            $get($line, 'Travel date') ??
            $get($line, 'Date') ??
            ''
        );
        $experience = (string)($get($line, 'Product Name') ?? '');
        $name       = (string)($get($line, 'Lead traveler Name') ?? '');
        $pax        = (int)($get($line, 'Number of Passengers') ?? 0);
        $inf        = (int)($get($line, 'Infants') ?? 0);
        $chd        = (int)($get($line, 'Children') ?? 0);
        $langRaw    = $get($line, 'Tour Language') ?? $get($line, 'Language') ?? '';

        // Contact info may contain phone + email, we will still use it for phone extraction
        $contactInfo = (string)($get($line, 'Lead traveler Contact Info') ?? '');
        $phone       = clean_phone($contactInfo);

        // Product code is in column O of the Viator CSV exports; header typically "Product Code"
        $prodCode   = (string)(
            $get($line, 'Product Code') ??
            $get($line, 'Product code') ??
            $get($line, 'Product ID') ??
            ''
        );
        $tourGrade = (string)(
            $get($line, 'Tour Grade Code') ??
            $get($line, 'Tour Grade') ??
            ''
        );
        $tourGradeTitle = (string)($get($line, 'Tour Grade Title') ?? '');

        $hotel = choose_hotel_viator([
            'Hotel Pickup'         => $get($line, 'Hotel Pickup'),
            'Pickup Details'       => $get($line, 'Pickup Details'),
            'Meeting Point Details'=> $get($line, 'Meeting Point Details'),
        ]);

        // Viator-specific category hint from product code
        $isLowCost = false;
        $catHint   = categorize_from_viator_product($prodCode, $experience, $isLowCost);
        if (is_viator_maipo_tastings_lunch_grade($prodCode, $tourGrade, $tourGradeTitle)) {
            $catHint = 'Maipo Valley Tastings & Lunch';
        }
        $isPrivate = is_private_viator_product_grade($prodCode, $tourGrade);
        if ($isLowCost) {
            $experience = rtrim($experience) . ' (Low Cost)';
        }

        // Email extraction for Viator CSV
        $email = '';
        $emailRaw = $get($line, 'Lead traveler Email')
                 ?? $get($line, 'Lead Traveler Email')
                 ?? $get($line, 'Lead traveler email')
                 ?? $get($line, 'Email')
                 ?? null;

        if ($emailRaw !== null && trim((string)$emailRaw) !== '') {
            $email = trim((string)$emailRaw);
        } elseif ($contactInfo !== '') {
            // Fallback: look for an email pattern inside contact info
            if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $contactInfo, $m)) {
                $email = $m[0];
            }
        }

        $rows[] = ensure_cols([
            'G_Origin'        => 'TRIP',
            'A_PassengerName' => $name,
            'B_TotalPax'      => $pax,
            'C_Hotels'        => $hotel,
            'D_Empty'         => '',
            'E_Phone'         => $phone,              // digits only
            'F_Language'      => lang_code((string)$langRaw),
            'Experience'      => trim($experience),
            'H_Infants(n)'    => $inf,
            'I_Children(m)'   => $chd,
            'J_Email'         => $email,              // may be overridden by passenger list
            'K_BookingReference' => $bookingRef,       // Viator column A: Booking Reference
            'TravelDate'      => $travelDate,      // Viator column F: Travel Date
            'CategoryHint'    => $catHint,
            'isPrivate'       => $isPrivate,
        ]);
    }

    fclose($fh);
    usort($rows, fn($a,$b)=>[$a['Experience'],$a['A_PassengerName']] <=> [$b['Experience'],$b['A_PassengerName']]);
    return $rows;
}

// GYG XLSX — category from product id (col F) and/or column G tag + EMAIL extraction
function parse_gyg_xlsx(string $tmpPath): array {
    if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) return [];
    $rows = [];
    $sheet = IOFactory::load($tmpPath)->getSheet(0);
    $data  = $sheet->toArray(null, true, true, true); // A,B,C,... keys
    if (count($data) < 2) return $rows;

    $header = array_shift($data);
    $map = [];
    foreach ($header as $col => $name) {
        $map[trim((string)$name)] = $col;
    }
    $get = fn(array $row, string $key) => isset($map[$key]) ? ($row[$map[$key]] ?? null) : null;

    $paxCols = [
        'Adult','Senior','Student (with ID)',
        'EU Citizens (with ID)','EU citizens (with ID)',
        'Student EU Citizens (with ID)','Student EU citizens (with ID)',
        'Military (with ID)','Youth','Child','Infant'
    ];

    foreach ($data as $row) {
        $bookingRef = trim((string)(
            $get($row, 'Booking Ref No.') ??
            $get($row, 'Booking Ref No') ??
            $get($row, 'Booking Ref #') ??
            $get($row, 'Booking Reference') ??
            ($row['D'] ?? '')
        ));
        // GYG export: Date is column A.
        $travelDate = $get($row, 'Date') ?? ($row['A'] ?? '');
        $titleFromG = (string)($row['G'] ?? ''); // “[VLP] …” legacy tag / title
        $prodIdRaw  = (string)($row['F'] ?? ''); // Column F: product id / activity id
        $isPrivate = is_private_gyg_option($titleFromG);

        $isLowCost = false;
        // Primary: map via GYG Product ID in column F.
        $gygProductId = extract_gyg_product_id($prodIdRaw);
        $catHint = categorize_from_gyg_product_id($prodIdRaw, $titleFromG, $isLowCost);

        // If column F contains an unknown product ID, do NOT let semantic text or [MPO]/[VLP] tags
        // merge it into an existing tour. Put it into a review bucket instead.
        if ($catHint === null && $gygProductId !== '') {
            $catHint = 'GYG Unmapped Product';
        }

        // Legacy fallback only when no product ID exists in column F.
        if ($catHint === null) {
            $catHint = categorize_from_gyg_col_g($titleFromG);
        }

        if ($catHint === null) {
            $catHint = 'GYG Unmapped Product';
        }

        $prod  = (string)($get($row,'Product') ?? $titleFromG);
        if ($isLowCost) {
            $prod = rtrim($prod) . ' (Low Cost)';
        }

        // GYG changed several English headers in newer exports:
        // Traveller -> Traveler, Surname -> Last Name.
        // Keep the old headers as first options for backward compatibility.
        $first = (string)(
            $get($row, "Traveller's First Name") ??
            $get($row, "Traveler's First Name") ??
            ''
        );
        $last  = (string)(
            $get($row, "Traveller's Surname") ??
            $get($row, "Traveller's Last Name") ??
            $get($row, "Traveler's Surname") ??
            $get($row, "Traveler's Last Name") ??
            ''
        );
        $name  = trim($first . ' ' . $last);
        $phone = clean_phone((string)($get($row, 'Phone') ?? ''));
        $lang  = lang_code((string)($get($row, 'Language') ?? ''));

        // Email for GYG
        $email = '';
        $emailFromHeader = $get($row, 'Email');
        if ($emailFromHeader !== null && trim((string)$emailFromHeader) !== '') {
            $email = trim((string)$emailFromHeader);
        } else {
            // fallback: column L if present
            $email = trim((string)($row['L'] ?? ''));
        }

        $tot = 0;
        foreach ($paxCols as $pc) {
            $tot += (int)($get($row, $pc) ?? 0);
        }
        // Some private GYG exports use a Group field instead of the standard pax columns.
        if ($isPrivate) {
            $groupRaw = $get($row, 'Group') ?? ($row['X'] ?? null);
            $groupPax = (int)(preg_replace('/\D+/', '', (string)($groupRaw ?? '')) ?: 0);
            if ($groupPax > 0) $tot = $groupPax;
        }
        $inf = (int)($get($row, 'Infant') ?? 0);
        $chd = (int)($get($row, 'Child') ?? 0);

        $rows[] = ensure_cols([
            'G_Origin'        => 'GET',
            'A_PassengerName' => $name,
            'B_TotalPax'      => $tot,
            'C_Hotels'        => '',
            'D_Empty'         => '',
            'E_Phone'         => $phone,             // digits only
            'F_Language'      => $lang,
            'Experience'      => trim($prod),
            'H_Infants(n)'    => $inf,
            'I_Children(m)'   => $chd,
            'J_Email'         => $email,             // GYG email
            'K_BookingReference' => $bookingRef,      // GYG column D: Booking Ref No.
            'TravelDate'      => $travelDate,      // GYG column A: Date
            'CategoryHint'    => $catHint,
            'isPrivate'       => $isPrivate,
        ]);
    }

    usort($rows, fn($a,$b)=>[$a['Experience'],$a['A_PassengerName']] <=> [$b['Experience'],$b['A_PassengerName']]);
    return $rows;
}


// Civitatis XLS/XLSX — multiple blocks allowed. Origin CIV, language SPA.
function parse_civitatis_xls(string $tmpPath): array {
    if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) return [];
    if (!is_readable($tmpPath)) return [];

    $rows = [];
    $sheet = IOFactory::load($tmpPath)->getSheet(0);
    $data = $sheet->toArray(null, true, true, true); // A,B,C...

    $currentExperience = '';
    $currentDate = '';
    $currentCategory = null;
    $insideTable = false;

    foreach ($data as $row) {
        $a = trim((string)($row['A'] ?? ''));
        $b = trim((string)($row['B'] ?? ''));
        $c = trim((string)($row['C'] ?? ''));

        // Header/title row: usually the whole tour title/date is in column A.
        if ($a !== '' && $b === '' && $c === '' && stripos($a, 'Reserva') === false && strtoupper($a) !== 'TOTAL') {
            $info = parse_civitatis_header_info($a);
            $currentExperience = $info['experience'];
            $currentDate = $info['date'];
            $currentCategory = $info['category'];
            $insideTable = false;
            continue;
        }

        // Table header row.
        if (mb_strtolower($a) === 'reserva') {
            $insideTable = true;
            continue;
        }

        if (!$insideTable) continue;
        if ($a === '') continue;
        if (mb_strtoupper($a) === 'TOTAL') {
            $insideTable = false;
            continue;
        }

        $bookingRef = $a;
        $name = $b;
        $hotel = clean_civitatis_hotel($c);
        $adults = (int)($row['D'] ?? 0);
        $children = (int)($row['E'] ?? 0);
        $infants = (int)($row['F'] ?? 0);
        $pax = $adults + $children + $infants;

        if ($bookingRef === '' && $name === '') continue;

        $rows[] = ensure_cols([
            'G_Origin'        => 'CIV',
            'A_PassengerName' => $name,
            'B_TotalPax'      => $pax,
            'C_Hotels'        => $hotel,
            'D_Empty'         => '',
            'E_Phone'         => '',
            'F_Language'      => 'SPA',
            'Experience'      => $currentExperience,
            'H_Infants(n)'    => $infants,
            'I_Children(m)'   => $children,
            'J_Email'         => '',
            'K_BookingReference' => $bookingRef,
            'TravelDate'      => $currentDate,
            'CategoryHint'    => $currentCategory ?: categorize_experience_fallback($currentExperience),
            'isPrivate'       => false,
        ]);
    }

    usort($rows, fn($a,$b)=>[$a['Experience'],$a['A_PassengerName']] <=> [$b['Experience'],$b['A_PassengerName']]);
    return $rows;
}

/* Passenger List XLSX/XLS — NO produces rows; only returns lookup name->(phone,email,orig)
 * H => Name ("Last, First")
 * I => Phone (to digits)
 * J => Email
 */
function parse_passenger_list_lookup(string $tmpPath): array {
    if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) return [];
    if (!is_readable($tmpPath)) return [];

    $sheet = IOFactory::load($tmpPath)->getSheet(0);
    $data  = $sheet->toArray(null, true, true, true); // keys A,B,C...

    $lookup = []; // key: normalized name -> ['phone'=>digits, 'email'=>string, 'orig'=>string]
    foreach ($data as $row) {
        $rawName = (string)($row['H'] ?? '');               // H => Name ("Last, First")
        $phone   = clean_phone((string)($row['I'] ?? ''));  // I => Phone
        $email   = trim((string)($row['J'] ?? ''));         // J => Email

        if (trim($rawName) === '' && trim($phone) === '' && trim($email) === '') {
            continue;
        }

        $matchKey = normalize_for_match($rawName);          // "Last, First" -> "first last"
        if ($matchKey !== '') {
            $lookup[$matchKey] = [
                'phone' => $phone,
                'email' => $email,
                'orig'  => $rawName,
            ];
        }
    }
    return $lookup;
}

/* ---------- Consolidate + Categorize ---------- */
function consolidate_rows(array $frames): array {
    foreach ($frames as &$r) {
        $r = ensure_cols($r);
    }
    unset($r);

    foreach ($frames as &$r) {
        if (!empty($r['CategoryHint'])) {
            $baseCategory = $r['CategoryHint'];
        } else {
            $baseCategory = categorize_experience_fallback($r['Experience']);
        }
        $r['Category'] = apply_private_category($baseCategory, !empty($r['isPrivate']));
        unset($r['CategoryHint'], $r['isPrivate']);
    }
    unset($r);

    usort($frames, function($a,$b){
        $oa = CATEGORY_ORDER[$a['Category']] ?? 99;
        $ob = CATEGORY_ORDER[$b['Category']] ?? 99;
        return [$oa, $a['Experience'], $a['A_PassengerName']]
             <=> [$ob, $b['Experience'], $b['A_PassengerName']];
    });
    return $frames;
}

/* ---------- Phone + Email completion pass (Viator only) via Passenger List lookup ---------- */
function enrich_viator_with_passenger_lookup(array &$viatorRows, array $lookup, array &$unmatchedOut): void {
    // Copy lookup to track unmatched keys
    $unmatched = $lookup;

    foreach ($viatorRows as &$row) {
        if (($row['G_Origin'] ?? '') !== 'TRIP') continue;

        $name = (string)($row['A_PassengerName'] ?? '');
        $key  = normalize_for_match($name);
        if ($key !== '' && isset($lookup[$key])) {
            $info = $lookup[$key];
            if (!empty($info['phone'])) {
                $row['E_Phone'] = $info['phone'];   // override phone with Passenger List
            }
            if (!empty($info['email'])) {
                $row['J_Email'] = $info['email'];   // override/add email with Passenger List
            }
            unset($unmatched[$key]);
        } else {
            if (!isset($row['J_Email'])) {
                $row['J_Email'] = '';
            }
        }
    }
    unset($row);

    // Remaining unmatched passenger list names (for UI)
    $unmatchedOut = [];
    foreach ($unmatched as $key => $info) {
        $unmatchedOut[] = $info['orig'] ?? $key;
    }
}


/* ---------- WEB reservations from internal database ---------- */
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

function fetch_web_reservations_for_date(mysqli $conn, string $mysqlDate, array &$webDbDebug = []): array {
    $rows = [];
    if ($mysqlDate === '') {
        $webDbDebug[] = 'WEB/BD: sin fecha válida para consultar reservas web.';
        return $rows;
    }

    $sql = "
        SELECT
            r.id_reserva,
            r.reference_id,
            r.fecha_reserva,
            r.fecha_actividad,
            r.adultos,
            r.ninos,
            r.infantes,
            r.airport_pickup,
            r.id_experiencia,
            r.estado,
            r.total_venta,
            r.id_hotel,
            r.hotel_manual,
            r.id_titular,
            r.id_cupon,
            r.id_vendedor,
            r.id_guia,
            r.id_conductor,
            r.pais_origen,
            r.idioma_actividad,
            e.nombre AS experiencia_nombre,
            e.nombre_publico AS experiencia_nombre_publico,
            t.nombre AS titular_nombre,
            t.apellido AS titular_apellido,
            t.area_code AS titular_area_code,
            t.telefono AS titular_telefono,
            t.email AS titular_email,
            h.nombre_hotel,
            h.direccion,
            h.comuna
        FROM reservas r
        LEFT JOIN experiencias e ON e.id_experiencia = r.id_experiencia
        LEFT JOIN titulares t ON t.id_titular = r.id_titular
        LEFT JOIN hoteles h ON h.id_hotel = r.id_hotel
        WHERE r.fecha_actividad = ?
          AND TRIM(COALESCE(r.estado, '')) = 'realizado'
        ORDER BY COALESCE(e.nombre_publico, e.nombre), t.apellido, t.nombre, r.id_reserva
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $webDbDebug[] = 'WEB/BD ERROR prepare fecha_actividad: ' . $conn->error;
        return $rows;
    }

    $stmt->bind_param('s', $mysqlDate);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $experience = trim((string)(($row['experiencia_nombre_publico'] ?? '') ?: ($row['experiencia_nombre'] ?? '')));
        $name = trim((string)(($row['titular_nombre'] ?? '') . ' ' . ($row['titular_apellido'] ?? '')));
        $adults = (int)($row['adultos'] ?? 0);
        $children = (int)($row['ninos'] ?? 0);
        $infants = (int)($row['infantes'] ?? 0);
        $pax = $adults + $children + $infants;
        $phone = clean_phone((string)(($row['titular_area_code'] ?? '') . ($row['titular_telefono'] ?? '')));

        $rows[] = ensure_cols([
            'G_Origin'        => 'WEB',
            'A_PassengerName' => $name,
            'B_TotalPax'      => $pax,
            'C_Hotels'        => build_web_hotel_text($row),
            'D_Empty'         => '',
            'E_Phone'         => $phone,
            'F_Language'      => lang_code((string)($row['idioma_actividad'] ?? '')),
            'Experience'      => $experience,
            'H_Infants(n)'    => $infants,
            'I_Children(m)'   => $children,
            'J_Email'         => trim((string)($row['titular_email'] ?? '')),
            'K_BookingReference' => trim((string)($row['reference_id'] ?? '')),
            'TravelDate'      => web_db_date_to_planilla((string)($row['fecha_actividad'] ?? '')),
            'CategoryHint'    => categorize_experience_fallback($experience),
            'isPrivate'       => is_private_web_db_row($row),
        ]);
    }

    $stmt->close();
    $webDbDebug[] = 'WEB/BD: ' . count($rows) . ' reserva(s) web realizada(s) encontrada(s) para fecha_actividad=' . $mysqlDate . ' (solo estado=realizado).';
    return $rows;
}

/* ---------- GYG Gmail hotel lookup bridge ---------- */
function build_gyg_hotel_lookup_planilla(array $rows): array {
    $planilla = [];
    foreach ($rows as $idx => $row) {
        $origin = strtoupper(trim((string)($row['G_Origin'] ?? '')));
        $hotel = trim((string)($row['C_Hotels'] ?? ''));
        $bookingRef = trim((string)($row['K_BookingReference'] ?? ''));
        $email = trim((string)($row['J_Email'] ?? ''));
        $passenger = trim((string)($row['A_PassengerName'] ?? ''));

        if ($origin !== 'GET') continue;
        if ($hotel !== '') continue;
        if ($passenger === '') continue;
        if ($bookingRef === '' && $email === '') continue;

        $planilla[] = [
            'row_id' => (string)$idx, // internal index in $_SESSION['consolidated']
            'origin' => 'GET',
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

    if ($payload === false) {
        throw new RuntimeException('Could not encode lookup payload as JSON.');
    }

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

function apply_gyg_hotel_lookup_results(array &$rows, array $lookupResponse): array {
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

    return ['updated' => $updated, 'not_found' => $notFound, 'checked' => count($results)];
}

/* ---------- Upload handling ---------- */
$errors = [];
$consolidated = [];
$unmatchedPassengers = []; // for bottom UI card
$webRows = [];
$webSummary = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'consolidate');

    if ($action === 'lookup_gyg_hotels') {
        $consolidated = $_SESSION['consolidated'] ?? [];
        if (empty($consolidated) || !is_array($consolidated)) {
            $errors[] = 'No consolidated planilla found in session. First upload files and click Consolidate & Display.';
        } else {
            try {
                $lookupPlanilla = build_gyg_hotel_lookup_planilla($consolidated);
                $lookupResponse = call_gyg_hotel_lookup_webapp($lookupPlanilla);
                $summary = apply_gyg_hotel_lookup_results($consolidated, $lookupResponse);
                $_SESSION['consolidated'] = $consolidated;
                $_SESSION['lookup_summary'] = $summary;
            } catch (Throwable $e) {
                $errors[] = 'GYG hotel lookup failed: ' . $e->getMessage();
            }
        }
    } else {
    $viatorRows = [];
    $gygRows = [];
    $civRows = [];
    $passengerLookup = [];

    // Trip/Viator — CSV ONLY
    if (!empty($_FILES['viator_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['viator_file']['name'], PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $viatorRows = parse_viator_csv($_FILES['viator_file']['tmp_name']);
        } else {
            $errors[] = "Trip/Viator must be CSV. You uploaded .$ext.";
        }
    }

    // GYG — XLSX/XLS
    if (!empty($_FILES['gyg_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['gyg_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx','xls'], true)) {
            if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                $gygRows = parse_gyg_xlsx($_FILES['gyg_file']['tmp_name']);
            } else {
                $errors[] = "PhpSpreadsheet not installed — cannot read GYG XLSX/XLS.";
            }
        } else {
            $errors[] = "Please upload GYG as XLSX/XLS.";
        }
    }

    // Civitatis — XLSX/XLS
    if (!empty($_FILES['civitatis_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['civitatis_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx','xls'], true)) {
            if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                $civRows = parse_civitatis_xls($_FILES['civitatis_file']['tmp_name']);
            } else {
                $errors[] = "PhpSpreadsheet not installed — cannot read Civitatis XLSX/XLS.";
            }
        } else {
            $errors[] = "Please upload Civitatis as XLSX/XLS.";
        }
    }

    // Viator Passenger List — XLSX/XLS (optional, used to complete/override phones/emails)
    if (!empty($_FILES['viator_passengers_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['viator_passengers_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx','xls'], true)) {
            if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                $passengerLookup = parse_passenger_list_lookup($_FILES['viator_passengers_file']['tmp_name']);
            } else {
                $errors[] = "PhpSpreadsheet not installed — cannot read Viator Passenger List XLSX/XLS.";
            }
        } else {
            $errors[] = "Viator Passenger List must be XLSX/XLS. You uploaded .$ext.";
        }
    }

    if (empty($viatorRows) && empty($gygRows) && empty($civRows)) {
        $errors[] = "Please upload at least one file (Viator CSV, GYG XLSX/XLS and/or Civitatis XLSX/XLS).";
    }

    $sourceRowsForDate = array_merge($viatorRows, $gygRows, $civRows);
    $planillaDateForWeb = get_planilla_date_from_rows($sourceRowsForDate);
    $mysqlDateForWeb = planilla_date_to_mysql($planillaDateForWeb);

    if (empty($errors) && $dbConnected && $conn instanceof mysqli) {
        $webRows = fetch_web_reservations_for_date($conn, $mysqlDateForWeb, $webDbDebug);
    } elseif (empty($errors)) {
        $webDbDebug[] = 'WEB/BD: se omite enriquecimiento WEB porque no hay conexión. ' . $dbError;
    }

    if (empty($errors)) {
        // Complete / override Viator phones/emails using Passenger List lookup (if provided)
        if (!empty($passengerLookup) && !empty($viatorRows)) {
            enrich_viator_with_passenger_lookup($viatorRows, $passengerLookup, $unmatchedPassengers);
        }

        // Merge and categorize (behavior preserved, now adding CIV rows and WEB rows from internal DB for the same travel date)
        $consolidated = consolidate_rows(array_merge($viatorRows, $gygRows, $civRows, $webRows));
        $webSummary = [
            'date' => $planillaDateForWeb,
            'mysql_date' => $mysqlDateForWeb,
            'connected' => $dbConnected,
            'count' => count($webRows),
            'debug' => $webDbDebug,
        ];
        $_SESSION['consolidated'] = $consolidated;
        $_SESSION['unmatched_passengers'] = $unmatchedPassengers;
        $_SESSION['web_summary'] = $webSummary;
        unset($_SESSION['lookup_summary']);
    }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Booking Consolidator — Copy Groups</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="/css/bootstrap.min.css" rel="stylesheet">
<link href="/css/style.css" rel="stylesheet">
<link href="/css/vendors.css" rel="stylesheet">
<link href="/css/admin.css" rel="stylesheet">
<link href="/css/custom.css" rel="stylesheet">
<style>
  :root{--bg:#0b0e13;--card:#121722;--chip:#1b2331;--muted:#9aa3b2;--ink:#e6ebf2;--brand:#6aa1ff;--ok:#65d6ad}
  *{box-sizing:border-box}
  body{margin:0;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--ink)}
  .wrap{max-width:1100px;margin:auto;padding:20px}
  h1{font-size:20px;margin:0 0 8px}
  .sub{color:var(--muted);margin:0 0 16px}
  form{background:var(--card);border-radius:16px;padding:16px;margin-bottom:16px}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  .box{flex:1 1 260px;background:var(--chip);border-radius:12px;padding:12px}
  input[type=file]{width:100%}
  .btn{background:var(--brand);border:none;border-radius:10px;padding:8px 12px;color:white;font-weight:600;cursor:pointer}
  .btn-mini{background:#2a3650;border:1px solid #42567a;border-radius:8px;padding:4px 8px;color:#cfe0ff;font-size:12px;margin-left:8px}
  .err{background:#3a1d1d;border:1px solid #8c3333;color:#ffdede;padding:10px;border-radius:8px;margin:10px 0}
  .card{background:var(--card);border-radius:16px;padding:16px;margin-top:16px}
  .group{background:var(--chip);border-radius:12px;margin:14px 0}
  .group h3{margin:0;padding:10px 12px;display:flex;align-items:center}
  .group .subtot{display:flex;gap:18px;color:var(--muted);padding:0 12px 10px 12px}
  table{width:100%;border-collapse:collapse;margin:0}
  th,td{padding:8px 10px;border-bottom:1px solid #1f2735;vertical-align:top}
  th{color:var(--muted);text-align:left}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:#24314a;color:#cfe0ff;font-size:12px}
  .actions{display:flex;gap:10px;margin-top:8px}
  .note{color:#cfe0ff;font-size:12px;margin-top:6px}


  /* Hidden HTML planilla used only for Copy Full Planilla.
     These styles are inline-friendly so Google Sheets can preserve the visual format. */
  .copy-planilla-table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:10pt;background:#fff;color:#000;text-align:center !important;vertical-align:middle !important;}
  .copy-planilla-table td{border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal !important;word-wrap:break-word;overflow-wrap:break-word;word-break:normal;padding:4px 6px;font-size:10pt;mso-number-format:"\\@";}
  .copy-planilla-date{background:#ffffff;font-weight:normal}
  .copy-planilla-total td{background:#eeeeee;font-weight:bold}
  .copy-planilla-hidden{position:absolute;left:-9999px;top:-9999px;width:auto;height:auto;overflow:hidden}

</style>
<script>
// Copy layout: Passenger, Total, Hotel, [spacer], Phone, Lang, Origin, Email, Booking Reference
function copyGroup(groupId){
  const table = document.getElementById('table-'+groupId);
  if(!table) return;

  const rows = [];
  // BODY ONLY (no header).
  // Indices according to <thead>:
  // 0 Passenger, 1 Total, 2 Hotel, 3 Phone, 4 Lang, 5 Origin,
  // 6 Experience, 7 Infants, 8 Children, 9 Email, 10 Booking Reference
  table.querySelectorAll('tbody tr').forEach(tr => {
    const cells = Array.from(tr.querySelectorAll('td')).map(td =>
      td.innerText.replace(/\s+/g,' ').trim()
    );
    const out = [
      cells[0] ?? '',  // Passenger
      cells[1] ?? '',  // Total
      cells[2] ?? '',  // Hotel
      '',              // spacer
      cells[3] ?? '',  // Phone
      cells[4] ?? '',  // Lang
      cells[5] ?? '',  // Origin
      cells[9] ?? '',  // Email
      cells[10] ?? ''  // Booking Reference
    ];
    rows.push(out.join('\t'));
  });

  const text = rows.join('\n');

  navigator.clipboard.writeText(text).then(() => {
    const btn = document.getElementById('btn-'+groupId);
    const prev = btn.innerText;
    btn.innerText = 'Copied!';
    btn.style.background = 'var(--ok)';
    setTimeout(() => { btn.innerText = prev; btn.style.background=''; }, 1200);
  }).catch(() => {
    alert('Could not copy to clipboard. Your browser may block clipboard access on insecure origins.');
  });
}


// Copies the entire visual planilla as HTML, preserving borders, centered text, totals, and rowspans.
// When pasted into Google Sheets, the DATE and CATEGORY columns should appear merged vertically.
async function copyFullPlanilla(){
  const table = document.getElementById('full-planilla-copy');
  if (!table) {
    alert('No planilla available to copy.');
    return;
  }

  const tableForCopy = table.cloneNode(true);

  // Force Google Sheets / Excel-friendly formatting directly into every copied cell.
  // This keeps borders, centers horizontally, centers vertically where supported,
  // and requests wrapped text (texto ajustado) instead of manual line breaks.
  tableForCopy.setAttribute('align', 'center');
  tableForCopy.setAttribute('valign', 'middle');
  tableForCopy.setAttribute('cellpadding', '0');
  tableForCopy.setAttribute('cellspacing', '0');

  tableForCopy.querySelectorAll('td, th').forEach(cell => {
    cell.setAttribute('align', 'center');
    cell.setAttribute('valign', 'middle');

    const previous = cell.getAttribute('style') || '';
    const forcedStyle = [
      'border:1px solid #000',
      'text-align:center !important',
      'vertical-align:middle !important',
      'font-family:Arial,sans-serif',
      'font-size:10pt',
      'white-space:normal !important',
      'word-wrap:break-word',
      'overflow-wrap:break-word',
      'word-break:normal',
      'mso-style-parent:style0',
      'mso-number-format:"\\@"'
    ].join(';');

    cell.setAttribute('style', previous + ';' + forcedStyle);
  });

  const html = `
    <html>
      <head>
        <meta charset="utf-8">
        <style>
          table { border-collapse: collapse; text-align: center; vertical-align: middle; }
          td, th {
            border: 1px solid #000;
            text-align: center !important;
            vertical-align: middle !important;
            font-family: Arial, sans-serif;
            font-size: 10pt;
            white-space: normal !important;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: normal;
            mso-style-parent: style0;
            mso-number-format: "\\@";
          }
        </style>
      </head>
      <body>${tableForCopy.outerHTML}</body>
    </html>
  `;

  const plainRows = [];
  table.querySelectorAll('tr').forEach(tr => {
    const row = [];
    tr.querySelectorAll('td').forEach(td => {
      row.push(td.innerText.replace(/\s+/g, ' ').trim());
    });
    plainRows.push(row.join('\t'));
  });
  const plainText = plainRows.join('\n');

  try {
    if (navigator.clipboard && window.ClipboardItem) {
      await navigator.clipboard.write([
        new ClipboardItem({
          'text/html': new Blob([html], {type: 'text/html'}),
          'text/plain': new Blob([plainText], {type: 'text/plain'})
        })
      ]);
    } else {
      throw new Error('HTML clipboard API not available');
    }

    const btn = document.getElementById('btn-full-planilla');
    if (btn) {
      const prev = btn.innerText;
      btn.innerText = 'Full Planilla Copied!';
      btn.style.background = 'var(--ok)';
      setTimeout(() => { btn.innerText = prev; btn.style.background=''; }, 1400);
    } else {
      alert('Full planilla copied with visual format.');
    }
  } catch (err) {
    try {
      await navigator.clipboard.writeText(plainText);
      alert('Copied as plain text. HTML visual format was blocked by the browser. Use HTTPS or localhost for formatted copy.');
    } catch (err2) {
      alert('Could not copy to clipboard. Your browser may block clipboard access on insecure origins.');
    }
  }
}

</script>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('consolidate-day'); ?>
<div class="wrap">
  <h1>Booking Consolidator</h1>
  <p class="sub">
    Upload <b>Trip/Viator (CSV only)</b>, <b>Viator Passenger List (XLSX/XLS)</b>, <b>GYG (XLSX/XLS)</b> and/or <b>Civitatis (XLSX/XLS)</b>.<br>
    Categories are primarily detected by product codes (Viator &amp; GYG),
    with GYG tags and keyword fallback preserved. Low-cost variants appear
    with “(Low Cost)” in the Experience text but stay in the same category.<br>
    Emails for TRIP are taken from the Viator CSV when available and then
    completed/overridden using the Passenger List (name in H, phone in I, email in J).
    Emails for GET come from the GYG export. CIV reservations are read from the Civitatis list (origin CIV, language SPA). WEB reservations are added from the internal database using the same travel date found in the uploaded files.
  </p>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="consolidate">
    <div class="row">
      <div class="box">
        <b>Trip/Viator (CSV only)</b>
        <input type="file" name="viator_file" accept=".csv">
        <div class="note">Bookings report exported from Viator</div>
      </div>
      <div class="box">
        <b>Viator Passenger List (XLSX/XLS)</b>
        <input type="file" name="viator_passengers_file" accept=".xlsx,.xls">
        <div class="note">Name in H, Phone in I, Email in J. Used to complete / override phones &amp; emails (TRIP only).</div>
      </div>
      <div class="box">
        <b>GetYourGuide (XLSX/XLS)</b>
        <input type="file" name="gyg_file" accept=".xlsx,.xls">
      </div>
      <div class="box">
        <b>Civitatis (XLSX/XLS)</b>
        <input type="file" name="civitatis_file" accept=".xlsx,.xls">
        <div class="note">A Reserva, B Nombre, C Hotel, D Adultos, E Niños, F Infantes. Origin CIV, Lang SPA.</div>
      </div>
    </div>
    <div class="actions">
      <button class="btn" type="submit">Consolidate &amp; Display</button>
    </div>
  </form>

  <?php if (!empty($errors)): ?>
    <div class="err">
      <b>Errors:</b><br>
      <?php foreach ($errors as $e) echo htmlspecialchars($e).'<br>'; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['lookup_summary'])): ?>
    <?php $ls = $_SESSION['lookup_summary']; ?>
    <div class="card" style="border:1px solid var(--ok);">
      <b>GYG Gmail hotel lookup completed.</b><br>
      Checked: <?php echo (int)($ls['checked'] ?? 0); ?> row(s) ·
      Updated: <?php echo (int)($ls['updated'] ?? 0); ?> hotel(s) ·
      Not found: <?php echo (int)($ls['not_found'] ?? 0); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['web_summary']) && empty($errors)): ?>
    <?php $ws = $_SESSION['web_summary']; ?>
    <div class="card" style="border:1px solid <?php echo !empty($ws['connected']) ? 'var(--ok)' : '#8a6a2f'; ?>;">
      <b>WEB / database enrichment</b><br>
      Date used: <?php echo htmlspecialchars((string)($ws['date'] ?? '')); ?>
      <?php if (!empty($ws['mysql_date'])): ?>(<?php echo htmlspecialchars((string)$ws['mysql_date']); ?>)<?php endif; ?> ·
      Added WEB reservations: <?php echo (int)($ws['count'] ?? 0); ?>
      <details style="margin-top:6px">
        <summary class="note">Debug</summary>
        <div class="note">
          <?php foreach (($ws['debug'] ?? []) as $msg) echo htmlspecialchars((string)$msg) . '<br>'; ?>
        </div>
      </details>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['consolidated']) && empty($errors)): ?>
    <?php
      $consolidated = $_SESSION['consolidated'] ?? [];
      $unmatchedPassengers = $_SESSION['unmatched_passengers'] ?? [];
      $webSummary = $_SESSION['web_summary'] ?? [];
    ?>

    <?php if (!empty($consolidated)): ?>
      <?php
        $byCat = [];
        $grandPax = $grandInf = $grandChd = 0;
        $grandReservations = count($consolidated);
        foreach ($consolidated as $r) {
            $cat = $r['Category'] ?? 'Uncategorized';
            $byCat[$cat][] = $r;
            $grandPax += (int)$r['B_TotalPax'];
            $grandInf += (int)$r['H_Infants(n)'];
            $grandChd += (int)$r['I_Children(m)'];
        }
        uksort($byCat, fn($a,$b)=>(CATEGORY_ORDER[$a] ?? 99) <=> (CATEGORY_ORDER[$b] ?? 99));
        function group_id($name){ return preg_replace('/[^a-z0-9]+/i','-', strtolower($name)); }
        function planilla_category_label($cat){
            // Keep labels as a single text value. The destination Google Sheet formatting
            // can handle wrap/alignment, while the copied HTML table keeps the borders.
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
        function e_cell($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
      ?>

      <div class="card">
        <h2 style="margin:0 0 8px">Consolidated by Category</h2>
        <div class="actions" style="margin:0 0 10px 0">
          <button id="btn-full-planilla" class="btn" type="button" onclick="copyFullPlanilla()">Copy Full Planilla</button>
          <form method="post" style="display:inline;background:transparent;padding:0;margin:0">
            <input type="hidden" name="action" value="lookup_gyg_hotels">
            <button class="btn" type="submit">Lookup GYG Hotels from Gmail</button>
          </form>
        </div>
        <div class="sub">
          Grand totals —
          <span class="pill">Pax: <?php echo (int)$grandPax; ?></span>
          <span class="pill">Reservations: <?php echo (int)$grandReservations; ?></span>
          <span class="pill">Infants: <?php echo (int)$grandInf; ?></span>
          <span class="pill">Children: <?php echo (int)$grandChd; ?></span>
        </div>

        <?php foreach ($byCat as $cat => $rows): ?>
          <?php
            $subPax = $subInf = $subChd = 0;
            $subReservations = count($rows);
            foreach ($rows as $r) {
                $subPax += (int)$r['B_TotalPax'];
                $subInf += (int)$r['H_Infants(n)'];
                $subChd += (int)$r['I_Children(m)'];
            }
            $gid = group_id($cat);
          ?>
          <div class="group" id="group-<?php echo $gid; ?>">
            <h3>
              <?php echo htmlspecialchars($cat); ?>
              <button id="btn-<?php echo $gid; ?>" class="btn-mini" type="button" onclick="copyGroup('<?php echo $gid; ?>')">Copy</button>
            </h3>
            <div class="subtot">
              <div>Total pax: <b><?php echo (int)$subPax; ?></b></div>
              <div>Total reservations: <b><?php echo (int)$subReservations; ?></b></div>
              <div>Infants: <b><?php echo (int)$subInf; ?></b></div>
              <div>Children: <b><?php echo (int)$subChd; ?></b></div>
            </div>
            <div style="overflow:auto">
              <table id="table-<?php echo $gid; ?>">
                <thead>
                  <tr>
                    <th style="min-width:220px">Passenger</th>
                    <th style="min-width:90px">Total</th>
                    <th style="min-width:320px">Hotel / Address</th>
                    <th style="min-width:150px">Phone</th>
                    <th style="min-width:70px">Lang</th>
                    <th style="min-width:70px">Origin</th>
                    <th style="min-width:200px">Experience (original)</th>
                    <th style="min-width:90px">Infants</th>
                    <th style="min-width:90px">Children</th>
                    <th style="min-width:220px">Email</th>
                    <th style="min-width:160px">Booking Reference</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['A_PassengerName']); ?></td>
                    <td><?php echo (int)$r['B_TotalPax']; ?></td>
                    <td><?php echo htmlspecialchars($r['C_Hotels']); ?></td>
                    <td><?php echo htmlspecialchars($r['E_Phone']); ?></td>
                    <td><?php echo htmlspecialchars($r['F_Language']); ?></td>
                    <td><?php echo htmlspecialchars($r['G_Origin']); ?></td>
                    <td><?php echo htmlspecialchars($r['Experience']); ?></td>
                    <td><?php echo (int)$r['H_Infants(n)']; ?></td>
                    <td><?php echo (int)$r['I_Children(m)']; ?></td>
                    <td><?php echo htmlspecialchars($r['J_Email']); ?></td>
                    <td><?php echo htmlspecialchars($r['K_BookingReference'] ?? ''); ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>

        <?php
          // Hidden full-planilla table for visual copy/paste into Google Sheets.
          // The visual emulation uses rowspans: one DATE cell for the whole planilla and one CATEGORY cell per category block.
          $planillaDate = get_planilla_date_from_rows($consolidated);
          $totalVisualRows = 0;
          foreach ($byCat as $catForCount => $rowsForCount) {
              $totalVisualRows += count($rowsForCount) + 1; // reservation rows + TOTAL row
          }
        ?>
        <div class="copy-planilla-hidden" aria-hidden="true">
          <table id="full-planilla-copy" class="copy-planilla-table" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:10pt;background:#ffffff;color:#000000;text-align:center !important;vertical-align:middle !important;">
            <tbody>
              <?php $firstDateCell = true; ?>
              <?php foreach ($byCat as $cat => $rows): ?>
                <?php
                  $catTotal = 0;
                  foreach ($rows as $r) {
                      $catTotal += (int)$r['B_TotalPax'];
                  }
                  $catRowspan = count($rows) + 1; // include TOTAL row
                  $firstCatCell = true;
                  $catLabel = planilla_category_label($cat);
                ?>

                <?php foreach ($rows as $r): ?>
                  <tr>
                    <?php if ($firstDateCell): ?>
                      <td align="center" valign="middle" rowspan="<?php echo (int)$totalVisualRows; ?>" class="copy-planilla-date" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#ffffff;font-weight:normal;width:90px;font-size:10pt;">
                        <?php echo e_cell($planillaDate); ?>
                      </td>
                      <?php $firstDateCell = false; ?>
                    <?php endif; ?>

                    <?php if ($firstCatCell): ?>
                      <td align="center" valign="middle" rowspan="<?php echo (int)$catRowspan; ?>" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal !important;word-wrap:break-word;overflow-wrap:break-word;word-break:normal;padding:4px 6px;width:260px;font-size:10pt;">
                        <?php echo e_cell($catLabel); ?>
                      </td>
                      <?php $firstCatCell = false; ?>
                    <?php endif; ?>

                    <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;width:190px;font-size:10pt;"><?php echo e_cell($r['A_PassengerName'] ?? ''); ?></td>
                    <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;width:55px;font-size:10pt;"><?php echo (int)($r['B_TotalPax'] ?? 0); ?></td>
                    <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;width:290px;font-size:10pt;"><?php echo e_cell($r['C_Hotels'] ?? ''); ?></td>
                    <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;width:35px;font-size:10pt;"></td>
                    <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;width:140px;font-size:10pt;mso-number-format:'\@';"><?php echo e_cell($r['E_Phone'] ?? ''); ?></td>
                    <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;width:60px;font-size:10pt;"><?php echo e_cell($r['F_Language'] ?? ''); ?></td>
                    <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;width:70px;font-size:10pt;"><?php echo e_cell($r['G_Origin'] ?? ''); ?></td>
                    <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;width:260px;font-size:10pt;"><?php echo e_cell($r['J_Email'] ?? ''); ?></td>
                    <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;width:160px;font-size:10pt;mso-number-format:'\@';"><?php echo e_cell($r['K_BookingReference'] ?? ''); ?></td>
                  </tr>
                <?php endforeach; ?>

                <tr class="copy-planilla-total">
                  <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#eeeeee;font-weight:bold;font-size:10pt;"></td>
                  <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#eeeeee;font-weight:bold;font-size:10pt;"><?php echo (int)$catTotal; ?></td>
                  <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#eeeeee;font-weight:bold;font-size:10pt;"></td>
                  <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#eeeeee;font-weight:bold;font-size:10pt;"></td>
                  <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#eeeeee;font-weight:bold;font-size:10pt;"></td>
                  <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#eeeeee;font-weight:bold;font-size:10pt;"></td>
                  <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#eeeeee;font-weight:bold;font-size:10pt;"></td>
                  <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#eeeeee;font-weight:bold;font-size:10pt;"></td>
                  <td align="center" valign="middle" style="border:1px solid #000;text-align:center !important;vertical-align:middle !important;white-space:normal;padding:4px 6px;background:#eeeeee;font-weight:bold;font-size:10pt;"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($unmatchedPassengers)): ?>
      <div class="card">
        <h2 style="margin:8px 0 8px">Passengers in Passenger List but NOT in Viator Bookings</h2>
        <div class="sub">These names exist in the passenger list file but were not found in the Viator bookings CSV (after normalization).</div>
        <ul style="margin:8px 0 0 20px">
          <?php foreach ($unmatchedPassengers as $origName): ?>
            <li><?php echo htmlspecialchars($origName); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
</body>
</html>