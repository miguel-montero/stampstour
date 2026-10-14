<?php
// includes/tour_price.php
// Server-side lookup for the "from $X" adult price shown in each tour
// page's hero. Used so the price is present in the initial HTML response
// instead of only appearing after tours.js's client-side fetch to
// get_prices.php - crawlers that don't execute JS were seeing an empty
// #dynamic_price span otherwise.

function fetch_tour_adult_price(mysqli $conn, string $expName): ?float
{
    $stmt = $conn->prepare('SELECT precio_adulto FROM experiencias WHERE nombre = ? LIMIT 1');
    $stmt->bind_param('s', $expName);
    $stmt->execute();
    $stmt->bind_result($precioAdulto);
    $found = $stmt->fetch();
    $stmt->close();

    return $found ? (float)$precioAdulto : null;
}

// Prices for several experiences in one query, keyed by experiencias.nombre.
// Used by pages that sell several variants of one tour (e.g. the Maipo
// options) and by includes/booking.php for server-side totals.
function fetch_tour_prices(mysqli $conn, array $expNames): array
{
    $expNames = array_values(array_unique(array_map('strval', $expNames)));
    if (empty($expNames)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($expNames), '?'));
    $stmt = $conn->prepare("SELECT nombre, precio_adulto, precio_nino, precio_infante FROM experiencias WHERE nombre IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($expNames)), ...$expNames);
    $stmt->execute();
    $result = $stmt->get_result();

    $prices = [];
    while ($row = $result->fetch_assoc()) {
        $prices[$row['nombre']] = [
            'adult'  => (float)$row['precio_adulto'],
            'child'  => (float)($row['precio_nino'] ?? 0),
            'infant' => (float)($row['precio_infante'] ?? 0),
        ];
    }
    $stmt->close();

    return $prices;
}
