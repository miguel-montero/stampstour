<?php
// includes/ta_booking.php
// Travel agent booking: agent-code check, server-side TA pricing, and the
// submit handler for the agent portal. Browser-supplied prices, coupon,
// vendor and email are never used; the booking is attributed to the agent.

require_once __DIR__ . '/travel_agents.php';
require_once __DIR__ . '/book_tour_handler.php';

const TA_TOURS = ['Valparaiso', 'Andes', 'Santiago', 'Maipo'];

/** TA prices per bookable tour present in experiencias (adult = TAR when > 0). */
function ta_tour_prices(mysqli $conn): array
{
    $in = implode(',', array_fill(0, count(TA_TOURS), '?'));
    $stmt = $conn->prepare("SELECT nombre, precio_adulto, TAR, precio_nino, precio_infante FROM experiencias WHERE nombre IN ($in)");
    $names = TA_TOURS;
    $stmt->bind_param(str_repeat('s', count($names)), ...$names);
    $stmt->execute();
    $res = $stmt->get_result();
    $prices = [];
    while ($row = $res->fetch_assoc()) {
        $tar = (float)$row['TAR'];
        $prices[$row['nombre']] = [
            'adult'  => $tar > 0 ? $tar : (float)$row['precio_adulto'],
            'child'  => (float)$row['precio_nino'],
            'infant' => (float)$row['precio_infante'],
        ];
    }
    $stmt->close();
    return $prices;
}

function ta_booking_handle(mysqli $conn, array $post, string $ip, DateTimeImmutable $today): array
{
    $fail = fn(string $msg) => ['ok' => false, 'error' => $msg];

    foreach ($post as $value) {
        if (!is_string($value)) {
            return $fail('Please check the booking form and try again.');
        }
    }

    if (ta_is_rate_limited($conn, $ip, $today)) {
        return $fail('Too many attempts. Please wait 15 minutes and try again.');
    }
    $agent = ta_find_active_by_code($conn, (string)($post['ta_code'] ?? ''));
    if ($agent === null) {
        ta_record_failure($conn, $ip, $today);
        return $fail('Your agent code is not valid or is no longer active.');
    }

    $exp = trim((string)($post['activity_name'] ?? ''));
    $prices = in_array($exp, TA_TOURS, true) ? (ta_tour_prices($conn)[$exp] ?? null) : null;
    if ($prices === null) {
        return $fail('This tour cannot be booked online. Please contact us.');
    }

    $first = trim((string)($post['customer_first_name'] ?? ''));
    $last = trim((string)($post['customer_last_name'] ?? ''));
    $whatsapp = trim((string)($post['customer_whatsapp'] ?? ''));
    if ($first === '' || $last === '' || $whatsapp === '') {
        return $fail("Please fill in the customer's name, last name and WhatsApp number.");
    }
    if (preg_match('/^[\d\s+\-()]+$/', $whatsapp) !== 1 || preg_match_all('/\d/', $whatsapp) < 8) {
        return $fail('Please enter a valid WhatsApp number.');
    }

    $rawDate = trim((string)($post['date_booking'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('!m-d-Y', $rawDate, $today->getTimezone());
    if ($date === false || $date->format('m-d-Y') !== $rawDate) {
        return $fail('Please choose a valid tour date.');
    }
    if ($date < $today->setTime(0, 0)) {
        return $fail('The tour date cannot be in the past.');
    }
    if ($date > $today->setTime(0, 0)->modify('+2 years')) {
        return $fail('Please choose a tour date within the next two years.');
    }

    $counts = [];
    foreach (['adults', 'children', 'infants'] as $field) {
        $raw = trim((string)($post[$field] ?? '0'));
        if ($raw === '' && $field !== 'adults') {
            $raw = '0';
        }
        if (preg_match('/^\d{1,2}$/', $raw) !== 1) {
            return $fail('Please enter a valid number of travelers.');
        }
        $counts[$field] = (int)$raw;
    }
    if ($counts['adults'] < 1) {
        return $fail('At least one adult is required.');
    }
    if (array_sum($counts) > BOOK_TOUR_MAX_TRAVELERS) {
        return $fail('Groups are limited to ' . BOOK_TOUR_MAX_TRAVELERS . ' travelers. Please contact us for larger groups.');
    }

    $total = round(
        $counts['adults'] * $prices['adult'] + $counts['children'] * $prices['child'] + $counts['infants'] * $prices['infant'],
        2
    );

    $clean = [
        'name_booking'      => $first,
        'last_name_booking' => $last,
        'phone_booking'     => $whatsapp,
        'email_booking'     => (string)$agent['email'],
        'date_booking'      => $rawDate,
        'adults'            => (string)$counts['adults'],
        'children'          => (string)$counts['children'],
        'infants'           => (string)$counts['infants'],
        'activity_name'     => $exp,
        'codigo_vendedor'   => (string)$agent['id_vendedor'],
    ];
    foreach (['id_hotel', 'hotel', 'hotelOption', 'customHotel'] as $key) {
        if (isset($post[$key])) {
            $clean[$key] = $post[$key];
        }
    }

    $ref = booking_insert_reservation($conn, $clean, $total, $total);

    return ['ok' => true, 'reference_id' => $ref];
}
