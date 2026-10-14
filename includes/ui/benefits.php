<?php
// includes/ui/benefits.php
// Benefit cards (icon, title, text): 2 columns on mobile, 4 on desktop.
// $items: list of ['icon' => ui_icon name, 'title' => ..., 'text' => ...]

function ui_benefits(array $items): void
{
    ?>
    <ul class="tw:grid tw:grid-cols-2 tw:gap-3 tw:md:gap-4 tw:lg:grid-cols-4">
     <?php foreach ($items as $item): ?>
      <li class="lab-card tw:p-4 tw:md:p-5">
       <span class="tw:flex tw:size-10 tw:items-center tw:justify-center tw:rounded-[10px] tw:bg-teal/20 tw:text-teal-soft"><?= ui_icon($item['icon']) ?></span>
       <h3 class="tw:mt-2.5 tw:mb-1.5 tw:text-base tw:leading-snug tw:font-bold tw:text-white"><?= ui_h($item['title']) ?></h3>
       <p class="tw:text-sm tw:text-muted"><?= ui_h($item['text']) ?></p>
      </li>
     <?php endforeach; ?>
    </ul>
    <?php
}
