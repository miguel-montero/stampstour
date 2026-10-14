<?php
// includes/ui/itinerary.php
// "Your day": segmented tour_option_itin switch + one timeline per option in
// [data-option] blocks (only the selected one visible; all server-rendered
// for SEO). Stop line: "<duration> · <place>", or just the place when the
// stop has no duration (pickup/return).

function ui_itinerary(array $options, array $prices, string $selected): void
{
    ui_option_cards($options, $prices, $selected, 'tour_option_itin', 'Show itinerary for', 'segmented');
    foreach ($options as $slug => $opt) {
        echo ui_option_block_open((string)$slug, $selected);
        ?>
        <ol class="lab-timeline tw:mt-7">
         <?php foreach ($opt['stops'] as $stop): ?>
          <li class="lab-stop">
           <p class="tw:text-xs tw:font-bold tw:tracking-wider tw:text-coral tw:uppercase"><?= ui_h($stop['duration'] !== '' ? $stop['duration'] . ' · ' . $stop['place'] : $stop['place']) ?></p>
           <h3 class="tw:mt-0.5 tw:mb-1 tw:text-lg tw:font-bold tw:text-white"><?= ui_h($stop['title']) ?></h3>
           <p class="tw:max-w-[640px] tw:text-sm tw:text-muted"><?= ui_h($stop['text']) ?></p>
          </li>
         <?php endforeach; ?>
        </ol>
        <?php
        echo ui_option_block_close();
    }
}
