<?php
$page_title       = 'Maipo Valley Wine Tour from Santiago: 4 Wineries, Classic or Premium';
$page_description = 'Small-group Maipo Valley wine tour from Santiago: 4 wineries with tastings, hotel pickup and guide. Choose Classic, Classic with lunch included, or Premium with Undurraga.';
$page_canonical   = 'https://stampstour.com/maipo-valley-wine-tour-santiago';
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
$lcp_preload_image = 'img/Tours/Maipo/big-optimized.webp';
$vendor_css_variant = 'tour';
$exp_name = 'Maipo'; // availability (cupos) is shared by all three options - always the Maipo row
require __DIR__ . '/../db_config.php';
require __DIR__ . '/includes/tour_price.php';
require __DIR__ . '/includes/tour_faq.php';
require __DIR__ . '/includes/tours/maipo_options.php';
require __DIR__ . '/includes/tours/option_ui.php';

$maipo_options  = maipo_options();
$maipo_selected = maipo_resolve_option($_GET['option'] ?? null);
$maipo_db_prices = fetch_tour_prices($conn, array_column($maipo_options, 'exp_name'));

// slug => {exp, adult, child, infant}; options without a DB row are left out
// (the selector shows them disabled with "Contact us")
$maipo_js_options = [];
foreach ($maipo_options as $slug => $opt) {
    if (!isset($maipo_db_prices[$opt['exp_name']])) {
        continue;
    }
    $maipo_js_options[$slug] = ['exp' => $opt['exp_name']] + $maipo_db_prices[$opt['exp_name']];
}
// If the requested option has no DB price (row missing) but others do, open on the first priced option
if (!isset($maipo_js_options[$maipo_selected]) && $maipo_js_options) {
    $maipo_selected = array_key_first($maipo_js_options);
}
$maipo_adult_prices  = array_column($maipo_js_options, 'adult');
$dynamic_price_adult = $maipo_adult_prices ? min($maipo_adult_prices) : null;

$tour_faqs = [
    ['q' => 'How many wineries do we visit on the Maipo Valley wine tour?', 'a' => "Four stops in the Maipo Valley on every option: Campo La Quirinca (wine and pisco tasting), Viña Santa Ema (wine tasting), Viña TerraMater (winery and olive oil) and a final winery that depends on the option you book - boutique Viña Ballek on the Classic routes, or historic Viña Undurraga on the Premium route."],
    ['q' => "What's the difference between Classic, Classic + Lunch and Premium?", 'a' => "The first three stops are the same. Classic ends at Viña Ballek, a relaxed boutique winery by the Maipo River, with lunch at your own cost. Classic + Lunch is the same itinerary with lunch at a winery restaurant included in the price. Premium replaces Ballek with an extended guided tour of Viña Undurraga - gardens, production area, barrel cellar and a tasting of four premium wines - with lunch at your own cost."],
    ['q' => 'Can the wineries change?', 'a' => 'Yes. If a winery is unavailable on your date, it may be replaced with another winery of equal quality.'],
    ['q' => 'Do we visit both Viña Ballek and Viña Undurraga?', 'a' => 'No. They are alternative final stops: the Classic routes end at Ballek and the Premium route ends at Undurraga.'],
    ['q' => 'Is lunch included?', 'a' => 'Only on the Classic Route + Lunch option. On Classic and Premium you stop for lunch at a winery restaurant and pay there, or you can bring your own food.'],
    ['q' => 'Where does the tour pick me up?', 'a' => 'Hotel pickup is offered from Las Condes, Vitacura, Providencia, Santiago Centro, and Recoleta; your exact pickup time is sent the night before.'],
    ['q' => 'How many people are on the tour?', 'a' => "Groups are capped at 15 travelers, with a minimum of 4 required to run - if the minimum isn't met, you'll be offered another date or a full refund."],
    ['q' => "What's the cancellation policy?", 'a' => "Free cancellation up to 24 hours before the tour's start time; cancellations made inside that window aren't refunded."],
    ['q' => 'Is the Maipo Valley wine tour worth it?', 'a' => "If you want a taste of Chilean wine country without renting a car or planning your own route between wineries, yes - in one day you visit four different Maipo Valley wineries with guided tastings and hotel pickup included."],
    ['q' => 'Can I book this tour privately?', 'a' => 'A private version of this tour is available - pricing depends on your group size, so please contact us directly to inquire.'],
];
?>
<!DOCTYPE html>
<html lang="en">
 <head>
