<?php /* includes/tour-scripts.php
 * Shared trailing <script> block for the 4 plain tour pages
 * (valparaiso, maipo, portillo/andes, discover-santiago).
 * Caller sets $exp_name before including, e.g.:
 *   <?php $exp_name = 'Maipo'; include __DIR__ . '/includes/tour-scripts.php'; ?>
 */
?>
<!-- jQuery FIRST -->
<script src="js/jquery-3.7.1.min.js"></script>

<!-- Common bundle (includes moment + daterangepicker + other core stuff) -->
<script src="js/common_scripts_min.js"></script>

<!-- Site functions (ok after common) -->
<script src="js/functions.js"></script>

<!-- Gallery Plugin -->
<link rel="stylesheet" href="css/slider-pro.min.css">
<script src="js/jquery.sliderPro.min.js"></script>

<!-- Sticky Sidebar -->
<script src="js/theia-sticky-sidebar.js"></script>
<script>
jQuery(function($){
  if ($.fn.theiaStickySidebar) {
    $('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
  }
});
</script>

<!-- Expose tour name BEFORE tours.js -->
<script>window.EXP_NAME = '<?php echo htmlspecialchars($exp_name, ENT_QUOTES, 'UTF-8'); ?>';</script>

<!-- Your custom code LAST -->
<script src="js/tours.js"></script>
