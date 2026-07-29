<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

/****************************************************
 * STAMP'S TOUR — CHECK + CIERRE OPERATIVO
 * Base: check.php
 * Adds:
 * 1) Driver/guide parsing from cierre operativo paste-in.
 * 2) Enrichment from OTA files: adults/children/infants, booking date,
 *    price, net price, currency, private flag, OTA product/option.
 * 3) Visual preview by operational group, similar to reco/check preview.
 *
 * IMPORTANT:
 * - Operational planilla remains the source of truth for: passenger name,
 *   pax, hotel, pickup time, phone, language, source, email and code.
 * - OTA files only enrich and validate.
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

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $string, ?string $encoding = null): string { return strtolower($string); }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $string, ?string $encoding = null): string { return strtoupper($string); }
}

$results = [];
$missingPassengers = [];
$errorMessage = '';
$planillaRows = [];
$groups = [];
$dashboardSaveMessage = '';
$dashboardSaveError = '';
$dashboardSavedClosureId = null;
$secondEnrichmentMessage = '';
$secondEnrichmentError = '';

/* ---------- DB Config para reservas WEB / STAMP_ ---------- */
$dbConnected = false;
$dbError = '';
$webDbDebug = [];
$conn = null;

$dbConfigPath = dirname(__DIR__, 2) . '/db_config.php';
if (!is_file($dbConfigPath)) {
    $dbConfigPath = __DIR__ . '/../db_config.php';
}
if (is_file($dbConfigPath)) {
    include $dbConfigPath;

    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->set_charset('utf8mb4');
        $dbConnected = true;
        $dbNameResult = $conn->query('SELECT DATABASE() AS db_name');
        $dbNameRow = $dbNameResult ? $dbNameResult->fetch_assoc() : null;
        $webDbDebug[] = 'WEB/BD OK: conexión activa. Base actual: ' . ($dbNameRow['db_name'] ?? 'desconocida');
    } else {
        $dbError = 'db_config.php fue cargado, pero $conn no está disponible o tiene error.';
        if (isset($conn) && $conn instanceof mysqli && $conn->connect_error) {
            $dbError .= ' MySQL: ' . $conn->connect_error;
        }
        $webDbDebug[] = 'WEB/BD ERROR: ' . $dbError;
    }
} else {
    $dbError = 'No se encontró db_config.php en: ' . $dbConfigPath;
    $webDbDebug[] = 'WEB/BD ERROR: ' . $dbError;
}


/* ---------- Dashboard DB master data (vineyards / guides / drivers) ---------- */
$dashboardDbName = 'stampst1_dashboard';
$dashboardMasterOptions = [
    'vineyards' => array_map(fn($name) => ['id' => null, 'name' => $name, 'category' => ''], hardcodedVineyardOptions()),
    'guides' => [],
    'drivers' => [],
];

if ($dbConnected && $conn instanceof mysqli) {
    $dashboardMasterOptions = loadDashboardMasterOptions($conn, $dashboardDbName, $webDbDebug);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dashboard'])) {
    try {
        if (!$dbConnected || !($conn instanceof mysqli)) {
            throw new RuntimeException('No hay conexión MySQL activa para guardar en dashboard.');
        }

        $resultsJson = (string)($_POST['results_json'] ?? '');
        $decodedResults = json_decode($resultsJson, true);
        if (!is_array($decodedResults)) {
            throw new RuntimeException('No se pudo leer el JSON del resultado enriquecido. Vuelve a ejecutar el check y guarda nuevamente.');
        }

        $closureTitle = clean($_POST['closure_title'] ?? 'Cierre operativo');
        $closureComments = clean($_POST['closure_comments'] ?? '');
        [$dashboardSavedClosureId, $savedCount, $skippedCount] = saveEnrichedResultsToDashboard($conn, $dashboardDbName, $decodedResults, $closureTitle, $closureComments);
        $dashboardSaveMessage = 'Guardado en dashboard OK. Closure ID: ' . $dashboardSavedClosureId . ' | Reservas guardadas: ' . $savedCount . ' | Saltadas: ' . $skippedCount;
    } catch (Throwable $e) {
        $dashboardSaveError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enrich_existing_results'])) {
    try {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new RuntimeException('PhpSpreadsheet no está disponible. Revisa vendor/autoload.php o phpspreadsheet_lib/vendor/autoload.php.');
        }

        $resultsJson = (string)($_POST['results_json'] ?? '');
        $decodedResults = json_decode($resultsJson, true);
        if (!is_array($decodedResults)) {
            throw new RuntimeException('No se pudo leer el JSON del resultado enriquecido actual. Vuelve al paso anterior y ejecuta el check nuevamente.');
        }
        // Preserve current results on screen even if the second enrichment files fail validation.
        $results = $decodedResults;

        [$extraViatorMap, $extraGygMap, $extraCivitatisMap, $loadedSources] = buildSourceMapsFromUploadedFiles(
            $_FILES['extra_viator_file'] ?? [],
            $_FILES['extra_gyg_file'] ?? [],
            $_FILES['extra_civitatis_file'] ?? []
        );

        if (empty($loadedSources)) {
            throw new RuntimeException('Sube al menos un archivo adicional para enriquecer el resultado actual.');
        }

        [$results, $updatedCount, $notFoundCount] = enrichExistingResultsWithSourceMaps(
            $decodedResults,
            $extraViatorMap,
            $extraGygMap,
            $extraCivitatisMap
        );

        $secondEnrichmentMessage = 'Segundo enriquecimiento aplicado. Archivos leídos: ' . implode(', ', $loadedSources) . ' | Reservas actualizadas: ' . $updatedCount . ' | Códigos no encontrados en archivos adicionales: ' . $notFoundCount;
    } catch (Throwable $e) {
        $secondEnrichmentError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['save_dashboard']) && !isset($_POST['enrich_existing_results'])) {
    try {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new RuntimeException('PhpSpreadsheet no está disponible. Revisa vendor/autoload.php o phpspreadsheet_lib/vendor/autoload.php.');
        }

        $planillaText = $_POST['planilla_text'] ?? '';
        [$planillaRows, $groups] = parseOperationalInput($planillaText, $_FILES['operative_report_file'] ?? []);

        $postedGroupVineyards = parsePostedGroupVineyards($_POST['group_vineyards'] ?? [], $dashboardMasterOptions['vineyards'] ?? []);
        applyVineyardsToPlanilla($planillaRows, $groups, $postedGroupVineyards);

        $postedGroupDrivers = parsePostedMasterOptionSelection($_POST['group_drivers'] ?? [], $dashboardMasterOptions['drivers'] ?? [], true);
        $postedGroupGuides = parsePostedMasterOptionSelection($_POST['group_guides'] ?? [], $dashboardMasterOptions['guides'] ?? [], true);
        applyDriverGuideSelectionsToPlanilla($planillaRows, $groups, $postedGroupDrivers, $postedGroupGuides);

        $postedReservationStatuses = parsePostedReservationStatuses($_POST['reservation_statuses'] ?? []);
        applyReservationStatusesToPlanilla($planillaRows, $groups, $postedReservationStatuses);

        $viatorRows = [];
        $gygRows = [];
        $civitatisRows = [];

        if (!empty($_FILES['viator_file']['tmp_name'])) {
            $viatorRows = parseCsvFile($_FILES['viator_file']['tmp_name']);
        }

        if (!empty($_FILES['gyg_file']['tmp_name'])) {
            $gygRows = parseExcelFile($_FILES['gyg_file']['tmp_name']);
        }

        if (!empty($_FILES['civitatis_file']['tmp_name'])) {
            $civitatisRows = parseCivitatisSourceFile(
                $_FILES['civitatis_file']['tmp_name'],
                $_FILES['civitatis_file']['name'] ?? ''
            );
        }

        $viatorMap = buildViatorMap($viatorRows);
        $gygMap = buildGygMap($gygRows);
        $civitatisMap = buildCivitatisMap($civitatisRows);

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

            $source = normalizeSource($op['source'] ?? '');

            if (str_starts_with($code, 'GYG') || $source === 'GET') {
                $sys = $gygMap[$code] ?? null;
                $expectedSource = 'GET';
            } elseif (str_starts_with($code, 'BR-') || $source === 'TRIP') {
                $sys = $viatorMap[$code] ?? null;
                $expectedSource = 'TRIP';
            } elseif (isCivitatisCode($code) || $source === 'CIV') {
                $sys = $civitatisMap[$code] ?? null;
                $expectedSource = 'CIV';
            } elseif (str_starts_with($code, 'STAMP_') || $source === 'WEB') {
                if (!$dbConnected || !($conn instanceof mysqli)) {
                    $results[] = makeResult(
                        $op,
                        null,
                        'WEB SIN CONEXIÓN BD',
                        'Reserva WEB parseada, pero no se pudo conectar a la BD interna. ' . $dbError
                    );
                    continue;
                }

                $sys = findWebReservationByReferenceId($conn, $code, $webDbDebug);
                $expectedSource = 'WEB';

                if (!$sys) {
                    $results[] = makeResult(
                        $op,
                        null,
                        'NO EXISTE EN BD',
                        'El código WEB/STAMP_ no fue encontrado en reservas.reference_id.'
                    );
                    continue;
                }

                $results[] = compareReservation($op, $sys, $expectedSource);
                continue;
            } else {
                $results[] = makeResult($op, null, 'FUENTE DESCONOCIDA', 'No se pudo identificar si el código pertenece a GYG, TRIP o WEB.');
                continue;
            }

            if (!$sys) {
                $results[] = makeResult($op, null, 'NO EXISTE EN ARCHIVO', 'El código no fue encontrado en el archivo fuente.');
                continue;
            }

            $results[] = compareReservation($op, $sys, $expectedSource);
        }

        $opServiceDates = collectOperationalServiceDates($planillaRows);
        $missingPassengers = findMissingPassengers($viatorMap, $gygMap, $opCodes, $civitatisMap, $opServiceDates);

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

/****************************************************
 * PARSE INPUT OPERATIVO ESTRUCTURADO / PLANILLA ANTIGUA
 ****************************************************/

function parseOperationalInput(string $text, array $operativeFile = []): array
{
    if (!empty($operativeFile['tmp_name']) && is_uploaded_file($operativeFile['tmp_name'])) {
        $matrix = readStructuredOperationalFile($operativeFile['tmp_name'], (string)($operativeFile['name'] ?? ''));
        if (!empty($matrix)) {
            return parseStructuredOperationalMatrix($matrix);
        }
    }

    if (looksLikeStructuredOperationalText($text)) {
        return parseStructuredOperationalText($text);
    }

    return parseCierrePlanillaText($text);
}

function looksLikeStructuredOperationalText(string $text): bool
{
    $lines = preg_split('/\r\n|\n|\r/', trim($text)) ?: [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $cols = splitStructuredLine($line);
        $headerText = strtoupper(removeAccents(implode(' ', $cols)));
        if ((str_contains($headerText, 'CODIGO') || str_contains($headerText, 'RESERVA'))
            && str_contains($headerText, 'TOUR')
            && (str_contains($headerText, 'COMENTARIO') || str_contains($headerText, 'COMMENT'))
        ) {
            return true;
        }
        // If there are many columns and one of them looks like a booking code, treat it as structured.
        if (count($cols) >= 14) {
            foreach ($cols as $c) {
                if (isBookingCodeLike((string)$c)) return true;
            }
        }
        return false;
    }
    return false;
}

function readStructuredOperationalFile(string $tmpPath, string $fileName): array
{
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (in_array($ext, ['xlsx', 'xls'], true)) {
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        return array_values(array_filter(array_map(function ($row) {
            return array_map('clean', $row);
        }, $rows), fn($row) => count(array_filter($row, fn($v) => clean($v) !== '')) > 0));
    }

    $raw = file_get_contents($tmpPath);
    if ($raw === false) return [];
    return structuredTextToMatrix($raw);
}

function parseStructuredOperationalText(string $text): array
{
    return parseStructuredOperationalMatrix(structuredTextToMatrix($text));
}

function structuredTextToMatrix(string $text): array
{
    $lines = preg_split('/\r\n|\n|\r/', trim($text)) ?: [];
    $matrix = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $matrix[] = splitStructuredLine($line);
    }
    return $matrix;
}

function splitStructuredLine(string $line): array
{
    if (str_contains($line, "\t")) {
        return array_map('clean', str_getcsv($line, "\t"));
    }
    if (substr_count($line, ';') >= substr_count($line, ',')) {
        return array_map('clean', str_getcsv($line, ';'));
    }
    return array_map('clean', str_getcsv($line, ','));
}

function parseStructuredOperationalMatrix(array $matrix): array
{
    $rows = [];
    $groups = [];
    if (empty($matrix)) return [[], []];

    $matrix = array_values(array_filter($matrix, fn($r) => count(array_filter($r, fn($v) => clean($v) !== '')) > 0));
    if (empty($matrix)) return [[], []];

    $first = array_map('clean', $matrix[0]);
    $hasHeader = structuredRowLooksLikeHeader($first);
    $map = $hasHeader ? buildStructuredHeaderMap($first) : [];
    $startIndex = $hasHeader ? 1 : 0;

    // Estado compartido: soporta celdas combinadas/shared cells de Google Sheets.
    // Si FECHA, TOUR, DRIVER, GUÍA o VIÑEDO aparecen solo en la primera fila del bloque,
    // se arrastran hacia abajo hasta encontrar una fila subtotal o un nuevo tour.
    $currentDate = '';
    $currentTourRaw = '';
    $currentTour = '';
    $currentIsPrivate = null;
    $currentDriver = '';
    $currentGuide = '';
    $currentVineyards = '';
    $currentHotel = '';
    $currentGroupIndex = -1;
    $groupSequence = 0;

    for ($i = $startIndex; $i < count($matrix); $i++) {
        $r = array_map('clean', $matrix[$i]);
        if (count(array_filter($r, fn($v) => $v !== '')) === 0) continue;
        if (structuredRowLooksLikeHeader($r)) continue;

        $get = function (string $key, int $fallbackIndex = -1) use ($r, $map): string {
            if (isset($map[$key]) && array_key_exists($map[$key], $r)) return clean($r[$map[$key]]);
            if ($fallbackIndex >= 0 && array_key_exists($fallbackIndex, $r)) return clean($r[$fallbackIndex]);
            return '';
        };

        // Fila de cierre de bloque: viene casi vacía y solo contiene el total de pax.
        // Esta fila NO es una reserva; solo declara el total del grupo anterior.
        if (isStructuredGroupBreakRow($r)) {
            if ($currentGroupIndex >= 0 && isset($groups[$currentGroupIndex])) {
                $groups[$currentGroupIndex]['declared_total'] = firstStructuredNumericCell($r);
            }

            // Cierra SOLO el grupo operativo.
            // Importante: NO limpiamos fecha ni tour, porque en Google Sheets esas celdas
            // pueden venir compartidas para varios grupos del mismo tour. La pista real de
            // nuevo grupo es: fila subtotal con total en columna D + nuevo driver/guía luego.
            $currentGroupIndex = -1;
            $currentDriver = '';
            $currentGuide = '';
            $currentVineyards = '';
            $currentHotel = '';
            continue;
        }

        // MODO REPORTE FIJO A-N:
        // A Fecha | B Tour | C Nombre | D Total | E Dirección/Hotel | F Horario
        // G Teléfono | H Idioma | I Origen | J Contacto | K Código
        // L Driver | M Guía | N Viñedo
        // Privado se infiere desde el nombre del tour cuando contiene PVTE/PVT/PRIVATE/PRIVADO.
        $dateCell = normalizeDate($get('service_date', 0));
        $tourRawCell = $get('tour', 1);
        $driverCell = $get('driver', 11);
        $guideCell = $get('guide', 12);
        $vineyardCell = $get('vineyards', 13);
        $hotelCell = $get('hotel', 4);

        $code = normalizeCode($get('booking_reference', 10));
        $name = $get('customer_name', 2);
        if ($code === '' && $name === '') continue;

        // Si aparece una nueva fecha, se actualiza. Si no, se mantiene la anterior.
        if ($dateCell !== '') {
            $currentDate = $dateCell;
        }

        // Si aparece un nuevo tour en columna B, comienza un nuevo bloque.
        // Esto evita mezclar dos grupos aunque tengan el mismo driver/guía.
        if ($tourRawCell !== '') {
            if ($currentGroupIndex >= 0 && !empty($groups[$currentGroupIndex]['row_numbers'])) {
                $currentGroupIndex = -1;
                $currentDriver = '';
                $currentGuide = '';
                $currentVineyards = '';
                $currentHotel = '';
            }
            $currentTourRaw = $tourRawCell;
            $currentIsPrivate = normalizePrivateFlag($currentTourRaw);
            $currentTour = applyPrivateTourLabel(canonicalTour($currentTourRaw), $currentIsPrivate === 1);
        }

        // Si aparece un driver/guía diferente en L/M y ya había reservas en el grupo,
        // se interpreta como inicio de nuevo grupo operativo aunque el tour siga compartido.
        // Esto cubre el caso real: subtotal en D, luego nuevo driver/guía.
        $incomingDriverKey = mb_strtolower(removeAccents($driverCell), 'UTF-8');
        $currentDriverKey = mb_strtolower(removeAccents($currentDriver), 'UTF-8');
        $incomingGuideKey = mb_strtolower(removeAccents($guideCell), 'UTF-8');
        $currentGuideKey = mb_strtolower(removeAccents($currentGuide), 'UTF-8');
        $hasCurrentGroupRows = $currentGroupIndex >= 0 && !empty($groups[$currentGroupIndex]['row_numbers'] ?? []);
        $driverChanged = $driverCell !== '' && $currentDriver !== '' && $incomingDriverKey !== $currentDriverKey;
        $guideChanged = $guideCell !== '' && $currentGuide !== '' && $incomingGuideKey !== $currentGuideKey;
        if ($hasCurrentGroupRows && ($driverChanged || $guideChanged)) {
            $currentGroupIndex = -1;
            $currentDriver = '';
            $currentGuide = '';
            $currentVineyards = '';
            $currentHotel = '';
        }

        // Shared cells: si vienen vacías, se arrastran desde el bloque actual.
        if ($driverCell !== '') $currentDriver = $driverCell;
        if ($guideCell !== '') $currentGuide = $guideCell;
        if ($vineyardCell !== '') $currentVineyards = $vineyardCell;
        if ($hotelCell !== '') $currentHotel = $hotelCell;

        $effectiveDriver = $currentDriver;
        $effectiveGuide = $currentGuide;
        $driverGuideMode = 0;
        $autoReservationStatus = '';
        $autoComment = '';

        if ($effectiveGuide === '' && $effectiveDriver !== '') {
            $effectiveGuide = $effectiveDriver;
            $driverGuideMode = 1;
        } elseif ($effectiveGuide === '' && $effectiveDriver === '') {
            $driverGuideMode = 2;
            $autoReservationStatus = 'refund';
            $autoComment = 'Preseleccionado refund: sin driver ni guía en reporte operativo.';
        }

        if ($currentGroupIndex < 0) {
            $groupSequence++;
            $currentGroupIndex = count($groups);
            $groups[$currentGroupIndex] = [
                'index' => $currentGroupIndex,
                'sequence' => $groupSequence,
                'date' => $currentDate,
                'tour' => $currentTour,
                'tour_raw' => $currentTourRaw,
                'transport' => $effectiveDriver,
                'guide' => $effectiveGuide,
                'driver_guide_mode' => $driverGuideMode,
                'declared_total' => null,
                'calculated_total' => 0,
                'status' => 'NO_DECLARED_TOTAL',
                'vineyards' => $currentVineyards,
                'vineyard_id' => null,
                'driver_id' => null,
                'guide_id' => null,
                'row_numbers' => [],
            ];
        } else {
            // Completa datos del grupo si aparecieron en una fila posterior.
            if (($groups[$currentGroupIndex]['date'] ?? '') === '' && $currentDate !== '') $groups[$currentGroupIndex]['date'] = $currentDate;
            if (($groups[$currentGroupIndex]['tour'] ?? '') === '' && $currentTour !== '') $groups[$currentGroupIndex]['tour'] = $currentTour;
            if (($groups[$currentGroupIndex]['transport'] ?? '') === '' && $effectiveDriver !== '') $groups[$currentGroupIndex]['transport'] = $effectiveDriver;
            if (($groups[$currentGroupIndex]['guide'] ?? '') === '' && $effectiveGuide !== '') $groups[$currentGroupIndex]['guide'] = $effectiveGuide;
            if (($groups[$currentGroupIndex]['vineyards'] ?? '') === '' && $currentVineyards !== '') $groups[$currentGroupIndex]['vineyards'] = $currentVineyards;
            if ((int)($groups[$currentGroupIndex]['driver_guide_mode'] ?? 0) === 2 && $driverGuideMode !== 2) $groups[$currentGroupIndex]['driver_guide_mode'] = $driverGuideMode;
        }

        $pax = parseNumber($get('pax_total', 3));
        $adults = $pax;
        $children = 0;
        $infants = 0;

        $source = normalizeSource($get('source', 8));
        if ($source === '') $source = inferSourceFromBookingCode($code);

        $commentCell = $get('comment', 14); // opcional si después agregas O Comentario.

        $row = [
            'row' => $i + 1,
            'group_index' => $currentGroupIndex,
            'group_sequence' => $groups[$currentGroupIndex]['sequence'] ?? $groupSequence,
            'group_id' => '',
            'date' => $currentDate,
            'tour_raw' => $currentTourRaw,
            'tour' => $currentTour,
            'transport' => $effectiveDriver,
            'guide' => $effectiveGuide,
            'driver_guide_mode' => $driverGuideMode,
            'name' => $name,
            'pax' => $pax,
            'adults_op' => $adults,
            'children_op' => $children,
            'infants_op' => $infants,
            'hotel' => $hotelCell !== '' ? $hotelCell : $currentHotel,
            'vineyards' => $currentVineyards,
            'vineyard_id' => null,
            'driver_id' => null,
            'guide_id' => null,
            'reservation_status' => $autoReservationStatus,
            'pickup' => $get('pickup_time', 5),
            'phone' => cleanPhone($get('phone', 6)),
            'lang' => normalizeLang($get('language', 7)),
            'source' => $source,
            'email' => $get('email', 9),
            'code' => $code,
            'is_private_op' => $currentIsPrivate,
            'comment' => trim(implode(' | ', array_filter([$commentCell, $autoComment], fn($v) => clean($v) !== ''))),
            'declared_group_total' => null,
            'calculated_group_total' => null,
            'group_total_status' => '',
        ];

        $rowIndex = count($rows);
        $rows[] = $row;
        $groups[$currentGroupIndex]['row_numbers'][] = $rowIndex;
        $groups[$currentGroupIndex]['calculated_total'] += $pax;
    }

    foreach ($groups as &$group) {
        $declared = $group['declared_total'];
        $calc = (int)($group['calculated_total'] ?? 0);
        if ($declared === null) {
            $group['status'] = 'NO_DECLARED_TOTAL';
        } elseif ((int)$declared === $calc) {
            $group['status'] = 'OK';
        } else {
            $group['status'] = 'TOTAL_MISMATCH';
        }

        $groupId = buildGroupId($group);
        foreach ($group['row_numbers'] as $rowIndex) {
            if (!isset($rows[$rowIndex])) continue;
            $rows[$rowIndex]['date'] = $group['date'];
            $rows[$rowIndex]['tour'] = $group['tour'];
            $rows[$rowIndex]['tour_raw'] = $group['tour_raw'] ?? $rows[$rowIndex]['tour_raw'];
            $rows[$rowIndex]['transport'] = $group['transport'];
            $rows[$rowIndex]['guide'] = $group['guide'];
            $rows[$rowIndex]['vineyards'] = $group['vineyards'] ?? '';
            $rows[$rowIndex]['group_id'] = $groupId;
            $rows[$rowIndex]['declared_group_total'] = $group['declared_total'];
            $rows[$rowIndex]['calculated_group_total'] = $group['calculated_total'];
            $rows[$rowIndex]['group_total_status'] = $group['status'];
            $rows[$rowIndex]['driver_guide_mode'] = (int)($group['driver_guide_mode'] ?? $rows[$rowIndex]['driver_guide_mode'] ?? 0);
        }
    }
    unset($group);

    return [$rows, $groups];
}

