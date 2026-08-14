<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

/****************************************************
 * STAMP'S TOUR — CHECK RESERVATIONS
 * PHP VERSION
 *
 * INPUT:
 * - Planilla operativa pegada como texto
 * - Viator / TripAdvisor CSV
 * - GetYourGuide XLSX / XLS
 * - Civitatis XLSX / XLS
 * - Reservas WEB desde BD propia, filtradas por fecha y estado=realizado
 *
 * MAIN MATCH:
 * - Booking code / Booking reference
 *
 * CHECKS:
 * - Código existe en archivo fuente
 * - Tour correcto
 * - Pax correcto
 * - Idioma correcto
 * - Hotel / pickup correcto
 * - Fuente correcta GET / TRIP / CIV / WEB
 *
 * ALSO:
 * - Detecta pasajeros que están en Viator/GYG/Civitatis/WEB
 *   pero faltan en la planilla pegada.
 * - Muestra preview grid normalizado de la planilla pegada.
 ****************************************************/

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

$results = [];
$missingPassengers = [];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new RuntimeException(
                'PhpSpreadsheet no está disponible. Revisa que exista vendor/autoload.php o phpspreadsheet_lib/vendor/autoload.php.'
            );
        }

        $planillaText = $_POST['planilla_text'] ?? '';
        $planillaRows = parsePlanillaText($planillaText);

        $viatorRows = [];
        $gygRows = [];
        $civitatisRows = [];
        $webRows = [];

        if (!empty($_FILES['viator_file']['tmp_name'])) {
            $viatorRows = parseCsvFile($_FILES['viator_file']['tmp_name']);
        }

        if (!empty($_FILES['gyg_file']['tmp_name'])) {
            $gygRows = parseExcelFile($_FILES['gyg_file']['tmp_name']);
        }

        if (!empty($_FILES['civitatis_file']['tmp_name'])) {
            $civitatisRows = parseCivitatisFile($_FILES['civitatis_file']['tmp_name']);
        }

        $planillaDateForWeb = inferPlanillaDateForWeb($planillaRows, $civitatisRows);
        $mysqlDateForWeb = planillaDateToMysql($planillaDateForWeb);

        if ($dbConnected && $conn instanceof mysqli) {
            $webRows = fetchWebReservationsForDate($conn, $mysqlDateForWeb, $webDbDebug);
        } else {
            $webDbDebug[] = 'WEB/BD: se omite comparación WEB porque no hay conexión. ' . $dbError;
        }

        $viatorMap = buildViatorMap($viatorRows);
        $gygMap = buildGygMap($gygRows);
        $civitatisMap = buildSimpleMap($civitatisRows);
        $webMap = buildSimpleMap($webRows);

        $systemMaps = [
            'GET'  => $gygMap,
            'TRIP' => $viatorMap,
            'CIV'  => $civitatisMap,
            'WEB'  => $webMap,
        ];

        $opCodes = [];

        foreach ($planillaRows as $opRow) {
            $opCode = normalizeCode($opRow['code'] ?? '');

            if ($opCode !== '') {
                $opCodes[$opCode] = true;
            }
        }

        foreach ($planillaRows as $op) {
            $code = normalizeCode($op['code'] ?? '');

            if ($code === '') {
                $results[] = makeResult($op, null, 'SIN CÓDIGO', 'La fila de planilla no tiene código de reserva.');
                continue;
            }

            [$sys, $expectedSource] = findSystemReservation($code, $op['source'] ?? '', $systemMaps);

            if ($expectedSource === '') {
                $results[] = makeResult($op, null, 'FUENTE DESCONOCIDA', 'No se pudo identificar si el código pertenece a GET, TRIP, CIV o WEB.');
                continue;
            }

            if (!$sys) {
                $results[] = makeResult($op, null, 'NO EXISTE EN ARCHIVO/BD', 'El código no fue encontrado en el archivo fuente ni en la BD interna filtrada por estado=realizado.');
                continue;
            }

            $results[] = compareReservation($op, $sys, $expectedSource);
        }

        $missingPassengers = findMissingPassengers($systemMaps, $opCodes);

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

/****************************************************
 * PARSE PLANILLA PEGADA
 ****************************************************/

function parsePlanillaText(string $text): array
{
    $text = trim($text);

    if ($text === '') {
        return [];
    }

    $lines = preg_split("/\r\n|\n|\r/", $text);
    $rows = [];

    $currentDate = '';
    $currentTour = '';

    foreach ($lines as $lineNumber => $line) {
        if (trim($line) === '') {
            continue;
        }

        $cols = explode("\t", $line);

        for ($i = 0; $i < 14; $i++) {
            if (!isset($cols[$i])) {
                $cols[$i] = '';
            }
        }

        $a = clean($cols[0]);
        $b = clean($cols[1]);
        $c = clean($cols[2]);

        if (isSubtotalRow($cols)) {
            continue;
        }

        $parsed = null;

        /*
         * Case 1:
         * A: Date
         * B: Tour
         * C: Name
         * D: Pax
         * E: Hotel
         * F: Pickup / blank
         * G: Phone
         * H: Lang
         * I: Source
         * J: Email
         * K: Code
         */
        if (isDateLike($a) && isTourLike($b)) {
            $currentDate = $a;
            $currentTour = $b;

            $parsed = [
                'row'       => $lineNumber + 1,
                'date'      => $currentDate,
                'tour_raw'  => $currentTour,
                'tour'      => canonicalTour($currentTour),
                'name'      => clean($cols[2]),
                'pax'       => parseNumber($cols[3]),
                'hotel'     => clean($cols[4]),
                'pickup'    => clean($cols[5]),
                'phone'     => cleanPhone($cols[6]),
                'lang'      => normalizeLang($cols[7]),
                'source'    => normalizeSource($cols[8]),
                'email'     => clean($cols[9]),
                'code'      => normalizeCode($cols[10]),
            ];
        }

        /*
         * Case 2:
         * A: Tour
         * B: Name
         * C: Pax
         * D: Hotel
         * E: Pickup / blank
         * F: Phone
         * G: Lang
         * H: Source
         * I: Email
         * J: Code
         */
        elseif (isTourLike($a) && $b !== '') {
            $currentTour = $a;

            $parsed = [
                'row'       => $lineNumber + 1,
                'date'      => $currentDate,
                'tour_raw'  => $currentTour,
                'tour'      => canonicalTour($currentTour),
                'name'      => clean($cols[1]),
                'pax'       => parseNumber($cols[2]),
                'hotel'     => clean($cols[3]),
                'pickup'    => clean($cols[4]),
                'phone'     => cleanPhone($cols[5]),
                'lang'      => normalizeLang($cols[6]),
                'source'    => normalizeSource($cols[7]),
                'email'     => clean($cols[8]),
                'code'      => normalizeCode($cols[9]),
            ];
        }

        /*
         * Case 2B — copied from Google Sheets with merged DATE column:
         * A empty
         * B Tour
         * C Name
         * D Pax
         * E Hotel
         * F Pickup / blank
         * G Phone
         * H Lang
         * I Source
         * J Email
         * K Code
         */
        elseif ($a === '' && isTourLike($b) && $c !== '') {
            $currentTour = $b;

            $parsed = [
                'row'       => $lineNumber + 1,
                'date'      => $currentDate,
                'tour_raw'  => $currentTour,
                'tour'      => canonicalTour($currentTour),
                'name'      => clean($cols[2]),
                'pax'       => parseNumber($cols[3]),
                'hotel'     => clean($cols[4]),
                'pickup'    => clean($cols[5]),
                'phone'     => cleanPhone($cols[6]),
                'lang'      => normalizeLang($cols[7]),
                'source'    => normalizeSource($cols[8]),
                'email'     => clean($cols[9]),
                'code'      => normalizeCode($cols[10]),
            ];
        }

        /*
         * Case 3:
         * A empty
         * B empty
         * C Name
         * D Pax
         * E Hotel
         * F Pickup / blank
         * G Phone
         * H Lang
         * I Source
         * J Email
         * K Code
         */
        elseif ($a === '' && $b === '' && $c !== '' && $currentTour !== '') {
            $parsed = [
                'row'       => $lineNumber + 1,
                'date'      => $currentDate,
                'tour_raw'  => $currentTour,
                'tour'      => canonicalTour($currentTour),
                'name'      => clean($cols[2]),
                'pax'       => parseNumber($cols[3]),
                'hotel'     => clean($cols[4]),
                'pickup'    => clean($cols[5]),
                'phone'     => cleanPhone($cols[6]),
                'lang'      => normalizeLang($cols[7]),
                'source'    => normalizeSource($cols[8]),
                'email'     => clean($cols[9]),
                'code'      => normalizeCode($cols[10]),
            ];
        }

        /*
         * Case 4:
         * A: Name
         * B: Pax
         * C: Hotel
         * D: Pickup / blank
         * E: Phone
         * F: Lang
         * G: Source
         * H: Email
         * I: Code
         */
        elseif ($a !== '' && $currentTour !== '') {
            $parsed = [
                'row'       => $lineNumber + 1,
                'date'      => $currentDate,
                'tour_raw'  => $currentTour,
                'tour'      => canonicalTour($currentTour),
                'name'      => clean($cols[0]),
                'pax'       => parseNumber($cols[1]),
                'hotel'     => clean($cols[2]),
                'pickup'    => clean($cols[3]),
                'phone'     => cleanPhone($cols[4]),
                'lang'      => normalizeLang($cols[5]),
                'source'    => normalizeSource($cols[6]),
                'email'     => clean($cols[7]),
                'code'      => normalizeCode($cols[8]),
            ];
        }

        if (!$parsed) {
            continue;
        }

        if (($parsed['name'] ?? '') === '') {
            continue;
        }

        if (($parsed['code'] ?? '') === '') {
            continue;
        }

        $rows[] = $parsed;
    }

    return $rows;
}

