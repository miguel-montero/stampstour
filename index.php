<?php
$page_title       = 'Stamps Tour | Santiago Day Tours: Valparaíso, Maipo & Andes';
$page_description = 'Daily small-group and private day tours from Santiago: Valparaíso wine tasting, Maipo Valley, the Andes, city tours & cruise transfers. Hotel pickup included.';
$page_canonical   = 'https://stampstour.com/';
$page_og = [
  'title'       => 'Stampstour - Discover Chile',
  'description' => 'Daily tours to Valparaíso, Maipo Wine Valley, Andes & Santiago. Book your curated experience with Stampstour!',
  'url'         => 'https://stampstour.com/',
  'image'       => 'https://stampstour.com/img/Tours/portada.jpg',
];
$critical_css_file = __DIR__ . '/includes/critical/home.css';
?>
<!DOCTYPE html>
<html lang="en">

<head>
<?php include __DIR__ . '/includes/head.php'; ?>

    <style>
        .normal_price_list {
            text-decoration: line-through;
            margin-left: 5px;
            color: #999;
            font-size: 0.9em;
            display: inline-block;
        }
    </style>
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

    <main>

        <h1 class="visually-hidden">Santiago Day Tours: Valparaíso, Maipo Wine Valley, the Andes &amp; More | Stamps Tour</h1>

        <!-- Hero background image, sized and animated by pure CSS
             (css/custom.css: .hero-wrap / .hero-bg / .hero-overlay) -->
        <div class="hero-wrap">
        <img
            src="img/Tours/portada.webp"
            width="1883"
            height="1059"
            fetchpriority="high"
            alt="Colorful hillside houses in Valparaíso, Chile"
            class="hero-bg">
        <div class="hero-overlay"></div>

        <!-- Hero text overlay: plain Bootstrap-friendly markup, positioned
             with CSS only, so it stays reliably centered on mobile
             without per-breakpoint pixel tuning. -->
        <div class="hero-content text-center text-white">
            <h2 class="hero-title">Discover Chile</h2>
            <p class="hero-subtitle">Daily tours, expert local guides,<br class="d-md-none"> unforgettable experiences</p>
            <a href="#tours" class="btn_1 hero-cta">EXPLORE OUR TOURS</a>
        </div>
        </div>
        <!-- End .hero-wrap -->

        <div class="container margin_60">

            <div class="main_title">
                <h2>Travel <span>with</span> Us</h2>
                <p>Whether you're sipping wine in Maipo Valley, exploring the vibrant hills of Valparaíso, or reaching the heights of the Andes, StampsTour offers curated experiences designed for curious, adventurous travelers. Our expert guides, comfortable transport, and small group tours ensure an unforgettable day, every day.</p>
            </div>

            <div id="tours" class="row">

                <div class="col-lg-6 col-md-6 wow zoomIn" data-wow-delay="0.1s">
                    <div class="tour_container">
                        <div class="img_container">
                            <a href="valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php">
                                <picture>
                                    <source srcset="img/Tours/Valpo/portada-mobile.webp 600w, img/Tours/Valpo/portada.webp 955w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
                                    <img src="img/Tours/Valpo/portada.jpeg" width="800" height="533" class="img-fluid" alt="Valparaíso tour" loading="lazy">
                                </picture>
                                <div class="badge_tripadvisor">
                                    <picture>
                                        <source srcset="img/badges/tripadvisor-2026-white.webp" type="image/webp">
                                        <img src="img/badges/tripadvisor-2026-white.png" alt="Tripadvisor Travelers' Choice Best of the Best 2026">
                                    </picture>
                                </div>
                                <div class="badge_save">Save<strong>20%</strong></div>
                                <div class="short_info">
                                    <i class="icon_set_1_icon-28"></i>
                                    <span class="price">
                                        <sup>$</sup>79
                                        <span class="normal_price_list">$99</span>
                                    </span>
                                </div>
                            </a>
                        </div>
                        <div class="tour_title">
                            <h3><strong>Valparaíso</strong></h3>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 wow zoomIn" data-wow-delay="0.2s">
                    <div class="tour_container">
                        <div class="img_container">
                            <a href="maipo-valley-wine-tour-santiago.php">
                                <picture>
                                    <source srcset="img/Tours/Maipo/portada-mobile.webp 600w, img/Tours/Maipo/portada.webp 720w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
                                    <img src="img/Tours/Maipo/portada.jpg" width="800" height="533" class="img-fluid" alt="Maipo Wine Tour" loading="lazy">
                                </picture>
                                <div class="badge_tripadvisor">
                                    <picture>
                                        <source srcset="img/badges/tripadvisor-2026.webp" type="image/webp">
                                        <img src="img/badges/tripadvisor-2026.png" alt="Tripadvisor Travelers' Choice Best of the Best 2026">
                                    </picture>
                                </div>
                                <div class="badge_save">Save<strong>10%</strong></div>
                                <div class="short_info">
                                    <i class="icon_set_1_icon-15"></i>
                                    <span class="price">
                                        <sup>$</sup>121
                                        <span class="normal_price_list">$135</span>
                                    </span>
                                </div>
                            </a>
                        </div>
                        <div class="tour_title">
                            <h3><strong>Maipo Wine Tour</strong></h3>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 wow zoomIn" data-wow-delay="0.3s">
                    <div class="tour_container">
                        <div class="img_container">
                            <a href="portillo-inca-lagoon-andes-mountains-vineyard.php">
                                <picture>
                                    <source srcset="img/Tours/Andes/portada-mobile.webp 600w, img/Tours/Andes/portada.webp 1400w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
                                    <img src="img/Tours/Andes/portada.jpg" width="800" height="533" class="img-fluid" alt="Andes tour" loading="lazy">
                                </picture>
                                <div class="badge_save">Save<strong>20%</strong></div>
                                <div class="short_info">
                                    <i class="icon_set_1_icon-28"></i>
                                    <span class="price">
                                        <sup>$</sup>79
                                        <span class="normal_price_list">$99</span>
                                    </span>
                                </div>
                            </a>
                        </div>
                        <div class="tour_title">
                            <h3><strong>Andes</strong></h3>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 wow zoomIn" data-wow-delay="0.4s">
                    <div class="tour_container">
                        <div class="img_container">
                            <a href="discover-santiago-city-tour.php">
                                <picture>
                                    <source srcset="img/Tours/Stgo/portada-mobile.webp 600w, img/Tours/Stgo/portada.webp 1440w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
                                    <img src="img/Tours/Stgo/portada.jpg" width="800" height="533" class="img-fluid" alt="Santiago City Tour" loading="lazy">
                                </picture>
                                <div class="short_info">
                                    <i class="icon_set_1_icon-23"></i>
                                    <span class="price"><sup>$</sup>59</span>
                                </div>
                            </a>
                        </div>
                        <div class="tour_title">
                            <h3><strong>Santiago</strong></h3>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 wow zoomIn" data-wow-delay="0.3s">
                    <div class="tour_container">
                        <div class="img_container">
                            <a href="cruise-transfer.php">
                                <picture>
                                    <source srcset="img/Tours/Cruise/portada-mobile.webp 600w, img/Tours/Cruise/portada.webp 900w" sizes="(max-width: 767px) 100vw, 50vw" type="image/webp">
                                    <img src="img/Tours/Cruise/portada.jpeg" width="800" height="533" class="img-fluid" alt="Cruise transfer with Valparaíso tour" loading="lazy">
                                </picture>
                                <div class="badge_save">Save<strong>20%</strong></div>
                                <div class="short_info">
                                    <i class="icon_set_1_icon-28"></i>
                                    <span class="price">
                                        <sup>$</sup>99
                                        <span class="normal_price_list">$124</span>
                                    </span>
                                </div>
                            </a>
                        </div>
                        <div class="tour_title">
                            <h3><strong>Cruise Transfer ↔ Santiago with Valparaiso Tour & Casablanca Wine Tasting</strong></h3>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <!-- End container -->

        <div class="white_bg">
            <div class="container margin_60">
                <div class="main_title">
                    <h2>Why <span>Choose</span> Us</h2>
                </div>

                <div class="row text-center">
                    <div class="col-md-3 col-sm-6 wow fadeInUp" data-wow-delay="0.1s">
                        <i class="icon_set_1_icon-16" style="font-size: 40px; color: #F96;"></i>
                        <h4 class="mt-2"><strong>Expert Local Guides</strong></h4>
                        <p>Our bilingual guides are passionate storytellers who bring Chile’s culture and history to life.</p>
                    </div>

                    <div class="col-md-3 col-sm-6 wow fadeInUp" data-wow-delay="0.2s">
                        <i class="icon_set_1_icon-37" style="font-size: 40px; color: #6CF;"></i>
                        <h4 class="mt-2"><strong>Curated Experiences</strong></h4>
                        <p>From iconic landmarks to hidden gems, every tour is handpicked for authenticity and depth.</p>
                    </div>

                    <div class="col-md-3 col-sm-6 wow fadeInUp" data-wow-delay="0.3s">
                        <i class="icon_set_1_icon-71" style="font-size: 40px; color: #9C6;"></i>
                        <h4 class="mt-2"><strong>Comfort & Safety</strong></h4>
                        <p>Modern, air-conditioned vehicles and attention to every detail make travel easy and worry-free.</p>
                    </div>

                    <div class="col-md-3 col-sm-6 wow fadeInUp" data-wow-delay="0.4s">
                        <i class="icon-users-outline" style="font-size: 40px; color: #F69;"></i>
                        <h4 class="mt-2"><strong>Small Group Tours</strong></h4>
                        <p>We keep groups small to ensure a more personal, immersive, and flexible experience.</p>
                    </div>
                </div>
            </div>
        </div>

    </main>
    <!-- End main -->

    <footer class="revealed">
        <?php include __DIR__ . '/includes/footer.php'; ?>
    </footer>
    <!-- End footer -->

    <div id="toTop"></div>

    <!-- Common scripts -->
    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="js/common_scripts_min.js"></script>
    <script src="js/functions.js"></script>

    <script>
        jQuery(function($){
            var $menu = $('.main-menu');

            if ($(window).scrollTop() === 0) {
                $menu.hide();
            }

            $(window).on('scroll', function(){
                if ($(this).scrollTop() > 0) {
                    $menu.show();
                } else {
                    $menu.hide();
                }
            });
        });
    </script>

    <script>
        jQuery(function($){
            var isHome = window.location.pathname === '/' || window.location.pathname === '/index.php';
            if (!isHome) return;

            $('a[href="/"]').on('click', function(e){
                e.preventDefault();

                $('html, body').stop().animate({
                    scrollTop: 0
                }, 200, 'linear');
            });
        });
    </script>

    <!-- Pop up script -->
    <script type="text/javascript" src="js/pop_up.min.js"></script>
    <script type="text/javascript" src="js/pop_up_func.js"></script>

    <!-- Start of OpenWidget code (gated behind cookie consent - see includes/cookie-banner.php) -->
    <script>
        window.__initOpenWidget = function () {
            if (window.__owInitialized) return;
            window.__owInitialized = true;

            window.__ow = window.__ow || {};
            window.__ow.organizationId = "f7a8e974-6c43-4b2d-a3f4-4a914bea8504";
            window.__ow.integration_name = "manual_settings";
            window.__ow.product_name = "openwidget";

            ;(function(n,t,c){
                function i(n){
                    return e._h ? e._h.apply(null,n) : e._q.push(n)
                }
                var e = {
                    _q: [],
                    _h: null,
                    _v: "2.0",
                    on: function(){ i(["on", c.call(arguments)]) },
                    once: function(){ i(["once", c.call(arguments)]) },
                    off: function(){ i(["off", c.call(arguments)]) },
                    get: function(){
                        if (!e._h) throw new Error("[OpenWidget] You can't use getters before load.");
                        return i(["get", c.call(arguments)])
                    },
                    call: function(){ i(["call", c.call(arguments)]) },
                    init: function(){
                        var n = t.createElement("script");
                        n.async = !0;
                        n.type = "text/javascript";
                        n.src = "https://cdn.openwidget.com/openwidget.js";
                        t.head.appendChild(n);
                    }
                };
                !n.__ow.asyncInit && e.init();
                n.OpenWidget = n.OpenWidget || e;
            }(window, document, [].slice));
        };

        // Returning visitor who already accepted cookies: start the widget now.
        (function () {
            try {
                if (localStorage.getItem('stamp_cookie_consent') === 'granted') {
                    window.__initOpenWidget();
                }
            } catch (e) {}
        })();
    </script>

    <noscript>
        You need to <a href="https://www.openwidget.com/enable-javascript" rel="noopener nofollow">enable JavaScript</a> to use the communication tool powered by <a href="https://www.openwidget.com/" rel="noopener nofollow" target="_blank">OpenWidget</a>
    </noscript>
    <!-- End of OpenWidget code -->

</body>
</html>