function isStructuredGroupBreakRow(array $row): bool
{
    // En el formato operativo real A-N, la fila de cierre de grupo viene casi vacía
    // y el total declarado aparece en columna D (índice 3). No debe tener nombre,
    // código, driver, guía ni viñedo.
    $d = clean($row[3] ?? '');
    if ($d !== '' && is_numeric(str_replace(',', '.', $d))) {
        foreach ($row as $i => $cell) {
            $v = clean($cell);
            if ($v === '') continue;
            if ((int)$i === 3) continue;
            return false;
        }
        return true;
    }

    // Fallback: acepta una fila con un único número en cualquier columna, por si el
    // pegado desde Sheets mueve el total. No aplica si contiene fecha, tour o código.
    $nonEmpty = [];
    foreach ($row as $cell) {
        $v = clean($cell);
        if ($v === '') continue;
        if (isBookingCodeLike($v) || isTourLike($v) || isDateLike($v)) return false;
        $nonEmpty[] = $v;
    }
    if (empty($nonEmpty)) return false;

    $numericCount = 0;
    foreach ($nonEmpty as $v) {
        if (is_numeric(str_replace(',', '.', $v))) $numericCount++;
    }

    return count($nonEmpty) === 1 && $numericCount === 1;
}

function firstStructuredNumericCell(array $row): ?int
{
    foreach ($row as $cell) {
        $v = clean($cell);
        if ($v !== '' && is_numeric(str_replace(',', '.', $v))) {
            return (int)round((float)str_replace(',', '.', $v));
        }
    }
    return null;
}

function structuredRowLooksLikeHeader(array $row): bool
{
    $txt = strtoupper(removeAccents(implode(' ', $row)));
    return (str_contains($txt, 'CODIGO') || str_contains($txt, 'RESERVA'))
        && (str_contains($txt, 'CLIENTE') || str_contains($txt, 'NOMBRE'));
}

function buildStructuredHeaderMap(array $header): array
{
    $map = [];
    foreach ($header as $i => $h) {
        $key = normalizeStructuredHeader($h);
        if ($key === '') continue;
        $map[$key] = $i;
    }
    return $map;
}

function normalizeStructuredHeader(string $header): string
{
    $h = strtoupper(removeAccents(clean($header)));
    $h = preg_replace('/[^A-Z0-9]+/', ' ', $h);
    $h = trim($h ?? '');
    return match (true) {
        str_contains($h, 'FECHA') && (str_contains($h, 'REALIZ') || str_contains($h, 'SERVICIO') || str_contains($h, 'TOUR')) => 'service_date',
        $h === 'FECHA' => 'service_date',
        str_contains($h, 'TOUR') => 'tour',
        str_contains($h, 'PRIV') => 'is_private',
        str_contains($h, 'CONDUCTOR') || str_contains($h, 'DRIVER') => 'driver',
        str_contains($h, 'GUIA') || str_contains($h, 'GUIDE') => 'guide',
        str_contains($h, 'VINED') || str_contains($h, 'VINA') || str_contains($h, 'WINE') => 'vineyards',
        str_contains($h, 'CODIGO') || str_contains($h, 'BOOKING') || str_contains($h, 'RESERVA') => 'booking_reference',
        str_contains($h, 'CLIENTE') || str_contains($h, 'PASAJERO') || $h === 'NOMBRE' || str_contains($h, 'CUSTOMER') => 'customer_name',
        str_contains($h, 'ADUL') => 'adults',
        str_contains($h, 'NIN') || str_contains($h, 'CHILD') || str_contains($h, 'KID') => 'children',
        str_contains($h, 'INFAN') || str_contains($h, 'BEBE') => 'infants',
        str_contains($h, 'PAX') || str_contains($h, 'PASAJEROS') || $h === 'TOTAL' => 'pax_total',
        str_contains($h, 'HOTEL') || str_contains($h, 'DIRECCION') || str_contains($h, 'DIRECCIÓN') || str_contains($h, 'PICK UP') || str_contains($h, 'PICKUP PLACE') => 'hotel',
        str_contains($h, 'HORA') || str_contains($h, 'HORARIO') || str_contains($h, 'TIME') => 'pickup_time',
        str_contains($h, 'TELEF') || str_contains($h, 'PHONE') || str_contains($h, 'WHATS') => 'phone',
        str_contains($h, 'IDIOMA') || str_contains($h, 'LANG') => 'language',
        str_contains($h, 'ORIGEN') || str_contains($h, 'FUENTE') || str_contains($h, 'SOURCE') => 'source',
        str_contains($h, 'EMAIL') || str_contains($h, 'MAIL') || str_contains($h, 'CORREO') || str_contains($h, 'CONTACTO') => 'email',
        str_contains($h, 'COMENT') || str_contains($h, 'COMMENT') || str_contains($h, 'NOTA') => 'comment',
        default => '',
    };
}

function inferSourceFromBookingCode(string $code): string
{
    $code = normalizeCode($code);
    if (str_starts_with($code, 'GYG')) return 'GET';
    if (str_starts_with($code, 'BR-')) return 'TRIP';
    if (str_starts_with($code, 'STAMP_')) return 'WEB';
    if (isCivitatisCode($code)) return 'CIV';
    return '';
}

function normalizePrivateFlag(mixed $value): ?int
{
    $v = strtoupper(removeAccents(clean($value)));
    if ($v === '') return null;
    if (in_array($v, ['1','SI','S','YES','Y','TRUE','PRIVADO','PRIVATE','PVT','PVTE'], true)) return 1;
    if (in_array($v, ['0','NO','N','FALSE','REGULAR','SHARED','GRUPAL'], true)) return 0;
    return (str_contains($v, 'PRIV') || str_contains($v, 'PRIVATE') || str_contains($v, 'PVT') || str_contains($v, 'PVTE')) ? 1 : null;
}

/****************************************************
 * PARSE CIERRE PLANILLA PEGADA
 ****************************************************/

function parseCierrePlanillaText(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [[], []];
    }

    $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];
    $rows = [];
    $groups = [];

    $currentDate = '';
    $currentTour = '';
    $currentTransport = '';
    $currentGuide = '';
    $currentHotel = ''; // mantiene hotel desde celdas combinadas/compartidas
    $currentGroupIndex = -1;
    $groupSequence = 0;

    foreach ($lines as $lineNumber => $line) {
        if (trim($line) === '') {
            continue;
        }

        $cols = explode("\t", $line);
        for ($i = 0; $i < 24; $i++) {
            if (!isset($cols[$i])) {
                $cols[$i] = '';
            }
        }

        $transportCell = clean($cols[0]);
        $guideCell = clean($cols[1]);

        if (isCierreGroupBreakRow($cols)) {
            if ($currentGroupIndex >= 0) {
                if ($guideCell !== '') {
                    $currentGuide = $guideCell;
                    $groups[$currentGroupIndex]['guide'] = $currentGuide;
                }
                $groups[$currentGroupIndex]['declared_total'] = firstNumericCell($cols);
            }

            $currentGroupIndex = -1;
            $currentTransport = '';
            $currentGuide = '';
            $currentHotel = '';
            continue;
        }

        $detected = detectCierreRowData($cols);

        if (($detected['date'] ?? '') !== '') {
            $currentDate = normalizeDate($detected['date']);
        }
        if (($detected['tour'] ?? '') !== '') {
            $currentTour = canonicalTour($detected['tour']);
        }

        if ($transportCell !== '') {
            $currentTransport = $transportCell;
            $currentGuide = '';
            $currentHotel = '';
            $groupSequence++;
            $currentGroupIndex = count($groups);
            $groups[$currentGroupIndex] = [
                'index' => $currentGroupIndex,
                'sequence' => $groupSequence,
                'date' => $currentDate,
                'tour' => $currentTour,
                'transport' => $currentTransport,
                'guide' => '',
                'driver_guide_mode' => 0,
                'declared_total' => null,
                'calculated_total' => 0,
                'status' => 'PENDING',
                'vineyards' => '',
                'vineyard_id' => null,
                'driver_id' => null,
                'guide_id' => null,
                'row_numbers' => [],
            ];
        }

        $hasPassenger = (($detected['name'] ?? '') !== '' || ($detected['code'] ?? '') !== '');

        if ($currentGroupIndex < 0 && $hasPassenger) {
            $groupSequence++;
            $currentGroupIndex = count($groups);
            $groups[$currentGroupIndex] = [
                'index' => $currentGroupIndex,
                'sequence' => $groupSequence,
                'date' => $currentDate,
                'tour' => $currentTour,
                'transport' => $currentTransport,
                'guide' => '',
                'driver_guide_mode' => 0,
                'declared_total' => null,
                'calculated_total' => 0,
                'status' => 'PENDING',
                'vineyards' => '',
                'vineyard_id' => null,
                'driver_id' => null,
                'guide_id' => null,
                'row_numbers' => [],
            ];
        }

        if ($guideCell !== '' && $currentGroupIndex >= 0) {
            $currentGuide = $guideCell;
            $groups[$currentGroupIndex]['guide'] = $currentGuide;
        }

        if ($currentGroupIndex >= 0) {
            if (($groups[$currentGroupIndex]['date'] ?? '') === '' && $currentDate !== '') {
                $groups[$currentGroupIndex]['date'] = $currentDate;
            }
            if (($groups[$currentGroupIndex]['tour'] ?? '') === '' && $currentTour !== '') {
                $groups[$currentGroupIndex]['tour'] = $currentTour;
            }
        }

        if (!$hasPassenger) {
            continue;
        }

        // Si el hotel viene desde una celda combinada/compartida en Google Sheets,
        // normalmente solo aparece en la primera fila copiada. Aquí lo arrastramos
        // hacia abajo dentro del mismo grupo hasta que aparezca un nuevo hotel
        // o hasta que comience un nuevo grupo/transporte.
        $detectedHotel = clean($detected['hotel'] ?? '');
        if ($detectedHotel !== '') {
            $currentHotel = $detectedHotel;
        }
        $rowHotel = $detectedHotel !== '' ? $detectedHotel : $currentHotel;

        $row = [
            'row' => $lineNumber + 1,
            'group_index' => $currentGroupIndex,
            'group_sequence' => $groupSequence,
            'group_id' => '',
            'date' => $currentDate,
            'tour_raw' => $detected['tour'] ?? '',
            'tour' => $currentTour,
            'transport' => $currentTransport,
            'guide' => $currentGuide,
            'driver_guide_mode' => 0,
            'name' => clean($detected['name'] ?? ''),
            'pax' => parseNumber($detected['pax'] ?? ''),
            'hotel' => $rowHotel,
            'vineyards' => '',
            'vineyard_id' => null,
            'driver_id' => null,
            'guide_id' => null,
            'reservation_status' => '',
            'pickup' => clean($detected['pickup'] ?? ''),
            'phone' => cleanPhone($detected['phone'] ?? ''),
            'lang' => normalizeLang($detected['lang'] ?? ''),
            'source' => normalizeSource($detected['source'] ?? ''),
            'email' => clean($detected['email'] ?? ''),
            'code' => normalizeCode($detected['code'] ?? ''),
            'declared_group_total' => null,
            'calculated_group_total' => null,
            'group_total_status' => '',
            'comment' => clean($detected['comment'] ?? ''),
        ];

        $rows[] = $row;
        $rowIndex = count($rows) - 1;

        if ($currentGroupIndex >= 0) {
            $groups[$currentGroupIndex]['row_numbers'][] = $rowIndex;
            $groups[$currentGroupIndex]['calculated_total'] += $row['pax'];
        }
    }

    foreach ($groups as &$group) {
        if (($group['guide'] ?? '') === '' && ($group['transport'] ?? '') !== '') {
            $group['guide'] = $group['transport'];
            $group['driver_guide_mode'] = 1;
        }

        $declared = $group['declared_total'];
        $calc = (int)($group['calculated_total'] ?? 0);
        if ($declared === null) {
            $group['status'] = 'NO_DECLARED_TOTAL';
        } elseif ((int)$declared === $calc) {
            $group['status'] = 'OK';
        } else {
            $group['status'] = 'TOTAL_MISMATCH';
        }

        $groupId = buildGroupId($group);

        foreach ($group['row_numbers'] as $rowIndex) {
            if (!isset($rows[$rowIndex])) continue;
            $rows[$rowIndex]['date'] = $group['date'];
            $rows[$rowIndex]['tour'] = $group['tour'];
            $rows[$rowIndex]['transport'] = $group['transport'];
            $rows[$rowIndex]['guide'] = $group['guide'];
            $rows[$rowIndex]['driver_guide_mode'] = (int)$group['driver_guide_mode'];
            $rows[$rowIndex]['declared_group_total'] = $group['declared_total'];
            $rows[$rowIndex]['calculated_group_total'] = $group['calculated_total'];
            $rows[$rowIndex]['group_total_status'] = $group['status'];
            $rows[$rowIndex]['vineyards'] = $group['vineyards'] ?? '';
            $rows[$rowIndex]['vineyard_id'] = $group['vineyard_id'] ?? null;
            $rows[$rowIndex]['driver_id'] = $group['driver_id'] ?? null;
            $rows[$rowIndex]['guide_id'] = $group['guide_id'] ?? null;
            $rows[$rowIndex]['group_id'] = $groupId;
        }
    }
    unset($group);

    return [$rows, $groups];
}

function detectCierreRowData(array $cols): array
{
    $data = [
        'date' => '', 'tour' => '', 'name' => '', 'pax' => '', 'hotel' => '',
        'pickup' => '', 'phone' => '', 'lang' => '', 'source' => '', 'email' => '', 'code' => '', 'comment' => ''
    ];
    $clean = array_map('clean', $cols);

    $tourIndex = null;
    foreach ($clean as $i => $value) {
        if ($data['date'] === '' && isDateLike($value)) {
            $data['date'] = $value;
        }
        if ($tourIndex === null && isTourLike($value)) {
            $tourIndex = $i;
            $data['tour'] = $value;
        }
    }

    $codeIndex = null;
    foreach ($clean as $i => $value) {
        if (isBookingCodeLike($value)) {
            $codeIndex = $i;
            $data['code'] = $value;
            break;
        }
    }

    if ($codeIndex !== null && $codeIndex >= 8) {
        return fillDetectedRow($data, $clean, $codeIndex - 8);
    }

    if ($tourIndex !== null && ($clean[$tourIndex + 1] ?? '') !== '') {
        return fillDetectedRow($data, $clean, $tourIndex + 1);
    }

    if (($clean[6] ?? '') !== '') {
        return fillDetectedRow($data, $clean, 6);
    }

    return $data;
}

function fillDetectedRow(array $data, array $clean, int $start): array
{
    $data['name'] = $clean[$start] ?? '';
    $data['pax'] = $clean[$start + 1] ?? '';
    $data['hotel'] = $clean[$start + 2] ?? '';
    $data['pickup'] = $clean[$start + 3] ?? '';
    $data['phone'] = $clean[$start + 4] ?? '';
    $data['lang'] = $clean[$start + 5] ?? '';
    $data['source'] = $clean[$start + 6] ?? '';
    $data['email'] = $clean[$start + 7] ?? '';
    $data['code'] = $data['code'] ?: ($clean[$start + 8] ?? '');
    $data['comment'] = $clean[$start + 9] ?? '';
    return $data;
}

function isCierreGroupBreakRow(array $cols): bool
{
    $nonEmpty = [];
    foreach ($cols as $cell) {
        $v = clean($cell);
        if ($v === '') continue;
        if (isBookingCodeLike($v) || isTourLike($v) || isDateLike($v)) return false;
        $nonEmpty[] = $v;
    }
    if (empty($nonEmpty)) return false;

    $numericCount = 0;
    foreach ($nonEmpty as $v) {
        if (is_numeric(str_replace(',', '.', $v))) $numericCount++;
    }

    return (count($nonEmpty) === 1 && $numericCount === 1)
        || (count($nonEmpty) === 2 && $numericCount === 1);
}

function isBookingCodeLike(string $value): bool
{
    $v = strtoupper(clean($value));
    return str_starts_with($v, 'GYG') || str_starts_with($v, 'BR-') || str_starts_with($v, 'STAMP_') || isCivitatisCode($v);
}

function firstNumericCell(array $cols): ?int
{
    foreach ($cols as $cell) {
        $v = clean($cell);
        if ($v !== '' && is_numeric(str_replace(',', '.', $v))) {
            return (int)round((float)str_replace(',', '.', $v));
        }
    }
    return null;
}

function buildGroupId(array $group): string
{
    return implode('_', [
        $group['date'] ?: 'NO_DATE',
        slugForId($group['tour'] ?? 'NO_TOUR'),
        slugForId($group['transport'] ?? 'NO_TRANSPORT'),
        slugForId($group['guide'] ?? 'NO_GUIDE'),
        str_pad((string)($group['sequence'] ?? 0), 3, '0', STR_PAD_LEFT),
    ]);
}



/****************************************************
 * DASHBOARD MASTER OPTIONS + GROUP ASSIGNMENTS
 * Reads vineyards, guides and drivers from stampst1_dashboard.
 * Falls back to the original vineyard list if the dashboard DB is unavailable.
 ****************************************************/

function hardcodedVineyardOptions(): array
{
    return ['Ballek', 'Santa Ema', 'Casa Molle', 'Emiliana', 'Casas del Bosque', 'Santa ema/Ballek'];
}

function loadDashboardMasterOptions(mysqli $conn, string $dbName, array &$debug = []): array
{
    $options = [
        'vineyards' => array_map(fn($name) => ['id' => null, 'name' => $name, 'category' => ''], hardcodedVineyardOptions()),
        'guides' => [],
        'drivers' => [],
    ];

    $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
    if ($safeDb === '') {
        $debug[] = 'DASHBOARD/BD ERROR: nombre de base dashboard inválido.';
        return $options;
    }

    $tables = [
        'vineyards' => 'vineyards',
        'guides' => 'guides',
        'drivers' => 'drivers',
    ];

    foreach ($tables as $key => $table) {
        $sql = "SELECT id, name, category FROM `{$safeDb}`.`{$table}` WHERE is_active = 1 ORDER BY category IS NULL, category, name";
        $res = $conn->query($sql);
        if (!$res) {
            $debug[] = 'DASHBOARD/BD WARNING: no se pudo leer ' . $table . ': ' . $conn->error;
            continue;
        }

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'id' => isset($row['id']) ? (int)$row['id'] : null,
                'name' => clean($row['name'] ?? ''),
                'category' => clean($row['category'] ?? ''),
            ];
        }
        $res->free();

        if (!empty($rows)) {
            $options[$key] = $rows;
            $debug[] = 'DASHBOARD/BD OK: ' . $table . ' cargados: ' . count($rows);
        }
    }

    $options['vineyards'] = ensureMasterOptionNames($options['vineyards'], ['Santa ema/Ballek']);

    return $options;
}

function ensureMasterOptionNames(array $options, array $names): array
{
    $existing = [];
    foreach ($options as $option) {
        $name = clean($option['name'] ?? '');
        if ($name === '') continue;
        $existing[normalizeMasterOptionKey($name)] = true;
    }

    foreach ($names as $name) {
        $name = clean($name);
        if ($name === '') continue;
        $key = normalizeMasterOptionKey($name);
        if (isset($existing[$key])) continue;
        $options[] = ['id' => null, 'name' => $name, 'category' => ''];
        $existing[$key] = true;
    }

    return $options;
}

function optionLabel(array $option): string
{
    $name = clean($option['name'] ?? '');
    $category = clean($option['category'] ?? '');
    return $category !== '' ? ($name . ' · Cat. ' . $category) : $name;
}

function parsePostedGroupVineyards(mixed $posted, array $vineyardOptions): array
{
    return parsePostedMasterOptionSelection($posted, $vineyardOptions);
}

