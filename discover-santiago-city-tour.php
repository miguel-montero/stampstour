<?php
$page_title       = 'Discover Santiago Half Day Guided Tour Included Local Snack';
$page_description = 'Half-day guided city tour of Santiago with an English-speaking guide. Hotel pickup, snack included, views, historic center & market.';
$page_canonical   = 'https://stampstour.com/discover-santiago-city-tour';
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
$lcp_preload_image = 'img/Tours/Stgo/big.jpg';
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
   <img src="img/Tours/Stgo/big.jpg" width="1400" height="1050" fetchpriority="high" alt="Santiago banner" class="tour-banner-bg">
   <div class="parallax-content-2">
    <div class="container">
     <div class="row">
      <div class="col-md-8">
        <!-- save badge -->
        <!-- <div class="badge_save">Save<strong>20%</strong></div>  -->
       <h1>
        Santiago City Tour with Hotel Pickup & English Guide
       </h1>
      </div>
      <div class="col-md-4">
       <div id="price_single_main">
        per person
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
        <li><i class="icon_set_1_icon-83"></i> 5 Hours</li>
        <li><i class="icon_set_1_icon-26"></i> Air Conditioned Bus</li>
        <li><i class="icon_set_1_icon-60"></i> Snack Included</li>
        <li><i class="icon_set_1_icon-29"></i> Professional tour guide</li>
        <li><i class="icon_set_1_icon-34"></i> Wildlife</li>
       </ul>
      </div>
      <!-- Image Gallery Carousel -->
      <div id="Img_carousel" class="slider-pro magnific-gallery">
       <div class="sp-slides">
        <!-- Cover image slide -->
        <div class="sp-slide">
         <a href="img/Tours/Stgo/portada.jpg" data-effect="mfp-zoom-in">
           <img class="sp-image" src="css/images/blank.gif" alt="Stgo tour cover"
                data-src="img/Tours/Stgo/portada.webp"
                data-small="img/Tours/Stgo/portada.webp"
                data-medium="img/Tours/Stgo/portada.webp"
                data-large="img/Tours/Stgo/portada.webp"
                data-retina="img/Tours/Stgo/portada.webp">
           <i class="icon-resize-full-2"></i>
         </a>
        </div>
        <!-- Slides for each gallery image -->
        <?php for ($i = 1; $i <= 8; $i++): ?>
        <?php $imagePath = "img/Tours/Stgo/{$i}_medium.jpg"; $imagePathWebp = "img/Tours/Stgo/{$i}_medium.webp"; ?>
        <div class="sp-slide">
         <a href="<?php echo $imagePath; ?>" data-effect="mfp-zoom-in">
           <img class="sp-image" src="css/images/blank.gif" alt="Stgo image <?php echo $i; ?>"
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
        <img class="sp-thumbnail" src="img/Tours/Stgo/portada_thumb.webp" alt="Stgo thumbnail cover" loading="lazy">
        <?php for ($i = 1; $i <= 8; $i++): ?>
         <img class="sp-thumbnail" src="img/Tours/Stgo/<?php echo $i; ?>_thumb.webp" alt="Stgo thumbnail <?php echo $i; ?>" loading="lazy">
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
        <p>
         Get acquainted with the treasured landmarks of the Chilean capital on a 5-hour tour of Santiago by luxury coach. From your guide, you&rsquo;ll receive an interesting introduction to Santiago as you visit landmarks such as Metropolitan Cathedral of Santiago, Cerro Santa Lucia and La Moneda palace. Absorb the bohemian charm of Bellavista neighborhood and opt to explore the city&rsquo;s financial district, nicknamed &lsquo;Sanhattan&rsquo;, due to its plethora of skyscrapers. Hotel pickup and drop-off is included in this tour.
        </p>
        <h4>
         What to expect.
        </h4>
        <p>
         Your half-day adventure begins with hotel pickup in Santiago. From there, you'll head to
         <strong>
          Parque Bicentenario
         </strong>
         , a peaceful green space in Vitacura where you&rsquo;ll enjoy views of the city alongside native flora and fauna &mdash; a perfect way to ease into the rhythm of the capital.
        </p>
        <p>
         Continuing through the vibrant city, you&rsquo;ll pass through
         <strong>
          Bellavista
         </strong>
         , Santiago&rsquo;s cultural and bohemian district. Your guide will point out local landmarks like La Chascona, Pablo Neruda&rsquo;s quirky former home, and the entrance to the Metropolitan Park, where cable cars and funiculars offer scenic rides up the hills.
        </p>
        <p>
         Next, immerse yourself in local life at the
         <strong>
          Central Market
         </strong>
         , where the flavors, colors, and energy of Santiago come alive. This is where you&rsquo;ll get your first real taste of the city&rsquo;s daily heartbeat.
        </p>
        <p>
         Step into history at
         <strong>
          Plaza de Armas
         </strong>
         , the historic heart of Santiago, surrounded by colonial-era architecture. Nearby, you&rsquo;ll enter the
         <strong>
          Metropolitan Cathedral
         </strong>
         , a masterpiece of neoclassical design and Chile&rsquo;s most important Catholic church.
        </p>
        <p>
         You&rsquo;ll also stop by the
         <strong>
          Ex Congreso Nacional
         </strong>
         , the former Chilean Congress building, and admire the fa&ccedil;ade of the elegant
         <strong>
          Stock Exchange
         </strong>
         building &mdash; both key pieces of Santiago&rsquo;s historical and political landscape.
        </p>
        <p>
         Then, at the iconic
         <strong>
          La Moneda Palace
         </strong>
         , you&rsquo;ll explore Chile&rsquo;s civic center. Your guide will share the story of the 1973 military coup that took place here, a pivotal moment in the country&rsquo;s history.
        </p>
        <p>
         From there, cruise past
         <strong>
          Parque Forestal
         </strong>
         , a beautiful French-style park that&rsquo;s home to the first fine arts museum in Latin America, before heading to your final destination:
         <strong>
          Cerro Santa Luc&iacute;a
         </strong>
         .
        </p>
        <p>
         This hilltop park offers panoramic 360&deg; views of the city and a fascinating glimpse into Santiago&rsquo;s origins. It&rsquo;s one of the city&rsquo;s most iconic spots.
        </p>
        <p>
         After a refreshing break, you&rsquo;ll be dropped off at your hotel, with a deeper appreciation of Santiago&rsquo;s culture, history, and charm &mdash; all in just half a day.
        </p>
        <h4>What's included</h4>
        <div class="row">
         <div class="col-md-6">
          <ul class="list_ok">
           <li>Hotel pickup and drop-off (Pick up time will be delivered the night before the tour)</li>
           <li>Professional and expert tour guide</li>
           <li>Local Snack</li>
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
         <li>On Sundays and national holidays, the winery is closed, and the wine tasting will be held at an alternative location.</li>
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
          <time class="cbp_tmtime" datetime="09:00"><span>15 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-leaf"></div>
          <div class="cbp_tmlabel">
            <h2><span>Vitacura</span> Parque Bicentenario</h2>
            <p>A peaceful park with native flora and lagoon birds — a perfect start to the day.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="09:20"><span>Pass by</span><span></span></time>
          <div class="cbp_tmicon icon_set_1_icon-28"></div>
          <div class="cbp_tmlabel">
            <h2><span>Providencia</span> Barrio Bellavista</h2>
            <p>Bohemian quarter. Pass by Pablo Neruda’s <em>La Chascona</em> and the entrance to Cerro San Cristóbal.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="09:40"><span>15 minutes</span><span></span></time>
          <div class="cbp_tmicon icon_set_3_restaurant-7"></div>
          <div class="cbp_tmlabel">
            <h2><span>Santiago Centro</span> Mercado Central</h2>
            <p>Iconic seafood market with colorful stalls and lively local atmosphere.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="10:00"><span>20 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-monument"></div>
          <div class="cbp_tmlabel">
            <h2><span>Santiago Centro</span> Plaza de Armas</h2>
            <p>Main square surrounded by historic buildings and street life — the heart of Santiago’s old town.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="10:25"><span>15 minutes</span><span></span></time>
          <div class="cbp_tmicon icon_set_1_icon-2"></div>
          <div class="cbp_tmlabel">
            <h2><span>Santiago Centro</span> Metropolitan Cathedral</h2>
            <p>Neoclassical interior and altars — Chile’s most important Catholic church.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="10:45"><span>5 minutes</span><span></span></time>
          <div class="cbp_tmicon icon_set_1_icon-4"></div>
          <div class="cbp_tmlabel">
            <h2><span>Santiago Centro</span> Ex Congreso Nacional</h2>
            <p>Elegant former Congress building, today used for cultural/government events.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="10:55"><span>5 minutes</span><span></span></time>
          <div class="cbp_tmicon icon_set_1_icon-44"></div>
          <div class="cbp_tmlabel">
            <h2><span>Santiago Centro</span> Bolsa de Comercio (Stock Exchange)</h2>
            <p>Quick photo stop for the beautiful neoclassical exchange building.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="11:10"><span>15 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-flag"></div>
          <div class="cbp_tmlabel">
            <h2><span>Distrito Cívico</span> Palacio de La Moneda</h2>
            <p>Chile’s presidential palace; stories of the modern republic and the 1973 events.</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="11:30"><span>Pass by</span><span></span></time>
          <div class="cbp_tmicon icon-tree"></div>
          <div class="cbp_tmlabel">
            <h2><span>Santiago Centro</span> Parque Forestal</h2>
            <p>Drive along this French-style park, home to the Fine Arts Museum (MNBA).</p>
          </div>
        </li>
        <li>
          <time class="cbp_tmtime" datetime="11:45"><span>30 minutes</span><span></span></time>
          <div class="cbp_tmicon icon-eye"></div>
          <div class="cbp_tmlabel">
            <h2><span>Santiago Centro</span> Cerro Santa Lucía</h2>
            <p>Hilltop park with fountains, terraces, and a panoramic 360° city view.</p>
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
  <footer class="revealed">
   <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>
  <!-- End footer -->
  <div id="toTop"></div>
<?php $exp_name = 'Santiago'; include __DIR__ . '/includes/tour-scripts.php'; ?>
  </body>
</html>
