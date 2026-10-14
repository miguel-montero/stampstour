<?php
// includes/ui/option_cards.php
// Option selector for tours sold in several options (Maipo first). Same DOM
// contract as includes/tours/option_ui.php so js/maipo-options.js (syncs every
// [data-tour-options] radio by value) and js/tours.js (coupon lock disables
// every [data-booking-lock]) work unchanged. Unpriced options are disabled
// and show "Contact us".
//   'cards'     big route cards (price, label, tagline) - legend visually hidden
//   'segmented' compact 3-up switch (label + price) with a visible legend

function ui_option_cards(array $options, array $prices, string $selected, string $name, string $legend, string $variant = 'cards'): void
{
    $segmented = $variant === 'segmented';
    ?>
    <fieldset class="<?= $segmented ? 'tw:min-w-0' : 'tw:grid tw:min-w-0 tw:gap-4 tw:md:grid-cols-3' ?>" data-tour-options>
     <legend class="<?= $segmented ? 'lab-label' : 'tw:sr-only' ?>"><?= ui_h($legend) ?></legend>
     <?php if ($segmented): ?><div class="tw:grid tw:grid-cols-3 tw:gap-1 tw:rounded-[10px] tw:border tw:border-line tw:bg-card tw:p-1"><?php endif; ?>
     <?php foreach ($options as $slug => $opt): ?>
      <?php $slug = (string)$slug; $price = $prices[$slug]['adult'] ?? null; $id = $name . '-' . $slug; ?>
      <?php if ($segmented): ?>
       <label for="<?= ui_h($id) ?>" class="tw:flex tw:cursor-pointer tw:flex-col tw:items-center tw:justify-center tw:rounded-[7px] tw:px-2 tw:py-2 tw:text-center tw:text-xs tw:leading-tight tw:font-bold tw:text-muted tw:transition-colors tw:hover:text-white tw:has-checked:bg-teal tw:has-checked:text-white tw:has-disabled:cursor-not-allowed tw:has-disabled:opacity-50 tw:has-focus-visible:outline-2 tw:has-focus-visible:outline-teal-soft tw:md:text-[13px]">
      <?php else: ?>
       <label for="<?= ui_h($id) ?>" class="lab-card tw:relative tw:block tw:cursor-pointer tw:p-5 tw:transition-shadow tw:hover:border-teal/60 tw:has-checked:border-teal tw:has-checked:shadow-[0_0_0_2px_rgba(0,132,137,.45)] tw:has-disabled:cursor-not-allowed tw:has-disabled:opacity-55 tw:has-focus-visible:outline-2 tw:has-focus-visible:outline-teal-soft">
      <?php endif; ?>
        <input class="tw:sr-only" type="radio" name="<?= ui_h($name) ?>" id="<?= ui_h($id) ?>" value="<?= ui_h($slug) ?>" data-booking-lock<?= $slug === $selected ? ' checked' : '' ?><?= $price === null ? ' disabled' : '' ?>>
      <?php if ($segmented): ?>
        <span><?= ui_h($opt['label']) ?></span>
        <span class="tw:mt-0.5 tw:font-semibold tw:opacity-80"><?= $price !== null ? ui_h(ui_price($price)) : 'Contact us' ?></span>
      <?php else: ?>
        <span class="tw:block tw:text-[28px] tw:leading-none tw:font-extrabold tw:text-coral"><?php if ($price !== null): ?><?= ui_h(ui_price($price)) ?> <small class="tw:text-xs tw:font-semibold tw:text-muted">/ adult</small><?php else: ?>Contact us<?php endif; ?></span>
        <span class="tw:mt-3 tw:block tw:text-base tw:font-bold tw:text-white"><?= ui_h($opt['label']) ?></span>
        <span class="tw:mt-1 tw:block tw:text-sm tw:text-muted"><?= ui_h($opt['tagline']) ?></span>
      <?php endif; ?>
       </label>
     <?php endforeach; ?>
     <?php if ($segmented): ?></div><?php endif; ?>
    </fieldset>
    <?php
}