function parsePostedMasterOptionSelection(mixed $posted, array $options, bool $includeEmptySelections = false): array
{
    if (!is_array($posted)) return [];

    $byId = [];
    $byName = [];
    foreach ($options as $option) {
        $id = $option['id'] ?? null;
        $name = clean($option['name'] ?? '');
        if ($name === '') continue;
        if ($id !== null && $id !== '') {
            $byId[(string)(int)$id] = ['id' => (int)$id, 'name' => $name, 'category' => clean($option['category'] ?? '')];
        }
        $byName[normalizeMasterOptionKey($name)] = ['id' => $id !== null && $id !== '' ? (int)$id : null, 'name' => $name, 'category' => clean($option['category'] ?? '')];
    }

    $out = [];
    foreach ($posted as $sequence => $value) {
        $seq = (int)$sequence;
        if ($seq <= 0) continue;
        if (is_array($value)) $value = reset($value);

        $value = clean($value);
        if ($value === '') {
            if ($includeEmptySelections) {
                $out[$seq] = ['id' => null, 'name' => '', 'category' => ''];
            }
            continue;
        }

        if (isset($byId[$value])) {
            $out[$seq] = $byId[$value];
            continue;
        }

        $key = normalizeMasterOptionKey($value);
        if (isset($byName[$key])) {
            $out[$seq] = $byName[$key];
        }
    }

    return $out;
}

function normalizeMasterOptionKey(string $value): string
{
    $key = mb_strtolower(removeAccents(clean($value)), 'UTF-8');
    $aliases = [
        'esme' => 'esmeralda',
        'molle' => 'casa molle',
    ];
    return $aliases[$key] ?? $key;
}

function applyVineyardsToPlanilla(array &$rows, array &$groups, array $groupVineyards): void
{
    foreach ($groups as &$group) {
        $seq = (int)($group['sequence'] ?? 0);
        if (!array_key_exists($seq, $groupVineyards)) {
            continue;
        }

        $selection = $groupVineyards[$seq] ?? null;
        $vineyardName = is_array($selection) ? clean($selection['name'] ?? '') : '';
        $vineyardId = is_array($selection) && isset($selection['id']) ? $selection['id'] : null;

        $group['vineyards'] = $vineyardName;
        $group['vineyard_id'] = $vineyardId;

        foreach (($group['row_numbers'] ?? []) as $rowIndex) {
            if (isset($rows[$rowIndex])) {
                $rows[$rowIndex]['vineyards'] = $vineyardName;
                $rows[$rowIndex]['vineyard_id'] = $vineyardId;
            }
        }
    }
    unset($group);
}

function applyDriverGuideSelectionsToPlanilla(array &$rows, array &$groups, array $groupDrivers, array $groupGuides): void
{
    foreach ($groups as &$group) {
        $seq = (int)($group['sequence'] ?? 0);

        if (isset($groupDrivers[$seq])) {
            $group['transport'] = clean($groupDrivers[$seq]['name'] ?? $group['transport'] ?? '');
            $group['driver_id'] = $groupDrivers[$seq]['id'] ?? null;
        }

        if (isset($groupGuides[$seq])) {
            $group['guide'] = clean($groupGuides[$seq]['name'] ?? $group['guide'] ?? '');
            $group['guide_id'] = $groupGuides[$seq]['id'] ?? null;
            $group['driver_guide_mode'] = 0;
        }

        foreach (($group['row_numbers'] ?? []) as $rowIndex) {
            if (!isset($rows[$rowIndex])) continue;
            $rows[$rowIndex]['transport'] = $group['transport'] ?? '';
            $rows[$rowIndex]['guide'] = $group['guide'] ?? '';
            $rows[$rowIndex]['driver_id'] = $group['driver_id'] ?? null;
            $rows[$rowIndex]['guide_id'] = $group['guide_id'] ?? null;
            $rows[$rowIndex]['driver_guide_mode'] = (int)($group['driver_guide_mode'] ?? 0);
        }
    }
    unset($group);
}

/****************************************************
 * INDIVIDUAL RESERVATION STATUS
 * Values accepted per passenger/reservation row:
 * - no show
 * - traspaso
 * - refund
 ****************************************************/

function reservationStatusOptions(): array
{
    return ['no show', 'traspaso', 'refund'];
}

function parsePostedReservationStatuses(mixed $posted): array
{
    if (!is_array($posted)) return [];

    $allowed = reservationStatusOptions();
    $allowedByKey = [];
    foreach ($allowed as $status) {
        $allowedByKey[mb_strtolower(removeAccents($status), 'UTF-8')] = $status;
    }

    $out = [];
    foreach ($posted as $sequence => $rows) {
        $seq = (int)$sequence;
        if ($seq <= 0 || !is_array($rows)) continue;

        foreach ($rows as $position => $value) {
            $pos = (int)$position;
            if ($pos < 0) continue;

            $key = mb_strtolower(removeAccents(clean($value)), 'UTF-8');
            if ($key !== '' && isset($allowedByKey[$key])) {
                $out[$seq][$pos] = $allowedByKey[$key];
            }
        }
    }

    return $out;
}

function applyReservationStatusesToPlanilla(array &$rows, array $groups, array $reservationStatuses): void
{
    foreach ($groups as $group) {
        $seq = (int)($group['sequence'] ?? 0);
        if ($seq <= 0 || empty($reservationStatuses[$seq])) continue;

        $rowNumbers = array_values($group['row_numbers'] ?? []);
        foreach ($reservationStatuses[$seq] as $position => $status) {
            $pos = (int)$position;
            if (!isset($rowNumbers[$pos])) continue;

            $rowIndex = $rowNumbers[$pos];
            if (isset($rows[$rowIndex])) {
                $rows[$rowIndex]['reservation_status'] = $status;
            }
        }
    }
}

/****************************************************
 * READ VIATOR CSV
 ****************************************************/

function parseCsvFile(string $filePath): array
{
    $content = file_get_contents($filePath);
    if ($content === false || trim($content) === '') return [];
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = preg_split('/\r\n|\n|\r/', trim($content));
    if (!$lines || count($lines) === 0) return [];
    $delimiter = detectDelimiter($lines[0]);
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $rows[] = str_getcsv($line, $delimiter);
    }
    return $rows;
}

function detectDelimiter(string $line): string
{
    $tabs = substr_count($line, "\t");
    $commas = substr_count($line, ',');
    $semicolons = substr_count($line, ';');
    if ($tabs >= $commas && $tabs >= $semicolons) return "\t";
    if ($semicolons > $commas) return ';';
    return ',';
}

/****************************************************
 * READ GYG EXCEL
 ****************************************************/

function parseExcelFile(string $filePath): array
{
    $spreadsheet = IOFactory::load($filePath);

    // GYG multi-day exports commonly use one worksheet per service date.
    // Read every worksheet and merge them so a booking code can be found
    // even when it is not on the first tab.
    $mergedRows = [];
    $mainHeader = [];

    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        $rows = $sheet->toArray(null, true, true, false);
        $cleanRows = [];

        foreach ($rows as $row) {
            $cleanRow = array_map('cleanExcelCell', $row);
            $hasData = false;

            foreach ($cleanRow as $cell) {
                if ($cell !== '') {
                    $hasData = true;
                    break;
                }
            }

            if ($hasData) {
                $cleanRows[] = $cleanRow;
            }
        }

        if (empty($cleanRows)) {
            continue;
        }

        $sheetHeader = $cleanRows[0];

        if (empty($mergedRows)) {
            $mainHeader = $sheetHeader;
            foreach ($cleanRows as $row) {
                $mergedRows[] = $row;
            }
            continue;
        }

        // If this worksheet repeats the same booking-export header,
        // skip its header row and append only reservation rows.
        $startIndex = excelHeadersLookCompatible($mainHeader, $sheetHeader) ? 1 : 0;
        for ($i = $startIndex; $i < count($cleanRows); $i++) {
            $mergedRows[] = $cleanRows[$i];
        }
    }

    return $mergedRows;
}

function cleanExcelCell(mixed $value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return clean($value);
}

function excelHeadersLookCompatible(array $a, array $b): bool
{
    $normA = array_values(array_filter(array_map(fn($v) => normalizeHeader((string)$v), $a), fn($v) => $v !== ''));
    $normB = array_values(array_filter(array_map(fn($v) => normalizeHeader((string)$v), $b), fn($v) => $v !== ''));

    if (empty($normA) || empty($normB)) {
        return false;
    }

    $intersection = array_intersect($normA, $normB);
    $headerText = implode(' | ', $intersection);

    if (
        str_contains($headerText, 'booking ref')
        || str_contains($headerText, 'booking reference')
        || str_contains($headerText, 'booking no')
        || str_contains($headerText, 'date')
    ) {
        return count($intersection) >= 3;
    }

    return count($intersection) >= max(3, (int)floor(min(count($normA), count($normB)) * 0.55));
}


function parseCivitatisSourceFile(string $filePath, string $originalName = ''): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (in_array($ext, ['csv', 'txt'], true)) {
        return parseCsvFile($filePath);
    }

    // Fallback: some Civitatis files may be CSV even if the name is unusual.
    $sample = @file_get_contents($filePath, false, null, 0, 512);
    if (is_string($sample) && str_contains($sample, ';') && str_contains($sample, 'Reserva')) {
        return parseCsvFile($filePath);
    }

    return parseExcelFile($filePath);
}

/****************************************************
 * BUILD VIATOR MAP — enriched
 ****************************************************/

function buildViatorMap(array $rows): array
{
    if (count($rows) < 2) return [];

    $headers = array_map('clean', $rows[0]);
    $idxBooking = findHeader($headers, ['Booking Reference', 'Booking Ref', 'Booking reference', 'Order No', 'Reference']);
    if ($idxBooking === -1) throw new RuntimeException('No se encontró Booking Reference en el CSV de Viator.');

    $idxStatus = findHeader($headers, ['Status', 'Booking Status']);
    $idxName = findHeader($headers, ['Lead traveler Name', 'Lead Traveler Name', 'Lead Traveller Name', 'Traveler Name', 'Traveller Name', 'Name']);
    $idxProduct = findHeader($headers, ['Product Name', 'Product Title', 'Experience Title', 'Product']);
    $idxProductCode = findHeader($headers, ['Product Code', 'Product code', 'Product ID']);
    $idxTourGrade = findHeader($headers, ['Tour Grade Code', 'Tour Grade', 'Tour Option']);
    $idxPax = findHeader($headers, ['Number of Passengers', 'Travelers', 'Travellers', 'Pax', 'Quantity']);
    $idxAdults = findHeader($headers, ['Adults', 'Adult']);
    $idxChildren = findHeader($headers, ['Children', 'Child']);
    $idxInfants = findHeader($headers, ['Infants', 'Infant']);
    $idxPickup = findHeader($headers, ['Hotel Pickup', 'Pickup', 'Pick-up', 'Pickup Location', 'Hotel', 'Pickup Details', 'Meeting Point Details', 'Special Requirements', 'Special requirements']);
    $idxLang = findHeader($headers, ['Tour Language', 'Language', 'Traveler Language']);
    $idxContact = findHeader($headers, ['Lead traveler Contact Info', 'Lead Traveler Contact Info', 'Contact Info']);
    $idxEmail = findHeader($headers, ['Lead traveler Email', 'Lead Traveler Email', 'Lead traveler email', 'Email']);
    $idxPhone = findHeader($headers, ['Phone', 'Lead traveler Phone', 'Lead Traveler Phone']);
    $idxBookingDate = findHeader($headers, ['Booking Date', 'Booking date', 'Date Booked', 'Created Date']);
    $idxTravelDate = findHeader($headers, ['Travel Date', 'Travel date', 'Service Date', 'TravelDate']);
    $idxCurrency = findHeader($headers, ['Currency']);
    $idxNet = findHeader($headers, ['Net Price', 'Net price', 'Net Rate', 'Net']);
    $idxPrice = findHeader($headers, ['Price', 'Retail Price', 'Total Price', 'Total']);

    $map = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $code = normalizeCode($row[$idxBooking] ?? '');
        if ($code === '') continue;

        $status = strtolower(clean($idxStatus !== -1 ? ($row[$idxStatus] ?? '') : ''));
        if (in_array($status, ['cancelled', 'canceled'], true)) continue;
        if ($status !== '' && !in_array($status, ['confirmed', 'amended'], true)) continue;

        $product = $idxProduct !== -1 ? clean($row[$idxProduct] ?? '') : '';
        $productCode = $idxProductCode !== -1 ? clean($row[$idxProductCode] ?? '') : '';
        $tourGrade = $idxTourGrade !== -1 ? clean($row[$idxTourGrade] ?? '') : '';
        $tour = canonicalTourFromViator($product, $productCode, $tourGrade);
        $isPrivate = isPrivateViatorProductGrade($productCode, $tourGrade);

        $adults = $idxAdults !== -1 ? parseNumber($row[$idxAdults] ?? '') : 0;
        $children = $idxChildren !== -1 ? parseNumber($row[$idxChildren] ?? '') : 0;
        $infants = $idxInfants !== -1 ? parseNumber($row[$idxInfants] ?? '') : 0;
        $pax = $adults + $children + $infants;
        if ($pax === 0) $pax = $idxPax !== -1 ? parseNumber($row[$idxPax] ?? '') : 0;
        if ($pax > 0 && $adults + $children + $infants === 0) $adults = $pax;

        $contactInfo = $idxContact !== -1 ? clean($row[$idxContact] ?? '') : '';
        $email = $idxEmail !== -1 ? clean($row[$idxEmail] ?? '') : '';
        if ($email === '' && $contactInfo !== '') $email = extractEmailFromText($contactInfo);
        $phone = $idxPhone !== -1 ? cleanPhone($row[$idxPhone] ?? '') : '';
        if ($phone === '' && $contactInfo !== '') $phone = cleanPhone($contactInfo);

        $currency = $idxCurrency !== -1 ? clean($row[$idxCurrency] ?? '') : '';
        // Viator / TripAdvisor reports do not provide a reliable public/retail price in this workflow.
        // Leave price_total blank and keep only net_price.
        $price = null;
        $net = parseMoneyValue($idxNet !== -1 ? ($row[$idxNet] ?? '') : '');

        $map[$code] = [
            'source' => 'TRIP', 'code' => $code,
            'name' => $idxName !== -1 ? clean($row[$idxName] ?? '') : '',
            'product' => $product, 'option' => $tourGrade, 'productCode' => $productCode, 'tourGrade' => $tourGrade,
            'tour' => $tour, 'pax' => $pax,
            'adults' => $adults, 'children' => $children, 'infants' => $infants, 'ota_pax_total' => $pax,
            'hotel' => $idxPickup !== -1 ? clean($row[$idxPickup] ?? '') : '',
            'lang' => $idxLang !== -1 ? normalizeLang($row[$idxLang] ?? '') : '',
            'phone' => $phone, 'email' => $email,
            'booking_date' => $idxBookingDate !== -1 ? clean($row[$idxBookingDate] ?? '') : '',
            'service_date' => $idxTravelDate !== -1 ? clean($row[$idxTravelDate] ?? '') : '',
            'currency' => $currency, 'price_total' => $price, 'net_price' => $net,
            'is_private' => $isPrivate ? 1 : 0,
            'status' => $status,
            'row' => $i + 1,
        ];
    }
    return $map;
}

/****************************************************
 * BUILD GYG MAP — enriched
 ****************************************************/

function buildGygMap(array $rows): array
{
    if (count($rows) < 2) return [];

    $headers = array_map('clean', $rows[0]);
    $idxBooking = findHeader($headers, ['Booking Ref #', 'Booking Ref No.', 'Booking Ref No', 'Booking No', 'Booking Number', 'Booking Reference', 'Order No', 'Reference']);
    if ($idxBooking === -1) throw new RuntimeException('No se encontró Booking Ref # / Booking Ref No. en el Excel de GYG.');

    $idxServiceDate = findHeader($headers, ['Date', 'Activity Date', 'Travel Date', 'Service Date']);

    $idxFirstName = findHeader($headers, ["Traveller's First Name", "Traveler's First Name", 'Traveller First Name', 'Traveler First Name', 'First Name']);
    $idxLastName = findHeader($headers, ["Traveller's Surname", "Traveler's Last Name", "Traveler's Surname", "Traveller's Last Name", 'Traveller Surname', 'Traveler Last Name', 'Last Name', 'Surname']);
    $idxProduct = findHeader($headers, ['Product', 'Product Title', 'Activity', 'Experience']);
    $idxOption = findHeader($headers, ['Option', 'Tour Option', 'Product Option']);
    $idxPickup = findHeader($headers, ['Pickup', 'Pick-up', 'Pickup Location', 'Hotel', 'Hotel Pickup', 'Meeting Point', 'Additional Information']);
    $idxLang = findHeader($headers, ['Language', 'Tour Language', 'Activity Language']);
    $idxEmail = findHeader($headers, ['Email', 'Traveller Email', 'Traveler Email']);
    $idxPhone = findHeader($headers, ['Phone', 'Traveller Phone', 'Traveler Phone']);
    $idxAdult = findHeader($headers, ['Adult']);
    $idxChild = findHeader($headers, ['Child']);
    $idxInfant = findHeader($headers, ['Infant']);
    $idxGroup = findHeader($headers, ['Group']);
    $idxPrice = findHeader($headers, ['Price']);
    $idxNet = findHeader($headers, ['Net Price', 'Net price']);
    $idxBookingDate = findHeader($headers, ['Purchase Date (local time)', 'Purchase Date', 'Booking Date']);

    $paxHeaderNames = ['Adult','Senior','Student (with ID)','EU citizens (with ID)','EU Citizens (with ID)','Student EU citizens (with ID)','Student EU Citizens (with ID)','Military (with ID)','Youth','Child','Infant','Group'];
    $paxIndexes = [];
    foreach ($paxHeaderNames as $headerName) {
        $idx = findHeader($headers, [$headerName]);
        if ($idx !== -1) $paxIndexes[] = $idx;
    }

    $map = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $code = normalizeCode($row[$idxBooking] ?? '');
        if ($code === '') continue;

        $firstName = $idxFirstName !== -1 ? clean($row[$idxFirstName] ?? '') : '';
        $lastName = $idxLastName !== -1 ? clean($row[$idxLastName] ?? '') : '';
        $name = trim($firstName . ' ' . $lastName);
        $product = $idxProduct !== -1 ? clean($row[$idxProduct] ?? '') : '';
        $option = $idxOption !== -1 ? clean($row[$idxOption] ?? '') : '';
        $tour = canonicalTourFromGyg($product . ' ' . $option);
        $isPrivate = isPrivateGygOption($option);

        $adults = $idxAdult !== -1 ? parseNumber($row[$idxAdult] ?? '') : 0;
        $children = $idxChild !== -1 ? parseNumber($row[$idxChild] ?? '') : 0;
        $infants = $idxInfant !== -1 ? parseNumber($row[$idxInfant] ?? '') : 0;
        $group = $idxGroup !== -1 ? parseNumber($row[$idxGroup] ?? '') : 0;
        $pax = 0;
        foreach ($paxIndexes as $paxIndex) $pax += parseNumber($row[$paxIndex] ?? '');
        if ($pax === 0 && $group > 0) $pax = $group;
        if ($pax > 0 && $adults + $children + $infants === 0) $adults = $pax;

        $hotel = $idxPickup !== -1 ? clean($row[$idxPickup] ?? '') : '';
        if ($hotel === "'") $hotel = '';
        $priceRaw = $idxPrice !== -1 ? clean($row[$idxPrice] ?? '') : '';
        $netRaw = $idxNet !== -1 ? clean($row[$idxNet] ?? '') : '';
        $price = parseMoneyValue($priceRaw);
        $net = parseMoneyValue($netRaw);
        $currency = detectCurrency($netRaw) ?: detectCurrency($priceRaw);

        $map[$code] = [
            'source' => 'GET', 'code' => $code,
            'name' => $name, 'product' => $product, 'option' => $option,
            'productCode' => '', 'tourGrade' => '', 'tour' => $tour, 'pax' => $pax,
            'adults' => $adults, 'children' => $children, 'infants' => $infants, 'ota_pax_total' => $pax,
            'hotel' => $hotel,
            'lang' => $idxLang !== -1 ? normalizeLang($row[$idxLang] ?? '') : '',
            'phone' => $idxPhone !== -1 ? cleanPhone($row[$idxPhone] ?? '') : '',
            'email' => $idxEmail !== -1 ? clean($row[$idxEmail] ?? '') : '',
            'booking_date' => $idxBookingDate !== -1 ? clean($row[$idxBookingDate] ?? '') : '',
            'service_date' => $idxServiceDate !== -1 ? clean($row[$idxServiceDate] ?? '') : '',
            'currency' => $currency, 'price_total' => $price, 'net_price' => $net,
            'is_private' => $isPrivate ? 1 : 0,
            'status' => 'confirmed',
            'row' => $i + 1,
        ];
    }
    return $map;
}



/****************************************************
 * BUILD CIVITATIS MAP
 * Supports:
 * 1) New Civitatis flat CSV export:
 *    Reserva;IDActividad;Producto;Fecha Reserva;Fecha Realización;Hora;Nombre;Info;Pasajeros;Adultos;...
 * 2) Older grouped Excel layout.
 ****************************************************/

function buildCivitatisMap(array $rows): array
{
    if (count($rows) < 2) return [];

    $firstRow = array_map('clean', $rows[0] ?? []);
    $headerMap = buildCivitatisHeaderIndexMap($firstRow);

    // New flat CSV/export format has headers in first row.
    if (isset($headerMap['reserva']) && isset($headerMap['producto']) && isset($headerMap['fecha_realizacion'])) {
        return buildCivitatisFlatMap($rows);
    }

    // Fallback to old grouped Excel format.
    return buildCivitatisGroupedMap($rows);
}