<?php include __DIR__ . '/includes/head.php'; ?>
  <link rel="stylesheet" href="css/timeline.css" media="print" onload="this.media='all';this.onload=null;">
  <noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="css/tour-options.css?v=20261013">
 </head>
 <body>
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
  <?php include __DIR__ . '/includes/header.php'; ?>
  <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
  <?php
  require __DIR__ . '/includes/tour_schema.php';
  $schema_offers = [];
  foreach ($maipo_js_options as $slug => $o) {
      $schema_offers[] = ['name' => $maipo_options[$slug]['label'], 'price' => $o['adult'], 'url' => $page_canonical . '?option=' . $slug];
  }
  render_tour_product_schema([
      'name' => 'Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries',
      'description' => $page_description,
      'image' => $lcp_preload_image,
      'url' => $page_canonical,
      'price' => $dynamic_price_adult,
      'offers' => $schema_offers,
      'sameAs' => ['https://www.tripadvisor.com/AttractionProductReview-g294305-d15040546-Maipo_Valley_Wine_Tour_with_4_vineyards_from_Santiago-Santiago_Santiago_Metropolit.html'],
  ]);
  ?>
  <!-- End Header -->
  <section class="tour-banner">
   <img src="img/Tours/Maipo/big-optimized.webp" width="720" height="480" fetchpriority="high" alt="Maipo Valley banner" class="tour-banner-bg">
   <div class="badge_tripadvisor_circle">
     <picture>
       <source srcset="img/badges/tripadvisor-circle-2026.webp" type="image/webp">
       <img src="img/badges/tripadvisor-circle-2026.png" alt="Tripadvisor Travelers' Choice Best of the Best 2026">
     </picture>
   </div>
   <div class="parallax-content-2">
    <div class="container">
     <div class="row">
      <div class="col-md-8">
          <!-- save badge -->
        <div class="badge_save">Save<strong>10%</strong></div> 
       <h1>Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries</h1>
      </div>
      <div class="col-md-4">
       <div id="price_single_main">
        From
        <span><sup>$</sup><span id="dynamic_price"><?= $dynamic_price_adult !== null ? htmlspecialchars((string)$dynamic_price_adult, ENT_QUOTES, 'UTF-8') : '' ?></span></span>
       </div>
      </div>
     </div>
    </div>
   </div>
  </section>
  <!-- End section -->
  <main>
   <div class="collapse" id="collapseMap">
    <div class="map" id="map"></div>
   </div>
   <!-- End Map -->
   <div class="container margin_60">
    <div class="row">
     <div class="col-lg-8" id="single_tour_desc">
      <div id="single_tour_feat">
       <ul>
        <li><i class="icon_set_1_icon-83"></i> 10 Hours</li>
        <li><i class="icon_set_1_icon-26"></i> Air Conditioned Bus</li>
        <li><i class="icon_set_1_icon-15"></i> Wine tasting</li>
        <li><i class="icon_set_1_icon-60"></i> Pisco Tasting</li>
        <li><i class="icon_set_1_icon-29"></i> Professional tour guide</li>
        <li><i class="icon_set_1_icon-34"></i> Wildlife</li>
       </ul>
      </div>
      <!-- Image Gallery Carousel -->
      <div id="Img_carousel" class="slider-pro magnific-gallery">
       <div class="sp-slides">
        <!-- Cover image slide -->
        <div class="sp-slide">
         <a href="img/Tours/Maipo/portada.jpg" data-effect="mfp-zoom-in">
           <img class="sp-image" src="css/images/blank.gif" alt="Maipo tour cover"
                data-src="img/Tours/Maipo/portada.webp"
                data-small="img/Tours/Maipo/portada.webp"
                data-medium="img/Tours/Maipo/portada.webp"
                data-large="img/Tours/Maipo/portada.webp"
                data-retina="img/Tours/Maipo/portada.webp">
           <i class="icon-resize-full-2"></i>
         </a>
        </div>
        <!-- Slides for each gallery image -->
        <?php for ($i = 1; $i <= 8; $i++): ?>
        <?php $imagePath = "img/Tours/Maipo/{$i}_medium.jpg"; $imagePathWebp = "img/Tours/Maipo/{$i}_medium.webp"; ?>
        <div class="sp-slide">
         <a href="<?php echo $imagePath; ?>" data-effect="mfp-zoom-in">
           <img class="sp-image" src="css/images/blank.gif" alt="Maipo image <?php echo $i; ?>"
                data-src="<?php echo $imagePathWebp; ?>"
                data-small="<?php echo $imagePathWebp; ?>"
                data-medium="<?php echo $imagePathWebp; ?>"
                data-large="<?php echo $imagePathWebp; ?>"
                data-retina="<?php echo $imagePathWebp; ?>">
           <i class="icon-resize-full-2"></i>
         </a>
        </div>
        <?php endfor; ?>
       </div>
       <div class="sp-thumbnails">
        <img class="sp-thumbnail" src="img/Tours/Maipo/portada_thumb.webp" alt="Maipo thumbnail cover" loading="lazy">
        <?php for ($i = 1; $i <= 8; $i++): ?>
         <img class="sp-thumbnail" src="img/Tours/Maipo/<?php echo $i; ?>_thumb.webp" alt="Maipo thumbnail <?php echo $i; ?>" loading="lazy">
        <?php endfor; ?>
       </div>
      </div>
      <!-- End Image Gallery -->
      <hr/>
      <div class="row">
        <h2>Maipo Wine Tour Overview & Highlights</h2>
       <div class="col-lg-3">
        <h3>Description</h3>
       </div>
       <div class="col-lg-9">
        <p>Spend a day in Chilean wine country around Isla de Maipo, a farming town named for the tributaries of the Maipo River that surround it and make for perfect grape-growing conditions. Every option visits four stops, from a small family farm to established wineries, with guided tastings, hotel pickup and an English-speaking guide.</p>
        <h4>Choose your route</h4>
        <p>All three options share the first three stops: Campo La Quirinca, Vi&ntilde;a Santa Ema and Vi&ntilde;a TerraMater. They differ in the final winery and in whether lunch is included.</p>
        <h4>What to expect</h4>
        <p>Your day begins with pickup from your hotel or residence in Santiago Centro, Providencia, Las Condes, Vitacura or Recoleta. If you are staying elsewhere, send us your address and we will confirm pickup or the nearest meeting point.</p>
        <p>The first stop is Campo La Quirinca, a family farm where you learn about traditional Chilean winemaking and taste local wines and Chilean pisco. Next is Vi&ntilde;a Santa Ema for a guided tasting of three wines, then Vi&ntilde;a TerraMater, also known for its award-winning olive oil. Along the way you stop for lunch at a winery restaurant.</p>
        <p>The Classic routes end at Vi&ntilde;a Ballek, a boutique family winery by the Maipo River. The Premium route ends instead at Vi&ntilde;a Undurraga, one of Chile's most traditional wineries, with an extended guided tour and a tasting of four premium wines. The day ends with drop-off at your original pickup point.</p>
        <h4>What's included</h4>
        <?php foreach ($maipo_options as $slug => $opt): ?>
         <?php render_option_block_start($slug, $maipo_selected); ?>
          <div class="row">
           <div class="col-md-6">
            <ul class="list_ok">
             <?php foreach ($opt['included'] as $item): ?>
              <li><?= option_ui_h($item) ?></li>
             <?php endforeach; ?>
            </ul>
           </div>
           <div class="col-md-6">
            <p><strong>Not included</strong></p>
            <ul>
             <?php foreach ($opt['not_included'] as $item): ?>
              <li><?= option_ui_h($item) ?></li>
             <?php endforeach; ?>
            </ul>
           </div>
          </div>
         <?php render_option_block_end(); ?>
        <?php endforeach; ?>
       </div>
      </div>
      <!-- End row -->
      <hr/>
      <hr/>
      <div class="row">
       <div class="col-lg-3">
        <h3>Departure and return</h3>
       </div>
       <div class="col-lg-9">
        <h4>Start:</h4>
        <p>Multiple pickup locations offered.</p>
        <h6><i class="icon_set_1_icon-87"></i> Pickup details</h6>
        <p>Pick ups are available from central locations in the following districts: Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, Airport Area.</p>
        <p>If your hotel is not in the available areas please send us the location and we will evaluate if it is possible to pick up; otherwise we will provide a meeting point at the nearest place from your location.</p>
        <h6><i class="icon_set_2_icon-104"></i> Hotel pickup offered</h6>
        <p>During checkout you will be able to select from the list of included hotels.</p>
        <h4>End:</h4>
        <p>This activity ends back at the meeting point.</p>
       </div>
      </div>
      <hr/>
      <hr/>
      <div class="row">
       <div class="col-lg-3">
        <h3>Additional Information</h3>
       </div>
       <div class="col-lg-9">
        <ul>
         <li>Confirmation will be received at time of booking</li>
         <li>Wheelchair accessible</li>
         <li>Stroller accessible</li>
         <li>Minimum numbers apply. There is a possibility of cancellation after confirmation if there are not enough passengers (4) to meet requirements. In the event of this occurring, you will be offered an alternative or full refund.</li>
         <li>This experience requires good weather. If it’s canceled due to poor weather, you’ll be offered a different date or a full refund.</li>
         <li>This tour/activity will have a maximum of 15 travelers</li>
         <li>On national holidays some locations may close; in such cases the itinerary may be adjusted or replaced with a vineyard of equal or higher quality.</li>
        </ul>
       </div>
      </div>
      <hr/>
      <hr/>
      <div class="row">
       <div class="col-lg-3">
        <h3>Cancellation policy</h3>
       </div>
       <div class="col-lg-9">
        <p>For a full refund, you must cancel at least 24 hours before the experience start time.</p>
        <ul>
         <li>If you cancel less than 24 hours before the experience’s start time, the amount you paid will not be refunded.</li>
         <li>Any changes made less than 24 hours before the experience’s start time will not be accepted.</li>
         <li>Cut-off times are based on the experience’s local time.</li>
         <li>This experience requires a minimum number of travelers. If it’s canceled because the minimum isn’t met, you’ll be offered a different date/experience or a full refund.</li>
        </ul>
       </div>
      </div>
      <hr/>
      <hr/>
      <div class="main_title">
       <h2>Detailed <span>timeline</span> for your tour</h2>
       <p>Pick an option to see its itinerary. If a winery is unavailable on your date, it may be replaced with another of equal quality.</p>
      </div>
      <?php render_option_selector($maipo_options, $maipo_js_options, $maipo_selected, 'tour_option_itin', 'Itinerary for', 'inline'); ?>
      <hr/>
      <div id="itinerary-wrapper" style="position:relative; max-height:180px; overflow:hidden; transition:max-height 0.5s ease;">
       <?php foreach ($maipo_options as $slug => $opt): ?>
        <?php render_option_itinerary($slug, $opt, $maipo_selected); ?>
       <?php endforeach; ?>
       <div id="fade-effect" class="fade-overlay" style="position:absolute; bottom:0; left:0; right:0; height:160px; background:linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1)); pointer-events:none; transition: opacity 0.3s ease;"></div>
      </div>
      <div class="text-center">
       <button id="toggle-btn" class="btn_1" onclick="toggleItinerary()">See more</button>
      </div>
      <?php render_tour_faq($tour_faqs); ?>
     </div>
     <!--End single_tour_desc-->
     <aside class="col-lg-4" id="sidebar">
      <!--
      <p class="d-none d-xl-block d-lg-block">
       <a class="btn_map" data-bs-toggle="collapse" href="#collapseMap" aria-expanded="false" aria-controls="collapseMap" data-text-original="View on map" data-text-swap="Hide map">View on map</a>
      </p>
      -->
      <div class="box_style_1 expose">
       <form action="book_tour.php" method="POST" id="booking">
        <input id="activity_name" name="activity_name" type="hidden" value="<?= option_ui_h($maipo_options[$maipo_selected]['exp_name']) ?>"/>
        <h3 class="inner">- Booking -</h3>
        <?php render_option_selector($maipo_options, $maipo_js_options, $maipo_selected, 'tour_option', 'Choose your tour'); ?>
        <div class="row">
         <div class="col-sm-6">
          <div class="form-group">
           <label>Name</label>
           <input class="form-control required" id="name_booking" name="name_booking" type="text"/>
          </div>
         </div>
         <div class="col-sm-6">
          <div class="form-group">
           <label>Last name</label>
           <input class="form-control required" id="last_name_booking" name="last_name_booking" type="text"/>
          </div>
         </div>
        </div>
        <div class="form-group">
         <label>Email</label>
         <input class="form-control required" id="email_booking" name="email_booking" type="email"/>
        </div>
        <div class="form-group">
         <label>Telephone</label>
         <input class="form-control required" id="phone_booking" name="phone_booking" type="text"/>
        </div>
        <hr/>
        <div class="row">
         <div class="col-sm-6">
          <div class="form-group">
           <label><i class="icon-calendar-7"></i> Date</label>
           <input class="date-pick form-control required" id="date_booking" name="date_booking" type="text"/>
          </div>
         </div>
        </div>
        <div class="row">
         <div class="col-4">
          <div class="form-group">
           <label>Adults</label>
           <div class="numbers-row">
            <input class="qty2 form-control" id="adults" name="adults" type="text" value="1"/>
           </div>
          </div>
         </div>
         <div class="col-4">
          <div class="form-group">
           <label>Children</label>
           <div class="numbers-row">
            <input class="qty2 form-control" id="children" name="children" type="text" value="0"/>
           </div>
           <small style="font-size: 10px; color: #aaa;">&lt;12yo</small>
          </div>
         </div>
         <div class="col-4">
          <div class="form-group">
           <label>Infants</label>
           <div class="numbers-row">
            <input class="qty2 form-control" id="infants" name="infants" type="text" value="0"/>
           </div>
           <small style="font-size: 10px; color: #aaa;">&lt;2yo</small>
          </div>
         </div>
        </div>
        <table hidden class="table table-striped options_booking">
         <thead>
          <tr>
           <th colspan="3">Add options / Services</th>
          </tr>
         </thead>
         <tbody>
          <tr>
           <td><i class="icon-plane-outline"></i></td>
           <td>Airport Pick up service <strong>+$30*</strong></td>
           <td>
            <label class="switch-light switch-ios pull-right">
             <input id="option_2" name="option_2" type="checkbox" value="Yes"/>
             <span>
              <span>No</span>
              <span>Yes</span>
             </span>
             <a></a>
            </label>
           </td>
          </tr>
         </tbody>
        </table>
        
        <div class="form-group row" style="display: flex; align-items: center; margin-bottom: 1rem;">
         <div class="col-8">
          <input class="form-control required" id="coupon_code" name="coupon_code" type="text" placeholder="I have a coupon!"/>
         </div>
         <div class="col-4 text-end">
          <button class="btn_full" id="apply_coupon_valpo" type="button">Apply</button>
         </div>
        </div>
        <p><strong>Total: $ <span id="total_price">0</span> USD</strong></p>
        <p id="new_total_container" style="display:none;"><strong>New total:</strong> $ <span id="new_total_price"></span> USD</p>
        <button class="btn_full" type="submit">Book now</button>
       </form>
      </div>
     </aside>
    </div>
    <!--End row -->
   </div>
   <!--End container -->
  </main>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <!-- End footer -->
  <div id="toTop"></div>
  <!-- JavaScript Files -->
<script>
window.MAIPO_OPTIONS = <?= json_encode((object)$maipo_js_options, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.MAIPO_SELECTED = <?= json_encode($maipo_selected) ?>;
</script>
<?php include __DIR__ . '/includes/tour-scripts.php'; ?>
<script defer src="js/maipo-options.js?v=20261013"></script>
  </body>
</html>
