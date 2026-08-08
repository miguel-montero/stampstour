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
 */
?>
<!-- Scripts (jQuery, Bootstrap, plugins) -->
<script src="/js/jquery-3.7.1.min.js"></script>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/common_scripts_min.js"></script>
<script src="/js/functions.js"></script>
