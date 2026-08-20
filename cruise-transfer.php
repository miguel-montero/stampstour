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

require __DIR__ . '/includes/tour_faq.php';
$tour_faqs = [
    ['q' => 'Can I combine my cruise transfer with sightseeing?', 'a' => 'Yes - every transfer between Santiago and the port includes a stop in Valparaíso and a wine tasting in the Casablanca Valley, so you see the region instead of just sitting in traffic.'],
    ['q' => 'What pickup and drop-off points are available?', 'a' => 'Pickup from the San Antonio or Valparaíso cruise terminals, a Santiago hotel, or Santiago Airport (SCL); drop-off works the same way in reverse, with typical arrival around 2:00-2:30 PM at the passenger terminal or 5:00 PM in Santiago.'],
    ['q' => 'Will my luggage fit?', 'a' => 'Yes - luggage travels in the same van as passengers, and a standard suitcase plus a carry-on per person fits comfortably.'],
    ['q' => 'Is lunch included?', 'a' => 'No - meals and personal expenses are not included, only the wine tasting stop in Casablanca.'],
    ['q' => "What's the cancellation policy?", 'a' => "Full refund for cancellations made at least 24 hours before the transfer's start time; no-shows are non-refundable."],
    ['q' => 'Do we stop at the historic funicular in Valparaíso?', 'a' => "Yes - the transfer includes a ride on one of Valparaíso's historic funiculars as part of the included sightseeing stop."],
    ['q' => "What happens if the funicular isn't running?", 'a' => 'Funiculars occasionally pause for maintenance or weather; if that happens on your transfer day, your guide will adjust the stop accordingly.'],
    ['q' => 'Is this a private or shared transfer?', 'a' => "It's a small-group service, not a large bus tour. If you're traveling with a child, let us know in advance so we can arrange a child seat."],
    ['q' => 'Can I book this as a shore excursion from my cruise ship?', 'a' => "This is a one-way transfer, not a same-day round trip back to your ship. If your cruise is ending, we pick you up at the San Antonio or Valparaíso terminal on disembarkation day, add guided sightseeing and wine tasting, and drop you in Santiago. If your cruise is starting, it runs the other way: pickup in Santiago, sightseeing and wine tasting, then drop-off at the port in time to board."],
    ['q' => "What's the difference between a pre-cruise and post-cruise transfer?", 'a' => 'A pre-cruise transfer picks you up in Santiago (hotel or airport) and drops you at your ship in San Antonio or Valparaíso before boarding. A post-cruise transfer picks you up at the cruise terminal on disembarkation day and takes you into Santiago. Either way, the same guided sightseeing and Casablanca wine tasting are included en route.'],
    ['q' => 'Can I book this directly instead of through Viator, Expedia, or Travelocity?', 'a' => 'Yes - this is the same shore excursion listed on Viator, Expedia, and Travelocity. Booking directly on this page means working straight with the local team that runs it, with no reseller in between.'],
];
?>
<?php
$page_title       = 'San Antonio & Valparaíso Shore Excursion | Santiago Cruise Transfer';
$page_description = 'Pre-cruise or post-cruise shore excursion from San Antonio or Valparaíso, with Santiago transfer, guided sightseeing, and Casablanca Valley wine tasting on disembarkation day or before boarding.';
$page_canonical   = 'https://stampstour.com/cruise-transfer.php';
$page_og = [
  'title'       => 'San Antonio & Valparaíso Shore Excursion | Santiago Cruise Transfer | Stamp\'s Tour',
  'description' => $page_description,
  'url'         => $page_canonical,
  'image'       => 'https://stampstour.com/img/Tours/Cruise/big.jpg',
];
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
$lcp_preload_image = 'img/Tours/Cruise/big.jpg';
$lcp_preload_image_mobile = 'img/Tours/Cruise/big-mobile.webp';
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
<section class="tour-banner">
<picture>
<source media="(max-width: 767px)" srcset="img/Tours/Cruise/big-mobile.webp">
<img src="img/Tours/Cruise/big.jpg" width="2000" height="1269" fetchpriority="high" alt="Cruise transfer banner" class="tour-banner-bg">
</picture>
  <div class="parallax-content-2">
    <div class="container">
      <div class="row">
        <div class="col-md-8">
          <!-- save badge -->
        <div class="badge_save">Save<strong>20%</strong></div>
          <h1>San Antonio & Valparaíso Shore Excursion with Santiago Cruise Transfer</h1>
        </div>
        <div class="col-md-4">
          <!-- Price display (per person) -->
          <div id="price_single_main">
            Special Offer 
            <span><sup>$</sup><span id="dynamic_price">99</span></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- End Parallax section -->