/****************************************************
 * READ VIATOR CSV
 ****************************************************/

function parseCsvFile(string $filePath): array
{
    $content = file_get_contents($filePath);

    if ($content === false || trim($content) === '') {
        return [];
    }

    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = preg_split("/\r\n|\n|\r/", trim($content));

    if (!$lines || count($lines) === 0) {
        return [];
    }

    $delimiter = detectDelimiter($lines[0]);

    $rows = [];

    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }

        $rows[] = str_getcsv($line, $delimiter);
    }

    return $rows;
}

function detectDelimiter(string $line): string
{
    $tabs = substr_count($line, "\t");
    $commas = substr_count($line, ',');
    $semicolons = substr_count($line, ';');

    if ($tabs >= $commas && $tabs >= $semicolons) {
        return "\t";
    }

    if ($semicolons > $commas) {
        return ';';
    }

    return ',';
}

/****************************************************
 * READ GYG EXCEL
 ****************************************************/

function parseExcelFile(string $filePath): array
{
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheet(0);

    $rows = $sheet->toArray(null, true, true, false);

    $cleanRows = [];

    foreach ($rows as $row) {
        $hasData = false;

        foreach ($row as $cell) {
            if (trim((string)$cell) !== '') {
                $hasData = true;
                break;
            }
        }

        if ($hasData) {
            $cleanRows[] = $row;
        }
    }

    return $cleanRows;
}

/****************************************************
 * READ CIVITATIS XLS / XLSX
 ****************************************************/

function parseCivitatisFile(string $filePath): array
{
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheet(0);
    $data = $sheet->toArray(null, true, true, true); // A,B,C...

    $rows = [];
    $currentExperience = '';
    $currentDate = '';
    $insideTable = false;
    $sourceRow = 0;

    foreach ($data as $excelRowNumber => $row) {
        $a = clean($row['A'] ?? '');
        $b = clean($row['B'] ?? '');
        $c = clean($row['C'] ?? '');

        // Header row usually contains: Tour name, language, YYYY-MM-DD, HH:MMhoras
        if ($a !== '' && $b === '' && $c === '' && stripos($a, 'Reserva') === false && strtoupper($a) !== 'TOTAL') {
            $info = parseCivitatisHeaderInfo($a);
            $currentExperience = $info['experience'];
            $currentDate = $info['date'];
            $insideTable = false;
            continue;
        }

        if (mb_strtolower($a, 'UTF-8') === 'reserva') {
            $insideTable = true;
            continue;
        }

        if (!$insideTable || $a === '') {
            continue;
        }

        if (mb_strtoupper($a, 'UTF-8') === 'TOTAL') {
            $insideTable = false;
            continue;
        }

        $adults = parseNumber($row['D'] ?? 0);
        $children = parseNumber($row['E'] ?? 0);
        $infants = parseNumber($row['F'] ?? 0);
        $pax = $adults + $children + $infants;

        $code = normalizeCode($a);
        $name = clean($b);

        if ($code === '' && $name === '') {
            continue;
        }

        $sourceRow = is_numeric($excelRowNumber) ? (int)$excelRowNumber : ($sourceRow + 1);

        $rows[] = [
            'source'      => 'CIV',
            'code'        => $code,
            'name'        => $name,
            'product'     => $currentExperience,
            'option'      => '',
            'productCode' => '',
            'tourGrade'   => '',
            'tour'        => canonicalTour($currentExperience),
            'pax'         => $pax,
            'hotel'       => cleanCivitatisHotel($c),
            'lang'        => 'SPA',
            'phone'       => '',
            'email'       => '',
            'row'         => $sourceRow,
            'date'        => $currentDate,
        ];
    }

    return $rows;
}

function parseCivitatisHeaderInfo(string $headerText): array
{
    $headerText = clean($headerText);
    $date = '';

    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $headerText, $m)) {
        $date = webDbDateToPlanilla($m[1]);
    }

    $experience = $headerText;
    if ($headerText !== '') {
        $parts = array_map('trim', explode(',', $headerText));
        $experience = $parts[0] ?? $headerText;
    }

    return [
        'experience' => $experience,
        'date' => $date,
    ];
}