function buildCivitatisFlatMap(array $rows): array
{
    if (count($rows) < 2) return [];

    $headers = array_map('clean', $rows[0]);
    $idx = buildCivitatisHeaderIndexMap($headers);
    $map = [];

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $reservation = clean($row[$idx['reserva'] ?? -1] ?? '');
        if ($reservation === '' || mb_strtoupper($reservation, 'UTF-8') === 'TOTAL') {
            continue;
        }

        $status = clean($row[$idx['estado'] ?? -1] ?? '');
        // IMPORTANT: Do not skip cancelled Civitatis rows.
        // We still need to match by column A "Reserva" so the operational row is not marked
        // as "NO EXISTE EN ARCHIVO". The cancelled/anulada status is saved in comments/status.
        $statusNorm = mb_strtolower(removeAccents($status), 'UTF-8');

        $product = clean($row[$idx['producto'] ?? -1] ?? '');
        $info = clean($row[$idx['info'] ?? -1] ?? '');
        $name = clean($row[$idx['nombre'] ?? -1] ?? '');

        $adults = parseNumber($row[$idx['adultos'] ?? -1] ?? '');
        $children = sumCivitatisIndexedNumbers($row, $idx['children_indexes'] ?? []);
        $infants = sumCivitatisIndexedNumbers($row, $idx['infant_indexes'] ?? []);
        $pax = parseNumber($row[$idx['pasajeros'] ?? -1] ?? '');
        if ($pax === 0) $pax = $adults + $children + $infants;
        if ($pax > 0 && $adults + $children + $infants === 0) {
            $adults = $pax;
        }

        $pvpRaw = clean($row[$idx['pvp'] ?? -1] ?? '');
        $price = parseMoneyValue($pvpRaw);
        $currency = detectCurrency($pvpRaw);
        if ($currency === '') $currency = 'USD';

        $code = normalizeCode($reservation);
        $serviceDate = clean($row[$idx['fecha_realizacion'] ?? -1] ?? '');
        $bookingDate = clean($row[$idx['fecha_reserva'] ?? -1] ?? '');
        $pickupTime = clean($row[$idx['hora'] ?? -1] ?? '');

        $map[$code] = [
            'source' => 'CIV',
            'code' => $code,
            'name' => $name,
            'product' => $product,
            'option' => $status !== '' ? ('Civitatis · ' . $status) : 'Civitatis',
            'productCode' => clean($row[$idx['idactividad'] ?? -1] ?? ''),
            'tourGrade' => '',
            'tour' => canonicalTourFromCivitatis($product),
            'pax' => $pax,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'ota_pax_total' => $pax,
            'hotel' => cleanCivitatisHotelInfo($info),
            'lang' => detectCivitatisLanguage($product),
            'phone' => '',
            'email' => '',
            'booking_date' => $bookingDate,
            'service_date' => $serviceDate,
            'pickup_time' => $pickupTime,
            'currency' => $currency,
            // Civitatis value is used as net price for the operational closure.
            // Keep price_total blank so it does not get stored as gross/public price.
            'price_total' => null,
            'net_price' => $price,
            'is_private' => 0,
            'status' => $status,
            'row' => $i + 1,
        ];
    }

    return $map;
}

function buildCivitatisGroupedMap(array $rows): array
{
    if (count($rows) < 3) return [];

    $map = [];
    $currentTourTitle = '';
    $currentServiceDate = '';
    $currentPickupTime = '';
    $headerIndexes = null;

    foreach ($rows as $i => $row) {
        $cells = array_map('clean', $row);
        $first = clean($cells[0] ?? '');

        if ($first === '' && count(array_filter($cells, fn($v) => clean($v) !== '')) === 0) {
            continue;
        }

        // Group title row example:
        // Estación de esquí El Portillo..., Tour en español,2026-05-17, 06:30horas
        if ($first !== '' && str_contains($first, ',') && preg_match('/\d{4}-\d{2}-\d{2}/', $first)) {
            [$currentTourTitle, $currentServiceDate, $currentPickupTime] = parseCivitatisGroupHeader($first);
            $headerIndexes = null;
            continue;
        }

        if (mb_strtolower($first, 'UTF-8') === 'reserva') {
            $headerIndexes = buildHeaderIndexMap($cells);
            continue;
        }

        if ($headerIndexes === null) {
            continue;
        }

        $reservation = clean($cells[$headerIndexes['reserva'] ?? 0] ?? '');
        if ($reservation === '' || mb_strtoupper($reservation, 'UTF-8') === 'TOTAL') {
            continue;
        }

        $status = clean($cells[$headerIndexes['estado'] ?? -1] ?? '');
        $statusNorm = mb_strtolower(removeAccents($status), 'UTF-8');
        if (str_contains($statusNorm, 'cancel') || str_contains($statusNorm, 'anulad')) {
            continue;
        }

        $name = clean($cells[$headerIndexes['nombre'] ?? -1] ?? '');
        $info = clean($cells[$headerIndexes['informacion'] ?? -1] ?? '');
        $adults = parseNumber($cells[$headerIndexes['adultos'] ?? -1] ?? '');
        $children = parseNumber($cells[$headerIndexes['ninos'] ?? -1] ?? '');
        $infants = parseNumber($cells[$headerIndexes['infantes'] ?? -1] ?? '');
        $pax = $adults + $children + $infants;
        if ($pax === 0) $pax = $adults;

        $pvpRaw = clean($cells[$headerIndexes['pvp'] ?? -1] ?? '');
        $price = parseMoneyValue($pvpRaw);
        $currency = detectCurrency($pvpRaw);
        if ($currency === '') $currency = 'USD';

        $code = normalizeCode($reservation);
        $tour = canonicalTourFromCivitatis($currentTourTitle);

        $map[$code] = [
            'source' => 'CIV',
            'code' => $code,
            'name' => $name,
            'product' => $currentTourTitle,
            'option' => $status !== '' ? ('Civitatis · ' . $status) : 'Civitatis',
            'productCode' => '',
            'tourGrade' => '',
            'tour' => $tour,
            'pax' => $pax,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'ota_pax_total' => $pax,
            'hotel' => cleanCivitatisHotelInfo($info),
            'lang' => detectCivitatisLanguage($currentTourTitle),
            'phone' => '',
            'email' => '',
            'booking_date' => '',
            'service_date' => $currentServiceDate,
            'pickup_time' => $currentPickupTime,
            'currency' => $currency,
            // Civitatis value is used as net price for the operational closure.
            // Keep price_total blank so it does not get stored as gross/public price.
            'price_total' => null,
            'net_price' => $price,
            'is_private' => 0,
            'status' => $status,
            'row' => $i + 1,
        ];
    }

    return $map;
}

function buildCivitatisHeaderIndexMap(array $headers): array
{
    $map = ['children_indexes' => [], 'infant_indexes' => []];

    foreach ($headers as $i => $header) {
        $h = normalizeHeader((string)$header);

        if ($h === 'reserva') $map['reserva'] = $i;
        elseif ($h === 'idactividad' || $h === 'id actividad') $map['idactividad'] = $i;
        elseif ($h === 'producto') $map['producto'] = $i;
        elseif ($h === 'fecha reserva') $map['fecha_reserva'] = $i;
        elseif ($h === 'fecha realizacion' || $h === 'fecha realización') $map['fecha_realizacion'] = $i;
        elseif ($h === 'hora') $map['hora'] = $i;
        elseif ($h === 'nombre') $map['nombre'] = $i;
        elseif ($h === 'info' || str_contains($h, 'informacion')) $map['info'] = $i;
        elseif ($h === 'pasajeros') $map['pasajeros'] = $i;
        elseif ($h === 'adultos') $map['adultos'] = $i;
        elseif (str_contains($h, '4 a 12')) $map['children_indexes'][] = $i;
        elseif (str_contains($h, 'menores')) $map['infant_indexes'][] = $i;
        elseif ($h === 'pvp') $map['pvp'] = $i;
        elseif ($h === 'estado') $map['estado'] = $i;
        elseif ($h === 'passengers info') $map['passengers_info'] = $i;
    }

    return $map;
}

function sumCivitatisIndexedNumbers(array $row, array $indexes): int
{
    // Civitatis may include duplicate passenger columns in Portuguese and Spanish
    // for the same category. Use the highest non-empty value to avoid double-counting.
    $max = 0;
    foreach ($indexes as $idx) {
        $max = max($max, parseNumber($row[$idx] ?? ''));
    }
    return $max;
}

function buildHeaderIndexMap(array $headers): array
{
    $map = [];
    foreach ($headers as $i => $header) {
        $h = normalizeHeader((string)$header);
        if ($h === 'reserva') $map['reserva'] = $i;
        elseif ($h === 'nombre') $map['nombre'] = $i;
        elseif (str_contains($h, 'informacion') || str_contains($h, 'información') || $h === 'info') $map['informacion'] = $i;
        elseif ($h === 'adultos') $map['adultos'] = $i;
        elseif (str_contains($h, 'ninos') || str_contains($h, 'niños')) $map['ninos'] = $i;
        elseif (str_contains($h, 'menores')) $map['infantes'] = $i;
        elseif ($h === 'pvp') $map['pvp'] = $i;
        elseif ($h === 'estado') $map['estado'] = $i;
    }
    return $map;
}

function parseCivitatisGroupHeader(string $header): array
{
    $parts = array_map('clean', explode(',', $header));
    $date = '';
    $time = '';
    foreach ($parts as $part) {
        if ($date === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $part)) {
            $date = $part;
            continue;
        }
        if ($time === '' && preg_match('/(\d{1,2}:\d{2})/', $part, $m)) {
            $time = $m[1];
        }
    }

    $titleParts = [];
    foreach ($parts as $part) {
        if ($part === $date) break;
        $titleParts[] = $part;
    }

    return [clean(implode(', ', $titleParts)), $date, $time];
}

function cleanCivitatisHotelInfo(string $info): string
{
    $info = preg_replace('/^\s*:\s*/', '', $info) ?? $info;
    return clean($info);
}

function detectCivitatisLanguage(string $title): string
{
    $t = mb_strtolower(removeAccents($title), 'UTF-8');
    if (str_contains($t, 'portugues') || str_contains($t, 'portuguese') || str_contains($t, 'portugues')) return 'BRA';
    if (str_contains($t, 'ingles') || str_contains($t, 'english')) return 'ENG';
    if (str_contains($t, 'espanol') || str_contains($t, 'español')) return 'SPA';

    // Many current Civitatis product names are translated by market.
    if (str_contains($t, 'estacao') || str_contains($t, 'esqui')) return 'BRA';
    if (str_contains($t, 'estacion') || str_contains($t, 'esqui')) return 'SPA';

    return '';
}

function canonicalTourFromCivitatis(string $title): string
{
    return canonicalTour($title);
}

function isCivitatisCode(string $value): bool
{
    return preg_match('/^A\d{5,}$/i', clean($value)) === 1;
}

/****************************************************
 * WEB / BD ENRICHMENT
 ****************************************************/

function findWebReservationByReferenceId(mysqli $conn, string $code, array &$webDbDebug = []): ?array
{
    $code = trim($code);
    if ($code === '') {
        $webDbDebug[] = 'WEB/BD: código vacío, no se consulta.';
        return null;
    }

    $webDbDebug[] = 'WEB/BD: buscando código ' . $code;

    $sql = "
        SELECT
            r.id_reserva,
            r.codigo_externo,
            r.reference_id,
            r.order_id,
            r.capture_id,
            r.fecha_reserva,
            r.fecha_actividad,
            r.adultos,
            r.ninos,
            r.infantes,
            r.subtotal,
            r.total_venta,
            r.estado,
            r.id_experiencia,
            e.nombre AS experiencia_nombre,
            e.nombre_publico AS experiencia_nombre_publico,
            t.nombre AS titular_nombre,
            t.apellido AS titular_apellido,
            t.email AS titular_email,
            t.telefono AS titular_telefono
        FROM reservas r
        LEFT JOIN experiencias e ON e.id_experiencia = r.id_experiencia
        LEFT JOIN titulares t ON t.id_titular = r.id_titular
        WHERE LOWER(TRIM(r.reference_id)) = LOWER(TRIM(?))
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $webDbDebug[] = 'WEB/BD ERROR prepare reference_id: ' . $conn->error;
        return null;
    }

    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $webDbDebug[] = 'WEB/BD: no encontrado por reference_id exacto/lower: ' . $code;

        // Fallback flexible: buscar sufijo STAMP_, codigo_externo, order_id o capture_id.
        $suffix = $code;
        if (stripos($suffix, 'STAMP_') === 0) {
            $suffix = substr($suffix, 6);
        }
        $like = '%' . $suffix . '%';

        $sqlFlex = "
            SELECT
                r.id_reserva,
                r.codigo_externo,
                r.reference_id,
                r.order_id,
                r.capture_id,
                r.fecha_reserva,
                r.fecha_actividad,
                r.adultos,
                r.ninos,
                r.infantes,
                r.subtotal,
                r.total_venta,
                r.estado,
                r.id_experiencia,
                e.nombre AS experiencia_nombre,
                e.nombre_publico AS experiencia_nombre_publico,
                t.nombre AS titular_nombre,
                t.apellido AS titular_apellido,
                t.email AS titular_email,
                t.telefono AS titular_telefono
            FROM reservas r
            LEFT JOIN experiencias e ON e.id_experiencia = r.id_experiencia
            LEFT JOIN titulares t ON t.id_titular = r.id_titular
            WHERE REPLACE(LOWER(TRIM(r.reference_id)), ' ', '') = REPLACE(LOWER(TRIM(?)), ' ', '')
               OR LOWER(r.reference_id) LIKE LOWER(?)
               OR LOWER(TRIM(COALESCE(r.codigo_externo, ''))) = LOWER(TRIM(?))
               OR LOWER(COALESCE(r.codigo_externo, '')) LIKE LOWER(?)
               OR LOWER(TRIM(COALESCE(r.order_id, ''))) = LOWER(TRIM(?))
               OR LOWER(TRIM(COALESCE(r.capture_id, ''))) = LOWER(TRIM(?))
            ORDER BY r.id_reserva DESC
            LIMIT 1
        ";

        $stmtFlex = $conn->prepare($sqlFlex);
        if (!$stmtFlex) {
            $webDbDebug[] = 'WEB/BD ERROR prepare flexible: ' . $conn->error;
            return null;
        }

        $stmtFlex->bind_param('ssssss', $code, $like, $code, $like, $code, $code);
        $stmtFlex->execute();
        $row = $stmtFlex->get_result()->fetch_assoc();
        $stmtFlex->close();

        if (!$row) {
            $webDbDebug[] = 'WEB/BD: no encontrado tampoco por fallback flexible. Sufijo usado: ' . $suffix;
            return null;
        }

        $webDbDebug[] = 'WEB/BD FLEX OK: encontrado id_reserva ' . ($row['id_reserva'] ?? '') . ' reference_id=' . ($row['reference_id'] ?? '');
    } else {
        $webDbDebug[] = 'WEB/BD OK: encontrado id_reserva ' . ($row['id_reserva'] ?? '') . ' reference_id=' . ($row['reference_id'] ?? '');
    }

    $experienceName = (string)($row['experiencia_nombre'] ?? '');
    $experiencePublic = (string)($row['experiencia_nombre_publico'] ?? '');
    $tour = canonicalTour($experienceName . ' ' . $experiencePublic);

    $adults = (int)($row['adultos'] ?? 0);
    $children = (int)($row['ninos'] ?? 0);
    $infants = (int)($row['infantes'] ?? 0);
    $pax = $adults + $children + $infants;

    $name = trim(($row['titular_nombre'] ?? '') . ' ' . ($row['titular_apellido'] ?? ''));

    return [
        'source' => 'WEB',
        'code' => $row['reference_id'] ?? $code,
        'name' => $name,
        'product' => $experiencePublic !== '' ? $experiencePublic : $experienceName,
        'option' => 'WEB · ' . ($row['estado'] ?? ''),
        'productCode' => (string)($row['id_experiencia'] ?? ''),
        'tourGrade' => '',
        'tour' => $tour,
        'pax' => $pax,
        'adults' => $adults,
        'children' => $children,
        'infants' => $infants,
        'ota_pax_total' => $pax,
        'hotel' => '',
        'lang' => '',
        'phone' => cleanPhone($row['titular_telefono'] ?? ''),
        'email' => clean($row['titular_email'] ?? ''),
        'booking_date' => $row['fecha_reserva'] ?? '',
        'currency' => 'USD',
        'price_total' => $row['total_venta'] ?? null,
        'net_price' => $row['total_venta'] ?? null,
        'is_private' => isPrivateTourFromDbRow($row),
        'row' => 'BD reserva #' . ($row['id_reserva'] ?? ''),
    ];
}

function isPrivateTourFromDbRow(array $row): int
{
    $experienceName = strtoupper(removeAccents((string)(($row['experiencia_nombre'] ?? '') . ' ' . ($row['experiencia_nombre_publico'] ?? ''))));
    return (str_contains($experienceName, 'PVT') || str_contains($experienceName, 'PRIVATE') || str_contains($experienceName, 'PRIVADO')) ? 1 : 0;
}


function isSourceCancelledStatus(mixed $value): bool
{
    $v = mb_strtolower(removeAccents(clean($value)), 'UTF-8');
    return $v !== '' && (str_contains($v, 'cancel') || str_contains($v, 'anulad'));
}


/****************************************************
 * SECOND CHANCE ENRICHMENT
 * Keeps the original same-day check intact. After reviewing the enriched result,
 * the operator may upload additional OTA files to fill missing financial/source data
 * or flag manual reschedules/activity differences before saving to the dashboard.
 ****************************************************/

function buildSourceMapsFromUploadedFiles(array $viatorFile, array $gygFile, array $civitatisFile): array
{
    $loadedSources = [];

    $viatorRows = [];
    if (!empty($viatorFile['tmp_name']) && is_uploaded_file($viatorFile['tmp_name'])) {
        $viatorRows = parseCsvFile($viatorFile['tmp_name']);
        $loadedSources[] = 'Viator/TRIP';
    }

    $gygRows = [];
    if (!empty($gygFile['tmp_name']) && is_uploaded_file($gygFile['tmp_name'])) {
        // Important: this keeps the previous stable behavior: first sheet only.
        // Use this second chance step for supplemental enrichment, not for replacing the main same-day check.
        $gygRows = parseExcelFile($gygFile['tmp_name']);
        $loadedSources[] = 'GetYourGuide';
    }

    $civitatisRows = [];
    if (!empty($civitatisFile['tmp_name']) && is_uploaded_file($civitatisFile['tmp_name'])) {
        $civitatisRows = parseCivitatisSourceFile($civitatisFile['tmp_name'], $civitatisFile['name'] ?? '');
        $loadedSources[] = 'Civitatis';
    }

    return [
        buildViatorMap($viatorRows),
        buildGygMap($gygRows),
        buildCivitatisMap($civitatisRows),
        $loadedSources,
    ];
}

function enrichExistingResultsWithSourceMaps(array $results, array $viatorMap, array $gygMap, array $civitatisMap): array
{
    $updated = 0;
    $notFound = 0;

    foreach ($results as &$result) {
        if (!is_array($result)) continue;

        $code = normalizeCode($result['code'] ?? '');
        if ($code === '') continue;

        $sys = findSystemRowForExistingResult($result, $viatorMap, $gygMap, $civitatisMap);
        if (!$sys) {
            $notFound++;
            continue;
        }

        $result = mergeExistingResultWithSystemRow($result, $sys);
        $updated++;
    }
    unset($result);

    return [$results, $updated, $notFound];
}

function findSystemRowForExistingResult(array $result, array $viatorMap, array $gygMap, array $civitatisMap): ?array
{
    $code = normalizeCode($result['code'] ?? '');
    if ($code === '') return null;

    $source = normalizeSource($result['op_source'] ?? '');
    if ($source === '') $source = inferSourceFromBookingCode($code);

    if (($source === 'GET' || str_starts_with($code, 'GYG')) && isset($gygMap[$code])) return $gygMap[$code];
    if (($source === 'TRIP' || str_starts_with($code, 'BR-')) && isset($viatorMap[$code])) return $viatorMap[$code];
    if (($source === 'CIV' || isCivitatisCode($code)) && isset($civitatisMap[$code])) return $civitatisMap[$code];

    // Fallback by code in any source. Useful when origin was typed incorrectly in planilla.
    if (isset($gygMap[$code])) return $gygMap[$code];
    if (isset($viatorMap[$code])) return $viatorMap[$code];
    if (isset($civitatisMap[$code])) return $civitatisMap[$code];

    return null;
}

