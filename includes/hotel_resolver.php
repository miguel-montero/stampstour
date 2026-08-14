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
        $idHotel = (int)$post['id_hotel'];
        $checkStmt = $conn->prepare("SELECT 1 FROM hoteles WHERE id_hotel = ? LIMIT 1");
        $checkStmt->bind_param("i", $idHotel);
        $checkStmt->execute();
        $checkStmt->store_result();
        $idHotelExists = $checkStmt->num_rows > 0;
        $checkStmt->close();

        if ($idHotelExists) {
            return [$idHotel, null];
        }
    }

    $hotelOption = $post['hotelOption'] ?? '';

    if ($hotelOption === 'not_listed') {
        $texto = is_string($post['customHotel'] ?? null) ? trim($post['customHotel']) : '';
        $texto = mb_substr($texto, 0, 255);
        return $texto !== '' ? [null, $texto] : [null, null];
    }

    if ($hotelOption === 'decide_later') {
        return [null, null];
    }

    $nombre = is_string($post['hotel'] ?? null) ? trim($post['hotel']) : '';
    $nombre = mb_substr($nombre, 0, 255);
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