<main>
  <div class="container margin_60">
    <div class="row">
      <!-- Left column: Gallery + Description + Itinerary -->
      <div class="col-lg-8 order-2 order-lg-1" id="single_tour_desc">
        <div id="single_tour_feat">
          <ul>
            <li><i class="icon_set_1_icon-83"></i> 7 Hours</li>
            <li><i class="icon_set_1_icon-26"></i> Air Conditioned Bus</li>
            <li><i class="icon_set_1_icon-15"></i> Wine tasting</li>
            <li><i class="icon_set_1_icon-28"></i> Historical Funicular</li>
            <li><i class="icon_set_1_icon-29"></i> Professional tour guide</li>
          </ul>
        </div>

        <!-- Image Gallery Carousel -->
        <div id="Img_carousel" class="slider-pro magnific-gallery">
          <div class="sp-slides">
            <div class="sp-slide">
              <a href="img/Tours/Cruise/cover.jpg" data-effect="mfp-zoom-in">
                <img class="sp-image" src="css/images/blank.gif" alt="Tour cover"
                     data-src="img/Tours/Cruise/cover.webp"
                     data-small="img/Tours/Cruise/cover.webp"
                     data-medium="img/Tours/Cruise/cover.webp"
                     data-large="img/Tours/Cruise/cover.webp"
                     data-retina="img/Tours/Cruise/cover.webp">
                <i class="icon-resize-full-2"></i>
              </a>
            </div>
            <?php for ($i = 0; $i <= 20; $i++):
              $imagePath = "img/Tours/Cruise/{$i}_medium.jpeg";
              $imagePathWebp = "img/Tours/Cruise/{$i}_medium.webp"; ?>
              <div class="sp-slide">
                <a href="<?= $imagePath ?>" data-effect="mfp-zoom-in">
                  <img class="sp-image" src="css/images/blank.gif" alt="Slide <?= $i ?>"
                       data-src="<?= $imagePathWebp ?>" data-small="<?= $imagePathWebp ?>"
                       data-medium="<?= $imagePathWebp ?>" data-large="<?= $imagePathWebp ?>"
                       data-retina="<?= $imagePathWebp ?>">
                  <i class="icon-resize-full-2"></i>
                </a>
              </div>
            <?php endfor; ?>
          </div>
          <div class="sp-thumbnails">
            <img class="sp-thumbnail" src="img/Tours/Cruise/cover_thumb.webp" alt="Cover thumbnail" loading="lazy">
            <?php for ($i = 0; $i <= 20; $i++): ?>
              <img class="sp-thumbnail" src="img/Tours/Cruise/<?= $i ?>_thumb.webp" alt="Thumbnail <?= $i ?>" loading="lazy">
            <?php endfor; ?>
          </div>
        </div>
        <!-- End Image Gallery -->

        <hr/>

        <!-- Description Section -->
        <div class="row">
          <div class="col-lg-3">
            <h3>Description</h3>
          </div>
          <div class="col-lg-9">
            <h4>San Antonio & Valparaíso Shore Excursion: Pre-Cruise and Post-Cruise Transfer with Wine Tasting</h4>
            <p>Whether it's disembarkation day and you're stepping off your ship in San Antonio or Valparaíso for good, or you need a pre-cruise transfer from Santiago to reach your ship before boarding, turn this one-way trip into a seamless, value-packed experience by pairing it with curated stops in Chile’s most iconic wine region and port cities. Instead of spending your limited time in port stuck in traffic or juggling separate bookings, a single small-group service handles luggage, timing, and door-to-dock logistics while adding guided sightseeing and wine tasting en route—so you arrive at your next stop having already sampled the local culture, scenery, and flavors.</p>
          </div>
        </div>

        <hr/>

        <!-- ***** Mobile slot for the booking form (visible only < lg) ***** -->
        <div id="mobileFormSlot" class="d-block d-lg-none mb-4"></div>

        <div class="main_title">
          <h2>Detailed <span>timeline</span> for your tour</h2>
          <p>select pick up and drop-off to see</p>
        </div>

        <!-- Dynamic Itinerary Timeline -->
        <div id="timelineContainer" class="mb-5"></div>

        <!-- ======= AFTER TIMELINE ======= -->
        <section id="tour_extras" class="add_bottom_45">
          <div class="container">
            <!-- What's included -->
            <div class="row">
              <div class="col-lg-3">
                <h3>What’s included</h3>
              </div>
              <div class="col-lg-9">
                <h4>What's included</h4>
                <div class="row">
                  <div class="col-md-6">
                    <ul class="list_ok">
                      <li>Small-group transport (air-conditioned van)</li>
                      <li>Professional, bilingual tour guide</li>
                      <li>Valparaíso walking highlights</li>
                      <li>Wine tasting in Casablanca (per itinerary)</li>
                      <li>Pickup & luggage handling</li>
                    </ul>
                  </div>
                  <div class="col-md-6">
                    <h4>Not included</h4>
                    <ul class="list_cancel">
                      <li>Meals and personal expenses</li>
                      <li>Gratuities (optional)</li>
                      <li>Additional tastings not mentioned</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <hr/>

            <!-- Departure and return -->
            <div class="row">
              <div class="col-lg-3">
                <h3>Departure and return</h3>
              </div>
              <div class="col-lg-9">
                <h4>Start:</h4>
                <p>Multiple pickup locations offered.</p>

                <h6><i class="icon_set_1_icon-87"></i> Pickup details</h6>
                <p>Pickups from Santiago hotels/Airbnbs or cruise terminal (depending on route). Exact time is confirmed the evening before.</p>

                <h6><i class="icon_set_2_icon-104"></i> Hotel pickup offered</h6>
                <p>Available in common tourist areas (Las Condes, Vitacura, Providencia, Santiago Centro, Airport area). If you’re outside these areas, we’ll arrange a nearby meeting point.</p>

                <h4>End:</h4>
                <p>Drop-off at Valparaíso Passenger Terminal (VTP), San Antonio Passenger Terminal (DP WORLD) Santiago Airport (SCL), or your hotel per selected route. Typical arrival: ~2:00–2:30 PM (to Passenger Terminal) or ~5:00 PM (to Santiago), subject to traffic/port ops.</p>
              </div>
            </div>

            <hr/>

            <!-- Additional information -->
            <div class="row">
              <div class="col-lg-3">
                <h3>Additional information</h3>
              </div>
              <div class="col-lg-9">
                <ul class="general_info list_unstyled">
                  <li><i class="icon-suitcase"></i> Luggage travels in the same van; standard suitcase + carry-on per person fits.</li>
                  <li><i class="icon-up-open"></i> Funicular rides may pause for maintenance or weather.</li>
                  <li><i class="icon-users"></i> Small-group service; child seats on request (notify in advance).</li>
                  <li><i class="icon-megaphone"></i> Route order can shift due to traffic, port schedules, or winery slots.</li>
                </ul>
              </div>
            </div>

            <hr/>

            <!-- Cancellation policy -->
            <div class="row">
              <div class="col-lg-3">
                <h3>Cancellation policy</h3>
              </div>
              <div class="col-lg-9">
                <p class="mb-2"><strong>Flexible:</strong> Full refund for cancellations up to 24 hours before the experience start time.</p>
                <ul class="list_unstyled small">
                  <li>Changes within 24 hours may not be accepted.</li>
                  <li>No-show is non-refundable.</li>
                  <li>If weather/operations force a cancellation, you can reschedule or receive a full refund.</li>
                </ul>
              </div>
            </div>

            <?php render_tour_faq($tour_faqs); ?>
          </div>
        </section>

      </div>
      <!-- End left column -->

      <!-- Right column: Sticky Booking Sidebar -->
      <aside class="col-lg-4 order-1 order-lg-2 d-none d-lg-block" id="sidebar">
        <div class="box_style_1 expose" id="bookingBox">
          <h3 class="inner" id="bookingTitle">- Check Availability -</h3>
          <form id="bookingForm" method="POST" action="submit.php" autocomplete="off">
            <!-- STEP 1 – Pick-up -->
            <div class="mb-3 form-group">
              <label for="pickup" class="form-label">Pick-up location</label>
              <div class="styled-select">
                <select id="pickup" name="pickup" class="form-control" required>
                  <option value="">Select…</option>
                  <option value="SA">San Antonio (Cruise Port)</option>
                  <option value="VLP">Valparaíso (Cruise Port)</option>
                  <option value="STGO_HOTEL">Hotel in Santiago</option>
                  <option value="STGO_AIRPORT">Santiago Airport (SCL)</option>
                </select>
              </div>
            </div>

            <!-- Hidden: keep for JS compatibility (pickup hotel) -->
            <div id="pickupHotelGroup" class="collapse d-none">
              <label for="hotelPickup" class="form-label d-none">Choose your hotel</label>
              <input id="hotelPickup" name="hotelPickup" class="form-control d-none" value="">
              <div id="customHotelPickupWrapper" class="d-none">
                <input id="customHotelPickup" name="customHotelPickup" class="form-control" value="">
              </div>
              <div class="d-none">
                <input type="radio" name="hotelPickupOption" id="notListedPickup" value="not_listed">
                <input type="radio" name="hotelPickupOption" id="decideLaterPickup" value="decide_later" checked>
              </div>
            </div>

            <!-- STEP 2 – Drop-off -->
            <div id="dropoff-wrapper" class="mb-3 form-group collapse">
              <label for="dropoff" class="form-label">Drop-off location</label>
              <div class="styled-select">
                <select id="dropoff" name="dropoff" class="form-control" disabled required></select>
              </div>
            </div>

            <!-- Hidden: keep for JS compatibility (dropoff hotel) -->
            <div id="dropoffHotelGroup" class="collapse d-none">
              <label for="hotelDropoff" class="form-label d-none">Choose your hotel</label>
              <input id="hotelDropoff" name="hotelDropoff" class="form-control d-none" value="">
              <div id="customHotelDropoffWrapper" class="d-none">
                <input id="customHotelDropoff" name="customHotelDropoff" class="form-control" value="">
              </div>
              <div class="d-none">
                <input type="radio" name="hotelDropoffOption" id="notListedDropoff" value="not_listed">
                <input type="radio" name="hotelDropoffOption" id="decideLaterDropoff" value="decide_later" checked>
              </div>
            </div>

            <!-- STEP 3 – Date -->
            <div id="date-wrapper" class="mb-3 form-group collapse">
              <label for="transfer_date" class="form-label">Date</label>
              <input type="date" id="transfer_date" name="transfer_date" class="form-control" disabled required>
            </div>

            <!-- STEP 4 – Passengers -->
            <div id="passengers-wrapper" class="collapse">
              <div class="row g-3">
                <div class="col-md-4 form-group">
                  <label for="adults" class="form-label">Adults (12+)</label>
                  <input type="number" id="adults" name="adults" class="form-control" min="1" value="1" disabled required>
                </div>
                <div class="col-md-4 form-group">
                  <label for="children" class="form-label">Children (3-11)</label>
                  <input type="number" id="children" name="children" class="form-control" min="0" value="0" disabled>
                </div>
                <div class="col-md-4 form-group">
                  <label for="infants" class="form-label">Infants (0-2)</label>
                  <input type="number" id="infants" name="infants" class="form-control" min="0" value="0" disabled>
                </div>
              </div>
            </div>

            <!-- STEP 5 – Contact info -->
            <div id="contact-wrapper" class="collapse">
              <p class="text-end mb-2">
                <a href="#" id="contact-back" class="small">« edit previous details</a>
              </p>
              <div class="mb-3 form-group">
                <label for="name_booking" class="form-label">First name</label>
                <input id="name_booking" name="name_booking" class="form-control" required>
              </div>
              <div class="mb-3 form-group">
                <label for="last_name_booking" class="form-label">Last name</label>
                <input id="last_name_booking" name="last_name_booking" class="form-control">
              </div>
              <div class="mb-3 form-group">
                <label for="email_booking" class="form-label">E-mail</label>
                <input type="email" id="email_booking" name="email_booking" class="form-control" required>
              </div>
              <div class="mb-4 form-group">
                <label for="phone_booking" class="form-label">Telephone</label>
                <input type="tel" id="phone_booking" name="phone_booking" class="form-control">
              </div>
              <button type="submit" class="btn_1 green w-100">Confirm Booking</button>
            </div>

            <!-- Total price & Check availability button -->
            <p class="fw-bold mt-3">Total US$ <span id="total_price">0.00</span></p>
            <button id="btnCheck" type="button" class="btn_1 w-100" disabled>Proceed</button>

            <!-- Hidden fields populated by transfer.js -->
            <input type="hidden" name="activity_name">
            <input type="hidden" name="date_booking">
            <input type="hidden" name="airport_pick_up">
            <input type="hidden" name="subtotal">
            <input type="hidden" name="total_price">
            <input type="hidden" name="coupon_code">
          </form>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->

