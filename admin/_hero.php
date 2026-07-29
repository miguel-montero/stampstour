<?php
/* admin/_hero.php — shared title banner for admin tool pages, matching the
 * background-image/opacity-mask pattern used on contact-us.php etc.
 * Caller sets $hero_title before including. Requires jQuery + functions.js
 * to be loaded on the page (functions.js applies data-background/data-opacity-mask).
 */
?>
<section id="hero_2" class="background-image" data-background="url(/img/Tours/Stgo/big.jpg)">
  <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.55)">
    <div class="intro_title">
      <h1><?= htmlspecialchars($hero_title ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
  </div>
</section>