function cleanCivitatisHotel(string $value): string
{
    $value = clean($value);
    $value = preg_replace('/^\s*:\s*/', '', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim((string)$value);
}

function buildSimpleMap(array $rows): array
{
    $map = [];

    foreach ($rows as $row) {
        $code = normalizeCode($row['code'] ?? '');
        if ($code === '') {
            continue;
        }

        $row['code'] = $code;
        $map[$code] = $row;
    }

    return $map;
}

/****************************************************
 * BUILD VIATOR MAP
 ****************************************************/

function buildViatorMap(array $rows): array
{
    if (count($rows) < 2) {
        return [];
    }

    $headers = array_map('clean', $rows[0]);

    $idxBooking = findHeader($headers, [
        'Booking Reference',
        'Booking Ref',
        'Booking reference',
        'Order No',
        'Reference'
    ]);

    if ($idxBooking === -1) {
        throw new RuntimeException('No se encontró la columna Booking Reference en el CSV de Viator.');
    }

    $idxStatus = findHeader($headers, ['Status', 'Booking Status']);
    $idxName = findHeader($headers, ['Lead traveler Name', 'Lead Traveler Name', 'Lead Traveller Name', 'Traveler Name', 'Traveller Name', 'Name']);
    $idxProduct = findHeader($headers, ['Product Name', 'Product Title', 'Experience Title', 'Product']);
    $idxProductCode = findHeader($headers, ['Product Code', 'Product code', 'Product ID']);
    $idxTourGrade = findHeader($headers, ['Tour Grade Code', 'Tour Grade', 'Tour Option']);
    $idxPax = findHeader($headers, ['Number of Passengers', 'Travelers', 'Travellers', 'Pax', 'Quantity']);
    $idxPickup = findHeader($headers, ['Hotel Pickup', 'Pickup', 'Pick-up', 'Pickup Location', 'Hotel', 'Pickup Details', 'Meeting Point Details', 'Special Requirements', 'Special requirements']);
    $idxLang = findHeader($headers, ['Tour Language', 'Language', 'Traveler Language']);
    $idxContact = findHeader($headers, ['Lead traveler Contact Info', 'Lead Traveler Contact Info', 'Contact Info']);
    $idxEmail = findHeader($headers, ['Lead traveler Email', 'Lead Traveler Email', 'Lead traveler email', 'Email']);
    $idxPhone = findHeader($headers, ['Phone', 'Lead traveler Phone', 'Lead Traveler Phone']);

    $map = [];

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $code = normalizeCode($row[$idxBooking] ?? '');

        if ($code === '') {
            continue;
        }

        $status = strtolower(clean($idxStatus !== -1 ? ($row[$idxStatus] ?? '') : ''));

        if (in_array($status, ['cancelled', 'canceled'], true)) {
            continue;
        }

        if ($status !== '' && !in_array($status, ['confirmed', 'amended'], true)) {
            continue;
        }

        $product = $idxProduct !== -1 ? clean($row[$idxProduct] ?? '') : '';
        $productCode = $idxProductCode !== -1 ? clean($row[$idxProductCode] ?? '') : '';
        $tourGrade = $idxTourGrade !== -1 ? clean($row[$idxTourGrade] ?? '') : '';

        $pax = $idxPax !== -1 ? parseNumber($row[$idxPax] ?? '') : 0;
        $contactInfo = $idxContact !== -1 ? clean($row[$idxContact] ?? '') : '';

        $email = $idxEmail !== -1 ? clean($row[$idxEmail] ?? '') : '';
        if ($email === '' && $contactInfo !== '') {
            $email = extractEmailFromText($contactInfo);
        }

        $phone = $idxPhone !== -1 ? cleanPhone($row[$idxPhone] ?? '') : '';
        if ($phone === '' && $contactInfo !== '') {
            $phone = cleanPhone($contactInfo);
        }

        $map[$code] = [
            'source'      => 'TRIP',
            'code'        => $code,
            'name'        => $idxName !== -1 ? clean($row[$idxName] ?? '') : '',
            'product'     => $product,
            'option'      => '',
            'productCode' => $productCode,
            'tourGrade'   => $tourGrade,
            'tour'        => canonicalTourFromViator($product, $productCode, $tourGrade),
            'pax'         => $pax,
            'hotel'       => $idxPickup !== -1 ? clean($row[$idxPickup] ?? '') : '',
            'lang'        => $idxLang !== -1 ? normalizeLang($row[$idxLang] ?? '') : '',
            'phone'       => $phone,
            'email'       => $email,
            'row'         => $i + 1,
        ];
    }

    return $map;
}

/****************************************************
 * BUILD GYG MAP
 ****************************************************/

function buildGygMap(array $rows): array
{
    if (count($rows) < 2) {
        return [];
    }

    $headers = array_map('clean', $rows[0]);

    $idxBooking = findHeader($headers, ['Booking Ref #', 'Booking Ref No.', 'Booking Ref No', 'Booking No', 'Booking Number', 'Booking Reference', 'Order No', 'Reference']);

    if ($idxBooking === -1) {
        throw new RuntimeException('No se encontró la columna Booking Ref # / Booking Ref No. en el Excel de GYG.');
    }

    $idxFirstName = findHeader($headers, ["Traveller's First Name", "Traveler's First Name", 'Traveller First Name', 'Traveler First Name', 'First Name']);
    $idxLastName = findHeader($headers, ["Traveller's Surname", "Traveler's Last Name", "Traveller's Last Name", 'Traveller Surname', 'Traveler Last Name', 'Last Name', 'Surname']);
    $idxProduct = findHeader($headers, ['Product', 'Product Title', 'Activity', 'Experience']);
    $idxOption = findHeader($headers, ['Option', 'Tour Option', 'Product Option']);
    $idxPickup = findHeader($headers, ['Pickup', 'Pick-up', 'Pickup Location', 'Hotel', 'Hotel Pickup', 'Meeting Point', 'Additional Information']);
    $idxLang = findHeader($headers, ['Language', 'Tour Language', 'Activity Language']);
    $idxEmail = findHeader($headers, ['Email', 'Traveller Email', 'Traveler Email']);
    $idxPhone = findHeader($headers, ['Phone', 'Traveller Phone', 'Traveler Phone']);

    $paxHeaderNames = [
        'Adult',
        'Senior',
        'Student (with ID)',
        'EU citizens (with ID)',
        'EU Citizens (with ID)',
        'Student EU citizens (with ID)',
        'Student EU Citizens (with ID)',
        'Military (with ID)',
        'Youth',
        'Child',
        'Infant',
        'Group'
    ];

    $paxIndexes = [];
    foreach ($paxHeaderNames as $headerName) {
        $idx = findHeader($headers, [$headerName]);
        if ($idx !== -1) {
            $paxIndexes[] = $idx;
        }
    }

    $map = [];

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $code = normalizeCode($row[$idxBooking] ?? '');

        if ($code === '') {
            continue;
        }

        $firstName = $idxFirstName !== -1 ? clean($row[$idxFirstName] ?? '') : '';
        $lastName = $idxLastName !== -1 ? clean($row[$idxLastName] ?? '') : '';
        $name = trim($firstName . ' ' . $lastName);

        $product = $idxProduct !== -1 ? clean($row[$idxProduct] ?? '') : '';
        $option = $idxOption !== -1 ? clean($row[$idxOption] ?? '') : '';

        $pax = 0;
        foreach ($paxIndexes as $paxIndex) {
            $pax += parseNumber($row[$paxIndex] ?? '');
        }

        $hotel = $idxPickup !== -1 ? clean($row[$idxPickup] ?? '') : '';
        if ($hotel === "'") {
            $hotel = '';
        }

        $email = $idxEmail !== -1 ? clean($row[$idxEmail] ?? '') : '';
        $phone = $idxPhone !== -1 ? cleanPhone($row[$idxPhone] ?? '') : '';

        $map[$code] = [
            'source'      => 'GET',
            'code'        => $code,
            'name'        => $name,
            'product'     => $product,
            'option'      => $option,
            'productCode' => '',
            'tourGrade'   => '',
            'tour'        => canonicalTourFromGyg($product . ' ' . $option),
            'pax'         => $pax,
            'hotel'       => $hotel,
            'lang'        => $idxLang !== -1 ? normalizeLang($row[$idxLang] ?? '') : '',
            'phone'       => $phone,
            'email'       => $email,
            'row'         => $i + 1,
        ];
    }

    return $map;
}

/****************************************************
 * WEB / BD INTERNAL RESERVATIONS
 ****************************************************/

function inferPlanillaDateForWeb(array $planillaRows, array $civitatisRows = []): string
{
    foreach ($planillaRows as $row) {
        $date = normalizePlanillaDate($row['date'] ?? '');
        if ($date !== '') {
            return $date;
        }
    }

    foreach ($civitatisRows as $row) {
        $date = normalizePlanillaDate($row['date'] ?? '');
        if ($date !== '') {
            return $date;
        }
    }

    return '';
}

function normalizePlanillaDate(mixed $value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y');
    }

    $value = clean($value);
    if ($value === '') {
        return '';
    }

    if (is_numeric($value)) {
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value);
            return $dt->format('d/m/Y');
        } catch (Throwable $e) {
            return '';
        }
    }

    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $value, $m)) {
        $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $year = strlen($m[3]) === 2 ? ('20' . $m[3]) : $m[3];
        return $day . '/' . $month . '/' . $year;
    }

    $ts = strtotime($value);
    return $ts !== false ? date('d/m/Y', $ts) : '';
}

