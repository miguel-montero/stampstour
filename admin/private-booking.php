<?php
// private_booking.php — posts to submit.php using the exact field names it expects
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';

// Friendly labels for experiencias.nombre values that aren't self-explanatory.
$tourLabels = [
    'custom'            => 'Custom (manual price)',
    'Valparaiso'        => 'Valparaíso & Viña del Mar',
    'Maipo'             => 'Maipo Valley Wine Tour',
    'MaipoLunch'        => 'Maipo Valley + Lunch',
    'Andes'             => 'Andes / Portillo & Inca Lagoon',
    'Santiago'          => 'Santiago City Tour',
    'Casablanca'        => 'Casablanca',
    'Colchagua'         => 'Colchagua',
    'CRUISE.SA_STGO'    => 'Cruise Transfer - Santiago to Port',
    'CRUISE.VLP_STGO'   => 'Cruise Transfer - Port to Santiago',
    'DROP_CRUISE.SA'    => 'Cruise Drop-off - Santiago',
    'DROP_CRUISE.VLPO'  => 'Cruise Drop-off - Valparaíso',
];

$tours = [];
if ($conn) {
    $res = $conn->query("SELECT nombre FROM experiencias ORDER BY (nombre = 'custom') DESC, nombre ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $tours[] = $row['nombre'];
        }
    }
}
if (empty($tours)) {
    $tours = ['custom']; // fallback if the DB is unreachable, matches prior hardcoded behavior
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Custom / Private Booking</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    .private-booking-form { max-width: 900px; margin: 24px auto; padding: 0 16px; }
    .row { display:flex; gap:1rem; flex-wrap:wrap; }
    .col { flex:1 1 220px; min-width:220px; }
    label { display:block; margin:.5rem 0 .25rem; }
    input:not([type=checkbox]), select, button { width:100%; padding:.55rem; }
    fieldset { border:1px solid #ddd; border-radius:10px; padding:12px; margin:12px 0; }
    .hint { color:#666; font-size:.9rem; }
    .req::after { content:" *"; color:#c00; }
  </style>
</head>
<body>
<?php require __DIR__ . '/_nav.php'; stamp_admin_nav('private-booking'); ?>
<div class="container private-booking-form">
  <h2>Custom / Private Booking</h2>
  <p class="hint">This form reuses <code>submit.php</code> to write the booking and redirect to <code>shopping.php</code>.</p>

  <form action="/submit.php" method="POST" id="customForm" novalidate>
    <fieldset>
      <legend>Tour</legend>
      <label class="req">Tour name</label>
      <select name="activity_name" id="activity_name" class="form-select" required>
        <?php foreach ($tours as $t): ?>
          <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"<?= $t === 'custom' ? ' selected' : '' ?>>
            <?= htmlspecialchars($tourLabels[$t] ?? $t, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="hint">Pick a real tour to link this booking to it, or leave "Custom" for a one-off manually priced booking.</div>
    </fieldset>

    <!-- Optional vendor: same mirror name used in preferential.php -->
    <input type="hidden" name="codigo_vendedor" id="codigo_vendedor" value="">

    <!-- Totals expected by submit.php -->
    <input type="hidden" name="subtotal" id="subtotal" value="0">
    <input type="hidden" name="total_price" id="total_price" value="0">

    <!-- DATE (submit.php expects MM-DD-YYYY in date_booking) -->
    <input type="hidden" name="date_booking" id="date_booking" value="">

    <fieldset>
      <legend>Private product</legend>
      <div class="row">
        <div class="col">
          <label class="req">Custom activity label (internal/receipt)</label>
          <input type="text" id="custom_label" placeholder="e.g., Private Colchagua + Lunch" required>
        </div>
        <div class="col">
          <label class="req">Total price (USD)</label>
          <input type="number" id="price" step="0.01" min="0" required>
        </div>
        <div class="col">
          <label class="req">Date (YYYY-MM-DD)</label>
          <input type="date" id="date_iso" required>
          <div class="hint">Will be converted to MM-DD-YYYY for submit.php</div>
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Lead traveler</legend>
      <div class="row">
        <div class="col">
          <label class="req">First name</label>
          <input type="text" name="name_booking" required>
        </div>
        <div class="col">
          <label class="req">Last name</label>
          <input type="text" name="last_name_booking" required>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <label class="req">Email</label>
          <input type="email" name="email_booking" required>
        </div>
        <div class="col">
          <label class="req">Phone</label>
          <input type="tel" name="phone_booking" required>
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Headcount</legend>
      <div class="row">
        <div class="col">
          <label class="req">Adults</label>
          <input type="number" name="adults" id="adults" min="1" step="1" value="1" required>
        </div>
        <div class="col">
          <label>Children</label>
          <input type="number" name="children" id="children" min="0" step="1" value="0">
        </div>
        <div class="col">
          <label>Infants</label>
          <input type="number" name="infants" id="infants" min="0" step="1" value="0">
        </div>
      </div>
    </fieldset>

    <div class="row">
      <div class="col">
        <label>Vendor code (optional)</label>
        <input type="text" id="vendor_code" placeholder="e.g., 11">
      </div>
      <div class="col">
        <label>Notes (optional)</label>
        <input type="text" id="notes" placeholder="Internal notes (not sent anywhere here)">
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Create booking &amp; continue</button>
    <p class="hint">On submit, <code>submit.php</code> will insert titular + reserva, generate a <code>reference_id</code>, and redirect to <code>shopping.php</code>.</p>
  </form>
</div>

  <script>
  (function(){
    const f = document.getElementById('customForm');
    function toMMDDYYYY(iso) {
      // iso: YYYY-MM-DD -> MM-DD-YYYY
      if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return '';
      const [y,m,d] = iso.split('-');
      return m + '-' + d + '-' + y;
    }
    f.addEventListener('submit', function(e){
      // minimal guards
      const price = parseFloat(document.getElementById('price').value || '0');
      const adults = parseInt(document.getElementById('adults').value || '0', 10);
      const children = parseInt(document.getElementById('children').value || '0', 10);
      const infants = parseInt(document.getElementById('infants').value || '0', 10);
      const totalPax = adults + children + infants;
      if (price <= 0) { e.preventDefault(); alert('Total price must be > 0'); return; }
      if (totalPax < 1) { e.preventDefault(); alert('At least 1 traveler is required'); return; }

      // mirror totals into the names submit.php expects
      document.getElementById('subtotal').value = price.toFixed(2);
      document.getElementById('total_price').value = price.toFixed(2);

      // convert date to MM-DD-YYYY for submit.php
      const iso = document.getElementById('date_iso').value;
      const mmddyyyy = toMMDDYYYY(iso);
      if (!mmddyyyy) { e.preventDefault(); alert('Please choose a valid date'); return; }
      document.getElementById('date_booking').value = mmddyyyy;

      // vendor code → hidden codigo_vendedor (submit.php override logic)
      document.getElementById('codigo_vendedor').value =
        (document.getElementById('vendor_code').value || '').trim();

      // custom label: we’re forcing activity_name = "custom" for experiencia resolution on the server.
      // If you want to persist this label too, store it in a concierge table from a webhook or later step.
    }, false);
  })();
  </script>
</body>
</html>
