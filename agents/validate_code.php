<?php
// agents/validate_code.php - POST code -> JSON {ok, name, email_masked} | {ok:false, error}
ini_set('display_errors', '0');

require __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../includes/travel_agents.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

try {
    $ip   = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $now  = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
    $code = trim((string)($_POST['code'] ?? ''));

    if (ta_is_rate_limited($conn, $ip, $now)) {
        echo json_encode(['ok' => false, 'error' => 'Too many attempts. Please try again in 15 minutes.']);
        exit;
    }

    $agent = preg_match('/^\d{4}$/', $code) ? ta_find_active_by_code($conn, $code) : null;
    if ($agent) {
        echo json_encode([
            'ok' => true,
            'name' => $agent['nombre_vendedor'],
            'email_masked' => ta_mask_email((string)$agent['email']),
        ]);
        exit;
    }

    ta_record_failure($conn, $ip, $now);
    echo json_encode(['ok' => false, 'error' => 'Invalid or inactive agent code.']);
} catch (Throwable $e) {
    error_log('agents/validate_code.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Validation error. Please try again.']);
}
