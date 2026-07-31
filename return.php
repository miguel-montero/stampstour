<?php
// return.php
// Página de gracias visible para el cliente. No es fuente de verdad.
// No altera estados ni lógica de Getnet/PayPal ni correos (eso queda en webhooks/procesos existentes).

// -------------------------------------------------------
// 0) Sesión, conexión a BD y helpers
// -------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../db_config.php';
require_once __DIR__ . '/helpers.php';

// ——— Helper seguro para leer arreglos profundamente sin notices ———
function arr_get($arr, $path, $default = null) {
    if (!is_array($arr)) return $default;
    $ref = $arr;
    foreach ($path as $k) {
        if (!is_array($ref) || !array_key_exists($k, $ref)) return $default;
        $ref = $ref[$k];
    }
    return $ref;
}

// ——— AJAX endpoint: actualizar hotel sin recargar ———
if (isset($_GET['action']) && $_GET['action'] === 'updateHotel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res_id = (int)($_POST['id_reserva'] ?? 0);

    // Si eligió "not_listed" o "decide_later", limpiamos el hotel
    if (!empty($_POST['hotelOption']) && in_array($_POST['hotelOption'], ['not_listed','decide_later'], true)) {
        $stmt = $conn->prepare("UPDATE reservas SET id_hotel = NULL WHERE id_reserva = ?");
        $stmt->bind_param("i", $res_id);
        $stmt->execute();
        $stmt->close();
    }
    // Si escribió o seleccionó un hotel del autocomplete
    elseif (!empty($_POST['hotel'])) {
        list($nombre) = explode(' – ', $_POST['hotel'], 2);
        $stmt = $conn->prepare("SELECT id_hotel FROM hoteles WHERE nombre_hotel = ? LIMIT 1");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $h = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($h) {
            $upd = $conn->prepare("UPDATE reservas SET id_hotel = ? WHERE id_reserva = ?");
            $upd->bind_param("ii", $h['id_hotel'], $res_id);
            $upd->execute();
            $upd->close();
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// -------------------------------------------------------
// 1) Obtener reference (formato STAMP_...) y requestId
// -------------------------------------------------------
$reference = $_GET['reference'] ?? ($_SESSION['reference'] ?? '');
$reference = is_string($reference) ? trim($reference) : '';

if ($reference === '') {
    die('Falta código de referencia.');
}
// Nota: no forzamos el prefijo, pero si quieres, puedes validar estrictamente:
// if (strpos($reference, 'STAMP_') !== 0) { die('Referencia inválida.'); }

$requestId = $_SESSION['requestId'] ?? null; // Getnet (respuesta instantánea por requestId)
$requestId = is_numeric($requestId) ? (int)$requestId : null;

// -------------------------------------------------------
// 2) Resolver estado de pago para mostrar al cliente
//    Prioridades de resolución rápidas:
//    a) Parámetros de retorno (ej. PayPal/Getnet redirect): ?status=APPROVED o ?pp_status=APPROVED
//    b) Estado guardado en sesión por flujo inmediato
//    c) Consulta Getnet por requestId (sólo lectura; sin alterar nada)
//    d) Por defecto: PENDING
// -------------------------------------------------------
$status = null;

// a) Parámetros directos (permiten “respuesta instantánea” sin esperar webhook)
$incomingStatus = $_GET['status'] ?? $_GET['pp_status'] ?? null;
if (is_string($incomingStatus) && $incomingStatus !== '') {
    $status = strtoupper($incomingStatus);
}

// b) Último estado visto en sesión para esta referencia (si lo usas en tu flujo)
if ($status === null && !empty($_SESSION['last_status'][$reference])) {
    $status = strtoupper((string)$_SESSION['last_status'][$reference]);
}

// c) Getnet: si hay requestId, consultamos sesión de pago
if ($status === null && $requestId) {
    try {
        $info = getSessionInfo($requestId); // definido en helpers.php (no tocamos su lógica)
    } catch (Throwable $e) {
        $info = null;
    }
    // extraemos con seguridad sin generar warnings
    $statusFromInfo = arr_get($info, ['status','status']);
    if ($statusFromInfo === null) {
        $statusFromInfo = arr_get($info, ['payment','status']);
    }
    if (is_string($statusFromInfo) && $statusFromInfo !== '') {
        $status = strtoupper($statusFromInfo);
    }
}

// d) Por defecto
if ($status === null || $status === '') {
    $status = 'PENDING';
}

// Si quieres normalizar algunos alias frecuentes
$map = [
    'APPROVE'   => 'APPROVED',
    'AUTHORIZED'=> 'APPROVED',   // para efectos de UI
    'COMPLETED' => 'APPROVED',    // PayPal capture
    'SUCCESS'   => 'APPROVED'
];
$status = $map[$status] ?? $status;

// -------------------------------------------------------
// 2.1) Disparo de correo/PDF deshabilitado (cron envía los correos)
//      Manteniendo todo lo demás intacto.
// -------------------------------------------------------
if (!empty($reference) && $status === 'APPROVED') {
    // Intencionalmente vacío: el correo lo gestiona un cron job.
}

// -------------------------------------------------------
// 3) Cargar id_reserva por reference (para el formulario de hotel AJAX)
// -------------------------------------------------------
$stmt = $conn->prepare("SELECT id_reserva FROM reservas WHERE reference_id = ? LIMIT 1");
$stmt->bind_param("s", $reference);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    die('Reserva no encontrada.');
}
$res_id = (int)$row['id_reserva'];

// -------------------------------------------------------
// 4) Consultar datos de reserva, titular y actividad
//     ✱ CAMBIO: traemos r.estado para mostrar el estado real guardado en BD.
// -------------------------------------------------------
$stmt = $conn->prepare(
    "SELECT 
         r.id_reserva,
         r.reference_id,
         r.process_id,
         r.fecha_actividad,
         r.adultos,
         r.ninos,
         r.infantes,
         r.airport_pickup,
         r.total_venta,
         r.estado,               -- <= estado real BD (pendiente/realizado/refund)
         t.nombre    AS tit_nombre,
         t.apellido  AS tit_apellido,
         t.email     AS tit_email,
         e.nombre    AS actividad
     FROM reservas r
     JOIN titulares   t ON r.id_titular    = t.id_titular
     JOIN experiencias e ON r.id_experiencia = e.id_experiencia
     WHERE r.reference_id = ?
     LIMIT 1"
);
$stmt->bind_param('s', $reference);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reserva) {
    die('Reserva no encontrada.');
}

// -------------------------------------------------------
// 4.1) Forzar el estado mostrado según lo que diga la BD (cambio principal)
//      Esto resuelve el caso: PayPal capturado -> reservas.estado='realizado'
//      pero la página devolvía 'PENDING'. Aquí respetamos la BD.
// -------------------------------------------------------
if (!empty($reserva['estado'])) {
    $mapDbToUi = [
        'realizado' => 'APPROVED',
        'pendiente' => 'PENDING',
        'refund'    => 'REFUNDED',
    ];
    $status = $mapDbToUi[$reserva['estado']] ?? $status;
}

// -------------------------------------------------------
// 5) Formatear datos
// -------------------------------------------------------
try {
    $fechaTour = (new DateTime($reserva['fecha_actividad']))->format('j F Y');
} catch (Throwable $e) {
    $fechaTour = htmlspecialchars((string)$reserva['fecha_actividad'], ENT_QUOTES, 'UTF-8');
}
$totalUSD  = number_format((float)$reserva['total_venta'], 2, '.', ',');

// Guarda el último estado mostrado para esta referencia (por si haces refreshs)
$_SESSION['last_status'][$reference] = $status;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- Google Consent Mode v2: default-deny until the visitor chooses via the
     cookie banner (includes/cookie-banner.php). Must run before gtag.js. -->
<script>
 window.dataLayer = window.dataLayer || [];
 function gtag(){dataLayer.push(arguments);}

 gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'analytics_storage': 'denied',
  'wait_for_update': 500
 });

 (function () {
  try {
   if (localStorage.getItem('stamp_cookie_consent') === 'granted') {
    gtag('consent', 'update', {
     'ad_storage': 'granted',
     'ad_user_data': 'granted',
     'ad_personalization': 'granted',
     'analytics_storage': 'granted'
    });
   }
  } catch (e) { /* localStorage unavailable: default-deny stands */ }
 })();
