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
$page_title       = 'Refunds & Cancellations.';
$page_description = 'Stamps Tour refund and cancellation policy: full refund if cancelled at least 24 hours before your tour, with weather and minimum-traveler protections.';
$page_canonical   = 'https://stampstour.com/refunds-cancellations.php';
$critical_css_file = __DIR__ . '/includes/critical/content.css';
$vendor_css_variant = 'core';
$lcp_preload_image = 'img/Tours/Stgo/big.webp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
  <link rel="stylesheet" href="css/timeline.css" media="print" onload="this.media='all';this.onload=null;">
  <noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
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
      <h1>Refunds & Cancellations.</h1>
    </div>
  </div>
</section>
<!-- End Parallax section -->

<main>
  <div class="container margin_60">
    <div class="row">
      <div class="col-lg-8" id="single_tour_desc">
        <div class="row">
          <div class="col-lg-3"><h3>Overview</h3></div>
          <div class="col-lg-9">
            <p>
              We aim to be transparent and fair. These rules apply to direct bookings made on our website.
              If you booked through a marketplace (e.g., Viator or Tripadvisor), their policy may apply.
            </p>
          </div>
        </div>
        <hr>

        <div class="row">
          <div class="col-lg-3"><h3>Standard Window</h3></div>
          <div class="col-lg-9">
            <ul class="list_ok">
              <li><strong>Full refund</strong> if you cancel <strong>at least 24 hours</strong> before the tour start time.</li>
              <li><strong>No refund</strong> if you cancel <strong>less than 24 hours</strong> before the tour start time.</li>
            </ul>
            <p class="small text-muted">Cut‑off times are based on the local time of the experience (Chile Time).</p>
          </div>
        </div>
        <hr>

        <div class="row">
          <div class="col-lg-3"><h3>Minimum travelers</h3></div>
          <div class="col-lg-9">
            <p>
              Some experiences require a minimum number of travelers. If a tour is cancelled because the minimum isn’t met,
              you can choose a different date/experience or receive a <strong>full refund</strong>.
            </p>
          </div>
        </div>
        <hr>

        <div class="row">
          <div class="col-lg-3"><h3>Weather & safety</h3></div>
          <div class="col-lg-9">
            <p>
              Safety comes first—especially on mountain routes (e.g., <em>Andes Tour</em>). We may adjust routes or schedules,
              or cancel if conditions are unsafe (road closures, extreme weather, etc.). If we cancel for safety, you’ll be
              offered a new date/experience or a <strong>full refund</strong>.
            </p>
            <p class="small text-muted">
              On national holidays, some partners (e.g., wineries) may be closed; we will offer equivalent alternatives when needed.
            </p>
          </div>
        </div>
        <hr>

        <div class="row">
          <div class="col-lg-3"><h3>How to cancel</h3></div>
          <div class="col-lg-9">
            <p>Email us at <a href="mailto:reservations@stamptour.com">reservations@stamptour.com</a> with your name, booking email, and date.
               Refunds are issued to the original payment method.</p>
          </div>
        </div>
      </div>

      <aside class="col-lg-4" id="sidebar">
        <div class="box_style_4">
          <i class="icon_set_1_icon-89"></i>
          <h4>Need <span>help?</span></h4>
          <a href="tel://56923993146" class="phone">+56 9 2399 3146</a>
          <a href="mailto:reservations@stamptour.com" class="email">reservations@stamptour.com</a>
          <p><small>Monday to Sunday 7:30–21:00</small></p>
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
