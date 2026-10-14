<?php
// includes/ui/section.php
// Full-width landing-page section: eyebrow + H2 (+ optional intro paragraph).
// Always pair ui_section_open() with ui_section_close().

function ui_section_open(string $id, string $eyebrow, string $title, string $intro = ''): void
{
    echo '<section' . ($id !== '' ? ' id="' . ui_h($id) . '"' : '') . ' class="tw:scroll-mt-20 tw:border-t tw:border-line tw:py-11 tw:md:py-16">'
        . '<div class="tw:mx-auto tw:max-w-[1100px] tw:px-4 tw:md:px-7">'
        . '<p class="lab-eyebrow">' . ui_h($eyebrow) . '</p>'
        . '<h2 class="tw:mt-1.5 tw:mb-6 tw:text-2xl tw:leading-tight tw:font-extrabold tw:uppercase tw:text-white tw:md:text-[32px]">' . ui_h($title) . '</h2>';
    if ($intro !== '') {
        echo '<p class="tw:-mt-2 tw:mb-6 tw:max-w-[760px] tw:text-muted">' . ui_h($intro) . '</p>';
    }
}

function ui_section_close(): void
{
    echo '</div></section>';
}
