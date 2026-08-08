<?php /* includes/content-scripts.php
 * Shared trailing <script> block for the 6 content pages
 * (contact-us, privacy, refunds-cancellations, blog, blog-post, gallery).
 * No parameters.
 *
 * jQuery-UI, Slider Pro, and theia-sticky-sidebar were removed
 * 2026-08-08 after confirming via a site-wide grep that none of these 6
 * pages call any function from any of the three (no .sliderPro(,
 * .theiaStickySidebar(, or jQuery-UI widget method call site exists on
 * any of them) - those libraries were pure dead weight here. See
 * docs/superpowers/specs/2026-08-08-content-pages-script-trim-design.md
 *
 * common_scripts_min.js (208KB) removed 2026-08-08 after confirming it
 * bundles moment.js, daterangepicker, Magnific Popup, WOW.js, jQuery
 * parallax, hideShowPassword, and a duplicate copy of Bootstrap - none of
 * which any of these 6 pages use. js/functions.js's calls into
 * WOW/Magnific Popup/parallax are all guarded (`if (typeof WOW !==
 * 'undefined')`, `if ($.fn.magnificPopup)`, `if ($.fn.parallax)`) so they
 * safely no-op without this file; its one hideShowPassword reference
 * checks the wrong plugin name (`$.fn.hidePassword`, which this bundle
 * never actually registered) and was already permanently dead before
 * this change. Bootstrap itself is already covered by
 * bootstrap.bundle.min.js below (same v5.3.3, confirmed identical). See
 * docs/superpowers/plans/2026-08-08-content-pages-remove-common-scripts.md
 */
?>
<!-- Scripts (jQuery, Bootstrap, plugins) -->
<script src="/js/jquery-3.7.1.min.js"></script>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/functions.js"></script>