function planillaDateToMysql(string $value): string
{
    $normalized = normalizePlanillaDate($value);
    if ($normalized === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('d/m/Y', $normalized);
    if ($dt instanceof DateTime) {
        return $dt->format('Y-m-d');
    }

    $ts = strtotime($value);
    return $ts !== false ? date('Y-m-d', $ts) : '';
}

function webDbDateToPlanilla(string $value): string
{
    $value = clean($value);
    if ($value === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', substr($value, 0, 10));
    if ($dt instanceof DateTime) {
        return $dt->format('d/m/Y');
    }

    return normalizePlanillaDate($value);
}

function isPrivateWebDbRow(array $row): bool
{
    $txt = mb_strtolower((string)(($row['experiencia_nombre'] ?? '') . ' ' . ($row['experiencia_nombre_publico'] ?? '')), 'UTF-8');
    $txt = removeAccents($txt);

    return str_contains($txt, 'pvt') || str_contains($txt, 'private') || str_contains($txt, 'privado');
}

function buildWebHotelText(array $row): string
{
    $parts = [];

    foreach (['nombre_hotel', 'direccion', 'comuna'] as $key) {
        $value = clean($row[$key] ?? '');
        if ($value !== '' && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
    }

    if (!empty($parts)) {
        return implode(', ', $parts);
    }

    $manual = clean($row['hotel_manual'] ?? '');
    if ($manual !== '') {
        return $manual;
    }

    if ((int)($row['airport_pickup'] ?? 0) === 1) {
        return 'SCL Airport pickup';
    }

    return '';
}

function fetchWebReservationsForDate(mysqli $conn, string $mysqlDate, array &$webDbDebug = []): array
{
    $rows = [];

    if ($mysqlDate === '') {
        $webDbDebug[] = 'WEB/BD: sin fecha válida para consultar reservas web. Usa una planilla con fecha en la primera columna o sube Civitatis con fecha en el encabezado.';
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
        $experience = clean(($row['experiencia_nombre_publico'] ?? '') ?: ($row['experiencia_nombre'] ?? ''));
        $name = clean(($row['titular_nombre'] ?? '') . ' ' . ($row['titular_apellido'] ?? ''));
        $adults = (int)($row['adultos'] ?? 0);
        $children = (int)($row['ninos'] ?? 0);
        $infants = (int)($row['infantes'] ?? 0);
        $pax = $adults + $children + $infants;
        $phone = cleanPhone((string)(($row['titular_area_code'] ?? '') . ($row['titular_telefono'] ?? '')));
        $tour = canonicalTour($experience);
        $tour = applyPrivateTourLabel($tour, isPrivateWebDbRow($row));

        $rows[] = [
            'source'      => 'WEB',
            'code'        => normalizeCode($row['reference_id'] ?? ''),
            'name'        => $name,
            'product'     => $experience,
            'option'      => '',
            'productCode' => '',
            'tourGrade'   => '',
            'tour'        => $tour,
            'pax'         => $pax,
            'hotel'       => buildWebHotelText($row),
            'lang'        => normalizeLang($row['idioma_actividad'] ?? ''),
            'phone'       => $phone,
            'email'       => clean($row['titular_email'] ?? ''),
            'row'         => 'BD #' . (string)($row['id_reserva'] ?? ''),
            'date'        => webDbDateToPlanilla((string)($row['fecha_actividad'] ?? '')),
        ];
    }

    $stmt->close();
    $webDbDebug[] = 'WEB/BD: ' . count($rows) . ' reserva(s) web realizada(s) encontrada(s) para fecha_actividad=' . $mysqlDate . ' (solo estado=realizado).';

    return $rows;
}

function findSystemReservation(string $code, string $source, array $systemMaps): array
{
    $source = normalizeSource($source);

    if ($source !== '' && isset($systemMaps[$source])) {
        return [$systemMaps[$source][$code] ?? null, $source];
    }

    if (str_starts_with($code, 'GYG')) {
        return [$systemMaps['GET'][$code] ?? null, 'GET'];
    }

    if (str_starts_with($code, 'BR-')) {
        return [$systemMaps['TRIP'][$code] ?? null, 'TRIP'];
    }

    if (str_starts_with($code, 'STAMP_')) {
        return [$systemMaps['WEB'][$code] ?? null, 'WEB'];
    }

    foreach (['CIV', 'WEB', 'GET', 'TRIP'] as $possibleSource) {
        if (isset($systemMaps[$possibleSource][$code])) {
            return [$systemMaps[$possibleSource][$code], $possibleSource];
        }
    }

    return [null, ''];
}

/****************************************************
 * COMPARISON
 ****************************************************/

function compareReservation(array $op, array $sys, string $expectedSource): array
{
    $issues = [];

    if (($op['source'] ?? '') !== '' && ($op['source'] ?? '') !== $expectedSource) {
        $issues[] = 'Fuente distinta';
    }

    if (($op['tour'] ?? '') !== ($sys['tour'] ?? '')) {
        $issues[] = 'Tour distinto';
    }

    if ((int)($op['pax'] ?? 0) !== (int)($sys['pax'] ?? 0)) {
        $issues[] = 'Pax distinto';
    }

    if (!compareLang($op['lang'] ?? '', $sys['lang'] ?? '')) {
        $issues[] = 'Idioma distinto';
    }

    if (!compareHotels($op['hotel'] ?? '', $sys['hotel'] ?? '')) {
        $issues[] = 'Hotel / pickup distinto';
    }

    return [
        'status'      => count($issues) === 0 ? 'OK' : 'REVISAR',
        'issues'      => implode(' | ', $issues),
        'op_row'      => $op['row'] ?? '',
        'code'        => $op['code'] ?? '',
        'op_source'   => $op['source'] ?? '',
        'sys_source'  => $sys['source'] ?? '',
        'op_name'     => $op['name'] ?? '',
        'sys_name'    => $sys['name'] ?? '',
        'op_tour'     => $op['tour'] ?? '',
        'sys_tour'    => $sys['tour'] ?? '',
        'op_pax'      => $op['pax'] ?? '',
        'sys_pax'     => $sys['pax'] ?? '',
        'op_lang'     => $op['lang'] ?? '',
        'sys_lang'    => $sys['lang'] ?? '',
        'op_hotel'    => $op['hotel'] ?? '',
        'sys_hotel'   => $sys['hotel'] ?? '',
        'sys_phone'   => $sys['phone'] ?? '',
        'sys_email'   => $sys['email'] ?? '',
        'sys_product' => trim(($sys['product'] ?? '') . ' ' . ($sys['option'] ?? '')),
        'sys_row'     => $sys['row'] ?? '',
    ];
}

function makeResult(array $op, ?array $sys, string $status, string $note): array
{
    return [
        'status'      => $status,
        'issues'      => $note,
        'op_row'      => $op['row'] ?? '',
        'code'        => $op['code'] ?? '',
        'op_source'   => $op['source'] ?? '',
        'sys_source'  => $sys['source'] ?? '',
        'op_name'     => $op['name'] ?? '',
        'sys_name'    => $sys['name'] ?? '',
        'op_tour'     => $op['tour'] ?? '',
        'sys_tour'    => $sys['tour'] ?? '',
        'op_pax'      => $op['pax'] ?? '',
        'sys_pax'     => $sys['pax'] ?? '',
        'op_lang'     => $op['lang'] ?? '',
        'sys_lang'    => $sys['lang'] ?? '',
        'op_hotel'    => $op['hotel'] ?? '',
        'sys_hotel'   => $sys['hotel'] ?? '',
        'sys_phone'   => $sys['phone'] ?? '',
        'sys_email'   => $sys['email'] ?? '',
        'sys_product' => trim(($sys['product'] ?? '') . ' ' . ($sys['option'] ?? '')),
        'sys_row'     => $sys['row'] ?? '',
    ];
}

/****************************************************
 * MISSING PASSENGERS
 ****************************************************/

function findMissingPassengers(array $systemMaps, array $opCodes): array
{
    $missing = [];

    foreach ($systemMaps as $source => $map) {
        foreach ($map as $code => $row) {
            if (!isset($opCodes[$code])) {
                $missing[] = systemRowToMissingPassenger($row);
            }
        }
    }

    usort($missing, function ($a, $b) {
        $oa = missingCategoryOrder($a['tour'] ?? '');
        $ob = missingCategoryOrder($b['tour'] ?? '');

        return [
            $oa,
            $a['tour'] ?? '',
            $a['origin'] ?? '',
            $a['name'] ?? ''
        ] <=> [
            $ob,
            $b['tour'] ?? '',
            $b['origin'] ?? '',
            $b['name'] ?? ''
        ];
    });

    return $missing;
}

function systemRowToMissingPassenger(array $row): array
{
    return [
        'tour'   => $row['tour'] ?? '',
        'name'   => $row['name'] ?? '',
        'pax'    => $row['pax'] ?? 0,
        'hotel'  => $row['hotel'] ?? '',
        'blank'  => '',
        'phone'  => $row['phone'] ?? '',
        'lang'   => $row['lang'] ?? '',
        'origin' => $row['source'] ?? '',
        'email'  => $row['email'] ?? '',
        'code'   => $row['code'] ?? '',
    ];
}

function buildMissingPassengersCopyText(array $missingPassengers): string
{
    $lines = [];
    $currentTour = '';

    foreach ($missingPassengers as $m) {
        $tour = $m['tour'] ?? '';

        if ($tour !== $currentTour) {
            if ($currentTour !== '') {
                $lines[] = '';
            }

            $lines[] = $tour;
            $currentTour = $tour;
        }

        $lines[] = implode("\t", [
            $m['name'] ?? '',
            $m['pax'] ?? '',
            $m['hotel'] ?? '',
            '',
            $m['phone'] ?? '',
            $m['lang'] ?? '',
            $m['origin'] ?? '',
            $m['email'] ?? '',
            $m['code'] ?? '',
        ]);
    }

    return implode("\n", $lines);
}

function missingCategoryOrder(string $tour): int
{
    $order = [
        'VALPARAISO & VIÑA DEL MAR'       => 1,
        'PVT VALPARAISO & VIÑA DEL MAR'   => 2,
        'CITY TOUR'                       => 3,
        'PVT CITY TOUR'                   => 4,
        'WINE TOUR MAIPO VALLEY'          => 5,
        'PVT WINE TOUR MAIPO VALLEY'      => 6,
        'WINE TOUR MAIPO VALLEY TASTINGS & LUNCH' => 7,
        'PVT WINE TOUR MAIPO VALLEY TASTINGS & LUNCH' => 8,
        'PORTILLO INCA LAGOON'            => 9,
        'PVT PORTILLO INCA LAGOON'        => 10,
        'OTRO'                            => 99,
    ];

    return $order[$tour] ?? 99;
}

/****************************************************
 * NORMALIZATION
 ****************************************************/

function clean(mixed $value): string
{
    $value = (string)($value ?? '');
    $value = str_replace("\u{00A0}", ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim($value ?? '');
}

function normalizeCode(mixed $value): string
{
    return strtoupper(clean($value));
}

function normalizeSource(mixed $value): string
{
    $s = strtoupper(clean($value));

    if ($s === 'GET' || str_contains($s, 'GYG') || str_contains($s, 'GETYOURGUIDE')) {
        return 'GET';
    }

    if ($s === 'TRIP' || str_contains($s, 'VIATOR') || str_contains($s, 'TRIPADVISOR')) {
        return 'TRIP';
    }

    return $s;
}

function normalizeLang(mixed $value): string
{
    $s = strtoupper(clean($value));

    if ($s === '') {
        return '';
    }

    if ($s === 'SPA' || $s === 'ESP' || str_contains($s, 'SPANISH') || str_contains($s, 'ESPAÑOL') || str_contains($s, 'ESPANOL')) {
        return 'SPA';
    }

    if ($s === 'ENG' || str_contains($s, 'ENGLISH') || str_contains($s, 'INGLES') || str_contains($s, 'INGLÉS')) {
        return 'ENG';
    }

    if ($s === 'BRA' || $s === 'POR' || $s === 'PT' || str_contains($s, 'PORTUGUESE') || str_contains($s, 'PORTUGUES') || str_contains($s, 'PORTUGUÉS')) {
        return 'BRA';
    }

    return $s;
}

function normalizeHotel(string $value): string
{
    $s = mb_strtolower(clean($value), 'UTF-8');

    if ($s === '' || $s === "'") {
        return '';
    }

    $s = removeAccents($s);
    $s = preg_replace('/\([^)]*\)/u', ' ', $s);
    $s = preg_replace('/\[image:[^\]]+\]/iu', ' ', $s);
    $s = preg_replace('/ph:\s*[\d+\s()-]+/iu', ' ', $s);
    $s = preg_replace('/\b\d{4,}\b/u', ' ', $s);
    $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s);

    return trim($s ?? '');
}

function removeAccents(string $text): string
{
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'ñ' => 'n', 'Ñ' => 'N',
        'ü' => 'u', 'Ü' => 'U'
    ];

    return strtr($text, $map);
}

function parseNumber(mixed $value): int
{
    $s = clean($value);

    if ($s === '') {
        return 0;
    }

    $s = str_replace(',', '.', $s);

    if (!is_numeric($s)) {
        return 0;
    }

    return (int)round((float)$s);
}

function cleanPhone(mixed $value): string
{
    $value = (string)($value ?? '');
    return preg_replace('/\D+/', '', $value) ?? '';
}

function extractEmailFromText(string $text): string
{
    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
        return trim($m[0]);
    }

    return '';
}

function isDateLike(string $value): bool
{
    return preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value) === 1
        || preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value) === 1;
}

