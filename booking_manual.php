<?php
session_start();
require __DIR__ . '/../db_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manual Booking</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    /* Grey-out + block interaction while locked */
    #booking_form[aria-disabled="true"] { opacity: .55; pointer-events: none; }
  </style>
</head>
<body class="container py-4">
  <h2 class="mb-3">Manual Booking Form</h2>

  <!-- Minimal vendor validation block (outside the form) -->
  <div class="card p-3 mb-3">
    <h5 class="mb-2">Identificar vendedor</h5>
    <div class="form-row align-items-end">
      <div class="form-group col-md-6">
        <label class="form-label" for="id_vendedor_input">ID de vendedor</label>
        <input type="number" id="id_vendedor_input" class="form-control" placeholder="Ej: 1" min="1">
        <small class="form-text text-muted">Ingresa el ID interno del vendedor (1, 2, 3, ...).</small>
      </div>
      <div class="form-group col-md-3">
        <button id="btn-validar" class="btn btn-primary btn-block" type="button">Validar</button>
      </div>
      <div class="form-group col-12 mb-0">
        <div id="saludo" class="font-weight-semibold"></div>
        <div id="vend-error" class="text-danger small"></div>
      </div>
    </div>
  </div>

  <form id="booking_form" method="post" action="submit.php" aria-disabled="true">
    <!-- Hidden: fecha_reserva (purchase date) in YYYY-MM-DD -->
    <input type="hidden" name="fecha_reserva" id="fecha_reserva" value="<?php echo date('Y-m-d'); ?>">
    <!-- Hidden: date_booking (activity date IN MM-DD-YYYY that submit.php expects). Default = today -->
    <input type="hidden" name="date_booking" id="date_booking" value="<?php echo date('m-d-Y'); ?>">

    <!-- Hidden fields with the names submit.php expects (mirrored by the shim) -->
    <input type="hidden" name="name_booking" id="name_booking_hidden" value="">
    <input type="hidden" name="last_name_booking" id="last_name_booking_hidden" value="">
    <input type="hidden" name="email_booking" id="email_booking_hidden" value="">
    <input type="hidden" name="phone_booking" id="phone_booking_hidden" value="">
    <input type="hidden" name="adults" id="adults_hidden" value="1">
    <input type="hidden" name="children" id="children_hidden" value="0">
    <input type="hidden" name="infants" id="infants_hidden" value="0">
    <input type="hidden" name="activity_name" id="activity_name" value="">
    <!-- Optional: many backends read total_price; we keep it in sync from the UI -->
    <input type="hidden" id="total_price_hidden" name="total_price" value="0">

    <!-- Your existing form starts here (unchanged except the Tour select options) -->
    <div class="form-group">
      <label for="codigo_vendedor">Código Vendedor</label>
      <input type="text" name="codigo_vendedor" id="codigo_vendedor" class="form-control" placeholder="Ingrese código de vendedor">
      <small class="form-text text-muted">Este campo se completa automáticamente al validar el ID arriba.</small>
    </div>

    <div class="form-row">
      <div class="form-group col-md-6">
        <label for="id_experiencia">Tour</label>
        <select name="id_experiencia" id="id_experiencia" class="form-control" required>
          <option value="">Seleccione...</option>
          <?php
          // IMPORTANT: expose both internal name and public label
          $res = $conn->query("
            SELECT id_experiencia,
                   nombre,                                 -- internal canonical name
                   COALESCE(nombre_publico, nombre) AS label -- public-facing label
            FROM experiencias
            ORDER BY id_experiencia ASC
            LIMIT 4
          ");
          while ($row = $res->fetch_assoc()) {
            $id     = (int)$row['id_experiencia'];
            $label  = htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8');
            $intern = htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8'); // canonical
            // value = id (unchanged); data-internal = canonical DB name; shown text = public label
            echo '<option value="'.$id.'" data-internal="'.$intern.'">'.$label.'</option>';
          }
          ?>
        </select>
      </div>
      <div class="form-group col-md-6">
        <label for="fecha_actividad">Fecha</label>
        <!-- Visible activity date (YYYY-MM-DD) -->
        <input type="date" name="fecha_actividad" id="fecha_actividad" class="form-control" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group col-md-4">
        <label>Adultos</label>
        <input type="number" name="adultos" id="adults" class="form-control" value="1" min="1" required>
      </div>
      <div class="form-group col-md-4">
        <label>Niños</label>
        <input type="number" name="ninos" id="children" class="form-control" value="0" min="0">
      </div>
      <div class="form-group col-md-4">
        <label>Infantes</label>
        <input type="number" name="infantes" id="infants" class="form-control" value="0" min="0">
      </div>
    </div>

    <div class="form-group">
      <label for="id_hotel">Hotel / Meeting point</label>
      <select name="id_hotel" id="id_hotel" class="form-control">
        <option value="">Seleccione...</option>
        <?php
        $res = $conn->query("SELECT id_hotel, nombre_hotel FROM hoteles ORDER BY nombre_hotel ASC");
        while ($row = $res->fetch_assoc()) {
          echo '<option value="'.$row['id_hotel'].'">'.htmlspecialchars($row['nombre_hotel']).'</option>';
        }
        ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Nombre</label>
        <input type="text" name="titular_nombre" id="titular_nombre" class="form-control" required>
      </div>
      <div class="form-group col-md-6">
        <label>Apellido</label>
        <input type="text" name="titular_apellido" id="titular_apellido" class="form-control" required>
      </div>
      <div class="form-group col-md-6">
        <label>Email</label>
        <input type="email" name="titular_email" id="titular_email" class="form-control" required>
      </div>
      <div class="form-group col-md-6">
        <label>Teléfono</label>
        <input type="text" name="titular_telefono" id="titular_telefono" class="form-control">
      </div>
    </div>

    <div class="form-group">
      <label for="coupon_code">Coupon</label>
      <div class="input-group">
        <input type="text" id="coupon_code_input" name="coupon_code" class="form-control" placeholder="I have a coupon">
        <div class="input-group-append">
          <button type="button" class="btn btn-success" id="apply_coupon_btn">Apply</button>
        </div>
      </div>
    </div>

    <div class="card p-3 mb-3">
      <p>Subtotal: $<span id="subtotal_display">0</span></p>
      <p>Discount: $<span id="discount_display">0</span></p>
      <p><strong>Total: $<span id="total_price">0</span></strong></p>
      <input type="hidden" id="subtotal" name="subtotal" value="0">
      <input type="hidden" id="descuento" name="descuento" value="0">
      <input type="hidden" id="total_venta" name="total_venta" value="0">
    </div>

    <button type="submit" id="submit-btn" class="btn btn-primary btn-block">Crear Reserva</button>
  </form>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <!-- Your existing calculator logic -->
  <script src="js/tours.js"></script>

  <script>
  (function () {
    function $(id){ return document.getElementById(id); }
    function ymdToMdy(ymd){
      if(!/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return '';
      var p = ymd.split('-'); return p[1]+'-'+p[2]+'-'+p[0];
    }
    function setVal(id, v){ var el=$(id); if(el) el.value=v; }

    // 1) Keep date_booking in sync (submit.php expects MM-DD-YYYY)
    var fecha = $('fecha_actividad'), mdy = $('date_booking');
    function syncDate(){ if (fecha && mdy) mdy.value = ymdToMdy(fecha.value); }
    if (fecha){ fecha.addEventListener('change', syncDate); fecha.addEventListener('input', syncDate); if (fecha.value) syncDate(); }

    // 2) Mirror lead traveler fields for submit.php
    function syncLead(){
      setVal('name_booking_hidden', $('titular_nombre')?.value || '');
      setVal('last_name_booking_hidden', $('titular_apellido')?.value || '');
      setVal('email_booking_hidden', $('titular_email')?.value || '');
      setVal('phone_booking_hidden', $('titular_telefono')?.value || '');
    }
    ['titular_nombre','titular_apellido','titular_email','titular_telefono'].forEach(function(id){
      var el=$(id); if(el){ el.addEventListener('change', syncLead); el.addEventListener('keyup', syncLead); }
    });
    syncLead();

    // 3) Mirror pax counts for submit.php
    function syncPax(){
      setVal('adults_hidden',   $('adults')?.value || '0');
      setVal('children_hidden', $('children')?.value || '0');
      setVal('infants_hidden',  $('infants')?.value || '0');
    }
    ['adults','children','infants'].forEach(function(id){
      var el=$(id); if(el){ el.addEventListener('change', syncPax); el.addEventListener('keyup', syncPax); }
    });
    syncPax();

    // 4) Activity name: send the INTERNAL DB name; keep label for UI
    function syncActivityName(){
      var sel = $('id_experiencia');
      var opt = sel && sel.options[sel.selectedIndex];
      var internal = opt ? (opt.getAttribute('data-internal') || '').trim() : '';
      var label    = opt ? (opt.text || '').trim() : '';
      setVal('activity_name', internal); // submit.php expects the canonical DB name
      window.EXP_NAME = label;           // keep the public label for totals/UI if needed
    }
    var sel = $('id_experiencia');
    if (sel){ sel.addEventListener('change', syncActivityName); }
    syncActivityName();

    // 5) Hidden totals alignment
    function syncTotals(){
      var totalText = $('total_price') ? $('total_price').textContent : '0';
      var total = parseFloat(String(totalText).replace(/[^0-9.]/g,'')) || 0;
      setVal('total_venta', total.toFixed(2));
      setVal('total_price_hidden', total.toFixed(2));
    }
    ['adults','children','infants','coupon_code_input','fecha_actividad','id_experiencia'].forEach(function(id){
      var el=$(id); if(el){ el.addEventListener('change', syncTotals); el.addEventListener('keyup', syncTotals); }
    });
    var applyBtn = $('apply_coupon_btn');
    if (applyBtn) addEventListener('click', function(){ setTimeout(syncTotals, 250); });
    setTimeout(syncTotals, 300);

    // 6) Vendor validation using your endpoint (GET ajax_vendor_lookup.php?id=NUM)
    const form = $('booking_form');
    const input = $('id_vendedor_input');
    const btn   = $('btn-validar');
    const saludo= $('saludo');
    const err   = $('vend-error');

    function lockForm(lock){
      if (!form) return;
      form.setAttribute('aria-disabled', lock ? 'true' : 'false');
    }
    function setFeedbackOK(name){
      if (err) err.textContent = '';
      if (saludo) saludo.textContent = 'Hola ' + (name || 'vendedor') + ' 👋';
    }
    function setFeedbackErr(msg){
      if (saludo) saludo.textContent = '';
      if (err) err.textContent = msg || 'Vendedor no encontrado.';
    }

    async function validateVendor(){
      const id = parseInt(input.value || '0', 10);
      setFeedbackErr('');
      setFeedbackOK('');

      if (!id || id <= 0) { setFeedbackErr('Ingresa un ID válido.'); lockForm(true); return; }

      try {
        const r = await fetch('ajax_vendor_lookup.php?id=' + encodeURIComponent(id), { cache: 'no-store' });
        if (!r.ok) throw new Error('No se pudo validar el vendedor.');
        const data = await r.json();

        if (data && data.ok) {
          setFeedbackOK(data.nombre || 'vendedor');
          // Put the validated ID into the form field that submit.php reads
          const cv = $('codigo_vendedor'); if (cv) cv.value = String(id);
          lockForm(false);
          // refresh totals once enabled so calculator shows current values
          if (typeof updateTotals === 'function') setTimeout(updateTotals, 50);
        } else {
          setFeedbackErr((data && (data.msg || data.error || data.message)) || 'Vendedor no encontrado.');
          const cv = $('codigo_vendedor'); if (cv) cv.value = '';
          lockForm(true);
        }
      } catch (e) {
        setFeedbackErr('Error al validar: ' + (e && e.message ? e.message : e));
        lockForm(true);
      }
    }

    if (btn) btn.addEventListener('click', validateVendor);
  })();

  // Your existing AJAX totals (unchanged)
  function updateTotals() {
    $.getJSON('get_prices_MB.php', $('#booking_form').serialize(), function(data) {
      $('#subtotal_display').text(data.subtotal);
      $('#discount_display').text(data.discount);
      $('#total_price').text(data.total);
      $('#subtotal').val(data.subtotal);
      $('#descuento').val(data.discount);
      $('#total_venta').val(data.total);
      $('#total_price_hidden').val(data.total);
    });
  }
  $('#booking_form input, #booking_form select').on('change keyup', updateTotals);
  $('#apply_coupon_btn').click(updateTotals);
  </script>
</body>
</html>
