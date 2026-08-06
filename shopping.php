<?php
session_start();
require __DIR__ . '/../db_config.php';
require_once 'helpers.php';

if (!empty($_GET['reference_id'])) {
    // sanitize reference_id a bit
    $_SESSION['reference_id'] = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $_GET['reference_id']);
}
// Redirigir si no hay referencia válida
if (empty($_SESSION['reference_id'])) {
    header('Location: Valparaiso.html');
    exit;
}

$reference = $_SESSION['reference_id'];

// Cargar detalles de la reserva con joins
$stmt = $conn->prepare("
    SELECT 
        r.fecha_reserva,
        r.fecha_actividad AS fecha_tour,
        r.adultos,
        r.ninos,
        r.infantes,
        r.airport_pickup,
        r.total_venta,
        t.nombre AS titular_nombre,
        t.apellido AS titular_apellido,
        t.email AS titular_email,
        t.telefono AS titular_telefono,
        COALESCE(e.nombre_publico, e.nombre) AS actividad   
    FROM reservas r
    LEFT JOIN titulares t ON r.id_titular = t.id_titular
    LEFT JOIN experiencias e ON r.id_experiencia = e.id_experiencia
    WHERE r.reference_id = ?
    LIMIT 1
");
$stmt->bind_param('s', $reference);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    die('Reservation not found.');
}
$row = $result->fetch_assoc();
$stmt->close();

// NOTA: no cerramos $conn aquí para poder usarlo más abajo

// Sanitizar datos
$activity       = htmlspecialchars($row['actividad']);
$name           = htmlspecialchars($row['titular_nombre']);
$lastname       = htmlspecialchars($row['titular_apellido']);
$email          = htmlspecialchars($row['titular_email']);
$phone          = htmlspecialchars($row['titular_telefono']); // ya no se mostrará
$resDate        = $row['fecha_reserva']; // ya no se mostrará
$tourDate       = $row['fecha_tour'];
$adults         = $row['adultos'];
$children       = $row['ninos'];
$infants        = $row['infantes'];
$pickup         = $row['airport_pickup'];
$originalTotal  = (float)$row['total_venta']; // USD
$conversionRate = 960;
$totalCLP       = round($originalTotal * $conversionRate);

// Exponer total USD para endpoints del server
$_SESSION['order_total_usd'] = $originalTotal;