function isTourLike(string $value): bool
{
    return canonicalTour($value) !== 'OTRO';
}

function isSubtotalRow(array $cols): bool
{
    $nonEmpty = array_values(array_filter(array_map('clean', $cols), fn($v) => $v !== ''));

    return count($nonEmpty) === 1 && is_numeric($nonEmpty[0]);
}

/****************************************************
 * MATCHING LOGIC
 ****************************************************/

function compareLang(string $a, string $b): bool
{
    $a = normalizeLang($a);
    $b = normalizeLang($b);

    if ($a === '' || $b === '') {
        return true;
    }

    return $a === $b;
}

function compareHotels(string $a, string $b): bool
{
    $a = normalizeHotel($a);
    $b = normalizeHotel($b);

    if ($a === '' || $b === '') {
        return true;
    }

    if ($a === $b) {
        return true;
    }

    if (str_contains($a, $b) || str_contains($b, $a)) {
        return true;
    }

    return hotelSimilarity($a, $b) >= 0.55;
}

function hotelSimilarity(string $a, string $b): float
{
    $stopwords = [
        'hotel', 'hostal', 'hostel', 'santiago', 'chile', 'metropolitana',
        'region', 'avenida', 'av', 'calle', 'de', 'del', 'la', 'el',
        'los', 'las', 'the', 'and', 'y', 'en', 'centro', 'ph',
        'providencia', 'condes', 'vitacura'
    ];

    $tokensA = array_values(array_filter(explode(' ', $a), fn($t) => $t !== '' && !in_array($t, $stopwords, true)));
    $tokensB = array_values(array_filter(explode(' ', $b), fn($t) => $t !== '' && !in_array($t, $stopwords, true)));

    if (count($tokensA) === 0 || count($tokensB) === 0) {
        return 0.0;
    }

    $matches = 0;

    foreach ($tokensA as $token) {
        if (in_array($token, $tokensB, true)) {
            $matches++;
        }
    }

    return $matches / max(count($tokensA), count($tokensB));
}

/****************************************************
 * TOUR CANONICALIZATION
 ****************************************************/

function canonicalTour(string $product): string
{
    $p = mb_strtolower(clean($product), 'UTF-8');
    $p = removeAccents($p);

    if ($p === '') {
        return 'OTRO';
    }

    if (str_contains($p, 'pvt valparaiso') || str_contains($p, 'pvt valparaiso & vina') || str_contains($p, 'private valparaiso')) {
        return 'PVT VALPARAISO & VIÑA DEL MAR';
    }

    if (str_contains($p, 'pvt city') || str_contains($p, 'private city') || str_contains($p, 'pvt discover santiago')) {
        return 'PVT CITY TOUR';
    }

    if (str_contains($p, 'pvt wine') || str_contains($p, 'pvt maipo') || str_contains($p, 'private maipo')) {
        return 'PVT WINE TOUR MAIPO VALLEY';
    }

    if (str_contains($p, 'pvt portillo') || str_contains($p, 'pvt inca') || str_contains($p, 'private portillo')) {
        return 'PVT PORTILLO INCA LAGOON';
    }

    if (str_contains($p, 'transfer to san antonio') || str_contains($p, 'san antonio port') || str_contains($p, 'cruise')) {
        return 'OTRO';
    }

    if (
        str_contains($p, 'maipo valley tastings') ||
        str_contains($p, 'tastings & lunch') ||
        str_contains($p, 'tastings and lunch') ||
        str_contains($p, 'classic maipo valley')
    ) {
        return 'WINE TOUR MAIPO VALLEY TASTINGS & LUNCH';
    }

    if (str_contains($p, 'maipo')) {
        return 'WINE TOUR MAIPO VALLEY';
    }

    if (str_contains($p, 'valparaiso') || str_contains($p, 'vina del mar') || str_contains($p, 'casablanca')) {
        return 'VALPARAISO & VIÑA DEL MAR';
    }

    if (str_contains($p, 'city tour') || str_contains($p, 'ciudad de santiago') || str_contains($p, 'discover santiago')) {
        return 'CITY TOUR';
    }

    if (str_contains($p, 'portillo') || str_contains($p, 'inca lagoon') || str_contains($p, 'inca laguna') || str_contains($p, 'andes range') || str_contains($p, 'andes')) {
        return 'PORTILLO INCA LAGOON';
    }

    return 'OTRO';
}

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

