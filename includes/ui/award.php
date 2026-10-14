<?php
// includes/ui/award.php
// Official Tripadvisor Travelers' Choice "Best of the Best" 2026 badge.
//   'hero'    white wordmark + caption, for use on photos
//   'compact' circle badge + caption ($note), for the booking panel

function ui_award(string $variant = 'hero', string $note = ''): void
{
    $alt = "Tripadvisor Travelers' Choice Best of the Best 2026";
    if ($variant === 'compact') {
        ?>
        <div class="tw:flex tw:items-center tw:gap-3">
         <picture class="tw:shrink-0"><source srcset="img/badges/tripadvisor-circle-2026.webp" type="image/webp"><img src="img/badges/tripadvisor-circle-2026.png" width="100" height="127" alt="<?= ui_h($alt) ?>" loading="lazy" class="tw:h-12 tw:w-auto"></picture>
         <p class="tw:text-xs tw:leading-snug tw:text-muted">Travelers' Choice Best of the Best 2026<?php if ($note !== ''): ?><br><?= ui_h($note) ?><?php endif; ?></p>
        </div>
        <?php
        return;
    }
    ?>
    <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-3.5">
     <picture class="tw:shrink-0"><source srcset="img/badges/tripadvisor-2026-white.webp" type="image/webp"><img src="img/badges/tripadvisor-2026-white.png" width="260" height="50" alt="<?= ui_h($alt) ?>" class="tw:h-11 tw:w-auto tw:md:h-14"></picture>
     <p class="tw:text-[13px] tw:leading-snug tw:text-white/85"><strong class="tw:text-white">Travelers' Choice · Best of the Best 2026</strong><br>Top 1% of experiences on Tripadvisor &amp; Viator</p>
    </div>
    <?php
}
