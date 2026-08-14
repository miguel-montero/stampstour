<?php
// includes/hotel_resolver.php
// Resolves a submitted hotel selection (booking_manual.php's numeric
// <select>, or preferentials.php's/return.php's autocomplete text +
// "not listed" custom text + "decide later" fields) into
// [id_hotel, hotel_manual]. Exactly one of the two is non-null, or both
// are null. Never writes to the hoteles catalog table — an unmatched
// name is returned as hotel_manual, not inserted.

function resolve_hotel_selection(mysqli $conn, array $post): array
{
    if (!empty($post['id_hotel']) && ctype_digit((string)$post['id_hotel'])) {
        return [(int)$post['id_hotel'], null];
    }

    $hotelOption = $post['hotelOption'] ?? '';

    if ($hotelOption === 'not_listed') {
        $texto = trim($post['customHotel'] ?? '');
        return $texto !== '' ? [null, $texto] : [null, null];
    }

    if ($hotelOption === 'decide_later') {
        return [null, null];
    }

    $nombre = trim($post['hotel'] ?? '');
    if ($nombre === '') {
        return [null, null];
    }

    $stmt = $conn->prepare("SELECT id_hotel FROM hoteles WHERE nombre_hotel = ? LIMIT 1");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $stmt->bind_result($foundId);
    $found = $stmt->fetch();
    $stmt->close();

    return $found ? [(int)$foundId, null] : [null, $nombre];
}
