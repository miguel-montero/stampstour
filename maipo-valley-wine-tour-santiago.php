<?php
$page_title       = 'Maipo Valley Wine Tour from Santiago: 4 Wineries, Classic or Premium';
$page_description = 'Small-group Maipo Valley wine tour from Santiago: 4 wineries with tastings, hotel pickup and guide. Choose Classic, Classic with lunch included, or Premium with Undurraga.';
$page_canonical   = 'https://stampstour.com/maipo-valley-wine-tour-santiago';
$critical_css_file = __DIR__ . '/includes/critical/tour.css';
$lcp_preload_image = 'img/Tours/Maipo/2026/vineyard-toast-1280.webp';
$lcp_preload_image_mobile = 'img/Tours/Maipo/2026/vineyard-toast-960.webp';
$vendor_css_variant = 'tour';
$page_og = [
    'title' => $page_title,
    'description' => $page_description,
    'url' => $page_canonical,
    'image' => 'https://stampstour.com/img/Tours/Maipo/2026/vineyard-toast-1280.jpg',
];
$exp_name = 'Maipo'; // availability (cupos) is shared by all three options - always the Maipo row
require __DIR__ . '/../db_config.php';
require __DIR__ . '/includes/tour_price.php';
require __DIR__ . '/includes/tour_faq.php';
require __DIR__ . '/includes/tours/maipo_options.php';
require __DIR__ . '/includes/tours/maipo_lab_content.php';
require __DIR__ . '/includes/ui/ui.php';

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
    ['q' => 'Is lunch included?', 'a' => 'Only on the Classic Route + Lunch option. On Classic and Premium you stop for lunch at a winery restaurant and pay there.'],
    ['q' => 'Where does the tour pick me up?', 'a' => 'Hotel pickup is offered from Las Condes, Vitacura, Providencia, Santiago Centro, and Recoleta; your exact pickup time is sent the night before.'],
    ['q' => 'How many people are on the tour?', 'a' => "Groups are capped at 15 travelers, with a minimum of 4 required to run - if the minimum isn't met, you'll be offered another date or a full refund."],
    ['q' => "What's the cancellation policy?", 'a' => "Free cancellation up to 24 hours before the tour's start time; cancellations made inside that window aren't refunded."],
    ['q' => 'Is the Maipo Valley wine tour worth it?', 'a' => "If you want a taste of Chilean wine country without renting a car or planning your own route between wineries, yes - in one day you visit four different Maipo Valley wineries with guided tastings and hotel pickup included."],
    ['q' => 'Can I book this tour privately?', 'a' => 'A private version of this tour is available - pricing depends on your group size, so please contact us directly to inquire.'],
];
$maipo_labels = array_map(static fn(array $o) => $o['label'], $maipo_options);
?>
<!DOCTYPE html>
<html lang="en">
 <head>
<?php include __DIR__ . '/includes/head.php'; ?>
  <link rel="stylesheet" href="css/lab.css?v=20261014">
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
      'image' => 'img/Tours/Maipo/2026/vineyard-toast-1280.jpg',
      'url' => $page_canonical,
      'price' => $dynamic_price_adult,
      'offers' => $schema_offers,
      'sameAs' => ['https://www.tripadvisor.com/AttractionProductReview-g294305-d15040546-Maipo_Valley_Wine_Tour_with_4_vineyards_from_Santiago-Santiago_Santiago_Metropolit.html'],
  ]);
  ?>
  <!-- End Header -->
  <!-- Lab landing page: components in includes/ui/, styles css/lab.css
       (bin/build-css). See docs/superpowers/specs/2026-10-14-maipo-lab-redesign-design.md -->
  <main class="lab">
   <?php ui_hero(maipo_lab_hero($dynamic_price_adult)); ?>

   <?php ui_section_open('why', 'Why this tour', 'A day in Chilean wine country', maipo_lab_intro()); ?>
    <?php ui_benefits(maipo_lab_benefits()); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('routes', 'Choose your route', 'Three ways to spend the day'); ?>
    <?php ui_option_cards($maipo_options, $maipo_js_options, $maipo_selected, 'tour_option', 'Choose your route'); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('gallery', 'Gallery', 'What the day looks like'); ?>
    <?php ui_gallery(maipo_lab_photos()); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('itinerary', 'Your day', 'Itinerary', 'If a winery is unavailable on your date, it may be replaced with another of equal quality.'); ?>
    <?php ui_itinerary($maipo_options, $maipo_js_options, $maipo_selected); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('included', "What's included", 'Included in your route'); ?>
    <?php foreach ($maipo_options as $slug => $opt): ?>
     <?= ui_option_block_open($slug, $maipo_selected) ?>
      <?php ui_checklist($opt['included'], $opt['not_included']); ?>
     <?= ui_option_block_close() ?>
    <?php endforeach; ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('book', 'Book your date', 'Reserve your seats'); ?>
    <?php ui_booking_panel($maipo_options, $maipo_js_options, $maipo_selected); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('good-to-know', 'Good to know', 'Practical info'); ?>
    <?php ui_accordion(maipo_lab_practical(), 'practical-info'); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('faq', 'FAQ', 'Questions travelers ask'); ?>
    <?php ui_accordion($tour_faqs, 'faq-list'); ?>
    <?php render_tour_faq_schema($tour_faqs); ?>
   <?php ui_section_close(); ?>

   <?php ui_sticky_bar($maipo_labels, $maipo_js_options, $maipo_selected); ?>
  </main>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <!-- End footer -->
  <div id="toTop"></div>
  <!-- JavaScript Files -->
<script>
window.MAIPO_OPTIONS = <?= json_encode((object)$maipo_js_options, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.MAIPO_SELECTED = <?= json_encode($maipo_selected) ?>;
</script>
<?php $tour_scripts_skip_gallery = true; include __DIR__ . '/includes/tour-scripts.php'; ?>
<script defer src="js/lab.js?v=20261014"></script>
<script defer src="js/maipo-options.js?v=20261013"></script>
  </body>
</html>