function canonicalTourFromGyg(string $product): string
{
    $p = clean($product);
    $pl = mb_strtolower($p, 'UTF-8');
    $pl = removeAccents($pl);

    $numCode = '';
    if (preg_match('/^(\d+)/', $p, $m)) {
        $numCode = $m[1];
    }

    $tag = '';
    if (preg_match('/\[([^\]]+)\]/', $p, $m)) {
        $tag = strtoupper($m[1]);
    }

    $isPrivate =
        preg_match('/^\s*\[?\s*PVT\s+(VLP|MPO|PRT|CTY)\s*\]?/i', $p) === 1 ||
        preg_match('/\bPVT\s+(VLP|MPO|PRT|CTY)\b/i', $p) === 1 ||
        str_contains($pl, 'private');

    if ($numCode === '765423' || str_contains($tag, 'CTY')) {
        return applyPrivateTourLabel('CITY TOUR', $isPrivate);
    }

    if ($numCode === '277632' || str_contains($tag, 'PRT')) {
        return applyPrivateTourLabel('PORTILLO INCA LAGOON', $isPrivate);
    }

    if ($numCode === '273397' || $numCode === '878148' || str_contains($tag, 'VLP') || str_contains($tag, 'VINA') || str_contains($tag, 'VIÑA')) {
        return applyPrivateTourLabel('VALPARAISO & VIÑA DEL MAR', $isPrivate);
    }

    if ($numCode === '1333337' || str_contains($pl, 'tastings & lunch') || str_contains($pl, 'tastings and lunch') || str_contains($pl, 'classic maipo valley')) {
        return applyPrivateTourLabel('WINE TOUR MAIPO VALLEY TASTINGS & LUNCH', $isPrivate);
    }

    if ($numCode === '288540' || str_contains($tag, 'SNMPO') || str_contains($tag, 'MPO') || str_contains($pl, 'maipo')) {
        return applyPrivateTourLabel('WINE TOUR MAIPO VALLEY', $isPrivate);
    }

    return applyPrivateTourLabel(canonicalTour($product), $isPrivate);
}

