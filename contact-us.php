<?php
$page_title       = 'Contact Us | Stamps Tour';
$page_description = 'Get in touch with Stamps Tour - call, WhatsApp, or email us for questions about bookings, tours, or support.';
$page_canonical   = 'https://stampstour.com/contact-us.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
  <link href="css/timeline.css" rel="stylesheet"/>

  <!-- Bootstrap Icons for WhatsApp icon -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
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
        <h1>Contact us</h1>
      </div>
    </div>
  </section>
  <!-- End Parallax section -->
<br>
  <main>
    <!-- Middle support box (only) -->
    <section class="container margin_60_35">
      <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
          <div class="box_style_4 text-center">
            <i class="icon_set_1_icon-89"></i>
            <h4>Have <span>questions?</span></h4>
            <a href="tel:+56923993146" class="phone">+56 9 2399 3146</a>
            <div style="margin-top:8px">
              <a href="https://api.whatsapp.com/send?phone=56923993146" aria-label="Chat on WhatsApp">
                <i class="bi bi-whatsapp" style="font-size:1.4rem; vertical-align:middle;"></i>
              </a>
            </div>
            <p><small>Monday to Sunday 7.30am - 9.00pm</small></p>
          </div>
        </div>
      </div>
    </section>
    <!-- End middle support box -->
  </main>
  <!-- End main -->

  <footer>
    <?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
  </footer>

  <?php include __DIR__ . '/includes/content-scripts.php'; ?>
</body>
</html>