function mergeExistingResultWithSystemRow(array $result, array $sys): array
{
    $notes = [];

    $opDateDb = normalizeDateForDb($result['date'] ?? '');
    $sysDateDb = normalizeDateForDb($sys['service_date'] ?? '');
    if ($opDateDb !== null && $sysDateDb !== null && $opDateDb !== $sysDateDb) {
        $notes[] = 'Fecha distinta / posible reprogramación manual: planilla ' . $opDateDb . ' / fuente ' . $sysDateDb;
    }

    $opTour = clean($result['op_tour'] ?? '');
    $sysTour = clean($sys['tour'] ?? '');
    if ($opTour !== '' && $sysTour !== '' && $opTour !== $sysTour) {
        $notes[] = 'Actividad distinta: planilla ' . $opTour . ' / fuente ' . $sysTour;
    }

    if ((int)($result['op_pax'] ?? 0) !== 0 && (int)($sys['pax'] ?? 0) !== 0 && (int)$result['op_pax'] !== (int)$sys['pax']) {
        $notes[] = 'Pax distinto: planilla ' . (int)$result['op_pax'] . ' / fuente ' . (int)$sys['pax'];
    }

    if (!compareLang((string)($result['op_lang'] ?? ''), (string)($sys['lang'] ?? ''))) {
        $notes[] = 'Idioma distinto: planilla ' . clean($result['op_lang'] ?? '') . ' / fuente ' . clean($sys['lang'] ?? '');
    }

    if (!compareHotels((string)($result['op_hotel'] ?? ''), (string)($sys['hotel'] ?? ''))) {
        $notes[] = 'Hotel / pickup distinto';
    }

    $sourceStatus = clean($sys['status'] ?? '');
    if (isSourceCancelledStatus($sourceStatus)) {
        $notes[] = 'Reserva en fuente aparece como ' . $sourceStatus;
    }

    // Update only the source/enrichment side. Keep operational truth untouched.
    $result['sys_source'] = $sys['source'] ?? ($result['sys_source'] ?? '');
    $result['sys_name'] = $sys['name'] ?? ($result['sys_name'] ?? '');
    $result['sys_tour'] = $sys['tour'] ?? ($result['sys_tour'] ?? '');
    $result['sys_pax'] = $sys['pax'] ?? ($result['sys_pax'] ?? '');
    $result['sys_lang'] = $sys['lang'] ?? ($result['sys_lang'] ?? '');
    $result['sys_hotel'] = $sys['hotel'] ?? ($result['sys_hotel'] ?? '');
    $result['sys_phone'] = $sys['phone'] ?? ($result['sys_phone'] ?? '');
    $result['sys_email'] = $sys['email'] ?? ($result['sys_email'] ?? '');
    $result['sys_product'] = trim(($sys['product'] ?? '') . ' ' . ($sys['option'] ?? ''));
    $result['sys_row'] = $sys['row'] ?? ($result['sys_row'] ?? '');

    $result['adults'] = $sys['adults'] ?? ($result['adults'] ?? null);
    $result['children'] = $sys['children'] ?? ($result['children'] ?? null);
    $result['infants'] = $sys['infants'] ?? ($result['infants'] ?? null);
    $result['booking_date'] = $sys['booking_date'] ?? ($result['booking_date'] ?? '');
    $result['source_service_date'] = $sys['service_date'] ?? ($result['source_service_date'] ?? '');
    $result['source_booking_status'] = $sys['status'] ?? ($result['source_booking_status'] ?? ($sys['source'] ?? ''));
    $result['currency'] = $sys['currency'] ?? ($result['currency'] ?? '');
    $result['price_total'] = $sys['price_total'] ?? ($result['price_total'] ?? null);
    $result['net_price'] = $sys['net_price'] ?? ($result['net_price'] ?? null);
    $result['is_private'] = $sys['is_private'] ?? ($result['is_private'] ?? null);

    $currentIssues = clean($result['issues'] ?? '');
    if (($result['status'] ?? '') === 'NO EXISTE EN ARCHIVO' || sourceNotFoundIssueExists($currentIssues)) {
        $currentIssues = removeResolvedSourceNotFoundIssues($currentIssues);
    }
    $result['issues'] = appendUniqueIssues($currentIssues, $notes);
    if (!empty($notes) && ($result['status'] ?? '') === 'OK') {
        $result['status'] = 'REVISAR';
    }
    if (($result['status'] ?? '') === 'NO EXISTE EN ARCHIVO') {
        $result['status'] = empty($notes) ? 'OK' : 'REVISAR';
    }

    return $result;
}

function sourceNotFoundIssueExists(string $current): bool
{
    $norm = mb_strtolower(removeAccents($current), 'UTF-8');
    return str_contains($norm, 'codigo no fue encontrado en el archivo fuente')
        || str_contains($norm, 'el codigo no fue encontrado en el archivo fuente')
        || str_contains($norm, 'no existe en archivo');
}

function removeResolvedSourceNotFoundIssues(string $current): string
{
    $kept = [];

    foreach (explode('|', $current) as $part) {
        $part = clean($part);
        if ($part === '') {
            continue;
        }

        $norm = mb_strtolower(removeAccents($part), 'UTF-8');
        $isResolvedMissingMessage =
            str_contains($norm, 'codigo no fue encontrado en el archivo fuente')
            || str_contains($norm, 'el codigo no fue encontrado en el archivo fuente')
            || $norm === 'no existe en archivo';

        if (!$isResolvedMissingMessage) {
            $kept[$part] = true;
        }
    }

    return implode(' | ', array_keys($kept));
}

function appendUniqueIssues(string $current, array $newNotes): string
{
    $parts = [];
    foreach (explode('|', $current) as $part) {
        $part = clean($part);
        if ($part !== '') $parts[$part] = true;
    }
    foreach ($newNotes as $note) {
        $note = clean($note);
        if ($note !== '') $parts[$note] = true;
    }
    return implode(' | ', array_keys($parts));
}

/****************************************************
 * COMPARISON + ENRICHED RESULT
 ****************************************************/

function compareReservation(array $op, array $sys, string $expectedSource): array
{
    $issues = [];
    if (($op['source'] ?? '') !== '' && ($op['source'] ?? '') !== $expectedSource) $issues[] = 'Fuente distinta';
    if (($op['tour'] ?? '') !== ($sys['tour'] ?? '')) $issues[] = 'Tour distinto';
    $opDateDb = normalizeDateForDb($op['date'] ?? '');
    $sysDateDb = normalizeDateForDb($sys['service_date'] ?? '');
    if ($opDateDb !== null && $sysDateDb !== null && $opDateDb !== $sysDateDb) {
        $issues[] = 'Fecha distinta: planilla ' . $opDateDb . ' / fuente ' . $sysDateDb;
    }
    if ((int)($op['pax'] ?? 0) !== (int)($sys['pax'] ?? 0)) $issues[] = 'Pax distinto';
    if (!compareLang($op['lang'] ?? '', $sys['lang'] ?? '')) $issues[] = 'Idioma distinto';
    if (!compareHotels($op['hotel'] ?? '', $sys['hotel'] ?? '')) $issues[] = 'Hotel / pickup distinto';
    if (($op['group_total_status'] ?? '') === 'TOTAL_MISMATCH') $issues[] = 'Total grupo distinto';

    $sourceStatus = clean($sys['status'] ?? '');
    if (isSourceCancelledStatus($sourceStatus)) {
        $issues[] = 'Reserva en fuente aparece como ' . $sourceStatus;
    }

    return makeResult($op, $sys, count($issues) === 0 ? 'OK' : 'REVISAR', implode(' | ', $issues));
}

function makeResult(array $op, ?array $sys, string $status, string $note): array
{
    $priceTotal = $sys['price_total'] ?? null;
    $netPrice = $sys['net_price'] ?? null;
    $isWebResult = (($sys['source'] ?? '') === 'WEB')
        || (($op['source'] ?? '') === 'WEB')
        || str_starts_with((string)($op['code'] ?? ''), 'STAMP_')
        || str_starts_with((string)($sys['code'] ?? ''), 'STAMP_');

    if ($isWebResult) {
        $netPrice = $priceTotal;
    }

    return [
        'status' => $status, 'issues' => $note, 'op_comment' => $op['comment'] ?? '',
        'op_row' => $op['row'] ?? '', 'group_id' => $op['group_id'] ?? '',
        'date' => $op['date'] ?? '', 'vineyards' => $op['vineyards'] ?? '', 'vineyard_id' => $op['vineyard_id'] ?? null, 'reservation_status' => $op['reservation_status'] ?? '', 'transport' => $op['transport'] ?? '', 'driver_id' => $op['driver_id'] ?? null, 'guide' => $op['guide'] ?? '', 'guide_id' => $op['guide_id'] ?? null,
        'mode' => ((int)($op['driver_guide_mode'] ?? 0) === 2 ? 'NO EJECUTADO / REFUND' : (((int)($op['driver_guide_mode'] ?? 0) === 1) ? 'DRIVER/GUIDE' : 'GUIDE + DRIVER')),
        'declared_group_total' => $op['declared_group_total'] ?? '',
        'calculated_group_total' => $op['calculated_group_total'] ?? '',
        'group_total_status' => $op['group_total_status'] ?? '',
        'code' => $op['code'] ?? '',
        'op_source' => $op['source'] ?? '', 'sys_source' => $sys['source'] ?? '',
        'op_name' => $op['name'] ?? '', 'sys_name' => $sys['name'] ?? '',
        'op_tour' => $op['tour'] ?? '', 'sys_tour' => $sys['tour'] ?? '',
        'op_pax' => $op['pax'] ?? '', 'sys_pax' => $sys['pax'] ?? '',
        'adults_op' => $op['adults_op'] ?? null, 'children_op' => $op['children_op'] ?? null, 'infants_op' => $op['infants_op'] ?? null,
        'op_lang' => $op['lang'] ?? '', 'sys_lang' => $sys['lang'] ?? '',
        'op_hotel' => $op['hotel'] ?? '', 'sys_hotel' => $sys['hotel'] ?? '',
        'op_pickup' => $op['pickup'] ?? '', 'op_phone' => $op['phone'] ?? '', 'op_email' => $op['email'] ?? '',
        'sys_phone' => $sys['phone'] ?? '', 'sys_email' => $sys['email'] ?? '',
        'sys_product' => trim(($sys['product'] ?? '') . ' ' . ($sys['option'] ?? '')),
        'sys_row' => $sys['row'] ?? '',
        'adults' => $sys['adults'] ?? null,
        'children' => $sys['children'] ?? null,
        'infants' => $sys['infants'] ?? null,
        'booking_date' => $sys['booking_date'] ?? '',
        'source_service_date' => $sys['service_date'] ?? '',
        'source_booking_status' => $sys['status'] ?? ($sys['source'] ?? ''),
        'currency' => $sys['currency'] ?? '',
        'price_total' => $priceTotal,
        'net_price' => $netPrice,
        'is_private' => $sys['is_private'] ?? ($op['is_private_op'] ?? null),
    ];
}

/****************************************************
 * MISSING PASSENGERS
 ****************************************************/

function collectOperationalServiceDates(array $planillaRows): array
{
    $dates = [];

    foreach ($planillaRows as $row) {
        $date = normalizeDateForDb($row['date'] ?? '');
        if ($date !== null) {
            $dates[$date] = true;
        }
    }

    $out = array_keys($dates);
    sort($out);
    return $out;
}

function sourceRowMatchesOperationalDates(array $row, array $targetDates): bool
{
    if (empty($targetDates)) {
        return false;
    }

    $sourceDate = normalizeDateForDb($row['service_date'] ?? '');
    if ($sourceDate === null) {
        return false;
    }

    return in_array($sourceDate, $targetDates, true);
}

function findMissingPassengers(array $viatorMap, array $gygMap, array $opCodes, array $civitatisMap = [], array $targetDates = []): array
{
    // When OTA files contain many days, only consider as "missing" the reservations
    // whose source service_date matches one of the operational dates in the planilla.
    // If the planilla has no detectable date, return no missing passengers to avoid
    // false positives from a multi-day OTA export.
    if (empty($targetDates)) {
        return [];
    }

    $missing = [];
    foreach ($viatorMap as $code => $row) {
        if (!isset($opCodes[$code]) && !isSourceCancelledStatus($row['status'] ?? '') && sourceRowMatchesOperationalDates($row, $targetDates)) {
            $missing[] = systemRowToMissingPassenger($row);
        }
    }
    foreach ($gygMap as $code => $row) {
        if (!isset($opCodes[$code]) && !isSourceCancelledStatus($row['status'] ?? '') && sourceRowMatchesOperationalDates($row, $targetDates)) {
            $missing[] = systemRowToMissingPassenger($row);
        }
    }
    foreach ($civitatisMap as $code => $row) {
        if (!isset($opCodes[$code]) && !isSourceCancelledStatus($row['status'] ?? '') && sourceRowMatchesOperationalDates($row, $targetDates)) {
            $missing[] = systemRowToMissingPassenger($row);
        }
    }
    usort($missing, function ($a, $b) {
        return [missingCategoryOrder($a['tour'] ?? ''), $a['tour'] ?? '', $a['name'] ?? '']
            <=> [missingCategoryOrder($b['tour'] ?? ''), $b['tour'] ?? '', $b['name'] ?? ''];
    });
    return $missing;
}

function systemRowToMissingPassenger(array $row): array
{
    return [
        'tour' => $row['tour'] ?? '', 'name' => $row['name'] ?? '', 'pax' => $row['pax'] ?? 0,
        'hotel' => $row['hotel'] ?? '', 'blank' => '', 'phone' => $row['phone'] ?? '',
        'lang' => $row['lang'] ?? '', 'origin' => $row['source'] ?? '', 'email' => $row['email'] ?? '', 'code' => $row['code'] ?? '',
    ];
}

function buildMissingPassengersCopyText(array $missingPassengers): string
{
    $lines = [];
    $currentTour = '';
    foreach ($missingPassengers as $m) {
        $tour = $m['tour'] ?? '';
        if ($tour !== $currentTour) {
            if ($currentTour !== '') $lines[] = '';
            $lines[] = $tour;
            $currentTour = $tour;
        }
        $lines[] = implode("\t", [
            $m['name'] ?? '', $m['pax'] ?? '', $m['hotel'] ?? '', '', $m['phone'] ?? '', $m['lang'] ?? '', $m['origin'] ?? '', $m['email'] ?? '', $m['code'] ?? '',
        ]);
    }
    return implode("\n", $lines);
}

function missingCategoryOrder(string $tour): int
{
    $order = [
        'VALPARAISO & VIÑA DEL MAR' => 1, 'PVT VALPARAISO & VIÑA DEL MAR' => 2,
        'CITY TOUR' => 3, 'PVT CITY TOUR' => 4,
        'WINE TOUR MAIPO VALLEY' => 5, 'PVT WINE TOUR MAIPO VALLEY' => 6,
        'PORTILLO INCA LAGOON' => 7, 'PVT PORTILLO INCA LAGOON' => 8,
        'OTRO' => 99,
    ];
    return $order[$tour] ?? 99;
}

/****************************************************
 * NORMALIZATION + MATCHING
 ****************************************************/

function clean(mixed $value): string
{
    $value = (string)($value ?? '');
    $value = str_replace("\u{00A0}", ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value ?? '');
}

function normalizeCode(mixed $value): string { return strtoupper(clean($value)); }

function normalizeSource(mixed $value): string
{
    $s = strtoupper(clean($value));
    if ($s === 'WEB') return 'WEB';
    if ($s === 'GET' || str_contains($s, 'GYG') || str_contains($s, 'GETYOURGUIDE')) return 'GET';
    if ($s === 'TRIP' || str_contains($s, 'VIATOR') || str_contains($s, 'TRIPADVISOR')) return 'TRIP';
    if ($s === 'CIV' || str_contains($s, 'CIVITATIS')) return 'CIV';
    return $s;
}

function normalizeLang(mixed $value): string
{
    $s = strtoupper(clean($value));
    if ($s === '') return '';
    if ($s === 'SPA' || $s === 'ESP' || str_contains($s, 'SPANISH') || str_contains($s, 'ESPAÑOL') || str_contains($s, 'ESPANOL')) return 'SPA';
    if ($s === 'ENG' || str_contains($s, 'ENGLISH') || str_contains($s, 'INGLES') || str_contains($s, 'INGLÉS')) return 'ENG';
    if ($s === 'BRA' || $s === 'POR' || $s === 'PT' || str_contains($s, 'PORTUGUESE') || str_contains($s, 'PORTUGUES') || str_contains($s, 'PORTUGUÉS')) return 'BRA';
    return $s;
}

function normalizeHotel(string $value): string
{
    $s = mb_strtolower(clean($value), 'UTF-8');
    if ($s === '' || $s === "'") return '';
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
    return strtr($text, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U',
        'ñ'=>'n','Ñ'=>'N','ü'=>'u','Ü'=>'U'
    ]);
}

function parseNumber(mixed $value): int
{
    $s = str_replace(',', '.', clean($value));
    if ($s === '' || !is_numeric($s)) return 0;
    return (int)round((float)$s);
}

function cleanPhone(mixed $value): string { return preg_replace('/\D+/', '', (string)($value ?? '')) ?? ''; }

function extractEmailFromText(string $text): string
{
    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) return trim($m[0]);
    return '';
}

function isDateLike(string $value): bool
{
    return preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value) === 1
        || preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value) === 1;
}

function normalizeDate(string $value): string
{
    $v = clean($value);
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    return $v;
}

function isTourLike(string $value): bool { return canonicalTour($value) !== '' && canonicalTour($value) !== 'OTRO'; }

function compareLang(string $a, string $b): bool
{
    $a = normalizeLang($a); $b = normalizeLang($b);
    if ($a === '' || $b === '') return true;
    return $a === $b;
}

function compareHotels(string $a, string $b): bool
{
    $a = normalizeHotel($a); $b = normalizeHotel($b);
    if ($a === '' || $b === '') return true;
    if ($a === $b) return true;
    if (str_contains($a, $b) || str_contains($b, $a)) return true;
    return hotelSimilarity($a, $b) >= 0.55;
}

function hotelSimilarity(string $a, string $b): float
{
    $stopwords = ['hotel','hostal','hostel','santiago','chile','metropolitana','region','avenida','av','calle','de','del','la','el','los','las','the','and','y','en','centro','ph','providencia','condes','vitacura'];
    $tokensA = array_values(array_filter(explode(' ', $a), fn($t) => $t !== '' && !in_array($t, $stopwords, true)));
    $tokensB = array_values(array_filter(explode(' ', $b), fn($t) => $t !== '' && !in_array($t, $stopwords, true)));
    if (empty($tokensA) || empty($tokensB)) return 0.0;
    $intersect = array_intersect($tokensA, $tokensB);
    $union = array_unique(array_merge($tokensA, $tokensB));
    return count($intersect) / max(1, count($union));
}

function normalizeForMatch(string $name): string
{
    if (strpos($name, ',') !== false) {
        [$last, $first] = array_map('trim', explode(',', $name, 2));
        $name = $first . ' ' . $last;
    }
    $name = removeAccents($name);
    $name = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return mb_strtolower(trim($name ?? ''), 'UTF-8');
}

function parseMoneyValue(mixed $value): ?float
{
    $s = clean($value);
    if ($s === '') return null;
    $num = preg_replace('/[^0-9,\.\-]/', '', $s);
    if ($num === '' || $num === '-') return null;
    if (substr_count($num, ',') === 1 && substr_count($num, '.') === 0) $num = str_replace(',', '.', $num);
    else $num = str_replace(',', '', $num);
    return is_numeric($num) ? (float)$num : null;
}

function detectCurrency(string $value): string
{
    if (preg_match('/\b([A-Z]{3})\b/', strtoupper($value), $m)) return $m[1];
    return '';
}

function moneyCell(mixed $value): string
{
    if ($value === null || $value === '') return '';
    return number_format((float)$value, 2, '.', '');
}

/****************************************************
 * TOUR HELPERS
 ****************************************************/

function canonicalTour(string $product): string
{
    $p = removeAccents(mb_strtolower(clean($product), 'UTF-8'));
    if ($p === '') return '';

    $isPrivateText = str_contains($p, 'pvt') || str_contains($p, 'pvte') || str_contains($p, 'private') || str_contains($p, 'privado');

    if ($isPrivateText && (str_contains($p, 'valparaiso') || str_contains($p, 'vina del mar') || str_contains($p, 'casablanca'))) return 'PVT VALPARAISO & VIÑA DEL MAR';
    if ($isPrivateText && (str_contains($p, 'maipo') || str_contains($p, 'wine'))) return 'PVT WINE TOUR MAIPO VALLEY';
    if ($isPrivateText && (str_contains($p, 'city') || str_contains($p, 'discover') || str_contains($p, 'santiago'))) return 'PVT CITY TOUR';
    if ($isPrivateText && (str_contains($p, 'portillo') || str_contains($p, 'inca') || str_contains($p, 'andes'))) return 'PVT PORTILLO INCA LAGOON';

    if ((str_contains($p, 'maipo') && (str_contains($p, 'lunch') || str_contains($p, 'almuerzo') || str_contains($p, 'tastings') || str_contains($p, 'degustaciones')))) return 'WINE TOUR MAIPO VALLEY TASTINGS & LUNCH';
    if (str_contains($p, 'maipo')) return 'WINE TOUR MAIPO VALLEY';
    if (str_contains($p, 'valparaiso') || str_contains($p, 'vina del mar') || str_contains($p, 'casablanca')) return 'VALPARAISO & VIÑA DEL MAR';
    if (str_contains($p, 'city tour') || str_contains($p, 'discover santiago') || str_contains($p, 'ciudad de santiago') || str_contains($p, 'santiago')) return 'CITY TOUR';
    if (str_contains($p, 'portillo') || str_contains($p, 'inca lagoon') || str_contains($p, 'inca laguna') || str_contains($p, 'andes') || str_contains($p, 'estacion de esqui') || str_contains($p, 'san esteban')) return 'PORTILLO INCA LAGOON';
    return 'OTRO';
}

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

function canonicalTourFromGyg(string $productAndOption): string
{
    $s = clean($productAndOption);
    $lower = removeAccents(mb_strtolower($s, 'UTF-8'));
    $isPrivate = isPrivateGygOption($s);
    if (preg_match('/\b765423\b/', $s) || str_contains($lower, 'cty') || str_contains($lower, 'city')) return applyPrivateTourLabel('CITY TOUR', $isPrivate);
    if (preg_match('/\b277632\b/', $s) || str_contains($lower, 'prt') || str_contains($lower, 'portillo')) return applyPrivateTourLabel('PORTILLO INCA LAGOON', $isPrivate);
    if (preg_match('/\b273397\b|\b878148\b/', $s) || str_contains($lower, 'vlp') || str_contains($lower, 'valparaiso') || str_contains($lower, 'vina')) return applyPrivateTourLabel('VALPARAISO & VIÑA DEL MAR', $isPrivate);
    if (preg_match('/\b1333337\b/', $s) || str_contains($lower, 'classic maipo') || (str_contains($lower, 'maipo') && (str_contains($lower, 'tastings') || str_contains($lower, 'tasting') || str_contains($lower, 'lunch') || str_contains($lower, 'almuerzo')))) return applyPrivateTourLabel('WINE TOUR MAIPO VALLEY TASTINGS & LUNCH', $isPrivate);
    if (preg_match('/\b288540\b/', $s) || str_contains($lower, 'mpo') || str_contains($lower, 'maipo')) return applyPrivateTourLabel('WINE TOUR MAIPO VALLEY', $isPrivate);
    return applyPrivateTourLabel(canonicalTour($s), $isPrivate);
}

