<?php
// includes/ui/accordion.php
// <details>/<summary> accordion - works without JS.
// $items: list of ['q' => summary, 'a' => plain-text answer]  (escaped, wrapped in <p>)
//              or ['q' => summary, 'html' => trusted author-written HTML]  (output as-is)

function ui_accordion(array $items, string $id): void
{
    ?>
    <div id="<?= ui_h($id) ?>" class="tw:border-t tw:border-line">
     <?php foreach ($items as $item): ?>
      <details class="lab-acc tw:border-b tw:border-line">
       <summary class="tw:flex tw:cursor-pointer tw:items-center tw:justify-between tw:gap-4 tw:py-4 tw:font-semibold tw:text-white">
        <span><?= ui_h($item['q']) ?></span>
        <?= ui_icon('chevron-down', 'lab-acc-icon tw:size-5 tw:shrink-0 tw:text-teal-soft tw:transition-transform') ?>
       </summary>
       <div class="lab-prose tw:pb-5"><?= isset($item['html']) ? $item['html'] : '<p>' . ui_h($item['a'] ?? '') . '</p>' ?></div>
      </details>
     <?php endforeach; ?>
    </div>
    <?php
}
