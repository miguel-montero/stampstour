<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../db_config.php';
require_once __DIR__ . '/includes/hotel_resolver.php';

if (!$conn) {
    die("No hay conexión con la base de datos.");
}

/**
 * Limpia un valor para evitar pasar null a trim()
 */
function limpiar($valor) {
    return htmlspecialchars(trim($valor ?? ''));
}

// Recogemos y limpiamos campos
$nombre    = limpiar($_POST['name_booking']      ?? '');
$apellido  = limpiar($_POST['last_name_booking'] ?? '');
$email     = limpiar($_POST['email_booking']     ?? '');
$telefono  = limpiar($_POST['phone_booking']     ?? '');

// Parseo de fecha MM-DD-YYYY → YYYY-MM-DD
$rawDate   = $_POST['date_booking'] ?? '';
$dateObj   = DateTime::createFromFormat('m-d-Y', $rawDate);
$fecha     = $dateObj ? $dateObj->format('Y-m-d') : null;

// Cantidades
$adultos   = intval($_POST['adults']   ?? 0);
$ninos     = intval($_POST['children'] ?? 0);
$infantes  = intval($_POST['infants']  ?? 0);

// Airport pickup: 1 si está marcado, 0 si no
$pickup    = (isset($_POST['airport_pick_up']) && $_POST['airport_pick_up'] === 'Yes') ? 1 : 0;

$actividad = limpiar($_POST['activity_name'] ?? '');
$cupon     = limpiar($_POST['coupon_code']   ?? '');
$subtotal  = floatval($_POST['subtotal']    ?? 0);
$total     = floatval($_POST['total_price'] ?? 0);

// 1) Insertar titular
$stmt = $conn->prepare("
    INSERT INTO titulares (nombre, apellido, email, telefono)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("ssss", $nombre, $apellido, $email, $telefono);
$stmt->execute();
$id_titular = $stmt->insert_id;
$stmt->close();

// 2) Resolver id_experiencia (solo lectura)
// Buscamos por nombre; si no existe, abortamos sin crear ni usar fallback.
$stmt = $conn->prepare("
    SELECT id_experiencia
      FROM experiencias
     WHERE nombre = ?
     LIMIT 1
");
$stmt->bind_param("s", $actividad);
$stmt->execute();
$stmt->bind_result($id_experiencia);
$stmt->fetch();
$stmt->close();

if (!$id_experiencia) {
    http_response_code(400);
    $safeName = htmlspecialchars($actividad ?: '(vacío)', ENT_QUOTES, 'UTF-8');
    echo "<h3>Experiencia no encontrada</h3>";
    echo "<p>La experiencia <strong>{$safeName}</strong> no existe en el catálogo.</p>";
    echo "<p>Por favor utiliza un nombre válido que ya exista en <code>experiencias</code> (por ejemplo, <code>custom</code>).</p>";
    exit;
}
// 3) Buscar cupón y vendedor
$id_cupon    = null;
$id_vendedor = null;
if ($cupon !== '') {
    $stmt = $conn->prepare("
        SELECT id_cupon, id_vendedor
          FROM cupones
         WHERE nombre = ?
         LIMIT 1
    ");
    $stmt->bind_param("s", $cupon);
    $stmt->execute();
    $stmt->bind_result($id_cupon, $id_vendedor);
    $stmt->fetch();
    $stmt->close();
}

/* 3b) OVERRIDE MANUAL (mínimo cambio): si viene codigo_vendedor y se puede resolver, TIENE PRIORIDAD */
if (!empty($_POST['codigo_vendedor'])) {
    $codigoV = trim($_POST['codigo_vendedor']); // sin limpiar HTML, usamos el valor crudo para buscar

    if (ctype_digit($codigoV)) {
        // Si es numérico: validar que exista como id_vendedor
        $stmt = $conn->prepare("SELECT id_vendedor FROM vendedores WHERE id_vendedor = ? LIMIT 1");
        $tmpId = (int)$codigoV;
        $stmt->bind_param("i", $tmpId);
        $stmt->execute();
        $stmt->bind_result($foundId);
        if ($stmt->fetch()) { $id_vendedor = (int)$foundId; }
        $stmt->close();
    } else {
        // Si es texto: buscar por nombre del vendedor (ajusta la columna si usas otro identificador)
        $stmt = $conn->prepare("SELECT id_vendedor FROM vendedores WHERE nombre_vendedor = ? LIMIT 1");
        $stmt->bind_param("s", $codigoV);
        $stmt->execute();
        $stmt->bind_result($foundId);
        if ($stmt->fetch()) { $id_vendedor = (int)$foundId; }
        $stmt->close();
    }
}

// 3c) Resolver hotel: id_hotel (catálogo, solo lectura) o hotel_manual (texto libre)
[$id_hotel, $hotel_manual] = resolve_hotel_selection($conn, $_POST);

// 4) Generar reference_id (antes del INSERT: reference_id es NOT NULL sin default,
//    así que debe viajar en el propio INSERT en vez de depender de un UPDATE posterior)
$stampCode = 'STAMP_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 13);

// 5) Insertar reserva
$stmt = $conn->prepare("
    INSERT INTO reservas (
        reference_id,
        fecha_reserva,
        fecha_actividad,
        adultos,
        ninos,
        infantes,
        airport_pickup,
        id_cupon,
        id_titular,
        id_vendedor,
        id_experiencia,
        subtotal,
        total_venta,
        id_hotel,
        hotel_manual
    ) VALUES (
        ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
");
if (!$stmt) {
    die("Error preparando inserción de reserva: " . $conn->error);
}

// 2 strings, 9 integers, 2 doubles, 1 string = 14 parámetros
$stmt->bind_param(
    "ssiiiiiiiiddis",
    $stampCode,       // s: reference_id
    $fecha,           // s: fecha actividad (YYYY-MM-DD)
    $adultos,         // i
    $ninos,           // i
    $infantes,        // i
    $pickup,          // i
    $id_cupon,        // i (puede ser null)
    $id_titular,      // i
    $id_vendedor,     // i (puede ser null; ya con override si aplica)
    $id_experiencia,  // i
    $subtotal,        // d
    $total,           // d
    $id_hotel,        // i (puede ser null)
    $hotel_manual     // s (puede ser null)
);

if ($stmt->execute()) {
    // Guardar en sesión y redirigir (mantengo tu comportamiento actual)
    session_start();
    $_SESSION['reference_id'] = $stampCode;

    header('Location: shopping.php?reference_id=' . urlencode($stampCode));
    exit();
} else {
    die("Error al insertar reserva: " . $stmt->error);
}

$stmt->close();
$conn->close();
