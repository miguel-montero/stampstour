<?php
$page_title       = 'Valparaiso Port and Viña del Mar with Casablanca Wine Tasting';
$page_description = 'Full-day Valparaíso & Viña del Mar tour from Santiago with Casablanca Valley wine tasting. Hotel pickup, small groups, free cancellation.';
$page_canonical   = 'https://stampstour.com/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca';
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
$lcp_preload_image = 'img/Tours/Valpo/big.jpg';
$vendor_css_variant = 'tour';
?>
<!DOCTYPE html>
<html lang="en">

 <head>
<?php include __DIR__ . '/includes/head.php'; ?>
  <link rel="preload" href="css/timeline.css" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
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
   <img src="img/Tours/Valpo/big.jpg" width="1920" height="716" fetchpriority="high" alt="Valparaíso banner" class="tour-banner-bg">
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
        <div class="badge_save">Save<strong>20%</strong></div> 
       <h1>Valparaíso & Viña del Mar Day Trip from Santiago with Wine Tasting</h1>
      </div>
      <div class="col-md-4">
       <div id="price_single_main">
        Special offer
        <span>
         <sup>
          $
         </sup>
         <span id="dynamic_price"></span>
        </span>
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
        <li><i class="icon_set_1_icon-28"></i> Funicular</li>
        <li><i class="icon_set_1_icon-29"></i> Professional tour guide</li>
       </ul>
      </div>
      <!-- Image Gallery Carousel -->
      <div id="Img_carousel" class="slider-pro magnific-gallery">
       <div class="sp-slides">
        <!-- Cover image slide -->
        <div class="sp-slide">
         <a href="img/Tours/Valpo/portada.jpeg" data-effect="mfp-zoom-in">
           <img class="sp-image" src="css/images/blank.gif" alt="Valpo tour cover"
                data-src="img/Tours/Valpo/portada.webp"
                data-small="img/Tours/Valpo/portada.webp"
                data-medium="img/Tours/Valpo/portada.webp"
                data-large="img/Tours/Valpo/portada.webp"
                data-retina="img/Tours/Valpo/portada.webp">
           <i class="icon-resize-full-2"></i>
         </a>
        </div>
        <!-- Slides for each gallery image -->
        <?php for ($i = 1; $i <= 44; $i++): ?>
        <?php $imagePath = "img/Tours/Valpo/{$i}_medium.jpeg"; $imagePathWebp = "img/Tours/Valpo/{$i}_medium.webp"; ?>
        <div class="sp-slide">
         <a href="<?php echo $imagePath; ?>" data-effect="mfp-zoom-in">
           <img class="sp-image" src="css/images/blank.gif" alt="Valpo image <?php echo $i; ?>"
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
        <img class="sp-thumbnail" src="img/Tours/Valpo/portada_thumb.webp" alt="Valparaiso thumbnail cover" loading="lazy">
        <?php for ($i = 1; $i <= 44; $i++): ?>
         <img class="sp-thumbnail" src="img/Tours/Valpo/<?php echo $i; ?>_thumb.webp" alt="Valparaiso thumbnail <?php echo $i; ?>" loading="lazy">
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
        
        <p>No trip to Santiago is complete without seeing the colorful confection of Valparaiso and Vi&ntilde;a del Mar. Let a guide organize transport and activities on a hassle-free excursion that gives a comprehensive introduction to the UNESCO World Heritage sites. Travel with ease between the highlights of Valparaiso and Vi&ntilde;a del Mar, and receive personalized attention in a small group limited to 15. For added convenience, hotel pickup and drop-off are included.</p>
        <h4>What to expect.</h4>
        <p>Your tour begins with hotel pickup in Santiago. Board a coach destined for the coastal towns of Valparaiso and Vi&ntilde;a del Mar. In Valparaiso, a UNESCO World Heritage Site, embark on a panoramic tour of the port and Plaza Sotomayor (Sotomayor Square).
        <p>Then, ascend to the summit of the Concepci&oacute;n and Alegre hills by traditional elevators. Break for lunch and enjoy Chilean cuisine at your own expense.</p>
        <p>The other stop is Vi&ntilde;a del Mar, known as the Garden City for its plethora of greenery. Visit a coastal spot to observe sea lions before traveling to your final stop, the Casablanca Valley.</p>
        <p>Here, you can sample wines at a vineyard before returning to Santiago in the early evening.</p>
        <p>Your experience concludes with a drop-off at your original departure point.</p>
        <h4>What's included</h4>
        <div class="row">
         <div class="col-md-6">
          <ul class="list_ok">
           <li>Hotel pickup and drop-off (Pick up time will be delivered the night before the tour)</li>
           <li>Professional and expert tour guide</li>
           <li>Wine tasting</li>
           <li>Entry/Admission - Winery</li>
           <li>One Funicular Ride in Valparaiso</li>
           <li>Live coordination via WhatsApp with guide. (Recommended the use of WhatsApp)</li>
          </ul>
         </div>
        </div>
        <!-- End row  -->
         <h4>What's not included</h4>
        <div class="row">
         <div class="col-md-6">
          <ul class="list_ok">
           <li>Airport pickup and drop-off (inquire about extra fee)</li>
           <li>Lunch</li>
           <li>Gratuities</li>
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
         <li>Most travelers can participate</li>
         <li> Minimum numbers apply (4 people) . There is a possibility of cancellation after confirmation if there are not enough passengers to meet requirements. In the event of this occurring, you will be offered an alternative or full refund</li>
         <li>This experience requires good weather. If it’s canceled due to poor weather, you’ll be offered a different date or a full refund.</li>
         <li>This tour/activity will have a maximum of 15 travelers</li>
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
          <time class="cbp_tmtime" datetime="07:30"><span>5 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-camera-alt"></div>
          <div class="cbp_tmlabel">
            <h2><span>Vi&ntilde;a del Mar</span> Flower Clock</h2>
            <p>Flowery garden that houses the famous clock.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>10 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-user"></div>
          <div class="cbp_tmlabel">
            <h2><span>Vi&ntilde;a del Mar</span> Moai del Ahu</h2>
            <p>Genuine Moai brought from Easter Island in 1951; part of the Fonck Museum collection.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>15 minutes</span><span></span></time>
          <div class="cbp_tmicon icon_set_2_icon-108"></div>
          <div class="cbp_tmlabel">
            <h2><span>Vi&ntilde;a del Mar</span> Avenida Peru</h2>
            <p>Oceanview overlooking the coast of Vi&ntilde;a del Mar and Cerro Castillo.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>20 minutes</span><span></span></time>
          <div class="cbp_tmicon icon_set_3_restaurant-7"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Caleta Portales</h2>
            <p>Fish market where you can watch sea lions and touch the Pacific Ocean.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>15 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-monument"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Sotomayor Square</h2>
            <p>Main civic center with Navy HQ and Monument to the Pacific War heroes.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>5 minutes</span><span></span></time>
          <div class="cbp_tmicon icon_set_1_icon-28"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Ascensor el Peral</h2>
            <p>Historic funicular included in this tour (ticket included).</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>10 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-eye"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Paseo Yugoslavo</h2>
            <p>Famous walkway with spectacular views of the bay and colorful hills.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>10 minutes</span><span></span></time>
          <div class="cbp_tmicon icon_set_1_icon-4"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Palacio Baburizza</h2>
            <p>Fine art museum in an old mansion with superb hill and bay views.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>5 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-home-1"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Pasaje Bavestrello</h2>
            <p>Historic stairs built by the Italian community as a shortcut up the hill.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>15 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-brush"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Galvez Inc. Arte Contemporaneo</h2>
            <p>Gateway to the wall art alleyways — a labyrinth of surprising street art.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>30 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-picture"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Cerro Alegre &amp; Cerro Concepcion</h2>
            <p>Walking tour past Victorian & German Tudor architecture, vintage shops, and more.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>15 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-eye"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Paseo Atkinson</h2>
            <p>Historic walkway with sea views; the oldest neighborhood on Concepci&oacute;n hill.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>5 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-brush"></div>
          <div class="cbp_tmlabel">
            <h2><span>Valparaiso</span> Piano Staircase</h2>
            <p>Iconic piano-themed mural on one of Valpara&iacute;so’s many stairways.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="07:30"><span>30 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-wine"></div>
          <div class="cbp_tmlabel">
            <h2><span>Casablanca</span> Winery</h2>
            <p>Enjoy a wine sample in one of the beautiful wineries of this prestigious valley.</p>
          </div>
        </li>
        <li>
          <!-- no time -->
          <div class="cbp_tmicon icon-location-outline"></div>
          <div class="cbp_tmlabel">
            <h2><span>Santiago</span> Return to the starting point</h2>
            <p>Drop-off at your location in Santiago City.</p>
          </div>
        </li>
       </ul>
       <div id="fade-effect" class="fade-overlay" style="position:absolute; bottom:0; left:0; right:0; height:160px; background:linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1)); pointer-events:none; transition: opacity 0.3s ease;"></div>
      </div>
      <div class="text-center">
       <button id="toggle-btn" class="btn_1" onclick="toggleItinerary()">See more</button>
      </div>
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
        <table class="table table-striped options_booking">
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
        <p><small>* Prices per person.</small></p>
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
  <footer class="revealed">
   <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>
  <!-- End footer -->
  <div id="toTop"></div>
<?php $exp_name = 'Valparaiso'; include __DIR__ . '/includes/tour-scripts.php'; ?>
  </body>
</html>
