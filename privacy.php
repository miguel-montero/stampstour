<?php 
require __DIR__ . '/../db_config.php';   // Database connection

/* Fetch prices – copied from test.php */
$expNames = ['CRUISE.SA_STGO','CRUISE.VLP_STGO','DROP_CRUISE.SA','DROP_CRUISE.VLPO'];
$placeholders = implode(',', array_fill(0, count($expNames), '?'));
$stmt = $conn->prepare(
  "SELECT nombre, precio_adulto, precio_nino, precio_infante 
   FROM experiencias 
   WHERE nombre IN ($placeholders)"
);
$stmt->bind_param(str_repeat('s', count($expNames)), ...$expNames);
$stmt->execute();
$res    = $stmt->get_result();
$prices = [];
while ($row = $res->fetch_assoc()) {
  $prices[$row['nombre']] = [
    'adult'  => $row['precio_adulto'],
    'child'  => $row['precio_nino'],
    'infant' => $row['precio_infante']
  ];
}
$stmt->close();
?>
<?php
$page_title       = 'Privacy Policy';
$page_description = 'Privacy Policy.';
$page_canonical   = 'https://stampstour.com/privacy.php';
$critical_css_file = __DIR__ . '/includes/critical/content.css';
$vendor_css_variant = 'core';
$lcp_preload_image = 'img/Tours/Stgo/big.webp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
  <link href="css/timeline.css" rel="stylesheet"/>
</head>
<body>
  <!-- Header================================================== -->
  <?php include __DIR__ . '/includes/header.php'; ?>
  <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
  <!-- End Header -->

<!-- Parallax Hero Section -->
<section id="hero_2">
  <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
  <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
    <div class="intro_title">
      <h1>Privacy Policy</h1>
    </div>
  </div>
</section>
<!-- End Parallax section -->

<main>
  <div class="container margin_60">
    <div class="row">
      <div class="col-lg-8">
        <div class="main_title">
          <h2>Our <span>Privacy</span> practices</h2>
          <p>Last updated: <?= date('F j, Y'); ?></p>
        </div>

        <h4>1. Information we collect</h4>
        <p>
          We collect the information you provide when booking (name, email, phone, travel date, party size),
          optional preferences (e.g., airport pickup), and payment confirmations. We also collect limited technical
          data (IP, user agent) for security and troubleshooting.
        </p>

        <h4>2. How we use information</h4>
        <ul class="list_ok">
          <li>To process and manage your booking and provide customer support</li>
          <li>To send transactional emails (confirmations, updates, invoices)</li>
          <li>To improve our tours, site performance, and customer experience</li>
          <li>To comply with legal, tax, and accounting obligations</li>
        </ul>

        <h4>3. Cookies & Analytics</h4>
        <p>
          We use cookies for two purposes: measuring site traffic (Google Analytics) and powering our
          live-chat widget (OpenWidget). Neither is required to browse the site or complete a booking.
        </p>
        <ul class="list_ok">
          <li><strong>Google Analytics</strong> &mdash; helps us understand which pages and tours visitors find useful. We use Google's Consent Mode, which keeps analytics cookies switched off until you accept them.</li>
          <li><strong>OpenWidget live chat</strong> &mdash; powers the chat bubble on our homepage and sets a cookie to remember your conversation. It also stays off until you accept.</li>
        </ul>
        <p>
          When you first visit stampstour.com, a banner lets you <strong>Accept</strong> or <strong>Reject</strong> these
          cookies. If you reject them, Google may still receive anonymous, aggregated traffic estimates that are not tied
          to you individually, but no cookies are set on your device. You can change your choice at any time using the
          "Manage cookies" link in the footer of any page.
        </p>
        <p>
          Card payments on our checkout page are processed directly by PayPal or Getnet and are not affected by this
          banner &mdash; those are necessary to complete a purchase you've actively started.
        </p>

        <h4>4. Payments</h4>
        <p>
          Card payments are processed securely by our payment partners (PayPal and Getnet). We do not store full card numbers.
          For PayPal, you can review how PayPal handles your data in their <a href="https://www.paypal.com/webapps/mpp/ua/privacy-full" target="_blank" rel="noopener">Privacy Statement</a>.
        </p>

        <h4>5. Sharing</h4>
        <p>
          We share your data only as needed to deliver the service (e.g., with payment processors, guides/operators for your tour).
          We do not sell personal data.
        </p>

        <h4>6. Data retention</h4>
        <p>
          Booking and invoice data is retained as required by law. Other information is kept only as long as needed for the purposes above.
        </p>

        <h4>7. Your rights</h4>
        <p>
          You may request access, correction, or deletion of your data when legally permissible.
          Contact us at <a href="mailto:reservations@stampstour.com">reservations@stampstour.com</a>.
        </p>

        <h4>8. Contact</h4>
        <p>
          Questions? Email <a href="mailto:reservations@stampstour.com">reservations@stampstour.com</a> or call <a href="tel:+56923993146">+56 9 2399 3146</a>.
        </p>
      </div>

      <aside class="col-lg-4">
        <div class="box_style_4">
          <i class="icon_set_1_icon-89"></i>
          <h4>Need <span>help?</span></h4>
          <a href="tel:+56923993146" class="phone">+56 9 2399 3146</a>
          <a href="mailto:reservations@stampstour.com" class="email">reservations@stampstour.com</a>
          <p><small>Mon–Sun 7:30–21:00 (local time)</small></p>
        </div>
      </aside>
    </div>
  </div>
</main>

<footer>
  <?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
</footer>

<?php include __DIR__ . '/includes/content-scripts.php'; ?>
</body>
</html>