function isPrivateGygOption(?string $value): bool
{
    $s = trim((string)($value ?? ''));
    return preg_match('/\bPVT\s+(VLP|MPO|PRT|CTY)\b/i', $s) === 1 || stripos($s, 'private') !== false;
}

function isPrivateViatorProductGrade(?string $productCode, ?string $tourGrade): bool
{
    $code = strtoupper(trim((string)($productCode ?? '')));
    $grade = strtoupper(trim((string)($tourGrade ?? '')));
    if ($code === '20268P5') return preg_match('/^TG3(?:\b|~)/i', $grade) === 1;
    if ($code === '20268P8') return preg_match('/^TG1(?:\b|~)/i', $grade) === 1;
    if ($code === '20268P12') return preg_match('/^TG1(?:\b|~)/i', $grade) === 1;
    if ($code === '20268P25') return preg_match('/^TG2(?:\b|~)/i', $grade) === 1;
    return false;
}

function applyPrivateTourLabel(string $tour, bool $isPrivate): string
{
    if (!$isPrivate) return $tour;
    if ($tour === 'VALPARAISO & VIÑA DEL MAR') return 'PVT VALPARAISO & VIÑA DEL MAR';
    if ($tour === 'CITY TOUR') return 'PVT CITY TOUR';
    if ($tour === 'WINE TOUR MAIPO VALLEY') return 'PVT WINE TOUR MAIPO VALLEY';
    if ($tour === 'WINE TOUR MAIPO VALLEY TASTINGS & LUNCH') return 'PVT WINE TOUR MAIPO VALLEY TASTINGS & LUNCH';
    if ($tour === 'PORTILLO INCA LAGOON') return 'PVT PORTILLO INCA LAGOON';
    return $tour;
}

function mainCategory(string $tour): string
{
    $t = strtoupper(removeAccents($tour));
    if (str_contains($t, 'VALPARAISO') || str_contains($t, 'VINA')) return 'VALPARAISO';
    if (str_contains($t, 'MAIPO')) return 'MAIPO';
    if (str_contains($t, 'CITY') || str_contains($t, 'SANTIAGO')) return 'CITY';
    if (str_contains($t, 'PORTILLO') || str_contains($t, 'INCA') || str_contains($t, 'ANDES')) return 'PORTILLO';
    return 'OTHER';
}

function slugForId(string $value): string
{
    $value = removeAccents(strtoupper($value));
    $value = preg_replace('/[^A-Z0-9]+/', '_', $value);
    return trim($value ?? '', '_');
}

function findHeader(array $headers, array $candidates): int
{
    $normalizedHeaders = array_map(fn($h) => normalizeHeader((string)$h), $headers);
    foreach ($candidates as $candidate) {
        $needle = normalizeHeader($candidate);
        foreach ($normalizedHeaders as $i => $header) {
            if ($header === $needle) return $i;
        }
    }
    foreach ($candidates as $candidate) {
        $needle = normalizeHeader($candidate);
        foreach ($normalizedHeaders as $i => $header) {
            if ($needle !== '' && str_contains($header, $needle)) return $i;
        }
    }
    return -1;
}

function normalizeHeader(string $header): string
{
    $header = removeAccents(mb_strtolower(trim($header), 'UTF-8'));
    $header = preg_replace('/\s+/', ' ', $header);
    return trim($header ?? '');
}


/****************************************************
 * SAVE ENRICHED RESULT TO DASHBOARD
 ****************************************************/

function deletePreviousDashboardClosuresForDates(mysqli $conn, string $safeDb, array $dates): void
{
    $dates = array_values(array_unique(array_filter($dates, fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))));
    if (empty($dates)) return;

    $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));
    $dateTypes = str_repeat('s', count($dates));

    $closureIds = [];

    // Closures whose main closure_date matches the uploaded service date.
    $sqlByClosureDate = "SELECT id FROM `{$safeDb}`.`operational_closures` WHERE closure_date IN ({$datePlaceholders})";
    $stmt = $conn->prepare($sqlByClosureDate);
    if (!$stmt) throw new RuntimeException('Prepare delete lookup closure_date error: ' . $conn->error);
    $stmt->bind_param($dateTypes, ...$dates);
    if (!$stmt->execute()) throw new RuntimeException('Execute delete lookup closure_date error: ' . $stmt->error);
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $closureIds[(int)$row['id']] = true;
    }
    $stmt->close();

    // Extra safety: closures with reservations for that service_date, even if closure_date was wrong/blank.
    $sqlByReservationDate = "SELECT DISTINCT closure_id AS id FROM `{$safeDb}`.`operational_reservations` WHERE service_date IN ({$datePlaceholders})";
    $stmt = $conn->prepare($sqlByReservationDate);
    if (!$stmt) throw new RuntimeException('Prepare delete lookup service_date error: ' . $conn->error);
    $stmt->bind_param($dateTypes, ...$dates);
    if (!$stmt->execute()) throw new RuntimeException('Execute delete lookup service_date error: ' . $stmt->error);
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $closureIds[(int)$row['id']] = true;
    }
    $stmt->close();

    $ids = array_values(array_filter(array_keys($closureIds), fn($id) => $id > 0));
    if (empty($ids)) return;

    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $idTypes = str_repeat('i', count($ids));

    // Delete reservations first; FK cascade may also exist, but this is explicit and safe.
    $sqlDeleteReservations = "DELETE FROM `{$safeDb}`.`operational_reservations` WHERE closure_id IN ({$idPlaceholders})";
    $stmt = $conn->prepare($sqlDeleteReservations);
    if (!$stmt) throw new RuntimeException('Prepare delete previous reservations error: ' . $conn->error);
    $stmt->bind_param($idTypes, ...$ids);
    if (!$stmt->execute()) throw new RuntimeException('Delete previous reservations error: ' . $stmt->error);
    $stmt->close();

    $sqlDeleteClosures = "DELETE FROM `{$safeDb}`.`operational_closures` WHERE id IN ({$idPlaceholders})";
    $stmt = $conn->prepare($sqlDeleteClosures);
    if (!$stmt) throw new RuntimeException('Prepare delete previous closures error: ' . $conn->error);
    $stmt->bind_param($idTypes, ...$ids);
    if (!$stmt->execute()) throw new RuntimeException('Delete previous closures error: ' . $stmt->error);
    $stmt->close();
}

function saveEnrichedResultsToDashboard(mysqli $conn, string $dbName, array $results, string $title = 'Cierre operativo', string $comments = ''): array
{
    $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
    if ($safeDb === '') {
        throw new RuntimeException('Nombre de base dashboard inválido.');
    }

    if (empty($results)) {
        throw new RuntimeException('No hay registros enriquecidos para guardar.');
    }

    $reservationColumns = getTableColumns($conn, $safeDb, 'operational_reservations');
    if (empty($reservationColumns)) {
        throw new RuntimeException('No se pudo leer la estructura de operational_reservations.');
    }

    $closureDate = null;
    $serviceDates = [];
    foreach ($results as $r) {
        $d = normalizeDateForDb($r['date'] ?? '');
        if ($d !== null) {
            $serviceDates[$d] = true;
            if ($closureDate === null) $closureDate = $d;
        }
    }
    if ($closureDate === null) $closureDate = date('Y-m-d');
    $serviceDates[$closureDate] = true;
    $targetDates = array_keys($serviceDates);

    $conn->begin_transaction();

    try {
        // Replacement mode: if the same operation date is uploaded again,
        // delete the previous dashboard closure(s) for that date before saving the new one.
        // This makes the latest upload the valid one and prevents duplicated daily operations.
        deletePreviousDashboardClosuresForDates($conn, $safeDb, $targetDates);

        $sqlClosure = "INSERT INTO `{$safeDb}`.`operational_closures` (closure_date, title, comments) VALUES (?, ?, ?)";
        $stmtClosure = $conn->prepare($sqlClosure);
        if (!$stmtClosure) throw new RuntimeException('Prepare closure error: ' . $conn->error);
        $stmtClosure->bind_param('sss', $closureDate, $title, $comments);
        if (!$stmtClosure->execute()) throw new RuntimeException('Insert closure error: ' . $stmtClosure->error);
        $closureId = (int)$conn->insert_id;
        $stmtClosure->close();

        $saved = 0;
        $skipped = 0;

        foreach ($results as $r) {
            $bookingReference = normalizeCode($r['code'] ?? '');
            if ($bookingReference === '') {
                $skipped++;
                continue;
            }

            $validationStatus = clean($r['reservation_status'] ?? '');
            if ($validationStatus === '') $validationStatus = 'normal';
            $allowedValidation = ['normal', 'no show', 'traspaso', 'refund'];
            if (!in_array($validationStatus, $allowedValidation, true)) $validationStatus = 'normal';

            $adults = nullableInt($r['adults'] ?? null) ?? nullableInt($r['adults_op'] ?? null) ?? 0;
            $children = nullableInt($r['children'] ?? null) ?? nullableInt($r['children_op'] ?? null) ?? 0;
            $infants = nullableInt($r['infants'] ?? null) ?? nullableInt($r['infants_op'] ?? null) ?? 0;
            $paxTotal = nullableInt($r['op_pax'] ?? null) ?? ($adults + $children + $infants);

            $issueComments = clean($r['issues'] ?? '');
            $opComment = clean($r['op_comment'] ?? '');
            $combinedComments = trim($issueComments . ($opComment !== '' ? ($issueComments !== '' ? ' | ' : '') . 'Comentario operativo: ' . $opComment : ''));

            $rawJson = json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($rawJson === false) $rawJson = null;

            $baseData = [
                'closure_id' => $closureId,
                'service_date' => normalizeDateForDb($r['date'] ?? ''),
                'booking_date' => normalizeDateForDb($r['booking_date'] ?? ''),
                'booking_reference' => $bookingReference,
                'tour_id' => resolveDashboardTourId($conn, $safeDb, $r),
                'vineyard_id' => nullableInt($r['vineyard_id'] ?? null),
                'is_private' => nullableBoolInt($r['is_private'] ?? null),
                'customer_name' => clean($r['op_name'] ?? ''),
                'adults' => $adults,
                'children' => $children,
                'infants' => $infants,
                'pax_total' => $paxTotal,
                'hotel' => clean($r['op_hotel'] ?? ''),
                'pickup_time' => clean($r['op_pickup'] ?? ''),
                'phone' => clean($r['op_phone'] ?? ''),
                'language' => normalizeLang($r['op_lang'] ?? ''),
                'source' => normalizeSource($r['op_source'] ?? ''),
                'driver_id' => nullableInt($r['driver_id'] ?? null),
                'guide_id' => nullableInt($r['guide_id'] ?? null),
                'match_status' => clean($r['status'] ?? ''),
                'validation_status' => $validationStatus,
                'comments' => $combinedComments,
                'operational_comment' => $opComment,
                'source_service_date' => normalizeDateForDb($r['source_service_date'] ?? ''),
                'source_booking_status' => clean($r['source_booking_status'] ?? ($r['sys_source'] ?? '')),
                // Financial fields saved directly in operational_reservations.
                // Viator/TRIP keeps price_total as NULL and net_price with the available net value.
                'price_total' => nullableMoney($r['price_total'] ?? null),
                'net_price' => nullableMoney($r['net_price'] ?? null),
                'currency' => clean($r['currency'] ?? ''),
                'raw_data' => $rawJson,
            ];

            // Only insert columns that actually exist in the table. This keeps the script compatible
            // whether you created vineyard_id or kept the first minimal schema.
            $data = [];
            foreach ($baseData as $col => $val) {
                if (isset($reservationColumns[$col])) $data[$col] = $val;
            }

            $cols = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $quotedCols = '`' . implode('`,`', $cols) . '`';

            // If the same booking_reference appears twice within the same upload/closure,
            // keep the last processed row instead of failing with duplicate key.
            $updateCols = array_values(array_filter($cols, fn($col) => !in_array($col, ['closure_id', 'booking_reference'], true)));
            $updateSql = [];
            foreach ($updateCols as $col) {
                $updateSql[] = "`{$col}` = VALUES(`{$col}`)";
            }
            if (isset($reservationColumns['updated_at'])) {
                $updateSql[] = "`updated_at` = CURRENT_TIMESTAMP";
            }

            $sql = "INSERT INTO `{$safeDb}`.`operational_reservations` ({$quotedCols}) VALUES ({$placeholders})";
            if (!empty($updateSql)) {
                $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateSql);
            }

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new RuntimeException('Prepare reservation error: ' . $conn->error . ' SQL: ' . $sql);

            $types = '';
            $values = [];
            foreach ($cols as $col) {
                $val = $data[$col];
                if (in_array($col, ['closure_id','tour_id','vineyard_id','is_private','adults','children','infants','pax_total','driver_id','guide_id'], true)) {
                    $types .= 'i';
                    $values[] = $val === null || $val === '' ? null : (int)$val;
                } else {
                    $types .= 's';
                    $values[] = $val === null ? null : (string)$val;
                }
            }

            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) throw new RuntimeException('Insert reservation error para ' . $bookingReference . ': ' . $stmt->error);
            $stmt->close();
            $saved++;
        }

        $conn->commit();
        return [$closureId, $saved, $skipped];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}


function resolveDashboardTourId(mysqli $conn, string $dbName, array $row): ?int
{
    $code = mapResultToDashboardTourCode($row);
    if ($code === '') {
        return null;
    }

    static $cache = [];
    $cacheKey = $dbName . '|' . $code;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
    if ($safeDb === '') {
        $cache[$cacheKey] = null;
        return null;
    }

    $stmt = $conn->prepare("SELECT id FROM `{$safeDb}`.`tours` WHERE code = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) {
        $cache[$cacheKey] = null;
        return null;
    }

    $stmt->bind_param('s', $code);
    if (!$stmt->execute()) {
        $stmt->close();
        $cache[$cacheKey] = null;
        return null;
    }

    $res = $stmt->get_result();
    $found = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $cache[$cacheKey] = $found && isset($found['id']) ? (int)$found['id'] : null;
    return $cache[$cacheKey];
}

function mapResultToDashboardTourCode(array $row): string
{
    $haystack = implode(' ', [
        $row['op_tour'] ?? '',
        $row['sys_tour'] ?? '',
        $row['sys_product'] ?? '',
        $row['code'] ?? '',
    ]);

    $text = normalizeTourTextForDashboard($haystack);

    // Specific option first, before generic Maipo mapping.
    if (
        str_contains($text, 'TASTINGS LUNCH')
        || str_contains($text, 'TASTING LUNCH')
        || str_contains($text, 'TASTINGS AND LUNCH')
        || str_contains($text, 'TASTING AND LUNCH')
        || str_contains($text, '3 TASTINGS')
        || str_contains($text, 'THREE TASTINGS')
        || str_contains($text, 'CLASSIC MAIPO')
        || str_contains($text, 'MAIPO VALLEY TASTINGS')
    ) {
        return 'Maipo_Tastings_Lunch';
    }

    if (str_contains($text, 'VALPARAISO') || str_contains($text, 'VINA DEL MAR') || str_contains($text, 'CASABLANCA')) {
        return 'Valparaiso';
    }

    if (str_contains($text, 'MAIPO') || str_contains($text, 'WINE TOUR')) {
        return 'Maipo';
    }

    if (str_contains($text, 'PORTILLO') || str_contains($text, 'INCA LAGOON') || str_contains($text, 'INCA LAGUNA') || str_contains($text, 'ANDES')) {
        return 'Andes';
    }

    if (str_contains($text, 'CITY TOUR') || str_contains($text, 'SANTIAGO')) {
        return 'Santiago';
    }

    return '';
}

function normalizeTourTextForDashboard(string $value): string
{
    $value = removeAccents($value);
    $value = strtoupper($value);
    $value = str_replace(['&', '+'], ' AND ', $value);
    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}