function isPrivateViatorProductGrade(?string $productCode, ?string $tourGrade): bool
{
    $code = strtoupper(trim((string)($productCode ?? '')));
    $grade = strtoupper(trim((string)($tourGrade ?? '')));

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

function applyPrivateTourLabel(string $tour, bool $isPrivate): string
{
    if (!$isPrivate) {
        return $tour;
    }

    if ($tour === 'VALPARAISO & VIÑA DEL MAR') {
        return 'PVT VALPARAISO & VIÑA DEL MAR';
    }

    if ($tour === 'CITY TOUR') {
        return 'PVT CITY TOUR';
    }

    if ($tour === 'WINE TOUR MAIPO VALLEY') {
        return 'PVT WINE TOUR MAIPO VALLEY';
    }

    if ($tour === 'WINE TOUR MAIPO VALLEY TASTINGS & LUNCH') {
        return 'PVT WINE TOUR MAIPO VALLEY TASTINGS & LUNCH';
    }

    if ($tour === 'PORTILLO INCA LAGOON') {
        return 'PVT PORTILLO INCA LAGOON';
    }

    return $tour;
}

/****************************************************
 * HEADER HELPERS
 ****************************************************/

function findHeader(array $headers, array $candidates): int
{
    $normalizedHeaders = array_map(fn($h) => mb_strtolower(clean($h), 'UTF-8'), $headers);

    foreach ($normalizedHeaders as $i => $header) {
        foreach ($candidates as $candidate) {
            $cand = mb_strtolower(clean($candidate), 'UTF-8');

            if ($header === $cand) {
                return $i;
            }
        }
    }

    return -1;
}

/****************************************************
 * HTML HELPERS
 ****************************************************/

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function countByStatus(array $results, string $status): int
{
    return count(array_filter($results, fn($r) => ($r['status'] ?? '') === $status));
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Stamp's Tour — Check Reservations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <link href="/css/vendors.css" rel="stylesheet">
    <link href="/css/admin.css" rel="stylesheet">
    <link href="/css/custom.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 24px;
            color: #111827;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 4px;
        }

        h2 {
            margin-top: 0;
        }

        p {
            margin-top: 4px;
            color: #4b5563;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        label {
            display: block;
            margin-bottom: 6px;
        }

        textarea {
            width: 100%;
            min-height: 260px;
            font-family: Consolas, monospace;
            font-size: 13px;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            resize: vertical;
        }

        input[type="file"] {
            margin-top: 6px;
            margin-bottom: 18px;
        }

        button {
            background: #1f2937;
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #374151;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            background: white;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 7px;
            vertical-align: top;
        }

        th {
            background: #1f2937;
            color: white;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .ok {
            background: #e6f4ea;
        }

        .review {
            background: #fff4ce;
        }

        .error {
            background: #fce8e6;
        }

        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .badge {
            padding: 10px 14px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
            font-weight: bold;
        }

        .badge.ok-badge {
            background: #e6f4ea;
        }

        .badge.review-badge {
            background: #fff4ce;
        }

        .badge.error-badge {
            background: #fce8e6;
        }

        .error-box {
            background: #fce8e6;
            border: 1px solid #ef4444;
            color: #7f1d1d;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 16px;
        }

        .small-note {
            font-size: 12px;
            color: #6b7280;
        }

        .table-wrapper {
            overflow-x: auto;
            max-height: 75vh;
            border-radius: 10px;
        }

        .nowrap {
            white-space: nowrap;
        }

        .hotel-cell {
            min-width: 260px;
        }

        .product-cell {
            min-width: 260px;
        }

        .copy-area {
            min-height: 180px;
            margin-top: 12px;
        }

        .preview-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            margin-bottom: 12px;
        }

        .preview-actions button {
            background: #374151;
            color: white;
            border: none;
            padding: 9px 14px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .preview-actions button:hover {
            background: #4b5563;
        }

        .preview-card {
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12px;
            background: white;
        }

        .preview-table th,
        .preview-table td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: middle;
            text-align: center;
            white-space: normal;
            word-break: break-word;
        }

        .preview-table th {
            background: #111827;
            color: #ffffff;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .preview-table th:nth-child(1),
        .preview-table td:nth-child(1) { width: 90px; }

        .preview-table th:nth-child(2),
        .preview-table td:nth-child(2) {
            width: 210px;
            font-weight: bold;
        }

        .preview-table th:nth-child(3),
        .preview-table td:nth-child(3) { width: 180px; }
        .preview-table th:nth-child(4),
        .preview-table td:nth-child(4) { width: 55px; }
        .preview-table th:nth-child(5),
        .preview-table td:nth-child(5) { width: 260px; }
        .preview-table th:nth-child(6),
        .preview-table td:nth-child(6) { width: 45px; }
        .preview-table th:nth-child(7),
        .preview-table td:nth-child(7) { width: 130px; }
        .preview-table th:nth-child(8),
        .preview-table td:nth-child(8) { width: 60px; }
        .preview-table th:nth-child(9),
        .preview-table td:nth-child(9) { width: 70px; }
        .preview-table th:nth-child(10),
        .preview-table td:nth-child(10) { width: 230px; }
        .preview-table th:nth-child(11),
        .preview-table td:nth-child(11) { width: 150px; }

        .preview-tour-row td {
            background: #e5e7eb;
            font-weight: bold;
        }

        .preview-total-row td {
            background: #f3f4f6;
            font-weight: bold;
        }

        .preview-empty-cell {
            background: #fafafa;
            color: #9ca3af;
        }

        .preview-shared-cell {
            vertical-align: middle !important;
            text-align: center !important;
            font-weight: bold;
            background: #ffffff;
        }

        .preview-tour-shared-cell {
            background: #e5e7eb;
            writing-mode: horizontal-tb;
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('check'); ?>
<div class="container">

    <h1>Stamp's Tour — Check Reservations</h1>
    <p>Control por código: planilla operativa vs Viator CSV, GetYourGuide Excel, Civitatis Excel y reservas WEB de la BD interna.</p>

    <?php if ($errorMessage !== ''): ?>
        <div class="error-box">
            <strong>Error:</strong> <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" enctype="multipart/form-data">
            <label><strong>1. Pega aquí la planilla operativa</strong></label>

            <textarea
                id="planilla_text"
                name="planilla_text"
                placeholder="Pega aquí la planilla copiada desde Google Sheets / Excel"
            ><?= e($_POST['planilla_text'] ?? '') ?></textarea>

            <div class="preview-actions">
                <button type="button" onclick="renderPlanillaPreview()">Preview grid</button>
                <button type="button" onclick="clearPlanillaPreview()">Clear preview</button>
            </div>

            <div id="planilla_preview_card" class="preview-card" style="display:none;">
                <h2>Vista previa de planilla pegada</h2>
                <p class="small-note">
                    Esta vista normaliza el texto pegado para mostrarlo como tabla. El check sigue usando el contenido original del textarea.
                </p>

                <div class="table-wrapper">
                    <table id="planilla_preview_table" class="preview-table"></table>
                </div>
            </div>

            <br><br>

            <label><strong>2. Archivo Viator / TripAdvisor CSV</strong></label>
            <input type="file" name="viator_file" accept=".csv,.txt">

            <label><strong>3. Archivo GetYourGuide Excel</strong></label>
            <input type="file" name="gyg_file" accept=".xlsx,.xls">

            <label><strong>4. Archivo Civitatis Excel</strong></label>
            <input type="file" name="civitatis_file" accept=".xlsx,.xls">

            <br>

            <button type="submit">Ejecutar check</button>

            <div class="small-note">
                Este módulo solo valida. No envía correos. La BD WEB se consulta automáticamente por la fecha de la planilla y solo toma reservas con estado <strong>realizado</strong>.
            </div>
        </form>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === ''): ?>
        <?php
            $total = count($results);
            $ok = countByStatus($results, 'OK');
            $review = countByStatus($results, 'REVISAR');
            $missing = countByStatus($results, 'NO EXISTE EN ARCHIVO') + countByStatus($results, 'NO EXISTE EN ARCHIVO/BD');
            $noCode = countByStatus($results, 'SIN CÓDIGO');
            $unknown = countByStatus($results, 'FUENTE DESCONOCIDA');
            $errors = $missing + $noCode + $unknown;
            $missingCount = count($missingPassengers);
        ?>

        <div class="summary">
            <div class="badge">Total planilla revisado: <?= e($total) ?></div>
            <div class="badge ok-badge">OK: <?= e($ok) ?></div>
            <div class="badge review-badge">Revisar: <?= e($review) ?></div>
            <div class="badge error-badge">Errores fuertes: <?= e($errors) ?></div>
            <div class="badge error-badge">Faltantes en planilla: <?= e($missingCount) ?></div>
        </div>

        <?php if (!empty($webDbDebug)): ?>
            <div class="card">
                <h2>Estado comparación WEB / BD</h2>
                <?php foreach ($webDbDebug as $debugLine): ?>
                    <div class="small-note"><?= e($debugLine) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Resultado del check de planilla</h2>

            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Problema / Nota</th>
                        <th>Fila Planilla</th>
                        <th>Código</th>
                        <th>Fuente Planilla</th>
                        <th>Fuente Sistema</th>
                        <th>Nombre Planilla</th>
                        <th>Nombre Sistema</th>
                        <th>Tour Planilla</th>
                        <th>Tour Sistema</th>
                        <th>Pax Planilla</th>
                        <th>Pax Sistema</th>
                        <th>Idioma Planilla</th>
                        <th>Idioma Sistema</th>
                        <th>Hotel Planilla</th>
                        <th>Hotel Sistema</th>
                        <th>Phone Sistema</th>
                        <th>Email Sistema</th>
                        <th>Producto Sistema</th>
                        <th>Fila Sistema</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $r): ?>
                        <?php
                            $class = 'error';

                            if (($r['status'] ?? '') === 'OK') {
                                $class = 'ok';
                            } elseif (($r['status'] ?? '') === 'REVISAR') {
                                $class = 'review';
                            }
                        ?>
                        <tr class="<?= e($class) ?>">
                            <td class="nowrap"><strong><?= e($r['status']) ?></strong></td>
                            <td><?= e($r['issues']) ?></td>
                            <td><?= e($r['op_row']) ?></td>
                            <td class="nowrap"><?= e($r['code']) ?></td>
                            <td><?= e($r['op_source']) ?></td>
                            <td><?= e($r['sys_source']) ?></td>
                            <td><?= e($r['op_name']) ?></td>
                            <td><?= e($r['sys_name']) ?></td>
                            <td><?= e($r['op_tour']) ?></td>
                            <td><?= e($r['sys_tour']) ?></td>
                            <td><?= e($r['op_pax']) ?></td>
                            <td><?= e($r['sys_pax']) ?></td>
                            <td><?= e($r['op_lang']) ?></td>
                            <td><?= e($r['sys_lang']) ?></td>
                            <td class="hotel-cell"><?= e($r['op_hotel']) ?></td>
                            <td class="hotel-cell"><?= e($r['sys_hotel']) ?></td>
                            <td><?= e($r['sys_phone']) ?></td>
                            <td><?= e($r['sys_email']) ?></td>
                            <td class="product-cell"><?= e($r['sys_product']) ?></td>
                            <td><?= e($r['sys_row']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($missingPassengers)): ?>
            <div class="card">
                <h2>Pasajeros faltantes en planilla</h2>
                <p class="small-note">
                    Estas reservas aparecen en Viator/GYG/Civitatis/WEB, pero su código no está en la planilla pegada.
                    Abajo queda el bloque copiable en el mismo orden de columnas de tu reserva:
                    nombre, pax, hotel, blanco, teléfono, idioma, origen, email y código.
                </p>

                <div class="table-wrapper">
                    <table>
                        <thead>
                        <tr>
                            <th>Tour</th>
                            <th>Nombre</th>
                            <th>Pax</th>
                            <th>Hotel</th>
                            <th>Blank</th>
                            <th>Phone</th>
                            <th>Lang</th>
                            <th>Origin</th>
                            <th>Email</th>
                            <th>Code</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($missingPassengers as $m): ?>
                            <tr class="error">
                                <td><?= e($m['tour']) ?></td>
                                <td><?= e($m['name']) ?></td>
                                <td><?= e($m['pax']) ?></td>
                                <td class="hotel-cell"><?= e($m['hotel']) ?></td>
                                <td><?= e($m['blank']) ?></td>
                                <td><?= e($m['phone']) ?></td>
                                <td><?= e($m['lang']) ?></td>
                                <td><?= e($m['origin']) ?></td>
                                <td><?= e($m['email']) ?></td>
                                <td class="nowrap"><?= e($m['code']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <label style="margin-top:16px;"><strong>Bloque copiable para agregar a la planilla</strong></label>
                <textarea readonly class="copy-area"><?= e(buildMissingPassengersCopyText($missingPassengers)) ?></textarea>
            </div>
        <?php endif; ?>

        <?php if (empty($missingPassengers)): ?>
            <div class="card ok">
                <strong>No hay pasajeros faltantes en la planilla.</strong>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
function renderPlanillaPreview() {
    const textarea = document.getElementById('planilla_text');
    const table = document.getElementById('planilla_preview_table');
    const card = document.getElementById('planilla_preview_card');

    if (!textarea || !table || !card) return;

    const raw = textarea.value;
    table.innerHTML = '';

    if (!raw.trim()) {
        card.style.display = 'none';
        return;
    }

    const parsedRows = parsePlanillaForPreview(raw);

    if (!parsedRows.length) {
        card.style.display = 'none';
        return;
    }

    const headers = [
        'Fecha',
        'Tour',
        'Nombre',
        'Pax',
        'Hotel',
        'Blank / Pickup',
        'Teléfono',
        'Lang',
        'Origen',
        'Email',
        'Código'
    ];

    const thead = document.createElement('thead');
    const headerTr = document.createElement('tr');

    headers.forEach(h => {
        const th = document.createElement('th');
        th.textContent = h;
        headerTr.appendChild(th);
    });

    thead.appendChild(headerTr);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');

    const dateRowspanInfo = calculateDateRowspans(parsedRows);
    const tourRowspanInfo = calculateTourRowspans(parsedRows);

    parsedRows.forEach((row, index) => {
        const tr = document.createElement('tr');

        if (row.type === 'total') {
            tr.classList.add('preview-total-row');
        }

        if (dateRowspanInfo[index]) {
            const tdDate = document.createElement('td');
            tdDate.textContent = row.date || '';
            tdDate.rowSpan = dateRowspanInfo[index];
            tdDate.classList.add('preview-shared-cell');
            tr.appendChild(tdDate);
        }

        if (tourRowspanInfo[index]) {
            const tdTour = document.createElement('td');
            tdTour.textContent = row.tour || '';
            tdTour.rowSpan = tourRowspanInfo[index];
            tdTour.classList.add('preview-shared-cell', 'preview-tour-shared-cell');
            tr.appendChild(tdTour);
        }

        const values = [
            row.name,
            row.pax,
            row.hotel,
            row.blank,
            row.phone,
            row.lang,
            row.origin,
            row.email,
            row.code
        ];

        values.forEach(value => {
            const td = document.createElement('td');
            td.textContent = value || '';

            if (!String(value || '').trim()) {
                td.classList.add('preview-empty-cell');
            }

            tr.appendChild(td);
        });

        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    card.style.display = 'block';
}

function calculateDateRowspans(rows) {
    const spans = {};
    let start = null;
    let count = 0;
    let currentDate = '';

    rows.forEach((row, index) => {
        if (row.date) {
            if (start !== null && count > 0) {
                spans[start] = count;
            }

            start = index;
            count = 1;
            currentDate = row.date;
            return;
        }

        if (currentDate && start !== null) {
            count++;
        }
    });

    if (start !== null && count > 0) {
        spans[start] = count;
    }

    return spans;
}

function calculateTourRowspans(rows) {
    const spans = {};
    let start = null;
    let count = 0;
    let currentTour = '';

    rows.forEach((row, index) => {
        if (row.tour) {
            if (start !== null && count > 0) {
                spans[start] = count;
            }

            start = index;
            count = 1;
            currentTour = row.tour;
            return;
        }

        if (currentTour && start !== null) {
            count++;
        }
    });

    if (start !== null && count > 0) {
        spans[start] = count;
    }

    return spans;
}

function parsePlanillaForPreview(raw) {
    const lines = raw.split(/\r?\n/);
    const rows = [];

    let currentDate = '';
    let currentTour = '';

    lines.forEach((line) => {
        if (!line.trim()) return;

        const cols = line.split('\t');

        while (cols.length < 12) {
            cols.push('');
        }

        const cleanCols = cols.map(c => String(c || '').trim());

        const a = cleanCols[0] || '';
        const b = cleanCols[1] || '';
        const c = cleanCols[2] || '';

        if (isTotalPreviewRow(cleanCols)) {
            rows.push({
                type: 'total',
                date: '',
                tour: '',
                name: '',
                pax: cleanCols.find(x => x.trim() !== '') || '',
                hotel: '',
                blank: '',
                phone: '',
                lang: '',
                origin: '',
                email: '',
                code: ''
            });
            return;
        }

        if (isDatePreview(a) && isTourPreview(b)) {
            currentDate = a;
            currentTour = b;

            rows.push({
                type: 'data',
                date: currentDate,
                tour: currentTour,
                name: cleanCols[2],
                pax: cleanCols[3],
                hotel: cleanCols[4],
                blank: cleanCols[5],
                phone: cleanCols[6],
                lang: cleanCols[7],
                origin: cleanCols[8],
                email: cleanCols[9],
                code: cleanCols[10]
            });
            return;
        }

        if (isTourPreview(a) && b) {
            currentTour = a;

            rows.push({
                type: 'data',
                date: '',
                tour: currentTour,
                name: cleanCols[1],
                pax: cleanCols[2],
                hotel: cleanCols[3],
                blank: cleanCols[4],
                phone: cleanCols[5],
                lang: cleanCols[6],
                origin: cleanCols[7],
                email: cleanCols[8],
                code: cleanCols[9]
            });
            return;
        }

        /*
         * Google Sheets copy with merged DATE column:
         * A empty, B Tour, C Name, D Pax...
         */
        if (!a && isTourPreview(b) && c) {
            currentTour = b;

            rows.push({
                type: 'data',
                date: '',
                tour: currentTour,
                name: cleanCols[2],
                pax: cleanCols[3],
                hotel: cleanCols[4],
                blank: cleanCols[5],
                phone: cleanCols[6],
                lang: cleanCols[7],
                origin: cleanCols[8],
                email: cleanCols[9],
                code: cleanCols[10]
            });
            return;
        }

        if (!a && !b && c && currentTour) {
            rows.push({
                type: 'data',
                date: '',
                tour: '',
                name: cleanCols[2],
                pax: cleanCols[3],
                hotel: cleanCols[4],
                blank: cleanCols[5],
                phone: cleanCols[6],
                lang: cleanCols[7],
                origin: cleanCols[8],
                email: cleanCols[9],
                code: cleanCols[10]
            });
            return;
        }

        if (a && currentTour) {
            rows.push({
                type: 'data',
                date: '',
                tour: '',
                name: cleanCols[0],
                pax: cleanCols[1],
                hotel: cleanCols[2],
                blank: cleanCols[3],
                phone: cleanCols[4],
                lang: cleanCols[5],
                origin: cleanCols[6],
                email: cleanCols[7],
                code: cleanCols[8]
            });
        }
    });

    return rows;
}

function clearPlanillaPreview() {
    const table = document.getElementById('planilla_preview_table');
    const card = document.getElementById('planilla_preview_card');

    if (table) table.innerHTML = '';
    if (card) card.style.display = 'none';
}

function isDatePreview(value) {
    const s = String(value || '').trim();

    return /^\d{1,2}\/\d{1,2}\/\d{4}$/.test(s) ||
           /^\d{4}-\d{1,2}-\d{1,2}$/.test(s);
}

function isTourPreview(value) {
    const s = String(value || '').toUpperCase();

    return (
        s.includes('VALPARAISO') ||
        s.includes('VALPARAÍSO') ||
        s.includes('VIÑA DEL MAR') ||
        s.includes('VINA DEL MAR') ||
        s.includes('CITY TOUR') ||
        s.includes('MAIPO') ||
        s.includes('PORTILLO') ||
        s.includes('INCA LAGOON') ||
        s.includes('PVT')
    );
}

function isTotalPreviewRow(cols) {
    const nonEmpty = cols.map(c => String(c || '').trim()).filter(Boolean);

    return nonEmpty.length === 1 && !isNaN(Number(nonEmpty[0]));
}

let previewTimer = null;

document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('planilla_text');

    if (!textarea) return;

    textarea.addEventListener('input', function () {
        clearTimeout(previewTimer);

        previewTimer = setTimeout(function () {
            renderPlanillaPreview();
        }, 300);
    });

    textarea.addEventListener('paste', function () {
        setTimeout(function () {
            renderPlanillaPreview();
        }, 100);
    });

    if (textarea.value.trim()) {
        renderPlanillaPreview();
    }
});
</script>

</body>
</html>
