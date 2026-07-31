<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';

/**
 * STAMP'S TOUR — Daily Operations Dashboard by Tour Instance
 * File: dashboard.php
 *
 * Shows daily operations as separated service instances:
 * same service_date + tour + driver + guide = one operational instance/card.
 *
 * Reads from:
 * - stampst1_dashboard.operational_reservations
 * - stampst1_dashboard.guides
 * - stampst1_dashboard.drivers
 * - stampst1_dashboard.vineyards
 *
 * Uses existing ../db_config.php connection.
 */

mysqli_report(MYSQLI_REPORT_OFF);
ini_set('display_errors', '0');
error_reporting(E_ALL);

$DASHBOARD_DB = 'stampst1_dashboard';
$errorMessage = '';
$debugMessages = [];

function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function cleanDash(mixed $value): string {
    $value = (string)($value ?? '');
    $value = str_replace("\u{00A0}", ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value ?? '');
}

function normalizeDateForSql(mixed $value): string {
    $value = cleanDash($value);
    if ($value === '') return date('Y-m-d');

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }

    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
}

function prettyDate(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : $date;
}

function decodeRawData(mixed $raw): array {
    if (!is_string($raw) || trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function firstNonEmpty(...$values): string {
    foreach ($values as $value) {
        $value = cleanDash($value);
        if ($value !== '') return $value;
    }
    return '';
}

function getRawValue(array $row, array $raw, array $keys): string {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && cleanDash($row[$key]) !== '') return cleanDash($row[$key]);
        if (array_key_exists($key, $raw) && cleanDash($raw[$key]) !== '') return cleanDash($raw[$key]);
    }
    return '';
}

