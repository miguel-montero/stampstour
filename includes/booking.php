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
