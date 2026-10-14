<?php
// includes/booking.php
// Shared booking logic. booking_calculate_total() is the server-side price
// used by book_tour.php, so the amount charged never comes from the browser.
// See docs/superpowers/specs/2026-10-13-maipo-tour-options-design.md

require_once __DIR__ . '/tour_price.php';
require_once __DIR__ . '/hotel_resolver.php';

final class BookingUnknownExperienceException extends InvalidArgumentException
{
}

/**
 * Limpia un valor para evitar pasar null a trim() (antes limpiar() en submit.php).
 */
function booking_clean($valor): string
{
    return htmlspecialchars(trim((string)($valor ?? '')));
}

function booking_coupon_percentage(mysqli $conn, string $code): ?int
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT porcentaje FROM cupones WHERE LOWER(nombre) = LOWER(?) LIMIT 1');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $stmt->bind_result($porcentaje);
    $found = $stmt->fetch();
    $stmt->close();

    return $found ? (int)$porcentaje : null;
}

/**
 * Total for a booking, from experiencias prices and the coupon percentage.
 * Coupon math matches api/apply_coupon.php so the total shown in the
 * browser equals the total stored here.
 *
 * @return array{subtotal: float, total: float}
 */
function booking_calculate_total(mysqli $conn, string $expName, int $adults, int $children, int $infants, string $coupon): array
{
    if ($adults < 0 || $children < 0 || $infants < 0) {
        throw new InvalidArgumentException('Passenger counts cannot be negative');
    }

    $prices = fetch_tour_prices($conn, [$expName]);
    $p = $prices ? reset($prices) : null;
    if ($p === null) {
        throw new BookingUnknownExperienceException($expName);
    }

    $subtotal = round($adults * $p['adult'] + $children * $p['child'] + $infants * $p['infant'], 2);

    $pct = booking_coupon_percentage($conn, $coupon);
    $total = $pct === null ? $subtotal : round($subtotal * (1 - $pct / 100), 2);

    return ['subtotal' => $subtotal, 'total' => $total];
}

/**
 * Inserts titular + reserva. Moved from submit.php; behavior is unchanged
 * except that errors are thrown instead of die()/exit, and the experience
 * is resolved before the titular insert.
 *
 * $subtotal / $total are stored as given - callers decide where they come
 * from (submit.php: posted by trusted admin forms; book_tour.php: computed
 * by booking_calculate_total()).
 */
function booking_insert_reservation(mysqli $conn, array $post, float $subtotal, float $total): string
{
    $nombre    = booking_clean($post['name_booking']      ?? '');
    $apellido  = booking_clean($post['last_name_booking'] ?? '');
    $email     = booking_clean($post['email_booking']     ?? '');
    $telefono  = booking_clean($post['phone_booking']     ?? '');

    // Parseo de fecha MM-DD-YYYY → YYYY-MM-DD
    $rawDate   = (string)($post['date_booking'] ?? '');
    $dateObj   = DateTime::createFromFormat('m-d-Y', $rawDate);
    $fecha     = $dateObj ? $dateObj->format('Y-m-d') : null;

    // Cantidades
    $adultos   = intval($post['adults']   ?? 0);
    $ninos     = intval($post['children'] ?? 0);
    $infantes  = intval($post['infants']  ?? 0);

    // Airport pickup: 1 si está marcado, 0 si no
    $pickup    = (isset($post['airport_pick_up']) && $post['airport_pick_up'] === 'Yes') ? 1 : 0;

    // Private experience: 1 si el checkbox viene marcado, 0 si no (público no lo envía)
    $isPrivate = (isset($post['is_private']) && $post['is_private'] === '1') ? 1 : 0;

    $actividad = booking_clean($post['activity_name'] ?? '');
    $cupon     = booking_clean($post['coupon_code']   ?? '');

    // 1) Resolver id_experiencia (solo lectura) - antes del titular para no dejar huérfanos
    $id_experiencia = null;
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
        throw new BookingUnknownExperienceException($actividad);
    }

    // 2) Insertar titular
    $stmt = $conn->prepare("
        INSERT INTO titulares (nombre, apellido, email, telefono)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssss", $nombre, $apellido, $email, $telefono);
    $stmt->execute();
    $id_titular = $stmt->insert_id;
    $stmt->close();

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
    if (!empty($post['codigo_vendedor'])) {
        $codigoV = trim((string)$post['codigo_vendedor']); // sin limpiar HTML, usamos el valor crudo para buscar

        if (ctype_digit($codigoV)) {
            $stmt = $conn->prepare("SELECT id_vendedor FROM vendedores WHERE id_vendedor = ? LIMIT 1");
            $tmpId = (int)$codigoV;
            $stmt->bind_param("i", $tmpId);
            $stmt->execute();
            $stmt->bind_result($foundId);
            if ($stmt->fetch()) { $id_vendedor = (int)$foundId; }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("SELECT id_vendedor FROM vendedores WHERE nombre_vendedor = ? LIMIT 1");
            $stmt->bind_param("s", $codigoV);
            $stmt->execute();
            $stmt->bind_result($foundId);
            if ($stmt->fetch()) { $id_vendedor = (int)$foundId; }
            $stmt->close();
        }
    }

    // 3c) Resolver hotel: id_hotel (catálogo, solo lectura) o hotel_manual (texto libre)
    [$id_hotel, $hotel_manual] = resolve_hotel_selection($conn, $post);

    // 4) Generar reference_id (reference_id es NOT NULL sin default)
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
            is_private,
            id_cupon,
            id_titular,
            id_vendedor,
            id_experiencia,
            subtotal,
            total_venta,
            id_hotel,
            hotel_manual
        ) VALUES (
            ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");
    if (!$stmt) {
        throw new RuntimeException("Error preparando inserción de reserva: " . $conn->error);
    }

    $stmt->bind_param(
        "ssiiiiiiiiiddis",
        $stampCode,
        $fecha,
        $adultos,
        $ninos,
        $infantes,
        $pickup,
        $isPrivate,
        $id_cupon,
        $id_titular,
        $id_vendedor,
        $id_experiencia,
        $subtotal,
        $total,
        $id_hotel,
        $hotel_manual
    );

    if (!$stmt->execute()) {
        throw new RuntimeException("Error al insertar reserva: " . $stmt->error);
    }
    $stmt->close();

    return $stampCode;
}
