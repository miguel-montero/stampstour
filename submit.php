<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../db_config.php';
require_once __DIR__ . '/includes/booking.php';

if (!$conn) {
    die("No hay conexión con la base de datos.");
}

// Trusted callers (booking_manual.php, admin/private-booking.php,
// admin/preferentials.php) post their own custom totals, stored as-is.
// Public tour pages are moving to book_tour.php, which recalculates the
// total server-side - see docs/superpowers/specs/2026-10-13-maipo-tour-options-design.md
$subtotal = floatval($_POST['subtotal']    ?? 0);
$total    = floatval($_POST['total_price'] ?? 0);

try {
    $stampCode = booking_insert_reservation($conn, $_POST, $subtotal, $total);
} catch (BookingUnknownExperienceException $e) {
    http_response_code(400);
    $actividad = booking_clean($_POST['activity_name'] ?? '');
    $safeName = htmlspecialchars($actividad ?: '(vacío)', ENT_QUOTES, 'UTF-8');
    echo "<h3>Experiencia no encontrada</h3>";
    echo "<p>La experiencia <strong>{$safeName}</strong> no existe en el catálogo.</p>";
    echo "<p>Por favor utiliza un nombre válido que ya exista en <code>experiencias</code> (por ejemplo, <code>custom</code>).</p>";
    exit;
} catch (RuntimeException $e) {
    die($e->getMessage());
}

// Guardar en sesión y redirigir (mismo comportamiento que antes)
session_start();
$_SESSION['reference_id'] = $stampCode;

header('Location: shopping.php?reference_id=' . urlencode($stampCode));
exit();
