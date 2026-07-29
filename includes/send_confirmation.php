<?php
// /includes/send_confirmation.php
// Reusable: fetch by reference → build ticket → generate PDF → send email

require_once __DIR__ . '/render_template.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/make_ticket_pdf.php';

function send_confirmation_by_reference(mysqli $conn, string $referenceId, array $opts = []): bool {
  // Optional: session-based de-duplication (avoid double-send on refresh)
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (($opts['dedupeSession'] ?? true) && !empty($_SESSION['email_sent'][$referenceId])) return false;

  // Pull booking + titular + activity name (experiencias.nombre)
    $sql = "
    SELECT
      r.id_reserva,
      r.reference_id,
      r.codigo_externo,
      r.fecha_actividad,
      r.adultos,
      r.ninos,
      r.infantes,
      r.airport_pickup,
      t.nombre   AS titular_nombre,
      t.apellido AS titular_apellido,
      t.email    AS titular_email,
      COALESCE(e.nombre_publico, e.nombre) AS actividad_nombre
    FROM reservas r
    JOIN titulares   t ON r.id_titular     = t.id_titular
    JOIN experiencias e ON r.id_experiencia = e.id_experiencia
   WHERE r.reference_id = ?
   LIMIT 1
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('s', $referenceId);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$r) return false;

  // Build ticket/email variables
  $titularNombre = trim(($r['titular_nombre'] ?? '') . ' ' . ($r['titular_apellido'] ?? ''));
  $ad  = (int)($r['adultos'] ?? 0);
  $ch  = (int)($r['ninos'] ?? 0);
  $inf = (int)($r['infantes'] ?? 0);

  // Date from fecha_actividad only; default time 08:00 (template shows DATE only)
  $fechaBase = $r['fecha_actividad'] ?: date('Y-m-d');
  $dt = \DateTime::createFromFormat('Y-m-d', $fechaBase) ?: new \DateTime($fechaBase);
  $dt->setTime(8, 0, 0);

  // IMPORTANT: keep brackets/commas exactly like this line to avoid parse errors
  $vars = [
    'provider_name'      => "Stamp's Tour",
    'actividad_nombre'   => ($r['actividad_nombre'] ?? ''),   // ← bracket after key!
    'fecha_actividad_dt' => $dt,
    'titular_nombre'     => $titularNombre,
    'pasajeros_total'    => $ad + $ch + $inf,
    'pasajeros_detalle'  => "{$ad} adults, {$ch} children, {$inf} infants",
    'idioma'             => 'English',                         // default; adjust if you add column
    'codigo_externo'     => ($r['codigo_externo'] ?: $referenceId),

    // Pickup flags (schema uses airport_pickup)
    'pickup_incluido'    => !empty($r['airport_pickup']),
    'dropoff_incluido'   => false,
    'pickup_lugar'       => !empty($r['airport_pickup']) ? 'Airport' : '',
    'dropoff_lugar'      => '',

    // Footer/contact
    'phone'              => '+56923993146',
    'email'              => 'reservations@stampstour.com',
  ];

  // Generate PDF ticket (saved in /tickets)
  $pdf = generate_ticket_pdf($vars);

  // Public/Local URL for the email button
  $baseUrl = $opts['publicTicketsBaseUrl'] ?? (
      ( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' .
      ($_SERVER['HTTP_HOST'] ?? 'https://stampstour.com') .
      rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/tickets/'
  );
  $vars['download_url'] = $baseUrl . rawurlencode($pdf['filename']);

  // Build email HTML
  $emailHtml = render_template(__DIR__ . '/../templates/email_booking.html', $vars);

  // Recipient (fallback to your inbox during tests)
  $toEmail = $r['titular_email'] ?: ($opts['fallbackTo'] ?? 'reservations@gmail.com');
  $toName  = $titularNombre ?: 'Guest';
  $replyTo = $opts['reply_to'] ?? ['email' => 'reservations@gmail.com', 'name' => 'Reservations'];

  // Send email (attach PDF)
  $ok = send_booking_email(
    $toEmail,
    $toName,
    "Your booking confirmation (Ref: {$vars['codigo_externo']})",
    $emailHtml,
    "Booking Ref: {$vars['codigo_externo']}\nActivity: {$vars['actividad_nombre']}\nDate: " .
      $vars['fecha_actividad_dt']->format('Y-m-d') .
      "\nPassengers: {$vars['pasajeros_detalle']}\nDownload ticket: {$vars['download_url']}",
    [['path' => $pdf['path'], 'name' => $pdf['filename']]],
    $replyTo
  );

  if ($ok && ($opts['dedupeSession'] ?? true)) {
    $_SESSION['email_sent'][$referenceId] = time();
  }
  return $ok;
}
