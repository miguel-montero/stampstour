<?php /* includes/tour-scripts.php
 * Shared trailing <script> block for the 4 plain tour pages
 * (valparaiso, maipo, portillo/andes, discover-santiago).
 * Caller sets $exp_name before including, e.g.:
 *   <?php $exp_name = 'Maipo'; include __DIR__ . '/includes/tour-scripts.php'; ?>
 *
 * All scripts below are `defer` so they download in parallel instead of
 * one at a time, and don't block HTML parsing/painting - deferred
 * scripts still execute in this exact document order (guaranteed by
 * spec), so the jQuery -> plugin -> tours.js dependency chain stays
 * intact without any changes inside tours.js itself. The inline sticky-
 * sidebar init block below can't itself be deferred (inline scripts
 * always run immediately at their parse position, regardless of defer
 * on surrounding tags), so it's wrapped in a native DOMContentLoaded
 * listener instead - that event only fires after every deferred script
 * above has finished, so jQuery/$ and the plugin are guaranteed to exist
 * by the time this callback runs. See
 * docs/superpowers/specs/2026-08-08-tour-gallery-defer-scripts-design.md
 */
?>
<!-- jQuery FIRST -->
<script defer src="js/jquery-3.7.1.min.js"></script>

<!-- Core bundle (Bootstrap) + tour-only extras (Parallax, Magnific Popup,
     daterangepicker + moment). See
     docs/superpowers/specs/2026-08-03-homepage-tour-bundle-split-design.md -->
<script defer src="js/vendors-core.min.js"></script>
<script defer src="js/vendors-tour.min.js"></script>

<!-- Site functions (ok after core+extras) -->
<script defer src="js/functions.js"></script>

<!-- Gallery Plugin -->
<link rel="stylesheet" href="css/slider-pro.min.css">
<script defer src="js/jquery.sliderPro.min.js"></script>

<!-- Sticky Sidebar -->
<script defer src="js/theia-sticky-sidebar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  jQuery(function($){
    if ($.fn.theiaStickySidebar) {
      $('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
    }
  });
});
</script>

<!-- Expose tour name BEFORE tours.js -->
<script>window.EXP_NAME = '<?php echo htmlspecialchars($exp_name, ENT_QUOTES, 'UTF-8'); ?>';</script>

<!-- Your custom code LAST -->
<script defer src="js/tours.js"></script>