function getTableColumns(mysqli $conn, string $dbName, string $table): array
{
    $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeDb}`.`{$safeTable}`");
    if (!$res) return [];
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $field = $row['Field'] ?? '';
        if ($field !== '') $cols[$field] = true;
    }
    $res->free();
    return $cols;
}

function normalizeDateForDb(mixed $value): ?string
{
    $v = clean($value);
    if ($v === '') return null;

    // Already normalized: 2026-05-13 or 2026-05-13 18:52:00
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }

    // GetYourGuide commonly arrives as: 13/05/2026 18:52
    // Also supports: 13/05/2026, 18:52 or 13-05-2026 18:52
    // Important: do this BEFORE strtotime(), because PHP may read slash dates as MM/DD/YYYY.
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})(?:\s*,?\s*\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM)?)?/i', $v, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }

    // Month name formats, e.g. Feb 13, 2026 / May 13, 2026 18:52
    $ts = strtotime($v);
    if ($ts !== false) return date('Y-m-d', $ts);

    return null;
}

function nullableMoney(mixed $value): ?float
{
    if ($value === null || $value === '') return null;
    if (is_float($value) || is_int($value)) return (float)$value;

    $s = clean($value);
    if ($s === '') return null;

    $num = preg_replace('/[^0-9,\.\-]/', '', $s);
    if ($num === '' || $num === '-') return null;

    if (substr_count($num, ',') === 1 && substr_count($num, '.') === 0) {
        $num = str_replace(',', '.', $num);
    } else {
        $num = str_replace(',', '', $num);
    }

    return is_numeric($num) ? (float)$num : null;
}

function nullableInt(mixed $value): ?int
{
    if ($value === null || $value === '') return null;
    if (is_int($value)) return $value;
    $s = clean($value);
    if ($s === '' || !is_numeric($s)) return null;
    return (int)round((float)$s);
}

function nullableBoolInt(mixed $value): int
{
    if ($value === null || $value === '') return 0;
    if (is_bool($value)) return $value ? 1 : 0;
    if (is_numeric($value)) return ((int)$value) === 1 ? 1 : 0;
    $s = strtoupper(clean($value));
    return in_array($s, ['1','YES','TRUE','SI','SÍ','PRIVATE','PVT'], true) ? 1 : 0;
}

/****************************************************
 * HTML HELPERS
 ****************************************************/

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function countByStatus(array $results, string $status): int { return count(array_filter($results, fn($r) => ($r['status'] ?? '') === $status)); }
function rowClass(string $status): string { return $status === 'OK' ? 'ok' : ($status === 'REVISAR' ? 'review' : 'error'); }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Stamp's Tour — Check Cierre Operativo 2 pasos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:24px;color:#111827}.container{max-width:1800px;margin:0 auto}h1{margin-bottom:4px}p{margin-top:4px;color:#4b5563}.card{background:white;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.06)}label{display:block;margin-bottom:6px}textarea{width:100%;min-height:260px;font-family:Consolas,monospace;font-size:13px;padding:12px;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;resize:vertical}input[type=file]{margin-top:6px;margin-bottom:18px}button{background:#1f2937;color:white;border:none;padding:12px 18px;border-radius:8px;font-weight:bold;cursor:pointer}button:hover{background:#374151}table{width:100%;border-collapse:collapse;font-size:12px;background:white}th,td{border:1px solid #d1d5db;padding:7px;vertical-align:top}th{background:#1f2937;color:white;position:sticky;top:0;z-index:2}.ok{background:#e6f4ea}.review{background:#fff4ce}.error{background:#fce8e6}.summary{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px}.badge{padding:10px 14px;background:white;border-radius:10px;box-shadow:0 1px 5px rgba(0,0,0,.06);font-weight:bold}.ok-badge{background:#e6f4ea}.review-badge{background:#fff4ce}.error-badge{background:#fce8e6}.error-box{background:#fce8e6;border:1px solid #ef4444;color:#7f1d1d;padding:14px;border-radius:10px;margin-bottom:16px}.small-note{font-size:12px;color:#6b7280}.table-wrapper{overflow:auto;max-height:75vh;border-radius:10px}.nowrap{white-space:nowrap}.hotel-cell{min-width:260px}.product-cell{min-width:260px}.copy-area{min-height:180px;margin-top:12px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}.preview-actions{display:flex;gap:10px;margin-top:10px;margin-bottom:12px}.preview-actions button{background:#374151;padding:9px 14px}.preview-card{background:#fff;border:1px solid #d1d5db;border-radius:12px;padding:16px;margin-top:16px}.preview-live-group{border:3px solid #111827;border-radius:12px;margin:18px 0 28px;background:#fff;overflow:hidden}.preview-live-header{display:grid;grid-template-columns:1.2fr 1.6fr 1fr 1fr 1fr;gap:0;background:#111827;color:#fff;font-weight:700}.preview-live-header div{padding:10px 12px;border-right:1px solid rgba(255,255,255,.25)}.preview-live-header div:last-child{border-right:0}.preview-live-title{font-size:14px;text-transform:uppercase;line-height:1.2}.preview-live-sub{font-size:11px;opacity:.85;font-weight:600;margin-top:3px}.preview-live-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12px;background:#fff}.preview-live-table th,.preview-live-table td{border:2px solid #111827;padding:7px;text-align:center;vertical-align:middle;word-break:break-word}.preview-live-table th{background:#f3f4f6;color:#111827;position:static}.preview-live-table td.hotel{text-align:left}.preview-live-total td{background:#f3f4f6!important;font-weight:800}.preview-live-warning{background:#fff4ce;font-weight:700;padding:10px;border:2px solid #111827;border-top:0;text-align:center}.vineyard-picker{border-top:2px solid #111827;background:#f9fafb;padding:10px 12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}.vineyard-picker strong{margin-right:6px}.vineyard-picker label{display:inline-flex;align-items:center;gap:4px;margin:0;font-size:12px;color:#111827}.vineyard-pill{background:#fff;border:1px solid #d1d5db;border-radius:999px;padding:5px 9px}.reservation-status-select{width:100%;font-size:11px;padding:4px;border:1px solid #9ca3af;border-radius:6px;background:#fff}.reservation-status-cell{min-width:90px}.editable-result-cell{background:#fffdf2!important;cursor:text;outline:none}.editable-result-cell:focus{box-shadow:inset 0 0 0 2px #2563eb;background:#eff6ff!important}.assignment-block{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-right:12px}.assignment-vineyards{flex:1 1 100%;margin-top:6px}.master-select{font-size:12px;padding:5px 8px;border:1px solid #9ca3af;border-radius:8px;background:#fff;min-width:150px}

        .sheet-toolbar{display:flex;gap:8px;margin:10px 0}.sheet-toolbar button{background:#4b5563;padding:8px 12px;font-size:12px}.sheet-wrapper{overflow:auto;border:1px solid #d1d5db;border-radius:10px;max-height:420px;background:white;margin-bottom:12px}.fixed-sheet{border-collapse:collapse;min-width:1500px;width:100%;font-size:12px}.fixed-sheet th{position:sticky;top:0;background:#111827;color:white;z-index:3;text-align:center}.fixed-sheet td,.fixed-sheet th{border:1px solid #d1d5db;padding:0}.fixed-sheet .rownum{position:sticky;left:0;background:#f3f4f6;color:#111827;z-index:2;text-align:center;width:38px;font-weight:700}.fixed-sheet thead .rownum{z-index:4;background:#111827;color:white}.fixed-cell{min-width:90px;height:28px;padding:5px 7px;outline:none;white-space:pre-wrap;word-break:break-word;background:#fff}.fixed-cell:focus{box-shadow:inset 0 0 0 2px #2563eb;background:#eff6ff}.fixed-cell.col-8{min-width:220px}.fixed-cell.col-14{min-width:220px}.fixed-sheet .col-code{min-width:120px}.fixed-sheet .col-name{min-width:180px}.fixed-sheet .col-tour{min-width:160px}
    </style>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('closing'); ?>
<div class="container">
    <h1>Stamp's Tour — Check + Cierre Operativo 2 pasos</h1>
    <p>Base estable check.php + parser driver/guía + enriquecimiento financiero. Flujo: check mismo día → revisar/editar → segundo enriquecimiento opcional → guardar BD.</p>

    <?php if ($errorMessage !== ''): ?>
        <div class="error-box"><strong>Error:</strong> <?= e($errorMessage) ?></div>
    <?php endif; ?>

    <?php if ($dashboardSaveError !== ''): ?>
        <div class="error-box"><strong>Error guardando en dashboard:</strong> <?= e($dashboardSaveError) ?></div>
    <?php endif; ?>

    <?php if ($dashboardSaveMessage !== ''): ?>
        <div class="card ok"><strong><?= e($dashboardSaveMessage) ?></strong></div>
    <?php endif; ?>

    <?php if ($secondEnrichmentError !== ''): ?>
        <div class="error-box"><strong>Error en segundo enriquecimiento:</strong> <?= e($secondEnrichmentError) ?></div>
    <?php endif; ?>

    <?php if ($secondEnrichmentMessage !== ''): ?>
        <div class="card ok"><strong><?= e($secondEnrichmentMessage) ?></strong></div>
    <?php endif; ?>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($webDbDebug)): ?>
        <div class="card">
            <h2>Debug cruce WEB / BD</h2>
            <pre style="white-space:pre-wrap;background:#111827;color:#e5e7eb;padding:14px;border-radius:10px;font-size:12px;max-height:320px;overflow:auto;"><?= e(implode("
", $webDbDebug)) ?></pre>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" enctype="multipart/form-data" onsubmit="syncFixedSheetToTextarea();">
            <label><strong>1. Pega aquí el reporte operativo estructurado o sube el archivo</strong></label>
            <p class="small-note">Usa la grilla fija A–N: A Fecha, B Tour, C Nombre, D Total, E Dirección, F Horario, G Teléfono, H Idioma, I Origen, J Contacto, K Código, L Driver, M Guía, N Viñedo. Si el tour contiene PVTE/PVT/PRIVATE/PRIVADO se marca como privado. Si no hay guía pero sí driver, se marca como DRIVER/GUIDE. Si no hay guía ni driver, se preselecciona refund.</p>
            <input type="file" name="operative_report_file" accept=".csv,.txt,.xlsx,.xls">
            <textarea id="planilla_text" name="planilla_text" style="display:none;"><?= e($_POST['planilla_text'] ?? '') ?></textarea>
            <div class="sheet-toolbar">
                <button type="button" onclick="addFixedSheetRows(10)">+ 10 filas</button>
                <button type="button" onclick="clearFixedSheet()">Limpiar grilla</button>
            </div>
            <div class="sheet-wrapper">
                <table id="fixed_input_sheet" class="fixed-sheet"></table>
            </div>

            <div class="preview-actions">
                <button type="button" onclick="syncFixedSheetToTextarea(); renderPlanillaPreview()">Preview cierre</button>
                <button type="button" onclick="clearPlanillaPreview()">Clear preview</button>
            </div>

            <div id="planilla_preview_card" class="preview-card" style="display:none;">
                <h2>Vista previa por grupo operativo</h2>
                <p class="small-note">Selecciona driver, guía y 1 viñedo por grupo. Las opciones vienen desde stampst1_dashboard y se copiarán a cada reserva del grupo en el resultado enriquecido.</p>
                <div id="planilla_preview_table"></div>
            </div>

            <br><br>
            <div class="grid">
                <div>
                    <label><strong>2. Archivo Viator / TripAdvisor CSV</strong></label>
                    <input type="file" name="viator_file" accept=".csv,.txt">
                </div>
                <div>
                    <label><strong>3. Archivo GetYourGuide Excel</strong></label>
                    <input type="file" name="gyg_file" accept=".xlsx,.xls">
                </div>
                <div>
                    <label><strong>4. Archivo Civitatis CSV / Excel</strong></label>
                    <input type="file" name="civitatis_file" accept=".csv,.txt,.xlsx,.xls">
                </div>
            </div>

            <button type="submit">Ejecutar check + enriquecer cierre</button>
            <div class="small-note">Planilla = verdad operacional. Archivos fuente = enriquecimiento y validación. Si hay diferencia de fecha o tour, quedará en comentario.</div>
        </form>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === ''): ?>
        <?php
            $total = count($results);
            $ok = countByStatus($results, 'OK');
            $review = countByStatus($results, 'REVISAR');
            $missing = countByStatus($results, 'NO EXISTE EN ARCHIVO');
            $noCode = countByStatus($results, 'SIN CÓDIGO');
            $unknown = countByStatus($results, 'FUENTE DESCONOCIDA');
            $web = count(array_filter($results, fn($r) => ($r['op_source'] ?? '') === 'WEB' || str_starts_with((string)($r['code'] ?? ''), 'STAMP_')));
            $errors = $missing + $noCode + $unknown;
            $missingCount = count($missingPassengers);
            $totalPax = array_sum(array_map(fn($r) => (int)($r['op_pax'] ?? 0), $results));
            $netRevenue = array_sum(array_map(fn($r) => (float)(((($r['op_source'] ?? '') === 'WEB' || ($r['sys_source'] ?? '') === 'WEB' || str_starts_with((string)($r['code'] ?? ''), 'STAMP_')) ? ($r['price_total'] ?? 0) : ($r['net_price'] ?? 0))), $results));
        ?>
        <div class="summary">
            <div class="badge">Registros: <?= e($total) ?></div>
            <div class="badge">Pax planilla: <?= e($totalPax) ?></div>
            <div class="badge ok-badge">OK: <?= e($ok) ?></div>
            <div class="badge review-badge">Revisar: <?= e($review) ?></div>
            <div class="badge error-badge">Errores fuertes: <?= e($errors) ?></div>
            <div class="badge">WEB: <?= e($web) ?></div>
            <div class="badge error-badge">Faltantes: <?= e($missingCount) ?></div>
            <div class="badge">Net estimado: <?= e(moneyCell($netRevenue)) ?></div>
        </div>

        <div class="card">
            <h2>Segundo enriquecimiento antes de subir a BD</h2>
            <p class="small-note">Usa esto solo si después del primer check ves reservas sin datos de fuente/net o casos reprogramados. Primero edita lo necesario en la tabla amarilla, luego sube archivos adicionales y presiona enriquecer. No se vuelve a interpretar la planilla operativa; solo se actualiza el resultado enriquecido actual por código de reserva.</p>
            <form method="post" enctype="multipart/form-data" id="second_enrichment_form" onsubmit="return prepareEditedResultsForSecondEnrichment();">
                <input type="hidden" name="enrich_existing_results" value="1">
                <input type="hidden" id="second_enrich_results_json_input" name="results_json" value="<?= e(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                <div class="grid">
                    <div>
                        <label><strong>Archivo adicional Viator / TripAdvisor CSV</strong></label>
                        <input type="file" name="extra_viator_file" accept=".csv,.txt">
                    </div>
                    <div>
                        <label><strong>Archivo adicional GetYourGuide Excel</strong></label>
                        <input type="file" name="extra_gyg_file" accept=".xlsx,.xls">
                    </div>
                    <div>
                        <label><strong>Archivo adicional Civitatis CSV / Excel</strong></label>
                        <input type="file" name="extra_civitatis_file" accept=".csv,.txt,.xlsx,.xls">
                    </div>
                </div>
                <button type="submit" style="background:#4338ca;">Enriquecer resultado actual</button>
            </form>
        </div>

        <div class="card">
            <h2>Guardar en Dashboard</h2>
            <p class="small-note">Revisa y edita las celdas amarillas del resultado enriquecido antes de guardar. Al presionar guardar, los cambios visibles en la tabla se sincronizan al JSON que se sube a <strong>stampst1_dashboard.operational_closures</strong> y <strong>operational_reservations</strong>.</p>
            <form method="post" id="dashboard_save_form" onsubmit="return prepareEditedResultsForDashboard();">
                <input type="hidden" name="save_dashboard" value="1">
                <input type="hidden" id="results_json_input" name="results_json" value="<?= e(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                <div class="grid">
                    <div>
                        <label><strong>Título del cierre</strong></label>
                        <input type="text" name="closure_title" value="Cierre operativo <?= e(date('Y-m-d')) ?>" style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label><strong>Comentarios</strong></label>
                        <input type="text" name="closure_comments" value="" placeholder="Opcional" style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;">
                    </div>
                </div>
                <br>
                <button type="submit" style="background:#0f766e;">Aceptar y subir a BD</button>
            </form>
        </div>

        <div class="card">
            <h2>Resultado enriquecido del cierre</h2>
            <div class="table-wrapper">
                <table id="enriched_results_table">
                    <thead><tr>
                        <th>Estado</th><th>Problema / Nota</th><th>Comentario operativo</th><th>Fecha</th><th>Viñedo</th><th>Estado reserva</th><th>Transporte</th><th>Guía</th><th>Modo</th><th>Total Grupo</th><th>Fila</th><th>Código</th><th>Fuente Planilla</th><th>Fuente Sistema</th><th>Nombre Planilla</th><th>Nombre Sistema</th><th>Tour Planilla</th><th>Tour Sistema</th><th>Pax Planilla</th><th>Pax Sistema</th><th>Adultos</th><th>Niños</th><th>Infantes</th><th>Idioma Planilla</th><th>Idioma Sistema</th><th>Hotel Planilla</th><th>Hotel Sistema</th><th>Pickup</th><th>Phone Planilla</th><th>Phone Sistema</th><th>Email Planilla</th><th>Email Sistema</th><th>Booking Date</th><th>Currency</th><th>Price</th><th>Net Price</th><th>Private</th><th>Producto Sistema</th><th>Fila Sistema</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr class="<?= e(rowClass($r['status'] ?? '')) ?>">
                            <td class="nowrap"><strong><?= e($r['status']) ?></strong></td>
                            <td><?= e($r['issues']) ?></td>
                            <td><?= e($r['op_comment'] ?? '') ?></td>
                            <td><?= e($r['date']) ?></td>
                            <td><?= e($r['vineyards'] ?? '') ?></td>
                            <td><?= e($r['reservation_status'] ?? '') ?></td>
                            <td><?= e($r['transport']) ?></td>
                            <td><?= e($r['guide']) ?></td>
                            <td><?= e($r['mode']) ?></td>
                            <td><?= e(($r['declared_group_total'] ?? '') . ' / ' . ($r['calculated_group_total'] ?? '') . ' ' . ($r['group_total_status'] ?? '')) ?></td>
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
                            <td><?= e($r['adults'] ?? '') ?></td>
                            <td><?= e($r['children'] ?? '') ?></td>
                            <td><?= e($r['infants'] ?? '') ?></td>
                            <td><?= e($r['op_lang']) ?></td>
                            <td><?= e($r['sys_lang']) ?></td>
                            <td class="hotel-cell"><?= e($r['op_hotel']) ?></td>
                            <td class="hotel-cell"><?= e($r['sys_hotel']) ?></td>
                            <td><?= e($r['op_pickup']) ?></td>
                            <td><?= e($r['op_phone']) ?></td>
                            <td><?= e($r['sys_phone']) ?></td>
                            <td><?= e($r['op_email']) ?></td>
                            <td><?= e($r['sys_email']) ?></td>
                            <td><?= e($r['booking_date']) ?></td>
                            <td><?= e($r['currency']) ?></td>
                            <td><?= e(moneyCell($r['price_total'])) ?></td>
                            <td><?= e(moneyCell((($r['op_source'] ?? '') === 'WEB' || ($r['sys_source'] ?? '') === 'WEB' || str_starts_with((string)($r['code'] ?? ''), 'STAMP_')) ? ($r['price_total'] ?? null) : ($r['net_price'] ?? null))) ?></td>
                            <td><?= ($r['is_private'] === null || $r['is_private'] === '') ? '' : ((int)$r['is_private'] === 1 ? 'YES' : 'NO') ?></td>
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
                <p class="small-note">Estas reservas aparecen en Viator/GYG, pero su código no está en la planilla pegada.</p>
                <div class="table-wrapper"><table><thead><tr><th>Tour</th><th>Nombre</th><th>Pax</th><th>Hotel</th><th>Blank</th><th>Phone</th><th>Lang</th><th>Origin</th><th>Email</th><th>Code</th></tr></thead><tbody>
                <?php foreach ($missingPassengers as $m): ?>
                    <tr class="error"><td><?= e($m['tour']) ?></td><td><?= e($m['name']) ?></td><td><?= e($m['pax']) ?></td><td class="hotel-cell"><?= e($m['hotel']) ?></td><td><?= e($m['blank']) ?></td><td><?= e($m['phone']) ?></td><td><?= e($m['lang']) ?></td><td><?= e($m['origin']) ?></td><td><?= e($m['email']) ?></td><td class="nowrap"><?= e($m['code']) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <label style="margin-top:16px;"><strong>Bloque copiable para agregar a la planilla</strong></label>
                <textarea readonly class="copy-area"><?= e(buildMissingPassengersCopyText($missingPassengers)) ?></textarea>
            </div>
        <?php endif; ?>

        <?php if (empty($missingPassengers)): ?>
            <div class="card ok"><strong>No hay pasajeros faltantes en la planilla.</strong></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const VINEYARD_OPTIONS = <?= json_encode($dashboardMasterOptions['vineyards'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const GUIDE_OPTIONS = <?= json_encode($dashboardMasterOptions['guides'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const DRIVER_OPTIONS = <?= json_encode($dashboardMasterOptions['drivers'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let ENRICHED_RESULTS = <?= json_encode($results ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]' ?>;
const RESERVATION_STATUS_OPTIONS = ['', 'no show', 'traspaso', 'refund'];
let previewVineyardSelections = {};
let previewReservationStatusSelections = {};
const FIXED_COLUMNS = [
    'Fecha', 'Tour', 'Nombre', 'Total', 'Dirección', 'Horario', 'Teléfono', 'Idioma', 'Origen', 'Contacto', 'Código', 'Driver', 'Guía', 'Viñedo'
];

function initFixedSheet(rowCount = 35) {
    const table = document.getElementById('fixed_input_sheet');
    if (!table) return;
    table.innerHTML = '';
    const thead = document.createElement('thead');
    const trh = document.createElement('tr');
    const corner = document.createElement('th');
    corner.className = 'rownum';
    corner.textContent = '#';
    trh.appendChild(corner);
    FIXED_COLUMNS.forEach(function(name, idx) {
        const th = document.createElement('th');
        th.textContent = String.fromCharCode(65 + idx) + ' · ' + name;
        trh.appendChild(th);
    });
    thead.appendChild(trh);
    table.appendChild(thead);
    const tbody = document.createElement('tbody');
    table.appendChild(tbody);
    addFixedSheetRows(rowCount);
    populateFixedSheetFromTextarea();
}

function addFixedSheetRows(count = 10) {
    const table = document.getElementById('fixed_input_sheet');
    if (!table) return;
    let tbody = table.querySelector('tbody');
    if (!tbody) { tbody = document.createElement('tbody'); table.appendChild(tbody); }
    const start = tbody.querySelectorAll('tr').length;
    for (let r = 0; r < count; r++) {
        const tr = document.createElement('tr');
        const rowNum = document.createElement('td');
        rowNum.className = 'rownum';
        rowNum.textContent = String(start + r + 1);
        tr.appendChild(rowNum);
        for (let c = 0; c < FIXED_COLUMNS.length; c++) {
            const td = document.createElement('td');
            const cell = document.createElement('div');
            cell.className = 'fixed-cell col-' + c + (c === 10 ? ' col-code' : '') + (c === 2 ? ' col-name' : '') + (c === 1 ? ' col-tour' : '');
            cell.contentEditable = 'true';
            cell.dataset.row = String(start + r);
            cell.dataset.col = String(c);
            cell.addEventListener('paste', handleFixedSheetPaste);
            cell.addEventListener('input', function(){ syncFixedSheetToTextarea(false); });
            td.appendChild(cell);
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
}

function handleFixedSheetPaste(event) {
    const text = (event.clipboardData || window.clipboardData).getData('text');
    if (!text || (!text.includes('\t') && !text.includes('\n'))) return;
    event.preventDefault();
    const startRow = parseInt(event.currentTarget.dataset.row || '0', 10);
    const startCol = parseInt(event.currentTarget.dataset.col || '0', 10);
    pasteMatrixIntoFixedSheet(text, startRow, startCol);
    syncFixedSheetToTextarea();
    renderPlanillaPreview();
}

function pasteMatrixIntoFixedSheet(text, startRow, startCol) {
    const rows = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    const neededRows = startRow + rows.length;
    const table = document.getElementById('fixed_input_sheet');
    const tbody = table ? table.querySelector('tbody') : null;
    if (tbody && tbody.querySelectorAll('tr').length < neededRows) {
        addFixedSheetRows(neededRows - tbody.querySelectorAll('tr').length + 5);
    }
    rows.forEach(function(line, rOffset) {
        if (line === '' && rOffset === rows.length - 1) return;
        const cols = line.split('\t');
        cols.forEach(function(value, cOffset) {
            const target = document.querySelector('.fixed-cell[data-row="' + (startRow + rOffset) + '"][data-col="' + (startCol + cOffset) + '"]');
            if (target) target.textContent = value;
        });
    });
}

function syncFixedSheetToTextarea(trimEmpty = true) {
    const textarea = document.getElementById('planilla_text');
    const table = document.getElementById('fixed_input_sheet');
    if (!textarea || !table) return;
    const lines = [];
    table.querySelectorAll('tbody tr').forEach(function(tr) {
        const values = [];
        tr.querySelectorAll('.fixed-cell').forEach(function(cell) {
            values.push((cell.textContent || '').replace(/\u00a0/g, ' ').trim());
        });
        if (!trimEmpty || values.some(v => v !== '')) lines.push(values.join('\t'));
    });
    textarea.value = lines.join('\n');
}

function populateFixedSheetFromTextarea() {
    const textarea = document.getElementById('planilla_text');
    if (!textarea || !textarea.value.trim()) return;
    pasteMatrixIntoFixedSheet(textarea.value, 0, 0);
}

function clearFixedSheet() {
    const table = document.getElementById('fixed_input_sheet');
    if (!table) return;
    table.querySelectorAll('.fixed-cell').forEach(cell => cell.textContent = '');
    syncFixedSheetToTextarea();
    clearPlanillaPreview();
}

function renderPlanillaPreview() {
    const textarea = document.getElementById('planilla_text');
    const container = document.getElementById('planilla_preview_table');
    const card = document.getElementById('planilla_preview_card');
    if (!textarea || !container || !card) return;
    container.innerHTML = '';
    const raw = textarea.value || '';
    if (!raw.trim()) { card.style.display = 'none'; return; }
    const groups = parsePreviewGroups(raw);
    if (!groups.length) { card.style.display = 'none'; return; }
    groups.forEach(function(group, index) {
        const box = document.createElement('div');
        box.className = 'preview-live-group';
        const header = document.createElement('div');
        header.className = 'preview-live-header';
        header.innerHTML =
            '<div><div class="preview-live-title">' + esc(group.category || 'OTHER') + '</div><div class="preview-live-sub">Categoría</div></div>' +
            '<div><div class="preview-live-title">' + esc(group.tour || '') + '</div><div class="preview-live-sub">Tour</div></div>' +
            '<div><div class="preview-live-title">' + esc(group.date || '') + '</div><div class="preview-live-sub">Fecha</div></div>' +
            '<div><div class="preview-live-title">' + esc(group.transport || '') + '</div><div class="preview-live-sub">Transporte / Driver</div></div>' +
            '<div><div class="preview-live-title">' + esc(group.guide || '') + '</div><div class="preview-live-sub">Guía · ' + esc(group.mode || '') + '</div></div>';
        box.appendChild(header);
        box.appendChild(buildGroupAssignmentPicker(group));
        const table = document.createElement('table');
        table.className = 'preview-live-table';
        table.innerHTML = '<thead><tr><th style="width:17%">Name</th><th style="width:5%">Pax</th><th style="width:25%">Hotel</th><th style="width:8%">Estado</th><th style="width:8%">Time</th><th style="width:12%">Phone</th><th style="width:6%">Lang</th><th style="width:7%">Source</th><th style="width:12%">Code</th></tr></thead>';
        const tbody = document.createElement('tbody');
        group.rows.forEach(function(row, rowIndex) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>' + esc(row.name) + '</td><td>' + esc(row.pax) + '</td><td class="hotel">' + esc(row.hotel) + '</td><td class="reservation-status-cell">' + buildReservationStatusSelect(group.sequence, rowIndex, row.reservation_status || '') + '</td><td>' + esc(row.pickup) + '</td><td>' + esc(row.phone) + '</td><td>' + esc(row.lang) + '</td><td>' + esc(row.source) + '</td><td>' + esc(row.code) + '</td>';
            tbody.appendChild(tr);
        });
        const totalTr = document.createElement('tr');
        totalTr.className = 'preview-live-total';
        totalTr.innerHTML = '<td>Total grupo</td><td>' + esc(String(group.totalPax)) + '</td><td colspan="7">Pax declarado: ' + esc(group.declaredTotal || '') + ' · Pax calculado: ' + esc(String(group.totalPax)) + ' · Grupo ' + esc(String(index + 1)) + '</td>';
        tbody.appendChild(totalTr);
        table.appendChild(tbody);
        box.appendChild(table);
        if (group.declaredTotal && Number(group.declaredTotal) !== Number(group.totalPax)) {
            const warn = document.createElement('div');
            warn.className = 'preview-live-warning';
            warn.textContent = 'Revisar total: declarado ' + group.declaredTotal + ' vs calculado ' + group.totalPax;
            box.appendChild(warn);
        }
        container.appendChild(box);
    });
    card.style.display = 'block';
}



function buildGroupAssignmentPicker(group) {
    const wrap = document.createElement('div');
    wrap.className = 'vineyard-picker';

    const driverBlock = document.createElement('div');
    driverBlock.className = 'assignment-block';
    driverBlock.innerHTML = '<strong>Driver:</strong> ';
    driverBlock.appendChild(buildMasterSelect('group_drivers[' + group.sequence + ']', DRIVER_OPTIONS, group.transport, 'driver', group.sequence));
    wrap.appendChild(driverBlock);

    const guideBlock = document.createElement('div');
    guideBlock.className = 'assignment-block';
    guideBlock.innerHTML = '<strong>Guía:</strong> ';
    guideBlock.appendChild(buildMasterSelect('group_guides[' + group.sequence + ']', GUIDE_OPTIONS, group.guide, 'guide', group.sequence));
    wrap.appendChild(guideBlock);

    const vineyardBlock = document.createElement('div');
    vineyardBlock.className = 'assignment-block assignment-vineyards';
    const title = document.createElement('strong');
    title.textContent = 'Viñedo:';
    vineyardBlock.appendChild(title);

    const selected = previewVineyardSelections[group.sequence] || inferVineyardOptionValue(group.vineyards || '');
    const emptyLabel = document.createElement('label');
    emptyLabel.className = 'vineyard-pill';
    const emptyInput = document.createElement('input');
    emptyInput.type = 'radio';
    emptyInput.name = 'group_vineyards[' + group.sequence + ']';
    emptyInput.value = '';
    emptyInput.checked = selected === '';
    emptyInput.addEventListener('change', function() {
        if (emptyInput.checked) previewVineyardSelections[group.sequence] = '';
    });
    emptyLabel.appendChild(emptyInput);
    emptyLabel.appendChild(document.createTextNode('Sin asignar'));
    vineyardBlock.appendChild(emptyLabel);

    VINEYARD_OPTIONS.forEach(function(opt) {
        const id = String(opt.id || '');
        const name = String(opt.name || '');
        const category = String(opt.category || '');
        if (!id && !name) return;
        const value = id || name;
        const label = document.createElement('label');
        label.className = 'vineyard-pill';
        const input = document.createElement('input');
        input.type = 'radio';
        input.name = 'group_vineyards[' + group.sequence + ']';
        input.value = value;
        input.checked = selected === value;
        input.addEventListener('change', function() {
            if (input.checked) previewVineyardSelections[group.sequence] = value;
        });
        label.appendChild(input);
        label.appendChild(document.createTextNode(category ? name + ' · Cat. ' + category : name));
        vineyardBlock.appendChild(label);
    });

    wrap.appendChild(vineyardBlock);
    return wrap;
}

function inferVineyardOptionValue(currentText) {
    const normalized = normalizeMasterOptionAlias(currentText || '');
    if (!normalized) return '';
    for (const opt of VINEYARD_OPTIONS) {
        const name = String(opt.name || '');
        if (normalizeMasterOptionAlias(name) === normalized) return String(opt.id || name);
    }
    return '';
}

function buildMasterSelect(inputName, options, currentText, type, groupSequence) {
    const select = document.createElement('select');
    select.className = 'master-select';
    select.name = inputName;

    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = 'Sin asignar';
    select.appendChild(empty);

    const normalizedCurrent = normalizeMasterOptionAlias(currentText || '');
    let matched = false;

    options.forEach(function(opt) {
        const id = String(opt.id || '');
        const name = String(opt.name || '');
        const category = String(opt.category || '');
        if (!id && !name) return;
        const option = document.createElement('option');
        option.value = id || name;
        option.textContent = category ? name + ' · Cat. ' + category : name;
        if (!matched && normalizedCurrent && normalizeMasterOptionAlias(name) === normalizedCurrent) {
            option.selected = true;
            matched = true;
        }
        select.appendChild(option);
    });

    return select;
}

function normalizeMasterOptionAlias(value) {
    const normalized = normalizePreviewName(value);
    const aliases = {
        'ESME': 'ESMERALDA',
        'MOLLE': 'CASA MOLLE'
    };
    return aliases[normalized] || normalized;
}

function normalizePreviewName(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toUpperCase();
}

function buildReservationStatusSelect(groupSequence, rowIndex, defaultStatus = '') {
    const key = String(groupSequence) + '_' + String(rowIndex);
    const selected = previewReservationStatusSelections[key] || defaultStatus || '';
    let html = '<select class="reservation-status-select" name="reservation_statuses[' + esc(groupSequence) + '][' + esc(rowIndex) + ']" onchange="previewReservationStatusSelections[\'' + esc(key) + '\']=this.value">';
    RESERVATION_STATUS_OPTIONS.forEach(function(value) {
        const label = value === '' ? '' : value;
        html += '<option value="' + esc(value) + '"' + (selected === value ? ' selected' : '') + '>' + esc(label) + '</option>';
    });
    html += '</select>';
    return html;
}

function parsePreviewGroups(raw) {
    const lines = raw.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    const groups = [];
    let current = null;
    let currentDate = '';
    let currentTourRaw = '';
    let currentTour = '';
    let currentIsPrivate = '';
    let currentDriver = '';
    let currentGuide = '';
    let currentVineyards = '';
    let currentHotel = '';

    lines.forEach(function(line) {
        if (!String(line || '').trim()) return;
        const cols = line.split('\t');
        while (cols.length < 14) cols.push('');
        const c = cols.map(x => String(x || '').trim());
        if (rowLooksLikeFixedHeader(c)) return;

        const breakInfo = detectPreviewBreak(c);
        if (breakInfo.isBreak) {
            if (current) current.declaredTotal = breakInfo.total;
            current = null;
            // No limpiamos fecha ni tour: pueden ser shared cells para varios grupos.
            // Solo limpiamos staff/viñedo para que el próximo driver/guía cree otra card.
            currentDriver = '';
            currentGuide = '';
            currentVineyards = '';
            currentHotel = '';
            return;
        }

        const dateCell = normalizeDatePreview(c[0] || '');
        const tourRawCell = c[1] || '';
        const name = c[2] || '';
        const code = String(c[10] || '').toUpperCase().trim();
        if (!name && !code) return;

        if (dateCell) currentDate = dateCell;

        if (tourRawCell) {
            if (current && current.rows.length > 0) {
                current = null;
                currentDriver = '';
                currentGuide = '';
                currentVineyards = '';
                currentHotel = '';
            }
            currentTourRaw = tourRawCell;
            currentTour = canonicalTourPreview(tourRawCell);
            currentIsPrivate = privatePreview(tourRawCell);
        }

        const incomingDriver = c[11] || '';
        const incomingGuide = c[12] || '';
        if (current && current.rows.length > 0) {
            const driverChanged = incomingDriver && currentDriver && normalizePreviewName(incomingDriver) !== normalizePreviewName(currentDriver);
            const guideChanged = incomingGuide && currentGuide && normalizePreviewName(incomingGuide) !== normalizePreviewName(currentGuide);
            if (driverChanged || guideChanged) {
                current = null;
                currentDriver = '';
                currentGuide = '';
                currentVineyards = '';
                currentHotel = '';
            }
        }

        if (c[11]) currentDriver = c[11];
        if (c[12]) currentGuide = c[12];
        if (c[13]) currentVineyards = c[13];
        if (c[4]) currentHotel = c[4];

        const row = fixedPreviewRow(c, {
            date: currentDate,
            tourRaw: currentTourRaw,
            tour: currentTour,
            isPrivate: currentIsPrivate,
            driver: currentDriver,
            guide: currentGuide,
            vineyards: currentVineyards,
            hotel: currentHotel
        });

        if (!current) {
            const mode = (!row.driver && !row.guide) ? 'NO EJECUTADO / REFUND' : ((!row.guide && row.driver) ? 'DRIVER/GUIDE' : 'GUIDE + DRIVER');
            const effectiveGuide = (!row.guide && row.driver) ? row.driver : row.guide;
            current = {
                sequence: groups.length + 1,
                date: row.date,
                tour: row.tour,
                category: mainCategoryPreview(row.tour),
                transport: row.driver,
                guide: effectiveGuide,
                vineyards: row.vineyards,
                mode: mode,
                rows: [],
                totalPax: 0,
                declaredTotal: ''
            };
            groups.push(current);
        }

        if (!current.transport && row.driver) current.transport = row.driver;
        if (!current.guide && (row.guide || row.driver)) current.guide = row.guide || row.driver;
        if (!current.vineyards && row.vineyards) current.vineyards = row.vineyards;

        current.rows.push(row);
        current.totalPax += parseInt(row.pax || '0', 10) || 0;
    });

    return groups.filter(g => g.rows.length > 0);
}

function rowLooksLikeFixedHeader(cols) {
    const txt = cols.join(' ').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
    return txt.includes('CODIGO') && (txt.includes('CLIENTE') || txt.includes('PASAJERO') || txt.includes('NOMBRE'));
}

function fixedPreviewRow(cols, state = {}) {
    const code = String(cols[10] || '').toUpperCase().trim();
    const driver = cols[11] || state.driver || '';
    const guide = cols[12] || state.guide || '';
    const status = (!driver && !guide) ? 'refund' : '';
    const pax = numPreview(cols[3]);
    const rawTour = cols[1] || state.tourRaw || state.tour || '';
    return {
        date: state.date || normalizeDatePreview(cols[0] || ''),
        tour: state.tour || canonicalTourPreview(rawTour),
        isPrivate: state.isPrivate || privatePreview(rawTour),
        code: code,
        name: cols[2] || '',
        adults: String(pax),
        children: '0',
        infants: '0',
        pax: String(pax),
        hotel: cols[4] || state.hotel || '',
        pickup: cols[5] || '',
        phone: cols[6] || '',
        lang: normalizeLangPreview(cols[7] || ''),
        source: cols[8] || inferSourcePreview(code),
        email: cols[9] || '',
        driver: driver,
        guide: guide,
        vineyards: cols[13] || state.vineyards || '',
        comment: cols[14] || '',
        reservation_status: status
    };
}

function numPreview(value) {
    const n = Number(String(value || '').replace(',', '.'));
    return Number.isFinite(n) ? Math.round(n) : 0;
}

function privatePreview(value) {
    const s = String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toUpperCase();
    if (['1','SI','S','YES','Y','TRUE','PRIVADO','PRIVATE','PVT','PVTE'].includes(s)) return '1';
    if (['0','NO','N','FALSE','REGULAR','SHARED','GRUPAL'].includes(s)) return '0';
    return (s.includes('PRIV') || s.includes('PRIVATE') || s.includes('PVT') || s.includes('PVTE')) ? '1' : '';
}

function inferSourcePreview(code) {
    const s = String(code || '').toUpperCase();
    if (s.startsWith('GYG')) return 'GET';
    if (s.startsWith('BR-')) return 'TRIP';
    if (s.startsWith('STAMP_')) return 'WEB';
    if (/^A\d{5,}$/.test(s)) return 'CIV';
    return '';
}

function normalizeLangPreview(value) {
    const s = String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toUpperCase();
    if (s === 'ESP' || s === 'SPA' || s.includes('SPANISH') || s.includes('ESPANOL')) return 'SPA';
    if (s === 'ENG' || s.includes('ENGLISH') || s.includes('INGLES')) return 'ENG';
    if (['BRA','POR','PT'].includes(s) || s.includes('PORTUG')) return 'BRA';
    return s;
}

function detectPreviewBreak(cols) {
    const result = {isBreak:false, guide:'', total:''};

    // Caso real del reporte: total declarado en columna D, todo lo demás vacío.
    const d = String(cols[3] || '').trim();
    if (d && !isNaN(Number(d.replace(',', '.')))) {
        const hasOther = cols.some((value, index) => index !== 3 && String(value || '').trim() !== '');
        if (!hasOther) {
            result.isBreak = true;
            result.total = d;
            return result;
        }
    }

    // Fallback: fila con un único número en cualquier columna.
    const nonEmpty = cols.map(c => String(c || '').trim()).filter(Boolean);
    if (!nonEmpty.length) return result;
    if (nonEmpty.some(isCodePreview) || nonEmpty.some(isTourPreview) || nonEmpty.some(isDatePreview)) return result;
    const numeric = nonEmpty.filter(v => !isNaN(Number(String(v).replace(',', '.'))));
    if (nonEmpty.length === 1 && numeric.length === 1) { result.isBreak = true; result.total = numeric[0]; }
    return result;
}

function clearPlanillaPreview() { previewVineyardSelections = {}; previewReservationStatusSelections = {}; const c = document.getElementById('planilla_preview_table'); const card = document.getElementById('planilla_preview_card'); if (c) c.innerHTML = ''; if (card) card.style.display = 'none'; }
function isDatePreview(v) { const s = String(v || '').trim(); return /^\d{1,2}\/\d{1,2}\/\d{4}$/.test(s) || /^\d{4}-\d{1,2}-\d{1,2}$/.test(s); }
function normalizeDatePreview(v) { const s = String(v || '').trim(); const m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/); return m ? (m[3] + '-' + m[2].padStart(2,'0') + '-' + m[1].padStart(2,'0')) : s; }
function isTourPreview(v) { const s = String(v || '').toUpperCase(); return s.includes('VALPARAISO') || s.includes('VALPARAÍSO') || s.includes('VIÑA') || s.includes('VINA') || s.includes('CITY') || s.includes('SANTIAGO') || s.includes('MAIPO') || s.includes('PORTILLO') || s.includes('INCA') || s.includes('ANDES') || s.includes('PVT') || s.includes('PVTE') || s.includes('PRIVATE') || s.includes('PRIVADO'); }
function isCodePreview(v) { const s = String(v || '').trim().toUpperCase(); return s.startsWith('GYG') || s.startsWith('BR-') || s.startsWith('STAMP_') || /^A\d{5,}$/.test(s); }
function canonicalTourPreview(v) { const s = String(v || '').toUpperCase(); if ((s.includes('PVT') || s.includes('PVTE') || s.includes('PRIVATE') || s.includes('PRIVADO')) && (s.includes('VALPARAISO') || s.includes('VALPARAÍSO') || s.includes('VIÑA') || s.includes('VINA'))) return 'PVT VALPARAISO & VIÑA DEL MAR'; if ((s.includes('PVT') || s.includes('PVTE') || s.includes('PRIVATE') || s.includes('PRIVADO')) && s.includes('MAIPO')) return 'PVT WINE TOUR MAIPO VALLEY'; if ((s.includes('PVT') || s.includes('PVTE') || s.includes('PRIVATE') || s.includes('PRIVADO')) && (s.includes('CITY') || s.includes('SANTIAGO'))) return 'PVT CITY TOUR'; if ((s.includes('PVT') || s.includes('PVTE') || s.includes('PRIVATE') || s.includes('PRIVADO')) && (s.includes('PORTILLO') || s.includes('INCA') || s.includes('ANDES'))) return 'PVT PORTILLO INCA LAGOON'; if (s.includes('VALPARAISO') || s.includes('VALPARAÍSO') || s.includes('VIÑA') || s.includes('VINA')) return 'VALPARAISO & VIÑA DEL MAR'; if (s.includes('MAIPO')) return 'WINE TOUR MAIPO VALLEY'; if (s.includes('CITY') || s.includes('SANTIAGO')) return 'CITY TOUR'; if (s.includes('PORTILLO') || s.includes('INCA') || s.includes('ANDES')) return 'PORTILLO INCA LAGOON'; return String(v || ''); }
function mainCategoryPreview(t) { const s = String(t || '').toUpperCase(); if (s.includes('VALPARAISO') || s.includes('VALPARAÍSO') || s.includes('VIÑA')) return 'VALPARAISO'; if (s.includes('MAIPO')) return 'MAIPO'; if (s.includes('CITY') || s.includes('SANTIAGO')) return 'CITY'; if (s.includes('PORTILLO') || s.includes('INCA') || s.includes('ANDES')) return 'PORTILLO'; return 'OTHER'; }
function esc(v) { return String(v || '').replace(/[&<>]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[ch])); }


const RESULT_TABLE_KEYS = [
    'status', 'issues', 'op_comment', 'date', 'vineyards', 'reservation_status', 'transport', 'guide', null, null, null,
    'code', 'op_source', 'sys_source', 'op_name', 'sys_name', 'op_tour', 'sys_tour', 'op_pax', 'sys_pax',
    'adults', 'children', 'infants', 'op_lang', 'sys_lang', 'op_hotel', 'sys_hotel', 'op_pickup', 'op_phone', 'sys_phone',
    'op_email', 'sys_email', 'booking_date', 'currency', 'price_total', 'net_price', 'is_private', 'sys_product', 'sys_row'
];

function setupEditableResultsTable() {
    const table = document.getElementById('enriched_results_table');
    if (!table || !Array.isArray(ENRICHED_RESULTS)) return;

    table.querySelectorAll('tbody tr').forEach(function(tr, rowIndex) {
        tr.dataset.resultIndex = String(rowIndex);
        tr.querySelectorAll('td').forEach(function(td, colIndex) {
            const key = RESULT_TABLE_KEYS[colIndex] || '';
            if (!key) return;
            td.contentEditable = 'true';
            td.classList.add('editable-result-cell');
            td.dataset.editResultIndex = String(rowIndex);
            td.dataset.resultKey = key;
            td.title = 'Editable: se guardará en la BD si presionas Aceptar y subir a BD';
        });
    });
}

function prepareEditedResultsForSecondEnrichment() {
    syncEditedResultsFromTable();
    const input = document.getElementById('second_enrich_results_json_input');
    if (input) {
        input.value = JSON.stringify(ENRICHED_RESULTS || []);
    }
    return true;
}

function prepareEditedResultsForDashboard() {
    syncEditedResultsFromTable();
    const input = document.getElementById('results_json_input');
    if (input) {
        input.value = JSON.stringify(ENRICHED_RESULTS || []);
    }
    return confirm('¿Confirmas guardar este resultado enriquecido editado en dashboard?');
}

function syncEditedResultsFromTable() {
    if (!Array.isArray(ENRICHED_RESULTS)) ENRICHED_RESULTS = [];
    document.querySelectorAll('[data-edit-result-index][data-result-key]').forEach(function(cell) {
        const idx = parseInt(cell.dataset.editResultIndex || '-1', 10);
        const key = cell.dataset.resultKey || '';
        if (idx < 0 || !key) return;
        if (!ENRICHED_RESULTS[idx]) ENRICHED_RESULTS[idx] = {};
        ENRICHED_RESULTS[idx][key] = normalizeEditedResultValue(key, cell.textContent || '');
    });
}

function normalizeEditedResultValue(key, rawValue) {
    const value = String(rawValue || '').replace(/\u00a0/g, ' ').trim();

    if (key === 'is_private') {
        const s = normalizePreviewName(value);
        if (s === '') return '';
        return ['1', 'YES', 'SI', 'SÍ', 'TRUE', 'PRIVATE', 'PVT', 'PRIVADO'].includes(s) ? 1 : 0;
    }

    if (['op_pax', 'sys_pax', 'adults', 'children', 'infants', 'adults_op', 'children_op', 'infants_op', 'vineyard_id', 'driver_id', 'guide_id'].includes(key)) {
        if (value === '') return null;
        const n = Number(value.replace(',', '.'));
        return Number.isFinite(n) ? Math.round(n) : null;
    }

    if (['price_total', 'net_price'].includes(key)) {
        return parseEditableMoney(value);
    }

    return value;
}

function parseEditableMoney(value) {
    const raw = String(value || '').trim();
    if (raw === '') return null;
    let num = raw.replace(/[^0-9,\.\-]/g, '');
    if (num === '' || num === '-') return null;
    if ((num.match(/,/g) || []).length === 1 && !num.includes('.')) {
        num = num.replace(',', '.');
    } else {
        num = num.replace(/,/g, '');
    }
    const parsed = Number(num);
    return Number.isFinite(parsed) ? parsed : null;
}

let previewTimer = null;
document.addEventListener('DOMContentLoaded', function() {
    setupEditableResultsTable();
    initFixedSheet(40);
    const table = document.getElementById('fixed_input_sheet');
    if (table) {
        table.addEventListener('input', function() {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(function(){ syncFixedSheetToTextarea(); renderPlanillaPreview(); }, 300);
        });
    }
    syncFixedSheetToTextarea();
    const textarea = document.getElementById('planilla_text');
    if (textarea && textarea.value.trim()) renderPlanillaPreview();
});
</script>
</body>
</html>
