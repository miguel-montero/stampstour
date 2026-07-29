<?php
include __DIR__ . '/../db_config.php';

// ——— AJAX endpoint: update hotel without reloading ———
if (isset($_GET['action']) && $_GET['action'] === 'updateHotel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res_id = (int)$_POST['id_reserva'];
    if (!empty($_POST['hotelOption']) && in_array($_POST['hotelOption'], ['not_listed','decide_later'], true)) {
        $stmt = $conn->prepare("UPDATE reservas SET id_hotel = NULL WHERE id_reserva = ?");
        $stmt->bind_param("i", $res_id);
        $stmt->execute();
    } elseif (!empty($_POST['hotel'])) {
        list($nombre) = explode(' – ', $_POST['hotel'], 2);
        $stmt = $conn->prepare("SELECT id_hotel FROM hoteles WHERE nombre_hotel = ? LIMIT 1");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $h = $stmt->get_result()->fetch_assoc();
        if ($h) {
            $upd = $conn->prepare("UPDATE reservas SET id_hotel = ? WHERE id_reserva = ?");
            $upd->bind_param("ii", $h['id_hotel'], $res_id);
            $upd->execute();
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// ——— Fetch latest reservation & mark realizado ———
$stmt = $conn->prepare("
    SELECT r.*, t.nombre, t.apellido, t.email
      FROM reservas r
      JOIN titulares t ON r.id_titular = t.id_titular
     ORDER BY r.id_reserva DESC
     LIMIT 1
");
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();

$wasPending = ($reserva['estado'] === 'pendiente');
if ($wasPending) {
    $u = $conn->prepare("UPDATE reservas SET estado = 'realizado' WHERE id_reserva = ?");
    $u->bind_param("i", $reserva['id_reserva']);
    $u->execute();
}

// ——— Generate & persist Reference Code if missing ———
if (empty($reserva['codigo_externo'])) {
    function generateReferenceCode($length = 8) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
    $res_id = $reserva['id_reserva'];
    do {
        $code = generateReferenceCode();
        $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM reservas WHERE codigo_externo = ?");
        $chk->bind_param("s", $code);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
    } while ($row['cnt'] > 0);
    $updCode = $conn->prepare("UPDATE reservas SET codigo_externo = ? WHERE id_reserva = ?");
    $updCode->bind_param("si", $code, $res_id);
    $updCode->execute();
    $reserva['codigo_externo'] = $code;
}

// ——— Send confirmation email once ———
if ($wasPending && !empty($reserva['email'])) {
    $to      = $reserva['email'];
    $subject = "Your booking confirmation (Ref: {$reserva['codigo_externo']})";
    $d = new DateTime($reserva['fecha_actividad']);
    $body  = "<html><body>";
    $body .= "<h2>Thank you, " . htmlspecialchars($reserva['nombre'].' '.$reserva['apellido']) . "!</h2>";
    $body .= "<p><strong>Reference Code:</strong> {$reserva['codigo_externo']}</p>";
    $body .= "<table cellpadding='5' cellspacing='0' border='1'>";
    $body .= "<tr><td><strong>Adults</strong></td><td>{$reserva['adultos']}</td></tr>";
    $body .= "<tr><td><strong>Children</strong></td><td>{$reserva['ninos']}</td></tr>";
    $body .= "<tr><td><strong>Infants</strong></td><td>{$reserva['infantes']}</td></tr>";
    $body .= "<tr><td><strong>Date of activity</strong></td><td>" . $d->format('j F Y') . "</td></tr>";
    $body .= "<tr><td><strong>Total paid</strong></td><td>$" . number_format($reserva['total_venta'], 2) . "</td></tr>";
    if ((int)$reserva['airport_pickup'] > 0) {
        $body .= "<tr><td><strong>Airport pickup</strong></td><td>Yes</td></tr>";
    }
    if (!empty($reserva['id_hotel'])) {
        $hstmt = $conn->prepare("SELECT nombre_hotel, direccion, comuna FROM hoteles WHERE id_hotel = ? LIMIT 1");
        $hstmt->bind_param("i", $reserva['id_hotel']);
        $hstmt->execute();
        $hdata = $hstmt->get_result()->fetch_assoc();
        $body .= "<tr><td><strong>Hotel</strong></td><td>"
              . htmlspecialchars($hdata['nombre_hotel']." – ".$hdata['direccion'].", ".$hdata['comuna'])
              . "</td></tr>";
    }
    $body .= "</table><p>We look forward to seeing you!</p>";
    $body .= "</body></html>";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: \"Stamp's Tour\" <reservations@stampstour.com>\r\n";
    @mail($to, $subject, $body, $headers);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
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
</head>
<body>
  <!-- Header (identical to shopping.php) -->
  <header>
    <!-- … full shopping.php header markup … -->
  </header>

  <section id="hero_2" class="background-image" data-background="url(img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.4)">
      <div class="intro_title">
        <h1>Booking Successful!</h1>
        <!-- Progress bar from confirmation_restaurant -->
        <div class="bs-wizard row">
          <div class="col-6 bs-wizard-step complete">
            <div class="text-center bs-wizard-stepnum">Your details</div>
            <div class="progress"><div class="progress-bar"></div></div>
            <a href="payment_restaurant.html" class="bs-wizard-dot"></a>
          </div>
          <div class="col-6 bs-wizard-step complete">
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

          <!-- Thank You -->
          <div class="form_title">
            <h3><strong><i class="icon-ok"></i></strong>Thank you!</h3>
            <br>
            <p>Your guide will contact you the previous evening between 8pm-9pm with exact details about your pick up time.</p>
          </div>

          <!-- Booking Summary -->
          <div class="form_title">
            <h3><strong><i class="icon-tag-1"></i></strong>Booking summary</h3>
          </div>
          <div class="step">
            <table class="table table-striped confirm">
              <tbody>
                <tr><td><strong>Reference Code</strong></td><td><?php echo htmlspecialchars($reserva['codigo_externo']); ?></td></tr>
                <tr><td><strong>Name</strong></td><td><?php echo htmlspecialchars($reserva['nombre'].' '.$reserva['apellido']); ?></td></tr>
                <tr><td><strong>Adults</strong></td><td><?php echo (int)$reserva['adultos']; ?></td></tr>
                <tr><td><strong>Children</strong></td><td><?php echo (int)$reserva['ninos']; ?></td></tr>
                <tr><td><strong>Infants</strong></td><td><?php echo (int)$reserva['infantes']; ?></td></tr>
                <tr><td><strong>Date of activity</strong></td>
                    <td><?php $d = new DateTime($reserva['fecha_actividad']); echo $d->format('j F Y'); ?></td>
                </tr>
                <tr><td><strong>Total paid</strong></td><td>$<?php echo number_format($reserva['total_venta'],2); ?></td></tr>
                <?php if ((int)$reserva['airport_pickup']>0): ?>
                <tr><td><strong>Airport pickup</strong></td><td>Yes</td></tr>
                <?php endif; ?>
                <?php if (!empty($reserva['id_hotel'])):
                  $hstmt = $conn->prepare("SELECT nombre_hotel,direccion,comuna FROM hoteles WHERE id_hotel=? LIMIT 1");
                  $hstmt->bind_param("i",$reserva['id_hotel']);
                  $hstmt->execute();
                  $hdata = $hstmt->get_result()->fetch_assoc();
                ?>
                <tr><td><strong>Hotel</strong></td>
                    <td><?php echo htmlspecialchars($hdata['nombre_hotel']." – ".$hdata['direccion'].", ".$hdata['comuna']); ?></td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Hotel-Selection Section -->
          <div class="form_title">
            <h3><strong><i class="icon-tag-1"></i></strong>Tell us where you will be staying</h3>
          </div>
          <div class="step">
            <form id="hotelForm">
              <input type="hidden" name="id_reserva" value="<?php echo $reserva['id_reserva']; ?>">
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
                <small class="text-muted">hotel added successfully</small><br>
                <a href="/" class="btn_1 mt-2">Do you want to go back?</a>
              </div>
            </form>
          </div>

        </div><!-- /col -->

        <aside class="col-lg-4">
          <div class="box_style_4">
            <i class="icon_set_1_icon-89"></i>
            <h4>Have <span>questions?</span></h4>
            <a href="tel://004542344599" class="phone">+56 9 2399 3146 </a>
            <a href="https://api.whatsapp.com/send?phone=56923993146"><i class="bi bi-whatsapp"></i></a>
            <p><small>Monday to Sunday 6.00am - 11.59pm</small></p> 
          </div>
        </aside>
      </div><!-- /row -->
    </div><!-- /container -->
  </main>

  <!-- Footer (identical to shopping.php) -->
  <footer class="revealed">
    <!-- … full shopping.php footer markup … -->
  </footer>

  <div id="toTop"></div>

  <script src="js/jquery-3.7.1.min.js"></script>
  <script src="js/common_scripts_min.js"></script>
  <script src="js/functions.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script>
    $(function() {
      $("#hotel").on("click", function(){
        $("#customHotelWrapper").hide();
        $(this).prop("readonly", false).val("");
        $("input[name='hotelOption']").prop("checked", false);
      });
      $("#hotel").autocomplete({
        source: "get_hotels.php",
        minLength: 1
      }).autocomplete("instance")._renderItem = function(ul, item){
        return $("<li>")
          .append(`<div><strong>${item.label}</strong><br><small style="color:#777;">${item.desc}</small></div>`)
          .appendTo(ul);
      };
      $("#hotel").on("autocompleteselect", function(e,ui){
        $(this).prop("readonly", true).val(ui.item.value + " – " + ui.item.desc);
        $("input[name='hotelOption']").prop("checked", false);
        return false;
      });
      $("input[name='hotelOption']").on("change", function(){
        if (this.value === "not_listed") {
          $("#hotel").prop("readonly", true).val("");
          $("#customHotelWrapper").show();
          $("#customHotel").prop("readonly", false).val("").focus();
        } else {
          $("#customHotelWrapper").hide();
          $("#customHotel").prop("readonly", true).val("");
          $("#hotel").prop("readonly", this.value === "decide_later").val("");
        }
      });
      $("#customHotel").on("focus", function(){
        if (this.placeholder === "enter address or hotel name...") {
          this.placeholder = "";
          $(this).css("color","#000");
        }
      }).on("blur", function(){
        if (!this.value) {
          this.placeholder = "enter address or hotel name...";
          $(this).css("color","#999");
        }
      });
      $("#submitHotel").on("click", function(){
        const hotelVal = $("#hotel").val(),
              radioVal = $("input[name='hotelOption']:checked").val();
        if (hotelVal || radioVal === "not_listed" || radioVal === "decide_later") {
          $.ajax({
            type: "POST",
            url: "success.php?action=updateHotel",
            data: $("#hotelForm").serialize(),
            dataType: "json"
          }).done(function(resp){
            if (resp.success) {
              $("#feedback").fadeIn();
            } else {
              alert("Error updating hotel.");
            }
          });
        } else {
          alert("Please select or enter a hotel.");
        }
      });
    });
  </script>
</body>
</html>