// Trigger Getnet payment if button clicked
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_with_getnet'])) {
    $auth = generateAuth();
    $payload = [
        'auth'            => $auth,
        'locale'          => 'es_CL',
        'payment'         => [
            'reference'   => $reference,
            'description' => $activity,
            'amount'      => [
                'currency' => 'CLP',
                'total'    => $totalCLP
            ]
        ],
        'expiration'      => date('c', strtotime('+30 minutes')),
        'returnUrl'       => 'https://stampstour.com/return.php?reference=' . urlencode($reference),
        'cancelUrl'       => 'https://stampstour.com/shopping.php',
        'ipAddress'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'userAgent'       => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
        'notificationUrl' => 'https://stampstour.com/getnet_notify.php'
    ];
    $response = callGetnet('/api/session', $payload);

    // Extraer el process_id (requestId o payment.id)
    $processId = $response['requestId'] ?? ($response['payment']['id'] ?? null);

    // Guardar process_id en la BD usando reference_id
    if ($processId !== null && !empty($reference)) {
        $updateStmt = $conn->prepare("
            UPDATE reservas 
               SET process_id = ? 
             WHERE reference_id = ?
        ");
        $updateStmt->bind_param("ss", $processId, $reference);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Redireccionar al processUrl
    if (!empty($response['processUrl'])) {
        $_SESSION['requestId'] = $response['requestId'] ?? null;
        header('Location: ' . $response['processUrl']);
        exit;
    } else {
        echo '<div class="alert alert-danger">Error al iniciar el pago con Getnet.</div>';
    }
}
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

    <meta name="description" content="StampTour - Place your order">
    <meta name="author" content="Miguel">
    <title>StampTour - Place your order</title>

    <!-- Favicons -->
    <link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="img/apple-touch-icon-57x57-precomposed.png">
    <link rel="apple-touch-icon" sizes="72x72" href="img/apple-touch-icon-72x72-precomposed.png">
    <link rel="apple-touch-icon" sizes="114x114" href="img/apple-touch-icon-114x114-precomposed.png">
    <link rel="apple-touch-icon" sizes="144x144" href="img/apple-touch-icon-144x144-precomposed.png">

    <!-- Speed up the PayPal SDK / button handshake -->
    <link rel="preconnect" href="https://www.paypal.com">
    <link rel="preconnect" href="https://www.paypalobjects.com">

    <!-- GOOGLE WEB FONT (self-hosted) -->
    <link href="fonts/fonts.css" rel="stylesheet">

    <!-- COMMON CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/vendors.css" rel="stylesheet">
    <!-- CUSTOM CSS -->
    <link href="css/custom.css" rel="stylesheet">

    <!-- Simple side-by-side styling for payment tiles -->
    <style>
      .pay-row { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
      @media (max-width: 992px){ .pay-row { grid-template-columns: 1fr; } }
      .pay-card { border:0; box-shadow: 0 2px 10px rgba(0,0,0,.06); border-radius: .5rem; overflow:hidden; background:#fff; }
      .pay-card .head { padding: .75rem 1rem; color:#6c757d; font-weight:600; font-size: .88rem; border-bottom: 1px solid #f0f0f0; background:#fff; }
      .pay-card .body { padding: 1rem; background:#fff; }
      .pay-hint { background:#f8f9fa; padding:.75rem 1rem; color:#6c757d; font-size:.85rem; }

      /* Mobile-only: compact booking summary */
      @media (max-width: 576px) {
        .booking-summary-compact table { font-size: 0.82rem; }
        .booking-summary-compact .table > :not(caption) > * > * { padding: .3rem .45rem; }
      }
    </style>
</head>
<body>
<script>
(function () {
  if (typeof gtag !== 'function') return;
  var reference = <?= json_encode($reference, JSON_INVALID_UTF8_SUBSTITUTE) ?>;
  var value = <?= json_encode($originalTotal, JSON_INVALID_UTF8_SUBSTITUTE) ?>;
  var itemName = <?= json_encode($activity, JSON_INVALID_UTF8_SUBSTITUTE) ?>;
  var qty = <?= (int)$adults + (int)$children + (int)$infants ?>;

  gtag('event', 'begin_checkout', {
    transaction_id: reference,
    value: value,
    currency: 'USD',
    items: [{ item_name: itemName, quantity: qty }]
  });

  document.addEventListener('DOMContentLoaded', function () {
    var getnetForm = document.getElementById('getnet-form');
    if (getnetForm) {
      getnetForm.addEventListener('submit', function () {
        gtag('event', 'add_payment_info', {
          transaction_id: reference,
          value: value,
          currency: 'USD',
          payment_type: 'getnet'
        });
      });
    }
  });
})();
</script>

    <div id="preloader">
        <div class="sk-spinner sk-spinner-wave">
            <div class="sk-rect1"></div>
            <div class="sk-rect2"></div>
            <div class="sk-rect3"></div>
            <div class="sk-rect4"></div>
            <div class="sk-rect5"></div>
        </div>
    </div>
    <!-- End Preload -->

    <div class="layer"></div>
    <!-- Mobile menu overlay mask -->

    <!-- Header================================================== -->
    <header class="d-none d-md-block">
       <?php include __DIR__ . '/includes/header.php'; ?>
    </header>
    <!-- End Header -->

    <!-- Hide hero on mobile -->
    <section id="hero_2" class="d-none d-md-block">
        <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
        <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.4)">
            <div class="intro_title">
                <h1>Place your order</h1>
                <div class="bs-wizard row">
                    <div class="col-6 bs-wizard-step complete">
                        <div class="text-center bs-wizard-stepnum">Your details</div>
                        <div class="progress"><div class="progress-bar"></div></div>
                        <a href="#" class="bs-wizard-dot"></a>
                    </div>
                    <div class="col-6 bs-wizard-step disabled">
                        <div class="text-center bs-wizard-stepnum">Finish!</div>
                        <div class="progress"><div class="progress-bar"></div></div>
                        <a href="confirmation_restaurant.html" class="bs-wizard-dot"></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <main>
        <div class="container margin_60">
            <div class="row">
                <div class="col-lg-8 add_bottom_15">
                    <div class="form_title d-flex align-items-center justify-content-between">
  <div>
    <h3 class="mb-0">
      <strong><i class="icon-tag-1"></i></strong>
      Booking summary
    </h3>
    <p class="mb-0">Review your details below.</p>
  </div>
  <!-- Mobile-only logo -->
  <img src="img/logolargo.png" alt="StampTour Logo"
       class="d-block d-md-none ms-2"
       style="height:28px; width:79px;">
</div>
                    <div class="step booking-summary-compact">
                        <table class="table table-striped confirm table-sm mb-2">
                            <tbody>
                                <tr><td><strong>Activity</strong></td><td><?= $activity ?></td></tr>

                                <!-- Lead traveler (name + lastname) + counts -->
                                <tr>
                                  <td><strong>Lead traveler</strong></td>
                                  <td>
                                    <?= trim($name . ' ' . $lastname) ?>
                                    &nbsp;&bull;&nbsp;
                                    <?= (int)$adults ?> adults, <?= (int)$children ?> children, <?= (int)$infants ?> infants
                                  </td>
                                </tr>

                                <tr><td><strong>Email</strong></td><td><?= $email ?></td></tr>
                                <tr><td><strong>Tour Date</strong></td><td><?= $tourDate ?></td></tr>

                                <!-- Airport pickup shown as Yes/No for clarity -->
                                <tr><td><strong>Airport Pickup</strong></td><td><?= $pickup ? 'Yes' : 'No' ?></td></tr>

                                <tr><td><strong>Total (USD)</strong></td><td>$<?= number_format($originalTotal, 2, '.', ',') ?> USD</td></tr>
                                <tr><td><strong>Total (CLP)</strong></td><td>$<?= number_format($totalCLP, 0, ',', '.') ?> CLP</td></tr>
                            </tbody>
                        </table>

                        <!-- Hidden reference for PayPal -->
                        <input type="hidden" id="reference_id" value="<?= htmlspecialchars($reference) ?>">

                        <!-- ===== SIDE-BY-SIDE PAYMENT OPTIONS (PayPal left, Getnet right) ===== -->
                        <div class="pay-row mt-3">
                          <!-- PayPal Payment Option -->
                          <div class="pay-card">
                            <div class="head">Pay with PayPal (uses your USD total)</div>
                            <div class="body">
                              <!-- PayPal renders here -->
                              <div id="paypal-buttons-container"></div>
                              <div id="paypal-status" class="small text-muted mt-2"></div>
                            </div>
                            <div class="pay-hint">
                              Pay securely with PayPal. On success, you’ll be redirected to our confirmation page.
                            </div>
                          </div>

                          <!-- Getnet Payment Option (unchanged logic) -->
                          <div class="pay-card">
                            <div class="head">Tarjeta de crédito, débito o prepago</div>
                            <div class="body">
                              <form method="POST" id="getnet-form">
                                <button type="submit" name="pay_with_getnet" class="btn text-start w-100 p-3 border-0 rounded-0" style="background-color:#fff;">
                                  <div class="text-muted fw-semibold small mb-2">
                                    Paga con Getnet (todas las tarjetas).
                                  </div>
                                  <div>
                                    <img src="img/GETNET.svg" alt="Getnet" style="height:44px;">
                                  </div>
                                </button>
                              </form>
                            </div>
                            <div class="pay-hint">
                              Paga seguro con tus tarjetas de crédito, débito y prepago, de emisores nacionales e internacionales.
                            </div>
                          </div>
                        </div>
                        <!-- ===== END SIDE-BY-SIDE PAYMENT OPTIONS ===== -->
                        <div class="text-muted small mt-2">
                            This site follows <a href="https://www.paypal.com/us/legalhub/paypal/acceptableuse-full" target="_blank" rel="noopener">PayPal’s Acceptable Use Policy</a>.
                        </div>
                    </div>
                </div>
                <aside class="col-lg-4">
                    <div class="box_style_4">
                        <i class="icon_set_1_icon-89"></i>
                        <h4>Have <span>questions?</span></h4>
                        <a href="tel://004542344599" class="phone">+56 9 2399 3146</a>
                        <a href="https://api.whatsapp.com/send?phone=56923993146"><i class="bi bi-whatsapp"></i></a>
                        <p><small>Monday to Sunday 6.00am - 11.59pm</small></p>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <footer class="revealed">
        <?php include __DIR__ . '/includes/footer.php'; ?>
    </footer>

    <div id="toTop"></div>

    <!-- jQuery -->
    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="js/common_scripts_min.js"></script>
    <script src="js/functions.js"></script>

    <!-- PayPal SDK + Integration (keeps your existing server endpoints) -->
<script>
/* Helper that shows raw server replies if JSON fails */
function postJson(url, data){
  return fetch(url, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(data)
  }).then(async r => {
    const txt = await r.text();
    try { return JSON.parse(txt); }
    catch(e){ throw new Error('Non-JSON from '+url+': ' + txt.slice(0,400)); }
  });
}

(function(){
  var CLIENT_ID = 'AXLxxD1OkRcWruO-hYleas1bGpUmPQa_Lc3KIqlLD3RKgt7bEAx13QjCNC8tgmgtHm3Ab9dEn2u1aOht'; // Sandbox client id (swap for LIVE in production)
  var CURRENCY  = 'USD';

  // Attach a small debug line under PayPal container
  var dbg = document.createElement('div');
  dbg.id = 'paypal-debug';
  dbg.className = 'small text-danger mt-2';
  var cont = document.getElementById('paypal-buttons-container');
  if (cont) cont.after(dbg);

  function show(msg){ if (dbg){ dbg.textContent = msg; } console.error(msg); }

  function initPayPal(){
    if (!window.paypal || !paypal.Buttons){
      show('SDK loaded but paypal.Buttons missing.');
      return;
    }
    var refEl = document.getElementById('reference_id');
    var ref = refEl ? refEl.value : '';

    paypal.Buttons({
      // Compliant appearance options
      style: {
        layout:  'vertical',
        color:   'gold',
        shape:   'pill',
        label:   'checkout',
        tagline: false // hide tagline bar
      },

      // Create order on your server (unchanged endpoints)
      createOrder: function(){
        return postJson('api/paypal/create-order.php', { reference_id: ref })
          .then(d => { if (d && d.id) return d.id; throw new Error('Create failed: '+JSON.stringify(d)); })
          .catch(err => { show(err.message); throw err; });
      },

      // Capture on your server and then redirect
      onApprove: function(data){
        document.getElementById('paypal-status').textContent = 'Capturing payment...';
        return postJson('api/paypal/capture-order.php', { orderID: data.orderID, reference_id: ref })
          .then(res => {
            if (res && res.ok && res.status === 'COMPLETED') {
              document.getElementById('paypal-status').textContent = 'Payment approved ✔';
              try {
                if (typeof gtag === 'function') {
                  gtag('event', 'add_payment_info', {
                    transaction_id: ref,
                    value: <?= json_encode($originalTotal, JSON_INVALID_UTF8_SUBSTITUTE) ?>,
                    currency: 'USD',
                    payment_type: 'paypal'
                  });
                }
              } catch (e) { /* analytics must never block the redirect */ }
              window.location.href = 'return.php?provider=paypal&reference=' + encodeURIComponent(ref);
            } else {
              throw new Error('Capture not completed: ' + JSON.stringify(res));
            }
          })
          .catch(err => { show(err.message); throw err; });
      },

      onError: function(err){ show('PayPal error: ' + err.message); }
    }).render('#paypal-buttons-container');
  }

  // Dynamically load the SDK and only init after it loads
  var s = document.createElement('script');
  // NOTE: components=buttons + disable-funding hides card/paylater rails (wallet-only view)
  s.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(CLIENT_ID) +
          '&currency=' + encodeURIComponent(CURRENCY) +
          '&intent=capture&components=buttons&disable-funding=paylater';
  s.onload  = initPayPal;
  s.onerror = function(){ show('SDK network error. Check client-id and any blockers/CSP.'); };
  document.head.appendChild(s);
})();
</script>

</body>
</html>
<?php
// Cerrar conexión al final del script
$conn->close();
?>
