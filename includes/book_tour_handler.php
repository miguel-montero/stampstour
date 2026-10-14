<?php
// includes/book_tour_handler.php
// Validation + server-side pricing for book_tour.php (public tour pages).
// The browser's subtotal/total_price are never used; admin-only fields
// (codigo_vendedor, is_private, airport_pick_up) are stripped.

require_once __DIR__ . '/booking.php';
require_once __DIR__ . '/tours/maipo_options.php';

const BOOK_TOUR_MAX_TRAVELERS = 15;

/** experiencias.nombre values that may be booked through book_tour.php */
function book_tour_allowed_experiences(): array
{
    return array_values(maipo_option_exp_names());
}

function book_tour_handle(mysqli $conn, array $post, DateTimeImmutable $today): array
{
    $fail = fn(string $msg) => ['ok' => false, 'error' => $msg];

    foreach ($post as $value) {
        if (!is_string($value)) {
            return $fail('Please check the booking form and try again.');
        }
    }

    $exp = trim((string)($post['activity_name'] ?? ''));
    if (!in_array($exp, book_tour_allowed_experiences(), true)) {
        return $fail('This tour cannot be booked online. Please contact us.');
    }

    foreach (['name_booking', 'last_name_booking', 'email_booking', 'phone_booking'] as $field) {
        if (trim((string)($post[$field] ?? '')) === '') {
            return $fail('Please fill in your name, last name, email and phone.');
        }
    }
    if (filter_var(trim((string)$post['email_booking']), FILTER_VALIDATE_EMAIL) === false) {
        return $fail('Please enter a valid email address.');
    }

    $rawDate = trim((string)($post['date_booking'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('!m-d-Y', $rawDate, $today->getTimezone());
    if ($date === false || $date->format('m-d-Y') !== $rawDate) {
        return $fail('Please choose a valid tour date.');
    }
    if ($date < $today->setTime(0, 0)) {
        return $fail('The tour date cannot be in the past.');
    }

    $counts = [];
    foreach (['adults', 'children', 'infants'] as $field) {
        $raw = trim((string)($post[$field] ?? '0'));
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

    try {
        $amounts = booking_calculate_total($conn, $exp, $counts['adults'], $counts['children'], $counts['infants'], (string)($post['coupon_code'] ?? ''));
    } catch (BookingUnknownExperienceException $e) {
        return $fail('This tour option is not available right now. Please contact us.');
    }

    $clean = $post;
    unset($clean['codigo_vendedor']);      // vendor attribution is admin-only
    $clean['is_private'] = '0';
    $clean['airport_pick_up'] = 'No';
    $clean['adults'] = (string)$counts['adults'];
    $clean['children'] = (string)$counts['children'];
    $clean['infants'] = (string)$counts['infants'];
    $clean['activity_name'] = $exp;
    $clean['date_booking'] = $rawDate;

    $ref = booking_insert_reservation($conn, $clean, $amounts['subtotal'], $amounts['total']);

    return ['ok' => true, 'reference_id' => $ref, 'total' => $amounts['total']];
}
