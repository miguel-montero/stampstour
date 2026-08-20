<?php
$page_title       = 'Maipo Valley Wine Tour with 4 vineyards from Santiago.';
$page_description = 'Small-group or private Maipo Valley wine tour from Santiago. Multiple tastings, optional winery lunch, hotel pickup, English-speaking guide.';
$page_canonical   = 'https://stampstour.com/maipo-valley-wine-tour-santiago';
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
$lcp_preload_image = 'img/Tours/Maipo/big-optimized.webp';
$vendor_css_variant = 'tour';
$exp_name = 'Maipo';
require __DIR__ . '/../db_config.php';
require __DIR__ . '/includes/tour_price.php';
require __DIR__ . '/includes/tour_faq.php';
$dynamic_price_adult = fetch_tour_adult_price($conn, $exp_name);
$tour_faqs = [
    ['q' => 'How many wineries do we visit on the Maipo Valley wine tour?', 'a' => "Four family-run stops in Isla de Maipo: Campo La Quirinca (wine and pisco tasting), Viña Santa Ema (wine tasting), Viña TerraMater (your lunch stop, at your own cost), and Viña Undurraga (wine tasting). Tastings aren't at every stop - see the itinerary below for the full breakdown."],
    ['q' => 'Is lunch included?', 'a' => "No. You'll stop at the Zinfandel restaurant on the TerraMater estate, but the meal itself is at your own cost."],
    ['q' => 'Where does the tour pick me up?', 'a' => 'Hotel pickup is offered from Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, and the Airport area; your exact pickup time is sent the night before.'],
    ['q' => 'How many people are on the tour?', 'a' => "Groups are capped at 15 travelers, with a minimum of 4 required to run - if the minimum isn't met, you'll be offered another date or a full refund."],
    ['q' => "What's the cancellation policy?", 'a' => "Free cancellation up to 24 hours before the tour's start time; cancellations made inside that window aren't refunded."],
    ['q' => 'Is the Maipo Valley wine tour worth it?', 'a' => "If you want a taste of Chilean wine country without renting a car or planning your own route between wineries, yes - in one day you visit four different Isla de Maipo wineries with guided tastings and hotel pickup included."],
    ['q' => 'Can I book this tour privately?', 'a' => 'A private version of this tour is available - pricing depends on your group size, so please contact us directly to inquire.'],
];
?>
<!DOCTYPE html>
<html lang="en">
 <head>
