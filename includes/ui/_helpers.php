<?php
// includes/ui/_helpers.php
// Shared helpers for the lab UI components in includes/ui/. Components are
// pure renderers: they echo HTML built from the arrays they get, escape every
// value, and never touch the DB. Tailwind classes must stay LITERAL strings
// (bin/build-css scans these files for them) - never build a class name by
// concatenation.

function ui_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ui_price($amount): string
{
    return '$' . number_format((float)$amount, 0);
}

/** "img/x-480.webp 480w, img/x-960.webp 960w" for a base path without width/extension. */
function ui_srcset(string $base, array $widths, string $ext = 'webp'): string
{
    return implode(', ', array_map(
        static fn($w) => $base . '-' . (int)$w . '.' . $ext . ' ' . (int)$w . 'w',
        $widths
    ));
}

/** Inline Lucide icon (https://lucide.dev, ISC licence). Unknown names render ''. */
function ui_icon(string $name, string $class = 'tw:size-5'): string
{
    $paths = [
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'rotate-ccw' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        'wine' => '<path d="M8 22h8"/><path d="M7 10h10"/><path d="M12 15v7"/><path d="M12 15a5 5 0 0 0 5-5c0-2-.5-4-2-8H9c-1.5 4-2 6-2 8a5 5 0 0 0 5 5Z"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="' . ui_h($class) . '" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/** Per-option block wrapper; js/maipo-options.js toggles [data-option] blocks. */
function ui_option_block_open(string $slug, string $selected): string
{
    return '<div data-option="' . ui_h($slug) . '"' . ($slug === $selected ? '' : ' hidden') . '>';
}

function ui_option_block_close(): string
{
    return '</div>';
}