<!-- Scripts (jQuery, Bootstrap, plugins) -->
<!-- All deferred: download in parallel instead of one at a time, execute
     in this exact document order once HTML parsing finishes. See
     docs/superpowers/specs/2026-08-08-tour-gallery-defer-scripts-design.md -->
<script defer src="js/jquery-3.7.1.min.js"></script>
<script defer src="js/vendor/jquery-ui-autocomplete.js"></script>
<script defer src="js/bootstrap.bundle.min.js"></script>
<script defer src="js/jquery.sliderPro.min.js"></script>
<script defer src="js/theia-sticky-sidebar.js"></script>
<script defer src="js/common_scripts_min.js"></script>
<script defer src="js/functions.js"></script>

<!-- Initialize gallery slider + sticky sidebar. Wrapped in DOMContentLoaded
     because inline scripts (no src) can't themselves be deferred - they'd
     otherwise run before the deferred jQuery/plugin scripts above have
     executed. DOMContentLoaded only fires after every deferred script has
     finished, so jQuery/$/the plugins are guaranteed ready here. The
     .css('visibility','visible') reveal is the CSS safety-net pairing -
     see css/vendors-tour.css's #Img_carousel visibility:hidden rule. -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  $('#Img_carousel').sliderPro({
    width: 960,
    height: 500,
    fade: true,
    arrows: true,
    buttons: false,
    fullScreen: false,
    smallSize: 500,
    startSlide: 0,
    mediumSize: 1000,
    largeSize: 3000,
    thumbnailArrows: true,
    autoplay: false
  }).css('visibility', 'visible');

  jQuery('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
});
</script>

<!-- Inject prices data into JS -->
<script>
  const prices = <?= json_encode($prices, JSON_NUMERIC_CHECK) ?>;
</script>

<!-- Page-specific booking and itinerary logic -->
<script defer src="js/transfer.js"></script>

<!-- Move the single booking box between sidebar (desktop) and mobile slot (below Description) -->
<script>
  (function () {
    var sidebar    = document.getElementById('sidebar');
    var bookingBox = document.getElementById('bookingBox');
    var mobileSlot = document.getElementById('mobileFormSlot');
    if (!sidebar || !bookingBox || !mobileSlot) return;

    function placeBooking() {
      if (window.innerWidth >= 992) {
        if (!sidebar.contains(bookingBox)) sidebar.appendChild(bookingBox);
      } else {
        if (!mobileSlot.contains(bookingBox)) mobileSlot.appendChild(bookingBox);
      }
    }
    window.addEventListener('load', placeBooking);
    window.addEventListener('resize', placeBooking);
    placeBooking();
  })();
</script>

</body>
</html>
