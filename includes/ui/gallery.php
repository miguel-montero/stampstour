<?php
// includes/ui/gallery.php
// Photo mosaic + native <dialog> lightbox (behaviour in js/lab.js). Each tile
// is a real link to the 1600px image, so it still works without JS or
// <dialog> support. Layout (.lab-mosaic in css/src/lab-base.css) expects 7
// photos: first = tall tile, last = wide tile on desktop.
// $photos: list of ['base' => path without -width.ext, 'w', 'h' (of the 960
//          image), 'alt', 'caption']; files from bin/make-images.

function ui_gallery(array $photos): void
{
    if (!$photos) {
        return;
    }
    ?>
    <div class="lab-mosaic" data-lab-gallery>
     <?php foreach (array_values($photos) as $i => $p): ?>
      <a class="tw:group tw:block tw:overflow-hidden tw:rounded-[10px] tw:bg-card" href="<?= ui_h($p['base'] . '-1600.webp') ?>" data-lab-gallery-item data-caption="<?= ui_h($p['caption']) ?>">
       <picture class="tw:block tw:h-full">
        <source type="image/webp" srcset="<?= ui_h(ui_srcset($p['base'], [480, 960])) ?>" sizes="<?= $i === 0 ? '(min-width: 768px) 50vw, 100vw' : '(min-width: 768px) 33vw, 50vw' ?>">
        <img src="<?= ui_h($p['base'] . '-960.jpg') ?>" width="<?= (int)$p['w'] ?>" height="<?= (int)$p['h'] ?>" alt="<?= ui_h($p['alt']) ?>" loading="lazy" decoding="async" class="tw:h-full tw:w-full tw:object-cover tw:transition-transform tw:duration-300 tw:group-hover:scale-105">
       </picture>
      </a>
     <?php endforeach; ?>
    </div>
    <dialog class="lab-lightbox" data-lab-lightbox aria-label="Photo gallery">
     <figure class="tw:flex tw:flex-col tw:items-center tw:gap-3 tw:px-14 tw:py-4">
      <img data-lab-lightbox-img alt="" class="tw:max-h-[80dvh] tw:w-auto tw:rounded-lg tw:object-contain">
      <figcaption data-lab-lightbox-caption class="tw:text-center tw:text-sm tw:text-white/85"></figcaption>
     </figure>
     <button type="button" data-lab-lightbox-prev aria-label="Previous photo" class="tw:absolute tw:top-1/2 tw:left-1 tw:flex tw:size-11 tw:-translate-y-1/2 tw:cursor-pointer tw:items-center tw:justify-center tw:rounded-full tw:border-0 tw:bg-black/50 tw:text-white"><?= ui_icon('chevron-left', 'tw:size-6') ?></button>
     <button type="button" data-lab-lightbox-next aria-label="Next photo" class="tw:absolute tw:top-1/2 tw:right-1 tw:flex tw:size-11 tw:-translate-y-1/2 tw:cursor-pointer tw:items-center tw:justify-center tw:rounded-full tw:border-0 tw:bg-black/50 tw:text-white"><?= ui_icon('chevron-right', 'tw:size-6') ?></button>
     <button type="button" data-lab-lightbox-close aria-label="Close" class="tw:absolute tw:top-1 tw:right-1 tw:flex tw:size-11 tw:cursor-pointer tw:items-center tw:justify-center tw:rounded-full tw:border-0 tw:bg-black/50 tw:text-white"><?= ui_icon('x', 'tw:size-6') ?></button>
    </dialog>
    <?php
}