<?php include __DIR__ . '/includes/head.php'; ?>
  <link rel="stylesheet" href="css/timeline.css" media="print" onload="this.media='all';this.onload=null;">
  <noscript><link href="css/timeline.css" rel="stylesheet"></noscript>
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
       <h1>Small-Group Maipo Valley Wine Tour: 4 Vineyards from Santiago</h1>
      </div>
      <div class="col-md-4">
       <div id="price_single_main">
        Special offer
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
        <h2>Tour Overview & Highlights</h2>
       <div class="col-lg-3">
        <h3>Description</h3>
       </div>
       <div class="col-lg-9">
        
        <p>Visit the bucolic town of Isla de Maipo for a day of exploring Chilean wine country. Though not truly an island, the area takes its name because it is surrounded by tributaries of the Maipo River, which make for perfect grape-growing conditions. Visit four wineries, each with their own specialties and enjoy several tastings and a delicious lunch along the way.</p>
        <h4>What to expect.</h4>
        <p>Your adventure begins with pickup from your Santiago hotel or private residence in Downtown, Providencia, Las Condes, Vitacura, and Recoleta. If you are not in one of these areas, you will be provided with the nearest pickup point.</p>
        <p>The first stop on your wine tour is the picturesque family farm, Campo La Quirinca, to learn about Chilean winemaking traditions and enjoy wine tastings, accompanied by the famous Chilean pisco.</p>
        <p>Next, visit Vi&ntilde;a Santa Ema, a charming winery offering tastings of three premium wines, including a signature blend. Then it’s on to Vi&ntilde;a TerraMater for lunch at the Zinfandel restaurant (at your own cost).</p>
        <p>Cap off your wine tour at Vi&ntilde;a Undurraga, one of the oldest and most traditional wineries with 130 years of expertise. Delight in tastings of four premium wines and a comprehensive tour of gardens, vineyards, production facilities, and the wine barrel cellar.</p>
        <p>Your experience concludes with a drop-off at your original departure point.</p>
        <h4>What's included</h4>
        <div class="row">
         <div class="col-md-6">
          <ul class="list_ok">
           <li>Hotel pickup and drop-off (Pick up time will be delivered the night before the tour)</li>
           <li>Professional and expert tour guide</li>
           <li>Pisco Tasting</li>
           <li>Entry/Admission - Campo la Quirinca</li>
           <li>Entry/Admission - Vi&ntilde;a Santa Ema</li>
           <li>Entry/Admission - Vi&ntilde;a Undurraga</li>
           <li>Live coordination via WhatsApp with guide. (Recommended the use of WhatsApp)</li>
          </ul>
         </div>
        </div>
        <!-- End row  -->
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
       <p>take a look</p>
      </div>
      <hr/>
      <ul class="cbp_tmtimeline">
       <li>
        <!-- <time class="cbp_tmtime" datetime="07:30"><span>45 minutes</span><span>07:30</span></time> -->
        <div class="cbp_tmicon icon-location-outline"></div>
        <div class="cbp_tmlabel">
         <h2><span>Santiago</span> Meeting point</h2>
         <p>Pick up at your location in Santiago City</p>
        </div>
       </li>
      </ul>
      <div id="itinerary-wrapper" style="position:relative; max-height:180px; overflow:hidden; transition:max-height 0.5s ease;">
       <ul class="cbp_tmtimeline" id="hidden-timeline">
        <li>
         <time class="cbp_tmtime" datetime="07:30"><span>1 hour 30 minutes</span><span></span></time>
         <div class="cbp_tmicon icon-camera-alt"></div>
         <div class="cbp_tmlabel">
          <h2><span>Isla de Maipo</span> Campo La Quirinca</h2>
          <p>The first stop is the amazing family farm “Campo La Quirinca”. The experience is completed with a full tour in the facilities and gardens, discovering different kinds of agricultural productions. You will be introduced to the Chilean countryside way to produce wine, also learn about the animal husbandry of alpacas, various breeds of chickens and more. After the fun tour you’ll relax in the salon and enjoy the wine tasting plus the famous Chilean pisco. Many of the local products are available for purchase so you can take something authentic home.</p>
         </div>
        </li>
        <li>
         <time class="cbp_tmtime" datetime="07:30"><span>1 Hour</span><span></span></time>
         <div class="cbp_tmicon icon-wine"></div>
         <div class="cbp_tmlabel">
          <h2><span>Isla de Maipo</span> Vi&ntilde;a Santa Ema</h2>
          <p>This charming winery offers a full tasting of 3 premium wines including one of their signature wines in a beautiful environment.</p>
         </div>
        </li>
        <li>
         <time class="cbp_tmtime" datetime="07:30"><span>1 hour 30 minutes</span><span></span></time>
         <div class="cbp_tmicon icon-restaurant"></div>
         <div class="cbp_tmlabel">
          <h2><span>Isla de Maipo</span> Vi&ntilde;a TerraMater</h2>
          <p>Around 1 pm you will visit the TerraMater winery which has a fantastic restaurant, Zinfandel, for lunch (own cost). This winery is also home to an olive oil that is the most awarded in Chile and the world. In the shop you will be able to buy it at cellar price.</p>
         </div>
        </li>
        <li>
         <time class="cbp_tmtime" datetime="07:30"><span>1 hour 30 minutes</span><span></span></time>
         <div class="cbp_tmicon icon-wine"></div>
         <div class="cbp_tmlabel">
          <h2><span>Isla de Maipo</span> Vi&ntilde;a Undurraga</h2>
          <p>One of the oldest and most traditional wineries with 130 years of experience. This winery will share the long history of Chilean wine production, giving you a better understanding of why our wines are high quality. You’ll taste 4 premium wines and enjoy a full tour of the gardens, vineyards, production warehouse, wine barrel cellar, and pre-Columbian exhibit.</p>
         </div>
        </li>
        <li>
         <!-- <time class="cbp_tmtime" datetime="07:30"><span>45 minutes</span><span>07:30</span></time> -->
         <div class="cbp_tmicon icon-location-outline"></div>
         <div class="cbp_tmlabel">
          <h2><span>Santiago</span> Return to the starting point</h2>
          <p>Drop-off at your location in Santiago City</p>
         </div>
        </li>
       </ul>
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
       <form action="submit.php" method="POST" id="booking">
        <input id="activity_name" name="activity_name" type="hidden" value="2"/>
        <!-- <input id="activity_name" name="activity_name" type="hidden" value=""/> -->
        <h3 class="inner">- Booking -</h3>
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
<?php include __DIR__ . '/includes/tour-scripts.php'; ?>
  </body>
</html>
