<?php
// includes/ui/hero.php
// Full-bleed photo hero: award badge, H1 (with one highlighted phrase), lead,
// fact chips, CTA and "From $X per adult". The <picture> media split mirrors
// head.php's $lcp_preload_image_mobile / $lcp_preload_image preloads
// ((max-width: 767px)) - keep them in sync or the LCP image downloads twice.
// $d: image{mobile,desktop,fallback,w,h,alt}, title, highlight, lead,
//     facts[{icon,text}], cta_href, cta_text, price_from (float|null)

function ui_hero(array $d): void
{
    $img = $d['image'];
    $title = ui_h($d['title']);
    $highlight = ui_h($d['highlight'] ?? '');
    if ($highlight !== '' && ($pos = strpos($title, $highlight)) !== false) {
        $title = substr_replace($title, '<em class="tw:not-italic tw:text-coral">' . $highlight . '</em>', $pos, strlen($highlight));
    }
    ?>
    <section class="tw:relative tw:flex tw:min-h-[560px] tw:items-end tw:overflow-hidden tw:pt-28 tw:md:min-h-[640px]">
     <picture>
      <source media="(max-width: 767px)" srcset="<?= ui_h($img['mobile']) ?>" type="image/webp">
      <source media="(min-width: 768px)" srcset="<?= ui_h($img['desktop']) ?>" type="image/webp">
      <img src="<?= ui_h($img['fallback']) ?>" width="<?= (int)$img['w'] ?>" height="<?= (int)$img['h'] ?>" alt="<?= ui_h($img['alt']) ?>" fetchpriority="high" class="tw:absolute tw:inset-0 tw:h-full tw:w-full tw:object-cover tw:object-[center_40%]">
     </picture>
     <div class="tw:absolute tw:inset-0 tw:bg-[linear-gradient(to_top,#1c1f21_2%,rgba(28,31,33,.55)_45%,rgba(28,31,33,.45)_100%)]" aria-hidden="true"></div>
     <div class="tw:relative tw:mx-auto tw:w-full tw:max-w-[1100px] tw:px-4 tw:pb-10 tw:md:px-7 tw:md:pb-12">
      <?php ui_award('hero'); ?>
      <h1 class="tw:mt-4 tw:mb-2.5 tw:max-w-[780px] tw:text-[30px] tw:leading-[1.05] tw:font-extrabold tw:tracking-[.3px] tw:text-white tw:uppercase tw:md:text-[46px]"><?= $title ?></h1>
      <p class="tw:mb-4 tw:max-w-[620px] tw:text-[15px] tw:text-white/85 tw:md:text-base"><?= ui_h($d['lead']) ?></p>
      <ul class="tw:mb-5 tw:flex tw:flex-wrap tw:gap-2">
       <?php foreach ($d['facts'] as $fact): ?>
        <li data-hero-fact class="tw:inline-flex tw:items-center tw:gap-1.5 tw:rounded-full tw:border tw:border-line tw:bg-white/10 tw:px-3 tw:py-1.5 tw:text-[13px] tw:font-semibold tw:text-white"><?= ui_icon($fact['icon'], 'tw:size-4 tw:text-teal-soft') ?><?= ui_h($fact['text']) ?></li>
       <?php endforeach; ?>
      </ul>
      <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-4">
       <a class="lab-btn" href="<?= ui_h($d['cta_href']) ?>"><?= ui_h($d['cta_text']) ?></a>
       <?php if ($d['price_from'] !== null): ?>
        <p class="tw:text-sm tw:text-muted">From <strong class="tw:text-[26px] tw:font-extrabold tw:text-white"><?= ui_h(ui_price($d['price_from'])) ?></strong> per adult</p>
       <?php endif; ?>
      </div>
     </div>
    </section>
    <?php
}
