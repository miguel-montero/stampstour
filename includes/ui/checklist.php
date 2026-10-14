<?php
// includes/ui/checklist.php
// "Included / Not included" cards. Wrap in ui_option_block_open/close for
// per-option lists. The "Not included" card is omitted when $no is empty.

function ui_checklist(array $yes, array $no): void
{
    ?>
    <div class="tw:grid tw:gap-4 tw:md:grid-cols-2">
     <div class="lab-card tw:p-5">
      <h3 class="lab-eyebrow">Included</h3>
      <ul class="tw:mt-2.5">
       <?php foreach ($yes as $item): ?>
        <li class="tw:flex tw:gap-2.5 tw:py-1.5 tw:text-sm"><?= ui_icon('check', 'tw:mt-0.5 tw:size-4 tw:shrink-0 tw:text-teal-soft') ?><span><?= ui_h($item) ?></span></li>
       <?php endforeach; ?>
      </ul>
     </div>
     <?php if ($no): ?>
      <div class="lab-card tw:p-5">
       <h3 class="lab-eyebrow">Not included</h3>
       <ul class="tw:mt-2.5">
        <?php foreach ($no as $item): ?>
         <li class="tw:flex tw:gap-2.5 tw:py-1.5 tw:text-sm"><?= ui_icon('x', 'tw:mt-0.5 tw:size-4 tw:shrink-0 tw:text-coral') ?><span><?= ui_h($item) ?></span></li>
        <?php endforeach; ?>
       </ul>
      </div>
     <?php endif; ?>
    </div>
    <?php
}
