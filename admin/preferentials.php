<?php
// /preferential.php
// Travel Agent preferential booking page (English) with TA prices from DB (experiencias.TAR).
// Adult = TAR (fallback precio_adulto), Child = precio_nino, Infant = precio_infante
require __DIR__ . '/_auth.php';

$taPrices = [
  'Valparaiso' => ['adult' => 0.0, 'child' => 0.0, 'infant' => 0.0],
  'Andes'      => ['adult' => 0.0, 'child' => 0.0, 'infant' => 0.0],
  'Santiago'   => ['adult' => 0.0, 'child' => 0.0, 'infant' => 0.0],
  'Maipo'      => ['adult' => 0.0, 'child' => 0.0, 'infant' => 0.0],
];

try {
  require __DIR__ . '/../../db_config.php';
  if (isset($conn) && $conn instanceof mysqli) {
    $names = ["Valparaiso","Andes","Santiago","Maipo"];
    $in = "'" . implode("','", array_map(fn($n)=>$conn->real_escape_string($n), $names)) . "'";
    $sql = "SELECT nombre, TAR, precio_adulto, precio_nino, precio_infante FROM experiencias WHERE nombre IN ($in)";
    if ($res = $conn->query($sql)) {
      while ($row = $res->fetch_assoc()) {
        $nombre = $row['nombre'];
        $adult  = (isset($row['TAR']) && (float)$row['TAR'] > 0) ? (float)$row['TAR'] : (float)$row['precio_adulto'];
        $child  = isset($row['precio_nino']) ? (float)$row['precio_nino'] : 0.0;
        $infant = isset($row['precio_infante']) ? (float)$row['precio_infante'] : 0.0;
        if (isset($taPrices[$nombre])) {
          $taPrices[$nombre] = ['adult'=>$adult,'child'=>$child,'infant'=>$infant];
        }
      }
      $res->free();
    }
  }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Preferential Booking (Travel Agents)</title>

  <!-- Favicon -->
  <link rel="shortcut icon" href="/img/favicon.ico" type="image/x-icon"/>

  <!-- Bootstrap + CSS (local, matches the version used site-wide) -->
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>

  <!-- jQuery UI for hotel autocomplete (self-hosted, trimmed to just what's used) -->
  <link href="/js/vendor/jquery-ui-autocomplete.css" rel="stylesheet"/>

  <style>
    main { padding: 32px 0; }
    .card { border-radius: 14px; }
    fieldset[disabled] { opacity: .55; pointer-events: none; }
    .logo-container { text-align:center; margin-bottom:20px; }
    .logo-container img { max-width: 240px; height:auto; }
  </style>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('preferentials'); ?>

<main>
  <div class="container">
    <!-- Visible logo -->
    <div class="logo-container">
      <img src="/img/logolargo.png" alt="Stamp’s Tour Logo" width="320" height="114"/>
    </div>

    <div class="row justify-content-center">
      <div class="col-xl-5 col-lg-6 col-md-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="h4 mb-3 text-center">Preferential Booking — Travel Agents</h3>
            <p class="text-muted text-center mb-4">Enter your vendor code to unlock the form.</p>

            <!-- FORM -->
            <form id="booking" action="/submit.php" method="POST" novalidate>
              <!-- Vendor validation -->
              <div class="form-group">
                <label for="vendor_code" class="font-weight-semibold">Vendor Code</label>
                <div class="d-flex">
                  <input
                    type="text"
                    class="form-control"
                    id="vendor_code"
                    name="vendor_code"
                    placeholder="e.g., 10"
                    autocomplete="off"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    style="flex:1; margin-right:.5rem;"
                  />
                  <button type="button" id="vendor_validate_btn" class="btn btn-primary">Validate</button>
                </div>
                <small id="vendor_message" class="form-text" style="display:none;"></small>
              </div>

              <!-- Hidden mirrors -->
              <input type="hidden" name="codigo_vendedor" id="codigo_vendedor" value="">
              <input type="hidden" name="date_booking" id="date_booking" value="">
              <input type="hidden" id="total_price_hidden" name="total_price" value="0">

              <hr class="my-4"/>

              <!-- LOCKED FIELDS -->
              <fieldset id="booking_fields" disabled>

                <!-- Tour -->
                <div class="form-group">
                  <label for="tour">Tour</label>
                  <select class="form-control" id="tour" name="activity_name" required>
                    <option value="" selected disabled>Select a tour</option>
                    <option value="Valparaiso">Valparaiso</option>
                    <option value="Andes">Andes</option>
                    <option value="Santiago">Santiago</option>
                    <option value="Maipo">Maipo</option>
                  </select>
                </div>

                <!-- Lead traveler -->
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label for="name_booking">First Name</label>
                    <input type="text" class="form-control" id="name_booking" name="name_booking" required>
                  </div>
                  <div class="form-group col-md-6">
                    <label for="last_name_booking">Last Name</label>
                    <input type="text" class="form-control" id="last_name_booking" name="last_name_booking" required>
                  </div>
                </div>

                <div class="form-group">
                  <label for="email_booking">Email</label>
                  <input type="email" class="form-control" id="email_booking" name="email_booking" required>
                </div>

                <div class="form-group">
                  <label for="phone_booking">Phone</label>
                  <input type="text" class="form-control" id="phone_booking" name="phone_booking" required>
                </div>

                <!-- Date -->
                <div class="form-group">
                  <label for="fecha_actividad">Tour Date</label>
                  <input type="date" class="form-control" id="fecha_actividad" name="fecha_actividad" required>
                </div>

                <!-- Passengers -->
                <div class="form-row">
                  <div class="form-group col-4">
                    <label for="adults">Adults</label>
                    <input type="number" min="1" class="form-control" id="adults" name="adults" value="1" required>
                  </div>
                  <div class="form-group col-4">
                    <label for="children">Children</label>
                    <input type="number" min="0" class="form-control" id="children" name="children" value="0">
                    <small class="text-muted" style="font-size:11px;">&lt;12 years</small>
                  </div>
                  <div class="form-group col-4">
                    <label for="infants">Infants</label>
                    <input type="number" min="0" class="form-control" id="infants" name="infants" value="0">
                    <small class="text-muted" style="font-size:11px;">&lt;2 years</small>
                  </div>
                </div>

                <!-- Hotel selector -->
                <div class="form-group">
                  <label for="hotel">Hotel (pickup)</label>
                  <input type="text" id="hotel" name="hotel" class="form-control" placeholder="Start typing to search…">
                </div>
                <div class="form-group">
                  <div class="custom-control custom-radio">
                    <input type="radio" id="notListed" name="hotelOption" class="custom-control-input" value="not_listed">
                    <label class="custom-control-label" for="notListed">My hotel is not listed</label>
                  </div>
                  <div id="customHotelWrapper" class="mt-2" style="display:none;">
                    <input type="text" id="customHotel" name="customHotel" class="form-control" placeholder="Enter hotel name or address…">
                  </div>
                </div>
                <div class="form-group">
                  <div class="custom-control custom-radio">
                    <input type="radio" id="decideLater" name="hotelOption" class="custom-control-input" value="decide_later">
                    <label class="custom-control-label" for="decideLater">Decide later</label>
                  </div>
                </div>

                <hr/>
                <p class="lead mb-3">Total (TA Rate): $ <span id="total_price">0.00</span> USD</p>
                <button type="submit" class="btn btn-success btn-block" id="book_now_btn">Proceed to Payment</button>
              </fieldset>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- JS -->
<script src="/js/jquery-3.7.1.min.js"></script>
<script src="/js/vendor/jquery-ui-autocomplete.js"></script>
<script src="/js/bootstrap.bundle.min.js"></script>

<script>
(function($){
  function enableBookingFields(){ $('#booking_fields').prop('disabled', false); }
  function disableBookingFields(){ $('#booking_fields').prop('disabled', true); }
  function msg(text, ok){ $('#vendor_message').text(text).css('color', ok ? 'green' : 'red').show(); }

  function ymdToMdy(ymd){
    if(!/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return '';
    var p = ymd.split('-'); return p[1] + '-' + p[2] + '-' + p[0];
  }
  function syncDateForSubmit(){
    var ymd = $('#fecha_actividad').val() || '';
    $('#date_booking').val( ymdToMdy(ymd) );
  }

  disableBookingFields();
  $('#fecha_actividad').on('change input', syncDateForSubmit);

  // PRICES from PHP
  var tourPrices = <?php echo json_encode($taPrices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  function updateTotalPrice(){
    var tourKey = $('#tour').val();
    if (!tourKey || !tourPrices[tourKey]) return;
    var p = tourPrices[tourKey];
    var a = parseInt($('#adults').val()||0,10);
    var c = parseInt($('#children').val()||0,10);
    var i = parseInt($('#infants').val()||0,10);
    var total = (a*(parseFloat(p.adult)||0)) + (c*(parseFloat(p.child)||0)) + (i*(parseFloat(p.infant)||0));
    $('#total_price').text(total.toFixed(2));
    $('#total_price_hidden').val(total.toFixed(2));
  }

  $('#tour, #adults, #children, #infants').on('change keyup', updateTotalPrice);

  // Vendor validation
  $('#vendor_validate_btn').on('click', function(){
    var code = $('#vendor_code').val().trim();
    if (!code) { msg('Please enter your vendor code.', false); return; }
    $.post('/api/validate_vendor_ta.php', { vendor_code: code }, function(resp){
      if (resp && resp.ok) {
        msg('Welcome, ' + resp.nombre_vendedor, true);
        $('#vendor_code, #vendor_validate_btn').prop('disabled', true);
        $('#codigo_vendedor').val(code);
        enableBookingFields();
        syncDateForSubmit();
        updateTotalPrice();
      } else {
        msg('Invalid vendor code.', false);
        disableBookingFields();
        $('#codigo_vendedor').val('');
      }
    }, 'json').fail(function(){
      msg('Validation error. Please try again.', false);
      disableBookingFields();
      $('#codigo_vendedor').val('');
    });
  });

  // Hotel autocomplete
  $('#hotel').autocomplete({
    source: '/get_hotels.php',
    minLength: 1,
    select: function(){
      $('input[name="hotelOption"]').prop('checked', false);
      $('#customHotelWrapper').hide();
      $('#customHotel').val('');
    }
  });

  $('input[name="hotelOption"]').on('change', function(){
    if (this.value === 'not_listed') {
      $('#hotel').val('');
      $('#customHotelWrapper').show();
      $('#customHotel').focus();
    } else {
      $('#customHotelWrapper').hide();
      $('#customHotel').val('');
      $('#hotel').val('');
    }
  });

  // Final guard
  $('#booking').on('submit', function(e){
    if ($('#booking_fields').is(':disabled')) {
      e.preventDefault();
      msg('Validate your vendor code to continue.', false);
      return false;
    }
    syncDateForSubmit();
    if (!$('#date_booking').val()) {
      e.preventDefault();
      alert('Please select a valid date.');
      $('#fecha_actividad').focus();
      return false;
    }
  });

})(jQuery);
</script>
</body>
</html>