</script>
<!-- Google tag (gtag.js) -->
<link rel="preconnect" href="https://www.googletagmanager.com">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-GWM59ECSLZ"></script>
<script>
 gtag('js', new Date());
 gtag('config', 'G-GWM59ECSLZ');
</script>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Favicons & CSS -->
  <link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon">
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css"        rel="stylesheet">
  <link href="css/vendors.css"      rel="stylesheet">
  <link href="css/custom.css"       rel="stylesheet">
  <link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">

  <title>Booking Success</title>

  <!-- Minimal mobile behavior to match shopping.php: hide header + hero -->
  <style>
    @media (max-width: 576px) {
      header, #header, .navbar, .top_header, .topbar, .main-menu, nav, .breadcrumbs, .sub_header, #hero_2 {
        display: none !important;
      }
      body { padding-top: 0 !important; }
    }
  </style>

</head>
<body>
  <!-- Header (igual al original) -->

  <!-- Hero -->
  <section id="hero_2" class="background-image" data-background="url(img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.4)">
      <div class="intro_title">
        <h1>Booking Successful!</h1>
        <!-- Progress bar -->
        <div class="bs-wizard row">
          <div class="col-6 bs-wizard-step complete">
            <div class="text-center bs-wizard-stepnum">Your details</div>
            <div class="progress"><div class="progress-bar"></div></div>
            <a href="#" class="bs-wizard-dot"></a>
          </div>
          <div class="col-6 bs-wizard-step complete">
            <div class="text-center bs-wizard-stepnum">Finish!</div>
            <div class="progress"><div class="progress-bar"></div></div>
            <a href="#" class="bs-wizard-dot"></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <main>
    <div class="container margin_60">
      <div class="row">
        <div class="col-lg-8 add_bottom_15">

          <!-- 1) Thank you -->
          <div class="form_title">
            <h3><strong><i class="icon-ok"></i></strong>Thank you!</h3>
            <p>Your transaction has been processed.</p>
          </div>

          <!-- 2) Booking Summary (ref + estado) -->
          <div class="step">
            <table class="table table-striped confirm">
              <tbody>
                <tr>
                  <td><strong>Reference</strong></td>
                  <td><?= htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                  <td><strong>Payment Status</strong></td>
                  <td><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- 4) Hotel -->
          <div class="form_title">
            <h3><strong><i class="icon-tag-1"></i></strong>Tell us where you will be staying</h3>
          </div>
          <div class="step">
            <form id="hotelForm">
              <input type="hidden" name="id_reserva" value="<?= $res_id ?>">
              <div class="mb-3">
                <label for="hotel" class="form-label">Choose your hotel:</label>
                <input type="text" id="hotel" name="hotel" class="form-control" placeholder="Start typing hotel name...">
              </div>
              <div class="d-flex align-items-center mb-3">
                <div class="form-check me-2">
                  <input class="form-check-input" type="radio" name="hotelOption" id="notListed" value="not_listed">
                  <label class="form-check-label" for="notListed">My hotel is not on this list</label>
                </div>
                <div id="customHotelWrapper" style="display:none; flex-grow:1;">
                  <input type="text" id="customHotel" name="customHotel" class="form-control"
                         placeholder="enter address or hotel name..." style="color:#999;">
                </div>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="hotelOption" id="decideLater" value="decide_later">
                <label class="form-check-label" for="decideLater">I'll decide later</label>
              </div>
              <div class="text-end mt-3">
                <button id="submitHotel" type="button" class="btn_full">SUBMIT</button>
              </div>
              <div class="text-end mt-2" id="feedback" style="display:none;">
                <small class="text-muted">Hotel added successfully</small><br>
                <a href="/" class="btn_1 mt-2">Go back to home</a>
              </div>
            </form>
          </div>

          <!-- 3) Detalle reserva -->
          <div class="form_title">
            <h3><strong><i class="icon-tag-1"></i></strong>Booking Summary</h3>
          </div>
          <div class="step">
            <table class="table table-striped confirm">
              <tbody>
                <tr>
                  <td><strong>Activity</strong></td>
                  <td><?= htmlspecialchars($reserva['actividad'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                  <td><strong>Name</strong></td>
                  <td><?= htmlspecialchars($reserva['tit_nombre'] . ' ' . $reserva['tit_apellido'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                  <td><strong>Email</strong></td>
                  <td><?= htmlspecialchars($reserva['tit_email'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                  <td><strong>Adults</strong></td>
                  <td><?= (int)$reserva['adultos'] ?></td>
                </tr>
                <tr>
                  <td><strong>Children</strong></td>
                  <td><?= (int)$reserva['ninos'] ?></td>
                </tr>
                <tr>
                  <td><strong>Infants</strong></td>
                  <td><?= (int)$reserva['infantes'] ?></td>
                </tr>
                <tr>
                  <td><strong>Tour Date</strong></td>
                  <td><?= htmlspecialchars($fechaTour, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                  <td><strong>Airport Pickup</strong></td>
                  <td><?= $reserva['airport_pickup'] ? 'Yes' : 'No' ?></td>
                </tr>
                <tr>
                  <td><strong>Total Paid (USD)</strong></td>
                  <td>$<?= htmlspecialchars($totalUSD, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              </tbody>
            </table>
          </div>

          
        </div>

        <!-- Aside -->
        <aside class="col-lg-4">
          <div class="box_style_4">
            <i class="icon_set_1_icon-89"></i>
            <h4>Have <span>questions?</span></h4>
            <a href="tel://56953474421" class="phone">+56 9 5347 4421 </a>
            <a href="https://api.whatsapp.com/send?phone=56953474421"><i class="bi bi-whatsapp"></i></a>
            <p><small>Monday to Sunday 6.00am - 11.59pm</small></p>
          </div>
        </aside>

      </div>
    </div>
  </main>

  <!-- Footer (igual al original) -->

  <!-- JS -->
  <script src="js/jquery-3.7.1.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="js/common_scripts_min.js"></script>
  <script src="js/functions.js"></script>
  <script>
    $(function() {
      // Autocomplete hotels
      $('#hotel').autocomplete({
        source: 'get_hotels.php',
        minLength: 1
      }).autocomplete('instance')._renderItem = function(ul, item) {
        return $('<li>')
          .append(`<div><strong>${item.label}</strong><br><small style="color:#777;">${item.desc}</small></div>`)
          .appendTo(ul);
      };

      // Manage radio options
      $('#hotel').on('click', function() {
        $('#customHotelWrapper').hide();
        $(this).prop('readonly', false).val('');
        $('input[name="hotelOption"]').prop('checked', false);
      });

      $('input[name="hotelOption"]').on('change', function() {
        if (this.value === 'not_listed') {
          $('#hotel').prop('readonly', true).val('');
          $('#customHotelWrapper').show();
          $('#customHotel').prop('readonly', false).val('').focus();
        } else {
          $('#customHotelWrapper').hide();
          $('#customHotel').prop('readonly', true).val('');
          $('#hotel').prop('readonly', this.value === 'decide_later').val('');
        }
      });

      $('#customHotel').on('focus', function() {
        if (this.placeholder === 'enter address or hotel name...') {
          this.placeholder = '';
          $(this).css('color','#000');
        }
      }).on('blur', function() {
        if (!this.value) {
          this.placeholder = 'enter address or hotel name...';
          $(this).css('color','#999');
        }
      });

      // Submit hotel selection
      $('#submitHotel').on('click', function() {
        const hotelVal = $('#hotel').val(),
              radioVal = $('input[name="hotelOption"]:checked').val();
        if (hotelVal || radioVal === 'not_listed' || radioVal === 'decide_later') {
          $.ajax({
            type: 'POST',
            url: 'return.php?action=updateHotel',
            data: $('#hotelForm').serialize(),
            dataType: 'json'
          }).done(function(resp) {
            if (resp.success) {
              $('#feedback').fadeIn();
            } else {
              alert('Error updating hotel.');
            }
          });
        } else {
          alert('Please select or enter a hotel.');
        }
      });
    });
  </script>
</body>
</html>
