<?php
// includes/tours/option_ui.php
// Server-rendered UI for tours sold in several options (Maipo first).
// Everything is in the HTML for SEO; js/maipo-options.js only toggles the
// [data-option] blocks and keeps the [data-tour-options] radios in sync.
// Option array shape: see includes/tours/maipo_options.php.

function option_ui_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function option_ui_price($amount): string
{
    return '$' . number_format((float)$amount, 0);
}

function render_option_selector(array $options, array $prices, string $selected, string $name, string $legend, string $modifier = ''): void
{
    ?>
    <fieldset class="tour-options<?= $modifier !== '' ? ' tour-options--' . option_ui_h($modifier) : '' ?>" data-tour-options>
     <legend class="tour-options__legend"><?= option_ui_h($legend) ?></legend>
     <?php foreach ($options as $slug => $opt): ?>
      <?php $price = $prices[$slug]['adult'] ?? null; $id = $name . '-' . $slug; ?>
      <label class="tour-option" for="<?= option_ui_h($id) ?>">
       <input type="radio" name="<?= option_ui_h($name) ?>" id="<?= option_ui_h($id) ?>" value="<?= option_ui_h($slug) ?>" data-booking-lock<?= $slug === $selected ? ' checked' : '' ?><?= $price === null ? ' disabled' : '' ?>>
       <span class="tour-option__body">
        <span class="tour-option__label"><?= option_ui_h($opt['label']) ?></span>
        <span class="tour-option__price"><?= $price !== null ? option_ui_h(option_ui_price($price)) : 'Contact us' ?></span>
        <span class="tour-option__tagline"><?= option_ui_h($opt['tagline']) ?></span>
       </span>
      </label>
     <?php endforeach; ?>
    </fieldset>
    <?php
}

function render_option_block_start(string $slug, string $selected): void
{
    echo '<div data-option="' . option_ui_h($slug) . '"' . ($slug === $selected ? '' : ' hidden') . '>';
}

function render_option_block_end(): void
{
    echo '</div>';
}

function render_option_itinerary(string $slug, array $option, string $selected): void
{
    render_option_block_start($slug, $selected);
    ?>
     <ul class="cbp_tmtimeline">
      <?php foreach ($option['stops'] as $stop): ?>
       <li>
        <?php if ($stop['duration'] !== ''): ?>
         <time class="cbp_tmtime"><span><?= option_ui_h($stop['duration']) ?></span><span></span></time>
        <?php endif; ?>
        <div class="cbp_tmicon <?= option_ui_h($stop['icon']) ?>"></div>
        <div class="cbp_tmlabel">
         <h2><span><?= option_ui_h($stop['place']) ?></span> <?= option_ui_h($stop['title']) ?></h2>
         <p><?= option_ui_h($stop['text']) ?></p>
        </div>
       </li>
      <?php endforeach; ?>
     </ul>
    <?php
    render_option_block_end();
}
