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
