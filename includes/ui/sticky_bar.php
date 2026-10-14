<?php
// includes/ui/sticky_bar.php
// Bottom CTA bar "<option> · $X · Check availability". js/lab.js hides it
// while $target (#book) is on screen and updates label/price on
// 'maipo:optionchange' from data-labels / data-prices. js/tours.js skips its
// old floating header button when [data-sticky-cta] exists.
// Fallbacks: selected option unpriced -> "From $<lowest>"; nothing priced ->
// CTA only.

function ui_sticky_bar(array $labels, array $prices, string $selected, string $target = '#book'): void
{
    $adult = array_map(static fn(array $p) => (float)$p['adult'], $prices);
    if (isset($adult[$selected])) {
        $label = $labels[$selected] ?? '';
        $price = ui_price($adult[$selected]);
    } elseif ($adult) {
        $label = 'From';
        $price = ui_price(min($adult));
    } else {
        $label = '';
        $price = '';
    }
    ?>
    <div class="lab-sticky" data-sticky-cta data-target="<?= ui_h($target) ?>" data-labels="<?= ui_h(json_encode($labels, JSON_UNESCAPED_UNICODE)) ?>" data-prices="<?= ui_h(json_encode($adult)) ?>">
     <div class="tw:mx-auto tw:flex tw:max-w-[1100px] tw:items-center tw:justify-between tw:gap-3 tw:px-4 tw:py-2.5 tw:md:px-7">
      <?php if ($price !== ''): ?>
       <p class="tw:text-sm tw:text-muted"><span data-sticky-label><?= ui_h($label) ?></span> · <strong class="tw:text-xl tw:font-extrabold tw:text-white" data-sticky-price><?= ui_h($price) ?></strong></p>
      <?php else: ?>
       <span></span>
      <?php endif; ?>
      <a class="lab-btn tw:px-5 tw:py-3" href="<?= ui_h($target) ?>">Check availability</a>
     </div>
    </div>
    <?php
}
