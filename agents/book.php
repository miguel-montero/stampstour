<?php
// agents/book.php - public travel-agent booking page (TA rates, unlocked by 4-digit agent code).
ini_set('display_errors', '0');

require __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../includes/ta_booking.php';

$taPrices = [];
try {
  $taPrices = ta_tour_prices($conn);
} catch (Throwable $e) {
  error_log('agents/book.php: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="robots" content="noindex">
  <title>Travel Agent Booking | Stamp's Tour</title>

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
            <h3 class="h4 mb-3 text-center">Travel Agent Booking</h3>
            <p class="text-muted text-center mb-4">Enter your 4-digit agent code to unlock the form.</p>

            <!-- FORM -->
            <form id="booking" action="/agents/book_submit.php" method="POST" novalidate>
              <!-- Agent code validation -->
              <div class="form-group">
                <label for="ta_code" class="font-weight-semibold">Agent Code</label>
                <div class="d-flex">
                  <input
                    type="text"
                    class="form-control"
                    id="ta_code"
                    name="ta_code"
                    placeholder="4 digits"
                    maxlength="4"
                    autocomplete="off"
                    inputmode="numeric"
                    pattern="[0-9]{4}"
                    style="flex:1; margin-right:.5rem;"
                  />
                  <button type="button" id="vendor_validate_btn" class="btn btn-primary">Validate</button>
                </div>
                <small id="vendor_message" class="form-text" style="display:none;"></small>
              </div>

              <!-- Hidden mirrors -->
              <input type="hidden" name="date_booking" id="date_booking" value="">

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
                    <label for="customer_first_name">First Name</label>
                    <input type="text" class="form-control" id="customer_first_name" name="customer_first_name" required>
                  </div>
                  <div class="form-group col-md-6">
                    <label for="customer_last_name">Last Name</label>
                    <input type="text" class="form-control" id="customer_last_name" name="customer_last_name" required>
                  </div>
                </div>

                <div class="form-group">
                  <label for="customer_whatsapp">WhatsApp</label>
                  <input type="text" class="form-control" id="customer_whatsapp" name="customer_whatsapp" placeholder="+56 9 1234 5678" required>
                  <small class="form-text text-muted">Include country code</small>
                </div>

                <!-- Date -->
                <div class="form-group">
                  <label for="fecha_actividad">Tour Date</label>
                  <input type="date" class="form-control" id="fecha_actividad" name="fecha_actividad" min="<?= (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d') ?>" required>
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
                  <input type="text" id="hotel" name="hotel" class="form-control" maxlength="255" placeholder="Start typing to search…">
                </div>
                <div class="form-group">
                  <div class="custom-control custom-radio">
                    <input type="radio" id="notListed" name="hotelOption" class="custom-control-input" value="not_listed">
                    <label class="custom-control-label" for="notListed">My hotel is not listed</label>
                  </div>
                  <div id="customHotelWrapper" class="mt-2" style="display:none;">
                    <input type="text" id="customHotel" name="customHotel" class="form-control" maxlength="255" placeholder="Enter hotel name or address…">
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
  var tourPrices = <?php echo json_encode($taPrices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;

  function updateTotalPrice(){
    var tourKey = $('#tour').val();
    if (!tourKey || !tourPrices[tourKey]) return;
    var p = tourPrices[tourKey];
    var a = parseInt($('#adults').val()||0,10);
    var c = parseInt($('#children').val()||0,10);
    var i = parseInt($('#infants').val()||0,10);
    var total = (a*(parseFloat(p.adult)||0)) + (c*(parseFloat(p.child)||0)) + (i*(parseFloat(p.infant)||0));
    $('#total_price').text(total.toFixed(2));
  }

  $('#tour, #adults, #children, #infants').on('change keyup', updateTotalPrice);

  // Agent code validation
  function validateCode(){
    var code = $('#ta_code').val().trim();
    if (!/^\d{4}$/.test(code)) { msg('Please enter your 4-digit agent code.', false); return; }
    $.post('/agents/validate_code.php', { code: code }, function(resp){
      if (resp && resp.ok) {
        msg('Welcome, ' + resp.name + '. Confirmations go to ' + resp.email_masked + '.', true);
        $('#ta_code').prop('readonly', true);
        $('#vendor_validate_btn').prop('disabled', true);
        enableBookingFields();
        syncDateForSubmit();
        updateTotalPrice();
      } else {
        msg((resp && resp.error) || 'Invalid or inactive agent code.', false);
        disableBookingFields();
      }
    }, 'json').fail(function(xhr){
      var m = 'Validation error. Please try again.';
      if (xhr && xhr.responseJSON && xhr.responseJSON.error) m = xhr.responseJSON.error;
      msg(m, false);
      disableBookingFields();
    });
  }
  $('#vendor_validate_btn').on('click', validateCode);
  $('#ta_code').on('keydown', function(e){ if (e.which === 13) { e.preventDefault(); validateCode(); } });

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
      msg('Validate your agent code to continue.', false);
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