function parseMoneyDash(mixed $value): ?float {
    if ($value === null || $value === '') return null;
    if (is_numeric($value)) return (float)$value;

    $s = cleanDash($value);
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

function moneyDash(mixed $value, string $currency = ''): string {
    $num = parseMoneyDash($value);
    if ($num === null) return '';
    $prefix = $currency !== '' ? $currency . ' ' : '';
    return $prefix . number_format($num, 2, '.', ',');
}

function clpDash(mixed $value): string {
    if ($value === null || $value === '') return '';
    $num = is_numeric($value) ? (int)round((float)$value) : 0;
    return '$' . number_format($num, 0, ',', '.');
}

function rowBookingDate(array $row, array $raw): string {
    return firstNonEmpty(
        $row['booking_date'] ?? '',
        getRawValue($row, $raw, ['booking_date', 'fecha_compra', 'purchase_date'])
    );
}

function rowNetPrice(array $row, array $raw): ?float {
    if (array_key_exists('net_price', $row) && $row['net_price'] !== null && $row['net_price'] !== '') {
        return parseMoneyDash($row['net_price']);
    }
    $rawNet = getRawValue($row, $raw, ['net_price', 'net', 'net_total']);
    return $rawNet !== '' ? parseMoneyDash($rawNet) : null;
}

function rowCurrency(array $row, array $raw): string {
    return firstNonEmpty(
        $row['currency'] ?? '',
        getRawValue($row, $raw, ['currency', 'moneda']),
        'USD'
    );
}

function statusLabel(string $value): string {
    $v = trim($value);
    return $v === '' ? 'normal' : $v;
}

function statusClass(string $value): string {
    $v = strtolower(trim($value));
    if ($v === 'ok') return 'ok';
    if ($v === 'normal' || $v === '') return 'normal';
    if ($v === 'revisar') return 'review';
    if (in_array($v, ['no show', 'refund', 'traspaso'], true)) return 'alert';
    return 'error';
}

function tableExists(mysqli $conn, string $db, string $table): bool {
    $dbEsc = $conn->real_escape_string($db);
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES FROM `{$dbEsc}` LIKE '{$tableEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function tableHasColumn(mysqli $conn, string $db, string $table, string $column): bool {
    $dbEsc = $conn->real_escape_string($db);
    $tableEsc = $conn->real_escape_string($table);
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$dbEsc}`.`{$tableEsc}` LIKE '{$colEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function tableColumnsDash(mysqli $conn, string $db, string $table): array {
    $dbEsc = preg_replace('/[^a-zA-Z0-9_]/', '', $db);
    $tableEsc = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($dbEsc === '' || $tableEsc === '') return [];

    $res = $conn->query("SHOW COLUMNS FROM `{$dbEsc}`.`{$tableEsc}`");
    if (!$res instanceof mysqli_result) return [];

    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $field = cleanDash($row['Field'] ?? '');
        if ($field !== '') $cols[$field] = true;
    }
    $res->free();
    return $cols;
}

function addNullAliasIfMissing(array &$select, array $columns, string $column): void {
    if (!isset($columns[$column])) {
        $select[] = "NULL AS `{$column}`";
    }
}

function rowPriceTotal(array $row, array $raw): ?float {
    if (array_key_exists('price_total', $row) && $row['price_total'] !== null && $row['price_total'] !== '') {
        return parseMoneyDash($row['price_total']);
    }
    $rawPrice = getRawValue($row, $raw, ['price_total', 'price', 'pvp', 'gross_price']);
    return $rawPrice !== '' ? parseMoneyDash($rawPrice) : null;
}

function rowNetPriceSafe(array $row, array $raw): ?float {
    $net = rowNetPrice($row, $raw);
    if ($net !== null) return $net;

    // Compatibilidad para cargas anteriores: Civitatis venía como PVP/price_total.
    // Para revisión diaria lo mostramos como net si net_price todavía está vacío.
    $source = mb_strtoupper(firstNonEmpty($row['source'] ?? '', getRawValue($row, $raw, ['op_source', 'sys_source'])), 'UTF-8');
    if ($source === 'CIV' || $source === 'CIVITATIS') {
        return rowPriceTotal($row, $raw);
    }

    return null;
}

function rowSaleValueSafe(array $row, array $raw): ?float {
    $price = rowPriceTotal($row, $raw);
    return $price !== null ? $price : rowNetPriceSafe($row, $raw);
}

function sourceKeyDash(array $row, array $raw): string {
    return firstNonEmpty($row['source'] ?? '', getRawValue($row, $raw, ['op_source', 'sys_source']), 'SIN FUENTE');
}

function buildSourceSummary(array $rows): array {
    $summary = [];
    foreach ($rows as $row) {
        $raw = decodeRawData($row['raw_data'] ?? null);
        $source = sourceKeyDash($row, $raw);
        if (!isset($summary[$source])) {
            $summary[$source] = [
                'source' => $source,
                'reservations' => 0,
                'pax' => 0,
                'net' => 0.0,
                'net_count' => 0,
                'currencies' => [],
            ];
        }
        $summary[$source]['reservations']++;
        $summary[$source]['pax'] += (int)($row['pax_total'] ?? 0);
        $cur = rowCurrency($row, $raw);
        if ($cur !== '') $summary[$source]['currencies'][$cur] = true;
        $net = rowNetPriceSafe($row, $raw);
        if ($net !== null) {
            $summary[$source]['net'] += $net;
            $summary[$source]['net_count']++;
        }
    }

    ksort($summary);
    return array_values($summary);
}

function findDbConfigPath(): ?string {
    $candidates = [
        dirname(__DIR__, 2) . '/db_config.php',
        __DIR__ . '/../db_config.php',
        dirname(__DIR__) . '/db_config.php',
        __DIR__ . '/db_config.php',
        __DIR__ . '/includes/db_config.php',
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) return $path;
    }

    return null;
}

function loadDashboardRows(mysqli $conn, string $db, string $dateType, string $dateFrom, string $dateTo, array &$debugMessages): array {
    if (!tableExists($conn, $db, 'operational_reservations')) {
        throw new RuntimeException("No existe {$db}.operational_reservations.");
    }

    $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $db);
    if ($safeDb === '') {
        throw new RuntimeException('Nombre de base dashboard inválido.');
    }

    $columns = tableColumnsDash($conn, $safeDb, 'operational_reservations');
    if (empty($columns)) {
        throw new RuntimeException("No se pudo leer la estructura de {$safeDb}.operational_reservations.");
    }

    $dateColumn = $dateType === 'booking_date' ? 'booking_date' : 'service_date';
    if (!isset($columns[$dateColumn])) {
        throw new RuntimeException('La tabla operational_reservations necesita la columna ' . $dateColumn . ' para consultar por rango.');
    }

    $hasVineyardId = isset($columns['vineyard_id']);
    $hasRawData = isset($columns['raw_data']);
    $hasDriverId = isset($columns['driver_id']);
    $hasGuideId = isset($columns['guide_id']);

    $hasVineyardsTable = tableExists($conn, $safeDb, 'vineyards');
    $hasGuidesTable = tableExists($conn, $safeDb, 'guides');
    $hasDriversTable = tableExists($conn, $safeDb, 'drivers');

    $select = ["r.*"];
    $optionalColumns = [
        'closure_id', 'booking_date', 'booking_reference', 'tour_id', 'vineyard_id', 'is_private',
        'customer_name', 'adults', 'children', 'infants', 'pax_total', 'hotel', 'pickup_time',
        'phone', 'language', 'source', 'driver_id', 'guide_id', 'match_status', 'validation_status',
        'comments', 'operational_comment', 'source_service_date', 'source_booking_status',
        'price_total', 'net_price', 'currency', 'raw_data', 'created_at', 'updated_at'
    ];
    foreach ($optionalColumns as $col) {
        addNullAliasIfMissing($select, $columns, $col);
    }

    $joins = [];

    if ($hasVineyardId && $hasVineyardsTable) {
        $select[] = "v.name AS vineyard_name";
        $select[] = "v.category AS vineyard_category";
        $joins[] = "LEFT JOIN `{$safeDb}`.`vineyards` v ON v.id = r.vineyard_id";
    } else {
        $select[] = "NULL AS vineyard_name";
        $select[] = "NULL AS vineyard_category";
    }

    if ($hasGuideId && $hasGuidesTable) {
        $select[] = "g.name AS guide_name";
        $select[] = "g.category AS guide_category";
        $joins[] = "LEFT JOIN `{$safeDb}`.`guides` g ON g.id = r.guide_id";
    } else {
        $select[] = "NULL AS guide_name";
        $select[] = "NULL AS guide_category";
    }

    if ($hasDriverId && $hasDriversTable) {
        $select[] = "d.name AS driver_name";
        $select[] = "d.category AS driver_category";
        $joins[] = "LEFT JOIN `{$safeDb}`.`drivers` d ON d.id = r.driver_id";
    } else {
        $select[] = "NULL AS driver_name";
        $select[] = "NULL AS driver_category";
    }

    $order = [];
    if (isset($columns['service_date'])) {
        $order[] = "r.service_date";
    }
    if (isset($columns['pickup_time'])) {
        $order[] = "r.pickup_time IS NULL";
        $order[] = "r.pickup_time";
    }
    if (isset($columns['driver_id'])) {
        $order[] = "r.driver_id IS NULL";
        $order[] = "r.driver_id";
    }
    if (isset($columns['guide_id'])) {
        $order[] = "r.guide_id IS NULL";
        $order[] = "r.guide_id";
    }
    if (isset($columns['customer_name'])) $order[] = "r.customer_name";
    elseif (isset($columns['booking_reference'])) $order[] = "r.booking_reference";
    elseif (isset($columns['id'])) $order[] = "r.id";
    else $order[] = "1";

    $dateExpression = "DATE(r.`{$dateColumn}`)";
    $sql = "
        SELECT " . implode(",\n               ", $select) . "
        FROM `{$safeDb}`.`operational_reservations` r
        " . implode("\n        ", $joins) . "
        WHERE {$dateExpression} BETWEEN ? AND ?
        ORDER BY " . implode(",\n                 ", $order) . "
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Error preparando consulta dashboard: ' . $conn->error);
    }

    $stmt->bind_param('ss', $dateFrom, $dateTo);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Error ejecutando consulta dashboard: ' . $err);
    }

    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        if (!$hasRawData) $row['raw_data'] = null;
        $rows[] = $row;
    }
    $stmt->close();

    $debugMessages[] = 'Dashboard rows loaded: ' . count($rows);
    $debugMessages[] = 'Filtro fecha: ' . $dateColumn . ' entre ' . $dateFrom . ' y ' . $dateTo;
    $debugMessages[] = 'Columnas detectadas operational_reservations: ' . implode(', ', array_keys($columns));
    return $rows;
}

function canonicalTourFromRow(array $row, array $raw): string {
    return firstNonEmpty(
        getRawValue($row, $raw, ['op_tour']),
        getRawValue($row, $raw, ['tour_planilla']),
        getRawValue($row, $raw, ['sys_tour']),
        getRawValue($row, $raw, ['tour_sistema']),
        'SIN TOUR'
    );
}

function categoryFromTour(string $tour): string {
    $t = mb_strtoupper($tour, 'UTF-8');

    $replacements = [
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N'
    ];
    $t = strtr($t, $replacements);

    if (str_contains($t, 'VALPARAISO') || str_contains($t, 'VINA')) return 'VALPARAISO';
    if (str_contains($t, 'MAIPO') || str_contains($t, 'WINE')) return 'MAIPO';
    if (str_contains($t, 'PORTILLO') || str_contains($t, 'INCA') || str_contains($t, 'ANDES')) return 'PORTILLO';
    if (str_contains($t, 'CITY') || str_contains($t, 'SANTIAGO')) return 'CITY';
    return 'OTRO';
}

function rowDriverName(array $row, array $raw): string {
    return firstNonEmpty($row['driver_name'] ?? '', getRawValue($row, $raw, ['transport', 'driver']));
}

function rowGuideName(array $row, array $raw): string {
    return firstNonEmpty($row['guide_name'] ?? '', getRawValue($row, $raw, ['guide']));
}

function rowVineyardName(array $row, array $raw): string {
    return firstNonEmpty($row['vineyard_name'] ?? '', getRawValue($row, $raw, ['vineyards', 'vineyard']));
}

function instanceKey(array $row, array $raw): string {
    $groupId = cleanDash($raw['group_id'] ?? '');
    if ($groupId !== '') {
        return 'GROUP:' . mb_strtoupper($groupId, 'UTF-8');
    }

    $serviceDate = cleanDash($row['service_date'] ?? getRawValue($row, $raw, ['date']));
    $tour = mb_strtoupper(canonicalTourFromRow($row, $raw), 'UTF-8');

    $driverId = cleanDash($row['driver_id'] ?? '');
    $guideId = cleanDash($row['guide_id'] ?? '');

    $driver = $driverId !== '' ? 'DID:' . $driverId : 'DN:' . mb_strtoupper(rowDriverName($row, $raw), 'UTF-8');
    $guide = $guideId !== '' ? 'GID:' . $guideId : 'GN:' . mb_strtoupper(rowGuideName($row, $raw), 'UTF-8');

    // Fallback: same service date + tour + driver + guide are one service instance.
    return $serviceDate . '|' . $tour . '|' . $driver . '|' . $guide;
}

function groupRowsByOperationalInstance(array $rows): array {
    $instances = [];
    $sequence = 0;

    foreach ($rows as $row) {
        $raw = decodeRawData($row['raw_data'] ?? null);
        $tour = canonicalTourFromRow($row, $raw);
        $driverName = rowDriverName($row, $raw);
        $guideName = rowGuideName($row, $raw);
        $vineyardName = rowVineyardName($row, $raw);
        $key = instanceKey($row, $raw);

        if (!isset($instances[$key])) {
            $sequence++;
            $instances[$key] = [
                'sequence' => $sequence,
                'category' => categoryFromTour($tour),
                'tour' => $tour,
                'tour_id' => $row['tour_id'] ?? null,
                'service_date' => cleanDash($row['service_date'] ?? ''),
                'driver_id' => $row['driver_id'] ?? null,
                'driver_name' => $driverName,
                'driver_category' => cleanDash($row['driver_category'] ?? ''),
                'guide_id' => $row['guide_id'] ?? null,
                'guide_name' => $guideName,
                'guide_category' => cleanDash($row['guide_category'] ?? ''),
                'vineyards' => [],
                'rows' => [],
                'pax' => 0,
                'adults' => 0,
                'children' => 0,
                'infants' => 0,
                'sources' => [],
                'languages' => [],
                'currencies' => [],
                'net_price_sum' => 0.0,
                'net_price_count' => 0,
                'sale_value_sum' => 0.0,
                'sale_value_count' => 0,
                'status_counts' => [],
                'first_pickup' => cleanDash($row['pickup_time'] ?? ''),
            ];
        }

        if ($vineyardName !== '') {
            $instances[$key]['vineyards'][$vineyardName] = true;
        }

        $row['_dash_raw'] = $raw;
        $row['_dash_vineyard'] = $vineyardName;
        $row['_dash_booking_date'] = rowBookingDate($row, $raw);
        $row['_dash_currency'] = rowCurrency($row, $raw);
        $row['_dash_net_price'] = rowNetPriceSafe($row, $raw);
        $row['_dash_sale_value'] = rowSaleValueSafe($row, $raw);

        if ($row['_dash_currency'] !== '') {
            $instances[$key]['currencies'][$row['_dash_currency']] = true;
        }
        if ($row['_dash_net_price'] !== null) {
            $instances[$key]['net_price_sum'] += $row['_dash_net_price'];
            $instances[$key]['net_price_count']++;
        }
        if ($row['_dash_sale_value'] !== null) {
            $instances[$key]['sale_value_sum'] += $row['_dash_sale_value'];
            $instances[$key]['sale_value_count']++;
        }

        $instances[$key]['rows'][] = $row;
        $instances[$key]['pax'] += (int)($row['pax_total'] ?? 0);
        $instances[$key]['adults'] += (int)($row['adults'] ?? 0);
        $instances[$key]['children'] += (int)($row['children'] ?? 0);
        $instances[$key]['infants'] += (int)($row['infants'] ?? 0);

        $source = cleanDash($row['source'] ?? '');
        if ($source !== '') $instances[$key]['sources'][$source] = true;

        $lang = cleanDash($row['language'] ?? '');
        if ($lang !== '') $instances[$key]['languages'][$lang] = true;

        $validation = statusLabel(cleanDash($row['validation_status'] ?? ''));
        $match = statusLabel(cleanDash($row['match_status'] ?? ''));
        $statusKey = $validation !== 'normal' ? $validation : $match;
        $instances[$key]['status_counts'][$statusKey] = ($instances[$key]['status_counts'][$statusKey] ?? 0) + 1;

        $pickup = cleanDash($row['pickup_time'] ?? '');
        if ($instances[$key]['first_pickup'] === '' || ($pickup !== '' && $pickup < $instances[$key]['first_pickup'])) {
            $instances[$key]['first_pickup'] = $pickup;
        }
    }

    uasort($instances, function ($a, $b) {
        return [
            $a['service_date'],
            $a['first_pickup'] === '' ? '99:99' : $a['first_pickup'],
            $a['category'],
            $a['tour'],
            $a['driver_name'],
            $a['guide_name'],
            $a['sequence']
        ] <=> [
            $b['service_date'],
            $b['first_pickup'] === '' ? '99:99' : $b['first_pickup'],
            $b['category'],
            $b['tour'],
            $b['driver_name'],
            $b['guide_name'],
            $b['sequence']
        ];
    });

    return array_values($instances);
}

function instanceHasAssignedDriver(array $instance): bool {
    return cleanDash($instance['driver_id'] ?? '') !== '';
}

function instanceIsRefund(array $instance): bool {
    foreach (($instance['status_counts'] ?? []) as $status => $count) {
        if ((int)$count > 0 && mb_strtolower(cleanDash($status), 'UTF-8') === 'refund') {
            return true;
        }
    }
    return false;
}

function instanceWasOperated(array $instance): bool {
    return instanceHasAssignedDriver($instance) && !instanceIsRefund($instance);
}

function operatedInstances(array $instances): array {
    return array_values(array_filter($instances, fn($instance) => instanceWasOperated($instance)));
}

function buildStaffTourReport(array $instances, string $staffType): array {
    $report = [];
    foreach ($instances as $instance) {
        $idKey = $staffType === 'driver' ? 'driver_id' : 'guide_id';
        $nameKey = $staffType === 'driver' ? 'driver_name' : 'guide_name';
        $categoryKey = $staffType === 'driver' ? 'driver_category' : 'guide_category';
        $name = cleanDash($instance[$nameKey] ?? '');
        $id = cleanDash($instance[$idKey] ?? '');
        if ($id === '' || $name === '') continue;

        $key = 'ID:' . $id;
        if (!isset($report[$key])) {
            $report[$key] = [
                'name' => $name,
                'id' => $id,
                'category' => cleanDash($instance[$categoryKey] ?? ''),
                'instances' => 0,
                'pax' => 0,
                'sale' => 0.0,
                'sale_count' => 0,
                'currencies' => [],
                'tours' => [],
            ];
        }

        $tour = cleanDash($instance['tour'] ?? 'SIN TOUR');
        if ($tour === '') $tour = 'SIN TOUR';
        if (!isset($report[$key]['tours'][$tour])) {
            $report[$key]['tours'][$tour] = ['instances' => 0, 'pax' => 0, 'sale' => 0.0, 'sale_count' => 0, 'dates' => []];
        }

        $serviceDate = cleanDash($instance['service_date'] ?? '');
        $report[$key]['instances']++;
        $report[$key]['pax'] += (int)($instance['pax'] ?? 0);
        $report[$key]['tours'][$tour]['instances']++;
        $report[$key]['tours'][$tour]['pax'] += (int)($instance['pax'] ?? 0);
        if ($serviceDate !== '') {
            $report[$key]['tours'][$tour]['dates'][$serviceDate] = true;
        }

        foreach (($instance['currencies'] ?? []) as $currency => $_) {
            $report[$key]['currencies'][$currency] = true;
        }
        if ((int)($instance['sale_value_count'] ?? 0) > 0) {
            $sale = (float)($instance['sale_value_sum'] ?? 0);
            $report[$key]['sale'] += $sale;
            $report[$key]['sale_count']++;
            $report[$key]['tours'][$tour]['sale'] += $sale;
            $report[$key]['tours'][$tour]['sale_count']++;
        }
    }

    uasort($report, fn($a, $b) => [$b['instances'], $b['pax'], $a['name']] <=> [$a['instances'], $a['pax'], $b['name']]);
    foreach ($report as &$row) {
        uasort($row['tours'], fn($a, $b) => [$b['instances'], $b['pax']] <=> [$a['instances'], $a['pax']]);
    }
    unset($row);

    return array_values($report);
}

function loadPaymentRateConfig(mysqli $conn, string $db, array &$debugMessages): array {
    $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $db);
    $config = [
        'tour_payment_types' => [],
        'guide_rates' => [],
        'driver_rates' => [],
        'missing_tables' => [],
    ];

    if ($safeDb === '') return $config;

    if (tableExists($conn, $safeDb, 'tour_payment_types')) {
        $res = $conn->query("SELECT tour_id, payment_type FROM `{$safeDb}`.`tour_payment_types` WHERE is_active = 1");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $tourId = (int)($row['tour_id'] ?? 0);
                $paymentType = cleanDash($row['payment_type'] ?? '');
                if ($tourId > 0 && $paymentType !== '') {
                    $config['tour_payment_types'][$tourId] = $paymentType;
                }
            }
            $res->free();
        }
    } else {
        $config['missing_tables'][] = 'tour_payment_types';
    }

    if (tableExists($conn, $safeDb, 'guide_payment_rates')) {
        $res = $conn->query("SELECT guide_category, payment_type, amount_clp FROM `{$safeDb}`.`guide_payment_rates` WHERE is_active = 1");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $category = cleanDash($row['guide_category'] ?? '');
                $paymentType = cleanDash($row['payment_type'] ?? '');
                if ($category !== '' && $paymentType !== '') {
                    $config['guide_rates'][$category . '|' . $paymentType] = (int)($row['amount_clp'] ?? 0);
                }
            }
            $res->free();
        }
    } else {
        $config['missing_tables'][] = 'guide_payment_rates';
    }

    if (tableExists($conn, $safeDb, 'driver_payment_rates')) {
        $res = $conn->query("SELECT driver_category, tour_id, payment_type, amount_clp FROM `{$safeDb}`.`driver_payment_rates` WHERE is_active = 1");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $category = cleanDash($row['driver_category'] ?? '');
                $tourId = $row['tour_id'] === null ? '*' : (string)(int)$row['tour_id'];
                $paymentType = cleanDash($row['payment_type'] ?? '');
                if ($category !== '' && $paymentType !== '') {
                    $config['driver_rates'][$category . '|' . $tourId . '|' . $paymentType] = (int)($row['amount_clp'] ?? 0);
                }
            }
            $res->free();
        }
    } else {
        $config['missing_tables'][] = 'driver_payment_rates';
    }

    if (!empty($config['missing_tables'])) {
        $debugMessages[] = 'Tablas de tarifas faltantes: ' . implode(', ', $config['missing_tables']);
    }

    return $config;
}

function categoryRateCandidates(string $category): array {
    $category = cleanDash($category);
    if ($category === '') return [];

    $candidates = [$category];
    if (preg_match('/^(\d+)/', $category, $m) && $m[1] !== $category) {
        $candidates[] = $m[1];
    }

    return array_values(array_unique($candidates));
}

function paymentTypeForInstance(array $instance, array $rateConfig): string {
    $tourId = (int)($instance['tour_id'] ?? 0);
    $tourPaymentTypes = $rateConfig['tour_payment_types'] ?? [];
    if ($tourId > 0 && isset($tourPaymentTypes[$tourId])) {
        return cleanDash($tourPaymentTypes[$tourId]);
    }

    return cleanDash($instance['category'] ?? '') === 'CITY' ? 'CITY' : 'FULL_DAY';
}

function lookupGuideRate(array $instance, array $rateConfig, string $paymentType): ?int {
    $guideRates = $rateConfig['guide_rates'] ?? [];
    foreach (categoryRateCandidates(cleanDash($instance['guide_category'] ?? '')) as $category) {
        $key = $category . '|' . $paymentType;
        if (isset($guideRates[$key])) return (int)$guideRates[$key];
    }
    return null;
}

function lookupDriverRate(array $instance, array $rateConfig, string $paymentType): ?int {
    $tourId = (int)($instance['tour_id'] ?? 0);
    $driverRates = $rateConfig['driver_rates'] ?? [];
    foreach (categoryRateCandidates(cleanDash($instance['driver_category'] ?? '')) as $category) {
        if ($tourId > 0) {
            $exactKey = $category . '|' . $tourId . '|' . $paymentType;
            if (isset($driverRates[$exactKey])) return (int)$driverRates[$exactKey];
        }

        $fallbackKey = $category . '|*|' . $paymentType;
        if (isset($driverRates[$fallbackKey])) return (int)$driverRates[$fallbackKey];

        if ($category === '0') {
            if ($paymentType === 'FULL_DAY') return 65000;
            if ($paymentType === 'CITY') return 50000;
        }
    }
    return null;
}

function buildStaffPaymentReport(array $instances, string $staffType, array $rateConfig): array {
    $report = [];

    foreach ($instances as $instance) {
        $idKey = $staffType === 'driver' ? 'driver_id' : 'guide_id';
        $nameKey = $staffType === 'driver' ? 'driver_name' : 'guide_name';
        $categoryKey = $staffType === 'driver' ? 'driver_category' : 'guide_category';
        $name = cleanDash($instance[$nameKey] ?? '');
        $id = cleanDash($instance[$idKey] ?? '');
        if ($id === '' || $name === '') continue;

        $paymentType = paymentTypeForInstance($instance, $rateConfig);
        $rate = $staffType === 'driver'
            ? lookupDriverRate($instance, $rateConfig, $paymentType)
            : lookupGuideRate($instance, $rateConfig, $paymentType);

        $key = 'ID:' . $id;
        if (!isset($report[$key])) {
            $report[$key] = [
                'name' => $name,
                'id' => $id,
                'category' => cleanDash($instance[$categoryKey] ?? ''),
                'instances' => 0,
                'pax' => 0,
                'amount_clp' => 0,
                'missing_rates' => 0,
                'details' => [],
            ];
        }

        $amount = $rate ?? 0;
        $report[$key]['instances']++;
        $report[$key]['pax'] += (int)($instance['pax'] ?? 0);
        $report[$key]['amount_clp'] += $amount;
        if ($rate === null) $report[$key]['missing_rates']++;
        $report[$key]['details'][] = [
            'service_date' => cleanDash($instance['service_date'] ?? ''),
            'tour' => cleanDash($instance['tour'] ?? ''),
            'tour_id' => cleanDash($instance['tour_id'] ?? ''),
            'payment_type' => $paymentType,
            'pax' => (int)($instance['pax'] ?? 0),
            'amount_clp' => $rate,
            'status' => $rate === null ? 'Tarifa no encontrada' : 'OK',
        ];
    }

    uasort($report, fn($a, $b) => [$b['amount_clp'], $b['instances'], $a['name']] <=> [$a['amount_clp'], $a['instances'], $b['name']]);
    return array_values($report);
}

function paymentReportTotal(array $report): int {
    return array_sum(array_map(fn($row) => (int)($row['amount_clp'] ?? 0), $report));
}

function paymentReportMissingRates(array $report): int {
    return array_sum(array_map(fn($row) => (int)($row['missing_rates'] ?? 0), $report));
}

function operatedSalesTotal(array $instances): array {
    $total = ['sale' => 0.0, 'sale_count' => 0, 'currencies' => [], 'instances' => count($instances), 'pax' => 0];
    foreach ($instances as $instance) {
        $total['pax'] += (int)($instance['pax'] ?? 0);
        foreach (($instance['currencies'] ?? []) as $currency => $_) {
            $total['currencies'][$currency] = true;
        }
        if ((int)($instance['sale_value_count'] ?? 0) > 0) {
            $total['sale'] += (float)($instance['sale_value_sum'] ?? 0);
            $total['sale_count']++;
        }
    }
    return $total;
}

function setToText(array $set): string {
    $keys = array_keys($set);
    sort($keys);
    return implode(', ', $keys);
}

function yesNoPrivate(mixed $value): string {
    return ((int)$value === 1) ? 'Sí' : 'No';
}

function totalCurrencyLabel(array $currencies): string {
    $txt = setToText($currencies);
    return $txt !== '' ? $txt : 'USD';
}

$configPath = findDbConfigPath();

if ($configPath === null) {
    $errorMessage = 'No se encontró db_config.php. Sube dashboard.php a la misma carpeta del script operativo o revisa la ruta ../db_config.php.';
} else {
    require_once $configPath;
}

if ($errorMessage === '' && (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error)) {
    $errorMessage = 'No se pudo conectar con MySQL usando db_config.php.';
}

if ($errorMessage === '') {
    $conn->set_charset('utf8mb4');
}

$selectedDateType = cleanDash($_GET['date_type'] ?? 'travel_date');
if (!in_array($selectedDateType, ['travel_date', 'booking_date'], true)) {
    $selectedDateType = 'travel_date';
}
$selectedDateFrom = normalizeDateForSql($_GET['date_from'] ?? ($_GET['date'] ?? date('Y-m-d')));
$selectedDateTo = normalizeDateForSql($_GET['date_to'] ?? $selectedDateFrom);
if ($selectedDateFrom > $selectedDateTo) {
    [$selectedDateFrom, $selectedDateTo] = [$selectedDateTo, $selectedDateFrom];
}
$rows = [];
$instances = [];

if ($errorMessage === '') {
    try {
        $rows = loadDashboardRows($conn, $DASHBOARD_DB, $selectedDateType, $selectedDateFrom, $selectedDateTo, $debugMessages);
        $instances = groupRowsByOperationalInstance($rows);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$operatedInstances = operatedInstances($instances);
$paymentRateConfig = [];
if ($errorMessage === '' && isset($conn) && $conn instanceof mysqli) {
    $paymentRateConfig = loadPaymentRateConfig($conn, $DASHBOARD_DB, $debugMessages);
}
$guideTourReport = buildStaffTourReport($operatedInstances, 'guide');
$driverTourReport = buildStaffTourReport($operatedInstances, 'driver');
$guidePaymentReport = buildStaffPaymentReport($operatedInstances, 'guide', $paymentRateConfig);
$driverPaymentReport = buildStaffPaymentReport($operatedInstances, 'driver', $paymentRateConfig);
$guidePaymentTotal = paymentReportTotal($guidePaymentReport);
$driverPaymentTotal = paymentReportTotal($driverPaymentReport);
$paymentMissingRates = paymentReportMissingRates($guidePaymentReport) + paymentReportMissingRates($driverPaymentReport);
$operatedSales = operatedSalesTotal($operatedInstances);
$debugMessages[] = 'Instancias agrupadas: ' . count($instances) . ' | Instancias operadas: ' . count($operatedInstances);

$totalInstances = count($instances);
$totalReservations = count($rows);
$totalPax = array_sum(array_map(fn($r) => (int)($r['pax_total'] ?? 0), $rows));
$totalAdults = array_sum(array_map(fn($r) => (int)($r['adults'] ?? 0), $rows));
$totalChildren = array_sum(array_map(fn($r) => (int)($r['children'] ?? 0), $rows));
$totalInfants = array_sum(array_map(fn($r) => (int)($r['infants'] ?? 0), $rows));

$sourceSummary = buildSourceSummary($rows);

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Stamp's Tour — Dashboard Operativo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <link href="/css/vendors.css" rel="stylesheet">
    <link href="/css/admin.css" rel="stylesheet">
    <link href="/css/custom.css" rel="stylesheet">
    <style>
        :root{
            --bg:#f4f6f8;
            --card:#ffffff;
            --ink:#111827;
            --muted:#6b7280;
            --line:#111827;
            --soft-line:#d1d5db;
            --dark:#111827;
            --ok:#e6f4ea;
            --review:#fff4ce;
            --error:#fce8e6;
            --money:#ecfdf5;
        }
        *{box-sizing:border-box}
        body{font-family:Arial,sans-serif;background:var(--bg);margin:0;padding:18px;color:var(--ink)}
        .container{max-width:1800px;margin:0 auto}
        .topbar{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:16px}
        h1{margin:0 0 6px;font-size:28px}
        .subtitle{margin:0;color:var(--muted);font-size:12px}
        .date-card{background:var(--card);border-radius:14px;padding:14px 16px;box-shadow:0 2px 8px rgba(0,0,0,.06);min-width:360px}
        label{font-weight:700;font-size:13px;display:block;margin-bottom:6px}
        input[type=date]{width:100%;padding:10px;border:1px solid var(--soft-line);border-radius:8px;font-size:15px}
        .radio-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px}
        .radio-row label{display:inline-flex;align-items:center;gap:5px;margin:0;font-weight:700;color:var(--ink)}
        .date-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        button{background:var(--dark);color:#fff;border:0;border-radius:8px;padding:10px 13px;font-weight:700;cursor:pointer;margin-top:8px}
        button:hover{background:#374151}
        .summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:16px 0}
        .stat{background:var(--card);border-radius:14px;padding:15px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
        .stat .num{font-size:28px;font-weight:800;margin-bottom:4px}
        .stat .label{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700}
        .stat.money{background:var(--money);border:1px solid #bbf7d0}
        .error-box{background:var(--error);border:1px solid #ef4444;color:#7f1d1d;padding:14px;border-radius:12px;margin:14px 0;font-weight:700}
        .empty{background:var(--card);border:1px dashed var(--soft-line);border-radius:14px;padding:22px;color:var(--muted);text-align:center}
        .instance-card{background:var(--card);border:3px solid var(--line);border-radius:14px;margin:18px 0 24px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08)}
        .instance-head{display:grid;grid-template-columns:1.2fr 1.6fr 1fr 1fr 1fr 1fr;gap:0;background:var(--dark);color:#fff}
        .head-cell{padding:14px 16px;border-right:1px solid rgba(255,255,255,.25)}
        .head-cell:last-child{border-right:0}
        .head-title{font-size:14px;text-transform:uppercase;font-weight:800;line-height:1.25}
        .head-sub{font-size:11px;opacity:.85;font-weight:700;margin-top:12px}
        .assignment-line{display:grid;grid-template-columns:repeat(6,1fr);gap:0;background:#f9fafb;border-bottom:2px solid var(--line)}
        .assign-cell{padding:10px 12px;border-right:1px solid var(--soft-line);font-size:13px}
        .assign-cell:last-child{border-right:0}
        .assign-cell strong{display:block;font-size:11px;text-transform:uppercase;color:var(--muted);margin-bottom:4px}
        .mini-line{display:flex;gap:8px;flex-wrap:wrap;padding:10px 12px;background:#fff;border-bottom:2px solid var(--line)}
        .pill{display:inline-flex;align-items:center;gap:4px;background:#f3f4f6;border:1px solid var(--soft-line);border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800}
        table{width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
        th,td{border:1px solid var(--line);padding:7px;vertical-align:middle;word-break:break-word;text-align:center}
        th{background:#f3f4f6;color:#111827;font-weight:800}
        td.left{text-align:left}
        td.money-cell{background:#f0fdf4;font-weight:700}
        .hotel{min-width:220px}
        .nowrap{white-space:nowrap}
        .status{display:inline-block;border-radius:999px;padding:3px 7px;font-size:11px;font-weight:800}
        .status.ok,.status.normal{background:var(--ok);color:#166534}
        .status.review{background:var(--review);color:#92400e}
        .status.alert,.status.error{background:var(--error);color:#991b1b}
        .total-row td{background:#f3f4f6!important;font-weight:900}
        .total-row td.money-cell{background:#dcfce7!important}
        .debug{margin-top:22px;color:var(--muted);font-size:12px}
        .audit-card{background:var(--card);border-radius:14px;padding:16px;margin:16px 0 22px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
        .report-card{background:var(--card);border-radius:14px;padding:16px;margin:16px 0 22px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
        .report-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:14px}
        .report-table{min-width:700px}
        .muted{color:var(--muted)}
        .audit-scroll{overflow:auto;border:1px solid var(--soft-line);border-radius:10px;background:#fff}
        .audit-scroll table{min-width:1500px}
        .audit-title{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px}
        .audit-title h2{margin:0;font-size:18px}
        .source-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:10px 0 14px}
        .source-box{background:#f9fafb;border:1px solid var(--soft-line);border-radius:10px;padding:10px}
        .source-box strong{display:block;font-size:12px;color:var(--muted);text-transform:uppercase;margin-bottom:4px}
        details{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px}
        @media(max-width:1100px){
            .instance-head{grid-template-columns:1fr 1fr}
            .assignment-line{grid-template-columns:1fr 1fr}
        }
        @media(max-width:760px){
            body{padding:10px}
            .topbar{display:block}
            .date-card{margin-top:12px;min-width:0}
            .date-grid{grid-template-columns:1fr}
            .instance-head,.assignment-line{grid-template-columns:1fr}
            .head-cell,.assign-cell{border-right:0;border-bottom:1px solid rgba(255,255,255,.15)}
            .instance-card{overflow:auto}
            table{min-width:1450px}
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('dashboard'); ?>
<div class="container">
    <div class="topbar">
        <div>
            <h1>Dashboard Operativo</h1>
            <p class="subtitle">Vista por instancia de servicio: mismo tour + mismo driver + misma guía.</p>
            <p class="subtitle">Base: <strong><?= e($DASHBOARD_DB) ?></strong> · Filtro: <?= e($selectedDateType === 'booking_date' ? 'Booking Date' : 'Travel Date') ?> · <?= e(prettyDate($selectedDateFrom)) ?> a <?= e(prettyDate($selectedDateTo)) ?></p>
        </div>

        <form class="date-card" method="get">
            <label>Fecha para el reporte</label>
            <div class="radio-row">
                <label><input type="radio" name="date_type" value="travel_date" <?= $selectedDateType === 'travel_date' ? 'checked' : '' ?>> Travel Date</label>
                <label><input type="radio" name="date_type" value="booking_date" <?= $selectedDateType === 'booking_date' ? 'checked' : '' ?>> Booking Date</label>
            </div>
            <div class="date-grid">
                <div>
                    <label for="date_from">Desde</label>
                    <input type="date" id="date_from" name="date_from" value="<?= e($selectedDateFrom) ?>">
                </div>
                <div>
                    <label for="date_to">Hasta</label>
                    <input type="date" id="date_to" name="date_to" value="<?= e($selectedDateTo) ?>">
                </div>
            </div>
            <button type="submit">Generar reportes</button>
        </form>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="error-box"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage === ''): ?>
        <div class="summary">
            <div class="stat"><div class="num"><?= e($totalInstances) ?></div><div class="label">Instancias</div></div>
            <div class="stat"><div class="num"><?= e($totalReservations) ?></div><div class="label">Reservas</div></div>
            <div class="stat"><div class="num"><?= e($totalPax) ?></div><div class="label">Pax total</div></div>
            <div class="stat"><div class="num"><?= e($totalAdults) ?></div><div class="label">Adultos</div></div>
            <div class="stat"><div class="num"><?= e($totalChildren) ?></div><div class="label">Niños</div></div>
            <div class="stat"><div class="num"><?= e($totalInfants) ?></div><div class="label">Infantes</div></div>
            <div class="stat"><div class="num"><?= e(count($operatedInstances)) ?></div><div class="label">Instancias operadas</div></div>
            <div class="stat money"><div class="num"><?= e($operatedSales['sale_count'] > 0 ? moneyDash($operatedSales['sale'], totalCurrencyLabel($operatedSales['currencies'])) : '-') ?></div><div class="label">Venta total operada</div></div>
            <div class="stat money"><div class="num"><?= e(clpDash($guidePaymentTotal + $driverPaymentTotal)) ?></div><div class="label">Pagos estimados</div></div>
            <div class="stat"><div class="num"><?= e($paymentMissingRates) ?></div><div class="label">Tarifas faltantes</div></div>
        </div>

        <div class="report-grid">
            <div class="report-card">
                <div class="audit-title">
                    <h2>Pagos por guía</h2>
                    <span class="subtitle">Calculado por instancia operada y tarifa de categoría.</span>
                </div>
                <div class="audit-scroll">
                    <table class="report-table">
                        <thead>
                        <tr>
                            <th style="width:170px;">Guía</th>
                            <th style="width:95px;">Instancias</th>
                            <th style="width:80px;">Pax</th>
                            <th>Detalle</th>
                            <th style="width:130px;">Pago</th>
                            <th style="width:110px;">Revisar</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($guidePaymentReport)): ?>
                            <tr><td colspan="6" class="left">No hay guías con pago calculable en este rango.</td></tr>
                        <?php else: ?>
                            <?php foreach ($guidePaymentReport as $staff): ?>
                                <tr>
                                    <td class="left"><strong><?= e($staff['name']) ?></strong><br><span class="muted">ID <?= e($staff['id']) ?><?= $staff['category'] !== '' ? ' · Cat. ' . e($staff['category']) : '' ?></span></td>
                                    <td><?= e($staff['instances']) ?></td>
                                    <td><?= e($staff['pax']) ?></td>
                                    <td class="left">
                                        <?php foreach ($staff['details'] as $detail): ?>
                                            <div>
                                                <strong><?= e($detail['service_date']) ?></strong> · <?= e($detail['tour']) ?> · <?= e($detail['payment_type']) ?> · <?= e($detail['pax']) ?> pax:
                                                <?= e($detail['amount_clp'] !== null ? clpDash($detail['amount_clp']) : 'tarifa no encontrada') ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="money-cell"><?= e(clpDash($staff['amount_clp'])) ?></td>
                                    <td><?= e((int)$staff['missing_rates'] > 0 ? $staff['missing_rates'] . ' tarifa(s)' : 'OK') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="4">Total pagos guías</td>
                                <td class="money-cell"><?= e(clpDash($guidePaymentTotal)) ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="report-card">
                <div class="audit-title">
                    <h2>Pagos por driver</h2>
                    <span class="subtitle">Cat. 1 usa tarifa general; cat. 2 y 3 usan tarifa por tour.</span>
                </div>
                <div class="audit-scroll">
                    <table class="report-table">
                        <thead>
                        <tr>
                            <th style="width:170px;">Driver</th>
                            <th style="width:95px;">Instancias</th>
                            <th style="width:80px;">Pax</th>
                            <th>Detalle</th>
                            <th style="width:130px;">Pago</th>
                            <th style="width:110px;">Revisar</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($driverPaymentReport)): ?>
                            <tr><td colspan="6" class="left">No hay drivers con pago calculable en este rango.</td></tr>
                        <?php else: ?>
                            <?php foreach ($driverPaymentReport as $staff): ?>
                                <tr>
                                    <td class="left"><strong><?= e($staff['name']) ?></strong><br><span class="muted">ID <?= e($staff['id']) ?><?= $staff['category'] !== '' ? ' · Cat. ' . e($staff['category']) : '' ?></span></td>
                                    <td><?= e($staff['instances']) ?></td>
                                    <td><?= e($staff['pax']) ?></td>
                                    <td class="left">
                                        <?php foreach ($staff['details'] as $detail): ?>
                                            <div>
                                                <strong><?= e($detail['service_date']) ?></strong> · <?= e($detail['tour']) ?> · <?= e($detail['payment_type']) ?> · <?= e($detail['pax']) ?> pax:
                                                <?= e($detail['amount_clp'] !== null ? clpDash($detail['amount_clp']) : 'tarifa no encontrada') ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="money-cell"><?= e(clpDash($staff['amount_clp'])) ?></td>
                                    <td><?= e((int)$staff['missing_rates'] > 0 ? $staff['missing_rates'] . ' tarifa(s)' : 'OK') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="4">Total pagos drivers</td>
                                <td class="money-cell"><?= e(clpDash($driverPaymentTotal)) ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="report-grid">
            <div class="report-card">
                <div class="audit-title">
                    <h2>Tours ejecutados por guía</h2>
                    <span class="subtitle">Solo staff registrado en BD; instancias con driver y sin refund.</span>
                </div>
                <div class="audit-scroll">
                    <table class="report-table">
                        <thead>
                        <tr>
                            <th style="width:170px;">Guía</th>
                            <th style="width:95px;">Instancias</th>
                            <th style="width:80px;">Pax</th>
                            <th>Tipo de tour ejecutado</th>
                            <th style="width:120px;">Venta</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($guideTourReport)): ?>
                            <tr><td colspan="5" class="left">No hay guías con instancias operadas en este rango.</td></tr>
                        <?php else: ?>
                            <?php foreach ($guideTourReport as $staff): ?>
                                <tr>
                                    <td class="left"><strong><?= e($staff['name']) ?></strong><br><span class="muted">ID <?= e($staff['id']) ?><?= $staff['category'] !== '' ? ' · Cat. ' . e($staff['category']) : '' ?></span></td>
                                    <td><?= e($staff['instances']) ?></td>
                                    <td><?= e($staff['pax']) ?></td>
                                    <td class="left">
                                        <?php foreach ($staff['tours'] as $tour => $tourStats): ?>
                                            <div><strong><?= e($tour) ?></strong>: <?= e($tourStats['instances']) ?> instancia(s), <?= e($tourStats['pax']) ?> pax · Fechas: <?= e(setToText($tourStats['dates'])) ?></div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="money-cell"><?= e($staff['sale_count'] > 0 ? moneyDash($staff['sale'], totalCurrencyLabel($staff['currencies'])) : '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="report-card">
                <div class="audit-title">
                    <h2>Tours ejecutados por driver</h2>
                    <span class="subtitle">Solo staff registrado en BD; instancias con driver y sin refund.</span>
                </div>
                <div class="audit-scroll">
                    <table class="report-table">
                        <thead>
                        <tr>
                            <th style="width:170px;">Driver</th>
                            <th style="width:95px;">Instancias</th>
                            <th style="width:80px;">Pax</th>
                            <th>Tipo de tour ejecutado</th>
                            <th style="width:120px;">Venta</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($driverTourReport)): ?>
                            <tr><td colspan="5" class="left">No hay drivers con instancias operadas en este rango.</td></tr>
                        <?php else: ?>
                            <?php foreach ($driverTourReport as $staff): ?>
                                <tr>
                                    <td class="left"><strong><?= e($staff['name']) ?></strong><br><span class="muted">ID <?= e($staff['id']) ?><?= $staff['category'] !== '' ? ' · Cat. ' . e($staff['category']) : '' ?></span></td>
                                    <td><?= e($staff['instances']) ?></td>
                                    <td><?= e($staff['pax']) ?></td>
                                    <td class="left">
                                        <?php foreach ($staff['tours'] as $tour => $tourStats): ?>
                                            <div><strong><?= e($tour) ?></strong>: <?= e($tourStats['instances']) ?> instancia(s), <?= e($tourStats['pax']) ?> pax · Fechas: <?= e(setToText($tourStats['dates'])) ?></div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="money-cell"><?= e($staff['sale_count'] > 0 ? moneyDash($staff['sale'], totalCurrencyLabel($staff['currencies'])) : '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="audit-card">
            <div class="audit-title">
                <h2>Comprobación de subida por fecha</h2>
                <span class="subtitle">Muestra todo lo encontrado en operational_reservations para el rango seleccionado.</span>
            </div>

            <?php if (!empty($sourceSummary)): ?>
                <div class="source-grid">
                    <?php foreach ($sourceSummary as $src): ?>
                        <div class="source-box">
                            <strong><?= e($src['source']) ?></strong>
                            <?= e($src['reservations']) ?> reservas · <?= e($src['pax']) ?> pax<br>
                            Net: <?= e($src['net_count'] > 0 ? moneyDash($src['net'], totalCurrencyLabel($src['currencies'])) : '-') ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="audit-scroll">
                <table>
                    <thead>
                    <tr>
                        <th style="width:85px;">Closure</th>
                        <th style="width:90px;">Service Date</th>
                        <th style="width:110px;">Booking Date</th>
                        <th style="width:145px;">Code</th>
                        <th style="width:70px;">Source</th>
                        <th style="width:220px;">Name</th>
                        <th style="width:55px;">Pax</th>
                        <th style="width:65px;">Adult</th>
                        <th style="width:65px;">Child</th>
                        <th style="width:65px;">Infant</th>
                        <th style="width:70px;">Time</th>
                        <th style="width:230px;">Hotel</th>
                        <th style="width:105px;">Price</th>
                        <th style="width:105px;">Net</th>
                        <th style="width:80px;">Currency</th>
                        <th style="width:100px;">Match</th>
                        <th style="width:105px;">Validación</th>
                        <th style="width:130px;">Estado fuente</th>
                        <th style="width:160px;">Actualizado</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="19" class="left">No hay registros para esta fecha.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $raw = decodeRawData($row['raw_data'] ?? null);
                                $auditCurrency = rowCurrency($row, $raw);
                                $auditPrice = rowPriceTotal($row, $raw);
                                $auditNet = rowNetPriceSafe($row, $raw);
                                $auditValidation = statusLabel((string)($row['validation_status'] ?? 'normal'));
                                $auditMatch = statusLabel((string)($row['match_status'] ?? ''));
                            ?>
                            <tr>
                                <td><?= e($row['closure_id'] ?? '') ?></td>
                                <td><?= e($row['service_date'] ?? '') ?></td>
                                <td><?= e(rowBookingDate($row, $raw)) ?></td>
                                <td><?= e($row['booking_reference'] ?? '') ?></td>
                                <td><?= e(sourceKeyDash($row, $raw)) ?></td>
                                <td class="left"><strong><?= e($row['customer_name'] ?? '') ?></strong></td>
                                <td><?= e((int)($row['pax_total'] ?? 0)) ?></td>
                                <td><?= e((int)($row['adults'] ?? 0)) ?></td>
                                <td><?= e((int)($row['children'] ?? 0)) ?></td>
                                <td><?= e((int)($row['infants'] ?? 0)) ?></td>
                                <td><?= e($row['pickup_time'] ?? '') ?></td>
                                <td class="left"><?= e($row['hotel'] ?? '') ?></td>
                                <td class="money-cell"><?= e($auditPrice !== null ? moneyDash($auditPrice, $auditCurrency) : '') ?></td>
                                <td class="money-cell"><?= e($auditNet !== null ? moneyDash($auditNet, $auditCurrency) : '') ?></td>
                                <td><?= e($auditCurrency) ?></td>
                                <td><span class="status <?= e(statusClass($auditMatch)) ?>"><?= e($auditMatch) ?></span></td>
                                <td><span class="status <?= e(statusClass($auditValidation)) ?>"><?= e($auditValidation) ?></span></td>
                                <td><?= e($row['source_booking_status'] ?? '') ?></td>
                                <td><?= e(firstNonEmpty($row['updated_at'] ?? '', $row['created_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (empty($instances)): ?>
            <div class="empty">No hay reservas cargadas para el rango seleccionado.</div>
        <?php else: ?>
            <?php foreach ($instances as $instance): ?>
                <section class="instance-card">
                    <div class="instance-head">
                        <div class="head-cell">
                            <div class="head-title"><?= e($instance['category']) ?></div>
                            <div class="head-sub">Categoría</div>
                        </div>
                        <div class="head-cell">
                            <div class="head-title"><?= e($instance['tour']) ?></div>
                            <div class="head-sub">Tour</div>
                        </div>
                        <div class="head-cell">
                            <div class="head-title"><?= e($instance['service_date']) ?></div>
                            <div class="head-sub">Fecha</div>
                        </div>
                        <div class="head-cell">
                            <div class="head-title"><?= e($instance['driver_name'] ?: 'Sin driver') ?></div>
                            <div class="head-sub">Transporte / Driver<?= $instance['driver_category'] !== '' ? ' · Cat. ' . e($instance['driver_category']) : '' ?></div>
                        </div>
                        <div class="head-cell">
                            <div class="head-title"><?= e($instance['guide_name'] ?: 'Sin guía') ?></div>
                            <div class="head-sub">Guía<?= $instance['guide_category'] !== '' ? ' · Cat. ' . e($instance['guide_category']) : '' ?></div>
                        </div>
                        <div class="head-cell">
                            <div class="head-title">Grupo <?= e($instance['sequence']) ?></div>
                            <div class="head-sub">Instancia operativa</div>
                        </div>
                    </div>

                    <div class="assignment-line">
                        <div class="assign-cell"><strong>Driver</strong><?= e($instance['driver_name'] ?: 'Sin asignar') ?></div>
                        <div class="assign-cell"><strong>Guía</strong><?= e($instance['guide_name'] ?: 'Sin asignar') ?></div>
                        <div class="assign-cell"><strong>Viñedo</strong><?= e(setToText($instance['vineyards']) ?: 'Sin asignar') ?></div>
                        <div class="assign-cell"><strong>Primer pickup</strong><?= e($instance['first_pickup'] ?: '-') ?></div>
                        <div class="assign-cell"><strong>Idiomas</strong><?= e(setToText($instance['languages']) ?: '-') ?></div>
                        <div class="assign-cell"><strong>Fuentes</strong><?= e(setToText($instance['sources']) ?: '-') ?></div>
                    </div>

                    <div class="mini-line">
                        <span class="pill"><?= e(count($instance['rows'])) ?> reservas</span>
                        <span class="pill"><?= e($instance['pax']) ?> pax</span>
                        <span class="pill"><?= e($instance['adults']) ?> adultos</span>
                        <span class="pill"><?= e($instance['children']) ?> niños</span>
                        <span class="pill"><?= e($instance['infants']) ?> infantes</span>
                        <span class="pill">Net: <?= e($instance['net_price_count'] > 0 ? moneyDash($instance['net_price_sum'], totalCurrencyLabel($instance['currencies'])) : '-') ?></span>
                        <?php foreach ($instance['status_counts'] as $st => $count): ?>
                            <span class="pill"><?= e($st) ?>: <?= e($count) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <table>
                        <thead>
                        <tr>
                            <th style="width:70px;">Time</th>
                            <th style="width:115px;">Booking Date</th>
                            <th style="width:220px;">Name</th>
                            <th style="width:55px;">Pax</th>
                            <th style="width:70px;">Adult</th>
                            <th style="width:70px;">Child</th>
                            <th style="width:70px;">Infant</th>
                            <th class="hotel">Hotel</th>
                            <th style="width:130px;">Phone</th>
                            <th style="width:70px;">Lang</th>
                            <th style="width:75px;">Source</th>
                            <th style="width:150px;">Code</th>
                            <th style="width:90px;">Private</th>
                            <th style="width:130px;">Viñedo</th>
                            <th style="width:95px;">Net Price</th>
                            <th style="width:100px;">Estado</th>
                            <th style="width:170px;">Comments</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($instance['rows'] as $row): ?>
                            <?php
                                $reservationStatus = statusLabel((string)($row['validation_status'] ?? 'normal'));
                                $matchStatus = statusLabel((string)($row['match_status'] ?? ''));
                                $displayStatus = $reservationStatus !== 'normal' ? $reservationStatus : $matchStatus;
                            ?>
                            <tr>
                                <td class="nowrap"><?= e($row['pickup_time'] ?? '') ?></td>
                                <td class="nowrap"><?= e($row['_dash_booking_date'] ?? '') ?></td>
                                <td class="left">
                                    <strong><?= e($row['customer_name'] ?? '') ?></strong>
                                </td>
                                <td><?= e((int)($row['pax_total'] ?? 0)) ?></td>
                                <td><?= e((int)($row['adults'] ?? 0)) ?></td>
                                <td><?= e((int)($row['children'] ?? 0)) ?></td>
                                <td><?= e((int)($row['infants'] ?? 0)) ?></td>
                                <td class="left hotel"><?= e($row['hotel'] ?? '') ?></td>
                                <td><?= e($row['phone'] ?? '') ?></td>
                                <td><?= e($row['language'] ?? '') ?></td>
                                <td><?= e($row['source'] ?? '') ?></td>
                                <td><?= e($row['booking_reference'] ?? '') ?></td>
                                <td><?= e(yesNoPrivate($row['is_private'] ?? 0)) ?></td>
                                <td><?= e($row['_dash_vineyard'] ?? '') ?></td>
                                <td class="money-cell"><?= e(($row['_dash_net_price'] ?? null) !== null ? moneyDash($row['_dash_net_price'], $row['_dash_currency'] ?? '') : '') ?></td>
                                <td>
                                    <span class="status <?= e(statusClass($displayStatus)) ?>"><?= e($displayStatus) ?></span>
                                </td>
                                <td class="left"><?= e($row['comments'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="3">Total grupo</td>
                            <td><?= e($instance['pax']) ?></td>
                            <td><?= e($instance['adults']) ?></td>
                            <td><?= e($instance['children']) ?></td>
                            <td><?= e($instance['infants']) ?></td>
                            <td colspan="7">Reservas: <?= e(count($instance['rows'])) ?> · Grupo <?= e($instance['sequence']) ?></td>
                            <td class="money-cell"><?= e($instance['net_price_count'] > 0 ? moneyDash($instance['net_price_sum'], totalCurrencyLabel($instance['currencies'])) : '') ?></td>
                            <td colspan="2"></td>
                        </tr>
                        </tbody>
                    </table>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($debugMessages)): ?>
        <div class="debug">
            <details>
                <summary>Debug</summary>
                <pre><?= e(implode("\n", $debugMessages)) ?></pre>
            </details>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
